<?php
/**
 * AJAX Git 自動化工作流
 * 建立 GitHub 倉庫並推送程式碼
 */
session_start();
require_once '../Connections/connect2data.php';

header('Content-Type: application/json');

// 增加執行時間上限，考慮到可能有大量檔案
set_time_limit(600); 
ini_set('display_errors', 0); // 避免 php warning 破壞 JSON 格式
error_reporting(E_ALL);

/**
 * 更新進度訊息並釋放 Session 鎖
 */
function updateProgress($msg) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['git_progress'] = $msg;
    session_write_close(); // 釋放鎖定，讓其他 AJAX 請求(如刪除)能同時進行
}

// 1. 基本安全檢查
updateProgress('正在啟動自動化環境...');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
    exit;
}

$itemId = intval($_POST['item_id'] ?? 0);
if (!$itemId) {
    echo json_encode(['success' => false, 'message' => '缺少必要的參數']);
    exit;
}

// 2. 取得網站資料
try {
    $stmt = $conn->prepare("SELECT * FROM data_set WHERE d_id = :id AND d_class1 = 'websites' LIMIT 1");
    $stmt->execute([':id' => $itemId]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        echo json_encode(['success' => false, 'message' => '找不到網站資料']);
        exit;
    }

    $title = $site['d_title'];
    $titleEn = $site['d_title_en'];
    
    updateProgress("確認網站目錄: {$titleEn}...");
    // 統一使用 SubsiteHelper 的工具函數來產生 Slug
    require_once 'includes/SubsiteHelper.php';
    $slug = SubsiteHelper::sanitizeSlug($titleEn);

    // 本地目錄路徑 (WAMP www 下，相對於目前 cms 目錄)
    $localPath = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . $slug;

    if (!is_dir($localPath)) {
        echo json_encode(['success' => false, 'message' => "本地目錄不存在: {$localPath}"]);
        exit;
    }

    // 3. 取得 GitHub 認證資訊
    $githubToken = $_ENV['GITHUB_TOKEN'] ?? null;
    $githubUser = $_ENV['GITHUB_USER'] ?? null;

    if (!$githubToken || !$githubUser) {
        echo json_encode(['success' => false, 'message' => '請先在 .env 中設定 GITHUB_TOKEN 與 GITHUB_USER']);
        exit;
    }

    // 4. Git 環境檢查 (簡單測試)
    $gitCheck = shell_exec("git --version");
    if (!$gitCheck) {
        echo json_encode(['success' => false, 'message' => '系統環境不支援 git 命令，請連絡系統管理員']);
        exit;
    }

    // 5. 建立 GitHub 倉庫 (REST API)
    updateProgress('正在向 GitHub 要求建立私有倉庫...');
    $repoName = $slug;
    $ch = curl_init("https://api.github.com/user/repos");
    $repoData = json_encode([
        'name' => $repoName,
        'private' => true,
        'description' => "Automated repo for CMS website: {$title}",
        'auto_init' => false
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $repoData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token {$githubToken}",
        "User-Agent: PHP-Git-Automation",
        "Content-Type: application/json"
    ]);
    // 解決 Windows WAMP 環境下的 SSL 憑證問題
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    $repoUrl = "";

    if ($httpCode === 201) {
        $repoUrl = $responseData['clone_url'];
    } elseif ($httpCode === 422 && strpos($response, 'already exists') !== false) {
        // 倉庫已存在，嘗試取得 URL
        $repoUrl = "https://github.com/{$githubUser}/{$repoName}.git";
    } else {
        $errorMsg = "GitHub API 錯誤 (HTTP {$httpCode})";
        if ($curlError) $errorMsg .= " - cURL Error: {$curlError}";
        if ($responseData && isset($responseData['message'])) $errorMsg .= " - API Message: {$responseData['message']}";
        elseif ($response) $errorMsg .= " - Raw Response: " . substr($response, 0, 100);
        
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }

    // 6. 執行本地 Git 指令
    chdir($localPath);
    
    // 優化 .gitignore：核心保留，但排除測試、範例與冗餘語系
    updateProgress('正在執行精確瘦身優化 (過濾非必要檔案)...');
    $gitignorePath = $localPath . DIRECTORY_SEPARATOR . '.gitignore';
    $defaultIgnores = "\n# Aggressive performance optimization\n" .
                      "vendor/**/tests/\nvendor/**/docs/\nvendor/**/examples/\nvendor/**/.git/\n" .
                      "cms/ckeditor/samples/\ncms/ckeditor/docs/\n" .
                      "public/temp/\n" .
                      "*.log\nnode_modules/\n*.map\n.DS_Store\nThumbs.db";
    
    if (file_exists($gitignorePath)) {
        $content = file_get_contents($gitignorePath);
        $content = str_replace("\nvendor/", "\n#vendor/", $content); 
        if (strpos($content, "Aggressive performance optimization") === false) {
            $content .= $defaultIgnores;
        }
        file_put_contents($gitignorePath, $content);
    } else {
        file_put_contents($gitignorePath, $defaultIgnores);
    }

    // 初始化 Git
    if (!is_dir(".git")) {
        shell_exec("git init");
        shell_exec('git config user.email "auto@cms-automation.com"');
        shell_exec('git config user.name "CMS Automation"');
        
        // --- Windows 效能特別優化 ---
        shell_exec('git config core.fscache true');
        shell_exec('git config core.preloadindex true');
        shell_exec('git config core.fscache true'); // 重複確認
        shell_exec('git config gc.auto 0'); // 關閉自動回收，減少 add 負擔
    }

    // 設定遠端 (先移除舊的以防萬一)
    shell_exec("git remote remove origin 2>&1");
    $authRepoUrl = str_replace("https://", "https://{$githubToken}@", $repoUrl);
    shell_exec("git remote add origin {$authRepoUrl}");

    // 加入檔案並提交
    // --- 第一階段：連線測試 (只推 .gitignore) ---
    updateProgress('第一階段：測試連線中 (只推 .gitignore)...');
    
    // 強制重置 index 確保測試乾淨
    shell_exec("git rm -r --cached . 2>&1");
    shell_exec("git add .gitignore 2>&1");
    shell_exec('git commit -m "Stage 1: Connection Test" 2>&1');
    shell_exec("git branch -M main 2>&1");
    
    $testPush = shell_exec("git push -u origin main 2>&1");
    
    if (strpos($testPush, 'rejected') !== false || strpos($testPush, 'error') !== false || strpos($testPush, 'fatal') !== false) {
        file_put_contents(__DIR__ . '/git_debug_log.txt', "[" . date('Y-m-d H:i:s') . "] Stage 1 Failed: {$testPush}\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => "第一階段連線測試失敗：\n" . substr($testPush, 0, 100)]);
        exit;
    }

    // --- 第二階段：完整推送 ---
    updateProgress('連線測試成功！正在處理核心代碼 (1/4)...');
    shell_exec("git add index.php app/ config/ Connections/ sql/ 2>&1");
    
    updateProgress('正在處理前端資源與範本 (2/4)...');
    shell_exec("git add template/ public/ css/ js/ 2>&1");
    
    updateProgress('正在處理套件庫 (3/4：檔案較多請稍候)...');
    shell_exec("git add vendor/ 2>&1"); 
    
    updateProgress('正在執行最後檔案彙整 (4/4)...');
    shell_exec("git add -A 2>&1"); 
    
    shell_exec('git commit -m "Stage 2: Full content deployment (Optimized)" 2>&1');
    
    updateProgress('最終階段：正在將檔案推送到 GitHub，請勿關閉視窗...');
    $fullPush = shell_exec("git push origin main 2>&1");

    if (strpos($fullPush, 'rejected') !== false || strpos($fullPush, 'error') !== false || strpos($fullPush, 'fatal') !== false) {
        file_put_contents(__DIR__ . '/git_debug_log.txt', "[" . date('Y-m-d H:i:s') . "] Stage 2 Failed: {$fullPush}\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => "完整推送失敗，但連線已確認。可能是檔案較多導致超時：\n" . substr($fullPush, 0, 100)]);
        exit;
    }

    // 7. 更新資料庫
    updateProgress('正在回寫資料庫設定...');
    $gitRepoPublicUrl = "https://github.com/{$githubUser}/{$repoName}";
    $currentTime = date('Y-m-d H:i:s');
    $noteAppend = "\n[Git History] Repo created and pushed at {$currentTime}.\nURL: {$gitRepoPublicUrl}";
    
    $updateStmt = $conn->prepare("UPDATE data_set SET d_data7 = :url, d_content = CONCAT(IFNULL(d_content, ''), :note) WHERE d_id = :id");
    $updateStmt->execute([
        ':url' => $gitRepoPublicUrl,
        ':note' => $noteAppend,
        ':id' => $itemId
    ]);

    echo json_encode([
        'success' => true, 
        'message' => "成功！倉庫已建立並推送到 main 分支。\n連結: {$gitRepoPublicUrl}",
        'url' => $gitRepoPublicUrl
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '執行失敗: ' . $e->getMessage()]);
}

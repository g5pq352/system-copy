<?php
/**
 * ajax_site_domain_setup.php
 * 階段 4：域名綁定 + Apache 虛擬主機設定 + Let's Encrypt SSL 申請
 * 
 * IS_LOCAL = true  → 模擬執行，僅做 Log 記錄
 * IS_LOCAL = false → 真實執行 Apache 設定與 Certbot
 */

set_time_limit(300);
header('Content-Type: application/json; charset=utf-8');

require_once '../Connections/connect2data.php';
require_once '../config/config.php';

define('ADMIN_EMAIL', 'design@goods-design.com.tw');
define('APACHE_SITES_AVAILABLE', '/etc/apache2/sites-available');
define('APACHE_SITES_ENABLED',   '/etc/apache2/sites-enabled');
define('WWW_ROOT', '/var/www');

$logFile = __DIR__ . '/../subsite_post_log.txt';

function logMsg(string $msg): void {
    global $logFile;
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] [DOMAIN] ' . $msg . PHP_EOL, FILE_APPEND);
}

function updateProgress(string $msg): void {
    $statusFile = sys_get_temp_dir() . '/site_progress.txt';
    file_put_contents($statusFile, $msg);
    logMsg($msg);
}

function runCmd(string $cmd): array {
    $output = [];
    $returnCode = 0;
    exec($cmd . ' 2>&1', $output, $returnCode);
    $outputStr = implode("\n", $output);
    logMsg("CMD: {$cmd} | RC: {$returnCode} | OUT: " . substr($outputStr, 0, 300));
    return ['code' => $returnCode, 'output' => $outputStr];
}

try {
    // 1. 取得 item_id
    $itemId = intval($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        echo json_encode(['success' => false, 'message' => '缺少 item_id']);
        exit;
    }

    // 2. 讀取網站資料
    $stmt = $conn->prepare("SELECT * FROM data_set WHERE d_id = :id AND d_class1 = 'websites' LIMIT 1");
    $stmt->execute([':id' => $itemId]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        echo json_encode(['success' => false, 'message' => '找不到網站資料']);
        exit;
    }

    $slug   = trim($site['d_title_en'] ?? '');
    $domain = trim($site['d_data10']   ?? '');
    $status = intval($site['d_active'] ?? 2);

    // 3. 驗證必要條件
    if (empty($slug) || empty($domain)) {
        echo json_encode(['success' => false, 'message' => '請確認英文名稱 (Slug) 與正式域名均已填寫']);
        exit;
    }
    if ($status !== 1) {
        echo json_encode(['success' => false, 'message' => '請先將專案狀態切換為「🟢 上線中」']);
        exit;
    }

    // =========================================================================
    // IS_LOCAL 環境判斷核心
    // =========================================================================
    if (IS_LOCAL) {
        // ===== 本機模式：僅做模擬，不執行任何真實指令 =====
        logMsg("[本機模式] 模擬執行 - Slug: {$slug}, Domain: {$domain}");
        logMsg("[本機模式] 模擬寫入 Apache VHost: " . APACHE_SITES_AVAILABLE . "/{$slug}.conf");
        logMsg("[本機模式] 模擬執行: a2ensite {$slug}.conf");
        logMsg("[本機模式] 模擬執行: certbot --apache -d {$domain}");

        // 本機模式也更新 d_data9，確保按鈕狀態正確切換
        $updateStmt = $conn->prepare("UPDATE data_set SET d_data9 = :now WHERE d_id = :id");
        $updateStmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $itemId]);

        echo json_encode([
            'success' => true,
            'message' => "[本機模式] 模擬完成。域名: {$domain}，實際 Apache 與 SSL 設定僅在正式 Linux 主機上執行。"
        ]);
        exit;
    }

    // ===== 線上模式：真實執行 =====

    // 4. DNS 預檢：確認域名已指向本機 IP
    updateProgress("正在檢查 DNS 解析 ({$domain})...");
    $serverIp   = trim(shell_exec("curl -s ifconfig.me 2>/dev/null") ?: gethostbyname(gethostname()));
    $domainIp   = gethostbyname($domain);

    if ($domainIp === $domain) {
        // gethostbyname 找不到時會回傳原本的字串
        echo json_encode(['success' => false, 'message' => "DNS 尚未解析：{$domain} 目前無法解析，請先在 DNS 服務商設定 A 紀錄指向 {$serverIp}"]);
        exit;
    }

    if ($domainIp !== $serverIp) {
        logMsg("DNS 不匹配：domain={$domainIp}, server={$serverIp}");
        // 給予警告但不強制阻擋（防止 CDN 或 Proxy 場景誤判）
        updateProgress("DNS 警告：域名 IP ({$domainIp}) 與伺服器 IP ({$serverIp}) 不符，繼續嘗試...");
    } else {
        updateProgress("DNS 解析正確！({$domain} → {$domainIp})");
    }

    // 5. 確認目標資料夾存在
    $targetDir = WWW_ROOT . '/' . $slug;
    if (!is_dir($targetDir)) {
        echo json_encode(['success' => false, 'message' => "網站目錄不存在：{$targetDir}，請先執行「初始化網站」"]);
        exit;
    }

    // 6. 生成 Apache VHost 設定檔（HTTP，Certbot 稍後自動加入 HTTPS）
    updateProgress("正在產生 Apache 虛擬主機設定...");
    $vhostConf = <<<APACHE
<VirtualHost *:80>
    ServerName {$domain}
    DocumentRoot {$targetDir}

    <Directory {$targetDir}>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/{$slug}_error.log
    CustomLog \${APACHE_LOG_DIR}/{$slug}_access.log combined
</VirtualHost>
APACHE;

    $confPath = APACHE_SITES_AVAILABLE . "/{$slug}.conf";
    $writeResult = runCmd("echo " . escapeshellarg($vhostConf) . " | sudo tee {$confPath} > /dev/null");
    if ($writeResult['code'] !== 0) {
        echo json_encode(['success' => false, 'message' => "寫入 Apache 設定失敗：\n" . $writeResult['output']]);
        exit;
    }

    // 7. 啟用站點
    updateProgress("正在啟用站點 (a2ensite)...");
    $enableResult = runCmd("sudo a2ensite {$slug}.conf");

    // 8. 測試 Apache 設定語法
    updateProgress("正在驗證 Apache 設定語法...");
    $testResult = runCmd("sudo apache2ctl configtest");
    if (strpos($testResult['output'], 'Syntax OK') === false) {
        // 設定有誤，撤銷啟用
        runCmd("sudo a2dissite {$slug}.conf");
        echo json_encode(['success' => false, 'message' => "Apache 設定語法錯誤：\n" . $testResult['output']]);
        exit;
    }

    // 9. 重新載入 Apache
    updateProgress("正在重新載入 Apache...");
    $reloadResult = runCmd("sudo systemctl reload apache2");

    // 10. 申請 Let's Encrypt SSL
    updateProgress("正在申請 Let's Encrypt SSL 憑證 (可能需要 30-60 秒)...");
    $certbotCmd = "sudo certbot --apache -d {$domain} --non-interactive --agree-tos --email " . ADMIN_EMAIL . " --redirect";
    $certResult = runCmd($certbotCmd);

    if (strpos($certResult['output'], 'Congratulations') === false && strpos($certResult['output'], 'Certificate not yet due') === false) {
        // Certbot 失敗，但 Apache 已設定好（HTTP 版本還是可以用）
        logMsg("Certbot 失敗，HTTP 版本依然可用。Output: " . $certResult['output']);
        echo json_encode([
            'success' => false,
            'message' => "Apache 已設定完成 (HTTP 版本可用)，但 SSL 申請失敗：\n" . substr($certResult['output'], 0, 400)
        ]);
        exit;
    }

    // 11. 寫入完成狀態 (d_data9)
    updateProgress("正在更新系統狀態...");
    $updateStmt = $conn->prepare("UPDATE data_set SET d_data9 = :now WHERE d_id = :id");
    $updateStmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $itemId]);

    logMsg("成功！域名 {$domain} 已綁定，SSL 已申請完成。");

    echo json_encode([
        'success' => true,
        'message' => "域名 https://{$domain} 已完成設定，SSL 憑證已自動安裝，重新整理後按鈕將鎖定。"
    ]);

} catch (Exception $e) {
    logMsg("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '系統錯誤：' . $e->getMessage()]);
}
?>

<?php
/**
 * AJAX 網站初始化 (實體化) 流程
 * 負責建立資料庫與複製實體檔案
 * 線上環境額外建立子網域 Apache VirtualHost
 */
session_start();
require_once '../Connections/connect2data.php';
require_once '../config/config.php';
require_once 'includes/SubsiteHelper.php';

header('Content-Type: application/json');

set_time_limit(600); 
ini_set('display_errors', 0);

function updateProgress($msg) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['git_progress'] = $msg;
    session_write_close();
}

function runInitCmd(string $cmd): array {
    $output = [];
    $returnCode = 0;
    exec($cmd . ' 2>&1', $output, $returnCode);
    return ['code' => $returnCode, 'output' => implode("\n", $output)];
}

try {
    updateProgress('正在啟動初始化程序...');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '無效的請求方法']);
        exit;
    }

    $itemId = intval($_POST['item_id'] ?? 0);
    if (!$itemId) {
        echo json_encode(['success' => false, 'message' => '缺少必要的參數']);
        exit;
    }

    // 取得網站資料
    $stmt = $conn->prepare("SELECT * FROM data_set WHERE d_id = :id AND d_class1 = 'websites' LIMIT 1");
    $stmt->execute([':id' => $itemId]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        echo json_encode(['success' => false, 'message' => '找不到網站資料']);
        exit;
    }

    $slug = trim($site['d_title_en'] ?? '');

    // 解析標準模組 (d_data5)
    $standardModules = [];
    $rawD5 = $site['d_data5'] ?? '';
    if (!empty($rawD5)) {
        $decoded = json_decode($rawD5, true);
        if (is_array($decoded)) {
            $standardModules = $decoded;
        } else {
            $standardModules = array_filter(explode(',', $rawD5));
        }
    }

    // 取得自定義模組資料 (從 data_dynamic_fields，依 UID 分組還原)
    $stmtModules = $conn->prepare("SELECT df_group_uid, df_field_name, df_field_value FROM data_dynamic_fields WHERE df_d_id = :id AND df_field_group = 'custom_modules' ORDER BY df_group_index ASC");
    $stmtModules->execute([':id' => $itemId]);
    $moduleRows = $stmtModules->fetchAll(PDO::FETCH_ASSOC);
    
    $groupedModules = [];
    foreach ($moduleRows as $row) {
        $uid = $row['df_group_uid'];
        if (!isset($groupedModules[$uid])) {
            $groupedModules[$uid] = ['_uid' => $uid];
        }
        $groupedModules[$uid][$row['df_field_name']] = $row['df_field_value'];
    }
    $customModules = array_values($groupedModules);

    // 準備建站參數
    $postData = [
        'd_data1'        => $site['d_data1'],
        'd_data2'        => $site['d_data2'],
        'd_title'        => $site['d_title'],
        'd_title_en'     => $site['d_title_en'],
        'd_data5'        => $standardModules,
        'custom_modules' => $customModules,
    ];

    // =========================================================================
    // 核心：建立 DB 與檔案
    // =========================================================================
    updateProgress('正在執行實體化建站 (建立檔案與資料庫)...');
    SubsiteHelper::factory($conn, $itemId, $postData);

    // =========================================================================
    // IS_LOCAL 判斷：只有線上環境才建立子網域
    // =========================================================================
    $subdomainMsg = '';
    if (!IS_LOCAL) {
        $hostName = $_ENV['HOST_NAME'] ?? '';
        if (!empty($slug) && !empty($hostName)) {
            $subdomain  = $slug . '.' . $hostName;          // e.g. zxc.goods-test.com.tw
            $targetDir  = '/var/www/' . $slug;
            $confPath   = '/etc/apache2/sites-available/' . $slug . '.conf';

            updateProgress("正在建立子網域 ({$subdomain})...");

            $vhostConf = <<<APACHE
<VirtualHost *:80>
    ServerName {$subdomain}
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

            // 寫入 VHost 設定
            runInitCmd("echo " . escapeshellarg($vhostConf) . " | sudo tee {$confPath} > /dev/null");
            // 啟用站點
            runInitCmd("sudo a2ensite {$slug}.conf");
            // 語法驗證
            $testResult = runInitCmd("sudo apache2ctl configtest");
            if (strpos($testResult['output'], 'Syntax OK') !== false) {
                runInitCmd("sudo systemctl reload apache2");
                $subdomainMsg = "，子網域 http://{$subdomain} 已啟用";
            } else {
                // 設定有誤，撤銷
                runInitCmd("sudo a2dissite {$slug}.conf");
                $subdomainMsg = "（子網域設定語法錯誤，請手動檢查）";
            }
        }
    }

    // 更新初始化狀態 (d_data8)
    updateProgress('正在同步狀態資訊...');
    $updateStmt = $conn->prepare("UPDATE data_set SET d_data8 = :init_time WHERE d_id = :id");
    $updateStmt->execute([
        ':init_time' => date('Y-m-d H:i:s'),
        ':id'        => $itemId
    ]);

    echo json_encode([
        'success' => true,
        'message' => '網站實體化成功！' . $subdomainMsg
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '執行失敗: ' . $e->getMessage()
    ]);
}

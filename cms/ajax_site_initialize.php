<?php
/**
 * AJAX 網站初始化 (實體化) 流程
 * 負責建立資料庫與複製實體檔案
 */
session_start();
require_once '../Connections/connect2data.php';
require_once 'includes/SubsiteHelper.php';

header('Content-Type: application/json');

// 增加執行時間上限 (考慮到文件複製)
set_time_limit(600); 
ini_set('display_errors', 0);

/**
 * 更新進度訊息並釋放 Session 鎖
 */
function updateProgress($msg) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['git_progress'] = $msg;
    session_write_close();
}

try {
    // 1. 基本安全檢查
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

    // 2. 取得網站資料
    $stmt = $conn->prepare("SELECT * FROM data_set WHERE d_id = :id AND d_class1 = 'websites' LIMIT 1");
    $stmt->execute([':id' => $itemId]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$site) {
        echo json_encode(['success' => false, 'message' => '找不到網站資料']);
        exit;
    }

    // 2.5 解析標準模組 (d_data5)
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

    // 2.6 取得自定義模組資料 (從 data_dynamic_fields) - 修正：依據 UID 分組還原
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

    // 3. 準備建站參數
    $postData = [
        'd_data1' => $site['d_data1'], // DB Host
        'd_data2' => $site['d_data2'], // DB Name
        'd_title' => $site['d_title'],
        'd_title_en' => $site['d_title_en'],
        'd_data5' => $standardModules, 
        'custom_modules' => $customModules // 修正後的完整模組陣列
    ];

    // 4. 呼叫 SubsiteHelper 核心工廠
    updateProgress('正在執行實體化建站 (建立檔案與資料庫)...');
    SubsiteHelper::factory($conn, $itemId, $postData);

    // 5. 更新初始化狀態 (d_data8)
    updateProgress('正在同步狀態資訊...');
    $currentTime = date('Y-m-d H:i:s');
    $updateStmt = $conn->prepare("UPDATE data_set SET d_data8 = :init_time WHERE d_id = :id");
    $updateStmt->execute([
        ':init_time' => $currentTime,
        ':id'        => $itemId
    ]);

    echo json_encode([
        'success' => true,
        'message' => '網站實體化成功！'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '執行失敗: ' . $e->getMessage()
    ]);
}

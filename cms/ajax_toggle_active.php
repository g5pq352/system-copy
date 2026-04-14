<?php
/**
 * AJAX 顯示狀態切換處理
 * 處理項目的顯示/不顯示/草稿狀態切換
 */
session_start();
require_once '../Connections/connect2data.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$module = $_POST['module'] ?? '';
$itemId = intval($_POST['item_id'] ?? 0);
$newValue = intval($_POST['new_value'] ?? 0);
$field = $_POST['field'] ?? 'd_active';

if (!$module || !$itemId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// 載入模組配置
$configFile = __DIR__ . "/set/{$module}Set.php";
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'message' => "Module config not found: {$module}Set.php"]);
    exit;
}

// 載入設定檔
$moduleConfig = require $configFile;
if (!is_array($moduleConfig) && isset($settingPage)) {
    $moduleConfig = $settingPage;
}

if (!is_array($moduleConfig)) {
    echo json_encode(['success' => false, 'message' => 'Invalid config format']);
    exit;
}

try {
    $tableName = $moduleConfig['tableName'];
    $primaryKey = $moduleConfig['primaryKey'];

    // 過濾欄位名稱，防止 SQL 注入
    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);

    // 更新狀態
    $sql = "UPDATE {$tableName} SET {$field} = :new_value WHERE {$primaryKey} = :item_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':new_value', $newValue, PDO::PARAM_INT);
    $stmt->bindValue(':item_id', $itemId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '狀態已更新']);
    } else {
        echo json_encode(['success' => false, 'message' => '更新失敗']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '錯誤: ' . $e->getMessage()]);
}

<?php
/**
 * 單筆刪除處理 - Bridge 版
 * 統一呼叫 AdminController::delete
 * 支援軟刪除和硬刪除（垃圾桶模式）
 */
session_start();
require_once '../Connections/connect2data.php';
require_once 'includes/AdminApiBridge.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 透過 Bridge 呼叫 AdminController::delete
// AdminController::delete 會根據 trash 參數決定是軟刪除還是硬刪除
$resJson = \App\Bridges\AdminApiBridge::callController($conn, '\App\Controllers\AdminController', 'delete', $_POST);
echo $resJson;

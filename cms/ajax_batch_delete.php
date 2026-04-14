<?php
/**
 * 批次刪除處理 - Bridge 版
 * 統一呼叫 AdminController::delete
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
// AdminController::delete 應支援批次處理 (傳入 item_ids)
$resJson = \App\Bridges\AdminApiBridge::callController($conn, '\App\Controllers\AdminController', 'delete', $_POST);
echo $resJson;

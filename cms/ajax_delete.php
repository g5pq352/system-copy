<?php
/**
 * 單筆刪除處理 - Bridge 版
 * 統一呼叫 AdminController::delete
 * 支援軟刪除和硬刪除（垃圾桶模式）
 */
ob_start(); // 防止任何 Warning/Notice 污染 JSON
ini_set('display_errors', 0);
session_start();
require_once '../Connections/connect2data.php';
require_once 'includes/AdminApiBridge.php';

ob_clean(); // 清除任何 include 產生的多餘輸出
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 透過 Bridge 呼叫 AdminController::delete
// AdminController::delete 會根據 trash 參數決定是軟刪除還是硬刪除
$resJson = \App\Bridges\AdminApiBridge::callController($conn, '\App\Controllers\AdminController', 'delete', $_POST);
echo $resJson;

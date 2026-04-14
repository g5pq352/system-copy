<?php
/**
 * 單筆還原處理 - Bridge 版
 * 統一呼叫 AdminController::restore
 */
session_start();
require_once '../Connections/connect2data.php';
require_once 'includes/AdminApiBridge.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 透過 Bridge 呼叫 AdminController::restore
$resJson = \App\Bridges\AdminApiBridge::callController($conn, '\App\Controllers\AdminController', 'restore', $_POST);
echo $resJson;
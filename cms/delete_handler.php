<?php
/**
 * 通用刪除處理 - Bridge 版
 * 統一呼叫 AdminController::delete
 */
session_start();
require_once '../Connections/connect2data.php';
require_once 'includes/AdminApiBridge.php';

// 安全驗證 (由 AdminController 內部處理權限，這裡只做基礎參數檢查)
$module = $_GET['module'] ?? '';
$id = $_GET['id'] ?? 0;

if (!$module || !$id) {
    die('Missing required parameters');
}

// 載入模組配置 (僅為了導向與變數定義)
$configFile = __DIR__ . "/set/{$module}Set.php";
if (file_exists($configFile)) {
    require $configFile;
    $moduleConfig = $settingPage ?? [];
    $categoryField = $moduleConfig['listPage']['categoryField'] ?? null;
}

// 透過 Bridge 呼叫 AdminController::delete
// 將 $_GET 轉為 POST data 傳給 controller (Controller 通常預期 Body)
$resJson = \App\Bridges\AdminApiBridge::callController($conn, '\App\Controllers\AdminController', 'delete', $_GET);
$res = json_decode($resJson, true);

if ($res['success']) {
    // 獲取該項目的資訊 (用於重定向帶回分類)
    // 雖然項目已刪除/標記刪除，但在 transaction 之前可能有讀過，
    // 或在 API 裡已經處理，這裡我們嘗試讀取原始資料中的分類欄位
    $redirectUrl = PORTAL_AUTH_URL . "tpl={$module}/list";
    
    // 如果 API 有回傳原項目資料或分類 ID (AdminController 應支援回傳 affected category)
    if (isset($res['redirect_category'])) {
        $redirectUrl .= "?selected1=" . $res['redirect_category'];
    }

    header("Location: " . $redirectUrl);
    exit;
} else {
    // 顯示錯誤訊息 (SweetAlert 已在列表頁處理，這裡如果是直接造訪則顯示文字)
    die("Delete failed: " . ($res['message'] ?? 'Unknown error'));
}
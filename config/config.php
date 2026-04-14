<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../');
$dotenv->load();
// ========================================
// URL 參數使用說明
// ========================================
/*
 * 統一的 URL 參數結構（推薦使用常數）：
 *
 * 【新的統一常數】（推薦使用）：
 * - APP_PROTOCOL       : 協議（如：http:// 或 https://）
 * - APP_HOST           : 域名（如：localhost）
 * - APP_FRONTEND_PATH  : 前端路徑（如：/template-system 或 空字串）
 * - APP_BACKEND_PATH   : 後端路徑（如：/template-system/cms 或 /cms）
 * - APP_BASE_URL       : 完整前端 URL = APP_PROTOCOL + APP_HOST + APP_FRONTEND_PATH
 *
 * 【向後相容變數】（舊代碼使用）：
 * - $base_gallery_url  : 等同於 APP_PROTOCOL + APP_HOST
 *
 * 使用建議：
 * - 新代碼：使用 APP_* 開頭的常數
 * - 舊代碼：繼續使用原有變數，已自動對應到新常數
 * - 前端頁面：使用 $baseurl（由 Middleware 注入）
 */

// ========================================
// 系統基本設定
// ========================================
define('SYSTEM_VERSION', '1.0.3');
define('SYSTEM_NAME', '後端管理系統');
define('SYSTEM_TEMPLATE', $_ENV['SYSTEM_TEMPLATE']); // 模板目錄 有 views / template 二選一

// ========================================
// 環境判斷與 URL 路徑設定（統一管理）
// ========================================
// 自動判斷本機或正式環境
define('IS_LOCAL', in_array($_SERVER['HTTP_HOST'], ['127.0.0.1', 'localhost']));

// 定義基礎路徑（本機環境使用專案資料夾名稱，正式環境為空）
define('APP_ROOT_PATH', IS_LOCAL ? '/template-system' : '');

// 定義路徑常數（基於 APP_ROOT_PATH）
define('APP_FRONTEND_PATH', APP_ROOT_PATH);
define('APP_BACKEND_PATH', APP_ROOT_PATH . '/cms');
define('PORTAL_AUTH_URL', APP_ROOT_PATH . '/portal-auth/');

// ========================================
// IP 白名單設定
// ========================================
define('ALLOWED_IPS', [
    '127.0.0.1',
    '::1',
    'localhost',
    '59.126.31.214'
]);

// URL 組件
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
define('APP_PROTOCOL', $protocol);
define('APP_HOST', $host);
define('APP_BASE_URL', $protocol . $host . APP_FRONTEND_PATH);  // 完整前端 URL

// 向後相容的變數（供舊代碼使用）
$base_gallery_url = $protocol . $host;

// ========================================
// 基礎路徑設定
// ========================================
$rootPath = realpath(__DIR__ . '/..');
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$autoBasePath = ($scriptDir === '/' || $scriptDir === '\\') ? '' : str_replace('\\', '/', $scriptDir);

if (!defined('BASE_PATH')) define('BASE_PATH', $autoBasePath);
if (!defined('BASE_PATH_CMS')) define('BASE_PATH_CMS', $rootPath . DIRECTORY_SEPARATOR . 'cms');
if (!defined('ROOT_PATH')) define('ROOT_PATH', $rootPath);

// ========================================
// 語系相關配置
// ========================================
define('DEFAULT_LANG_SLUG', 'tw');                               // 預設語系代碼（用於內部處理、URL、檔案路徑等）
define('DEFAULT_LANG_LOCALE', 'zh-Hant-TW');                     // 預設語系地區（用於 HTML lang 屬性）
if(SYSTEM_TEMPLATE == 'template') { // 圖片路徑格式（{lang} 會被替換為實際語系）
    define('IMG_PATH_FORMAT', '/img/{lang}/');
}else{
    define('IMG_PATH_FORMAT', '/images/{lang}/');
}
define('TEMPLATE_PATH', realpath(__DIR__ . '/../template/'));   // 模板目錄絕對路徑

// ========================================
// CMS 後台設定
// ========================================
define('CMS_LOGOUT_TIME', 3600);                // CMS 登出時間（秒）

// ========================================
// 圖片與檔案相關設定
// ========================================
// 取得伺服器單一檔案上傳限制
$server_upload_max = ini_get('upload_max_filesize');
$server_upload_max = preg_replace('/[^0-9]/', '', $server_upload_max);
$server_upload_max = (int)$server_upload_max;

// 取得伺服器 POST 總計大小限制
$server_post_max = ini_get('post_max_size');
$server_post_max = preg_replace('/[^0-9]/', '', $server_post_max);
$server_post_max = (int)$server_post_max;

$manual_max_size = 3;  // 手動設定的上傳大小限制 (MB)

// 防呆機制：取最小值作為最終限制
// 1. 如果 upload_max_filesize 有設定且小於手動設定，以它為主
// 2. 如果 post_max_size 有設定且更小，以它為主
// 3. 否則以手動設定為主
$final_max_size = $manual_max_size;
if ($server_upload_max > 0 && $server_upload_max < $final_max_size) {
    $final_max_size = $server_upload_max;
}
if ($server_post_max > 0 && $server_post_max < $final_max_size) {
    $final_max_size = $server_post_max;
}

define('DEFAULT_MAX_IMG_SIZE', $final_max_size);      // 全域預設圖片上傳大小限制 (MB)
define('DEFAULT_POST_MAX_SIZE', $server_post_max);    // POST 總計大小限制 (MB)

// ========================================
// 草稿系統設定
// ========================================
define('DRAFT_SYSTEM_ENABLED', false);          // 是否啟用草稿系統 (true/false)
define('DRAFT_AUTO_SAVE_INTERVAL', 300000);     // 自動暫存間隔時間 (毫秒) 預設: 300000 (5分鐘)
define('DRAFT_SHOW_CONSOLE_LOG', true);         // 是否顯示 console.log 偵錯訊息 (true/false)

// ========================================
// 儀錶板設定
// ========================================
define('SHOW_CONTACT_WIDGET', true);            // 是否顯示聯絡表單未讀訊息 (true/false)
define('SHOW_VIEW_LOG_WIDGET', false);           // 是否顯示瀏覽記錄統計 (true/false)
define('VIEW_LOG_STATS_DAYS', 7);               // 瀏覽記錄統計天數 (預設 7 天)

// 瀏覽記錄模組名稱對應表 (d_class1 => 顯示名稱)
// 可以自訂要顯示的模組類型及其名稱
define('VIEW_LOG_MODULE_NAMES', [
    'news'   => '最新消息',
    'latest' => '最新消息',
    'rooms'  => '房型介紹',
    'product' => '產品',
]);
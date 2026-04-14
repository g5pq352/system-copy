<?php
// 設定時區（方便 log 對時）
date_default_timezone_set('Asia/Taipei');

// 顯示所有錯誤
// error_reporting(E_ALL);
error_reporting(E_ALL ^ E_DEPRECATED);

// 在畫面顯示錯誤
ini_set('display_errors', '1');

// 同時記錄錯誤到檔案
ini_set('log_errors', '1');

// 設定錯誤 log 檔位置（本機專用）
ini_set('error_log', __DIR__ . '/../error_log/php_error.log');

// 如果要加上執行時間與錯誤細節
function devErrorHandler($errno, $errstr, $errfile, $errline) {
    $msg = "[" . date('Y-m-d H:i:s') . "] Error {$errno}: {$errstr} in {$errfile} on line {$errline}\n";
    // error_log($msg, 3, __DIR__ . '/../error_log/php_error.log');
    echo nl2br($msg); // 顯示在畫面
}
set_error_handler('devErrorHandler');

// 測試
// trigger_error("這是一個測試錯誤", E_USER_WARNING);

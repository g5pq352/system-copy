<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}


ini_set('date.timezone', 'Asia/Taipei');
// error_reporting(E_ALL ^ E_DEPRECATED);
$dbConfig = require __DIR__ . '/dbset.php';  // 調整為正確路徑
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/dev.php';



// ini_set('display_errors', 0);
// error_reporting(E_ALL);

// 後台懶得改成用class的方式
/* if($_SERVER['HTTP_HOST'] == "127.0.0.1" || $_SERVER['HTTP_HOST'] == "localhost" || $_SERVER['HTTP_HOST'] == "mylocalhost:8082"){
    define("HOSTNAME", "modern_mysql");
    define("DATABASE", "jdbgaming");
    define("USERNAME", "root");
    define("PASSWORD", "rootpass");
}else{
    define("HOSTNAME", "localhost");
    define("DATABASE", "goodsdes_apex-group");
    define("USERNAME", "goodsdes_apex-group");
    define("PASSWORD", "MCNFv2zMxR@X");
} */

define("HOSTNAME", $dbConfig['host']);
define("DATABASE", $dbConfig['dbname']);
define("USERNAME", $dbConfig['username']);
define("PASSWORD", $dbConfig['password']);


try {
    $dsn = "mysql:host=". HOSTNAME .";dbname=". DATABASE .";charset=utf8";
    $conn = new PDO($dsn, USERNAME , PASSWORD);
    
    // 修復 MySQL 5.7+ 嚴格模式 ONLY_FULL_GROUP_BY 問題
    $conn->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

    // 定義網站 ID 與路徑識別碼 (供圖片隔離使用)
    define("SITE_ID", $dbConfig['site_id'] ?? 0);
    define("SITE_DB", $dbConfig['dbname'] ?? DATABASE);

    // 【新增】定義動態上傳路徑網址
    $uploadPath = APP_FRONTEND_PATH . '/uploads';
    if (SITE_ID > 0) {
        $uploadPath .= '/site_' . SITE_ID;
    }
    define("UPLOAD_URL", $uploadPath);

} catch (PDOException $e){
    die("Error: " . $e->getMessage() . "\n");
}


// 前台用包好的class比較方便 (可能吧....)
require(__DIR__ . "/PDO.class.php");
$DB = new Db(HOSTNAME, DATABASE, USERNAME, PASSWORD);
$GLOBALS['db'] = $DB;


// 後台有些地方會用到
$selfPage = basename($_SERVER['PHP_SELF']);

function checkV($d) {
    return (isset($_REQUEST[$d])) ? $_REQUEST[$d] : NULL;
}

function moneyFormat($data, $n = 0) {
    $data1 = number_format(substr($data, 0, strrpos($data, ".") == 0 ? strlen($data) : strrpos($data, ".")));
    $data2 = substr(strrchr($data, "."), 1);
    if ($data2 == 0) {
        $data3 = "";
    } else {
        if (strlen($data2) > $n) {
            $data3 = substr($data2, 0, $n);
        } else {
            $data3 = $data2;
        }

        $data3 = "." . $data3;
    }
    return $data1;
}

function generate_slug($str) {
  // 將字符串轉換為小寫
  $slug = strtolower($str);
  // 替換空格為短橫線
  $slug = preg_replace('/\s+/', '-', $slug);
  // 替換點為底線
  $slug = str_replace('.', '_', $slug);
  // 移除非字母數字、短橫線、下划線和非中文字符
  $slug = preg_replace('/[^\p{Han}a-z0-9-_]/iu', '', $slug);
  // 移除多餘的短橫線
  $slug = preg_replace('/-+/', '-', $slug);
  // 移除首尾的短橫線
  $slug = trim($slug, '-');

  return $slug;
}

function locationTo($locationToPage) {
    header( "Location: {$locationToPage} " );
}

function post_trim($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

function post_intval($key) {
    return isset($_POST[$key]) ? intval($_POST[$key]) : 0;
}

function post_intval_null($key) {
    return isset($_POST[$key]) ? intval($_POST[$key]) : null;
}

function escape_with_br($str) {
    return nl2br(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
}

function trim_o($obj) {
    return isset($obj) ? trim($obj) : null;
}

function intval_o($obj) {
    return isset($obj) ? intval($obj) : 0;
}

function intval_null_o($obj) {
    return isset($obj) ? intval($obj) : null;
}
?>
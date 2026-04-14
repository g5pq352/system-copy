<?php
/**
 * Application Entry Point
 * 極簡化引導文件
 */

# 1. 初始化 Session
$sessionParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $sessionParams['lifetime'],
    'path'     => '/',
    'domain'   => $sessionParams['domain'],
    'secure'   => false, 
    'httponly' => true, 
    'samesite' => 'Lax' 
]);
session_start();

# 2. 基礎路徑定義
defined('APP_DIR') OR define('APP_DIR', __DIR__.DIRECTORY_SEPARATOR."app/"); 

# 3. 自動載入與配置
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/config.php';

use DI\Container;
use Slim\Factory\AppFactory;

# 4. 初始化 DI 容器與設定
$container = new Container();
if(SYSTEM_TEMPLATE == 'template') {
    require APP_DIR . "template_set.php";
}
include APP_DIR . "dependencies.php";

# 5. 建立 Slim 應用程式
AppFactory::setContainer($container);
$app = AppFactory::create();

if (!empty(BASE_PATH)) {
    $app->setBasePath(BASE_PATH);
}

# 【新增】URL 重定向處理
require APP_DIR . "url_redirect.php";

# 6. 載入中間件與路由
require APP_DIR . "middleware.php";
require APP_DIR . "routes_set.php";

# 7. 啟動應用
$app->run();
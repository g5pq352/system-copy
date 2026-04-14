<?php
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\PhpRenderer;
use function DI\autowire;

/**
 * Dependency Injection Container Setup
 */

# 先在全域載入連線定義，確保 $conn 與 $DB 進入全域範圍
require_once 'Connections/connect2data.php';

# 載入全域 Helper Functions
require_once __DIR__ . '/Helpers/helpers.php';

# 1. 資料庫連線 (Database Connection)
$container->set(\DB::class, function() {
    global $DB;
    return $DB; 
});
$container->set(\PDO::class, function() {
    global $conn;
    return $conn; 
});

# 2. 模板渲染器 (View Renderer)
$container->set(PhpRenderer::class, function() {
    if(SYSTEM_TEMPLATE == 'views') {
        return new PhpRenderer(dirname(__DIR__) . '/views/');
    }
    if(SYSTEM_TEMPLATE == 'template') {
        return new PhpRenderer(dirname(__DIR__) . '/template/');
    }
});

# 3. 全域變數注入 (Global Constants/Variables)
$container->set('frontend_url', $GLOBALS['frontend_url'] ?? '');
$container->set('systemTemplateSet', $GLOBALS['systemTemplateSet'] ?? []);

# 4. Middleware 自動注入 (Middleware Autowiring)
$container->set(\App\Middleware\BaseUrlMiddleware::class, autowire()
    ->constructorParameter('basePath', defined('BASE_PATH') ? BASE_PATH : '')
);

$container->set(\App\Middleware\LanguageMiddleware::class, autowire()
    ->constructorParameter('db', $container->get(\DB::class))
);
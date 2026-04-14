<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Application Routes
 */

$view = $container->get(PhpRenderer::class);

# 靜態資源處理 (Assets)
if(SYSTEM_TEMPLATE == 'template') {
    $app->get('/{ignore:.*}images/{path:.+}', function ($request, $response, $args) {
        $path = $args['path'];
        $baseDir = dirname(__DIR__) . '/template/img/images/';
        $imagePath = realpath($baseDir . $path);
        if ($imagePath && strpos($imagePath, realpath($baseDir)) === 0 && file_exists($imagePath)) {
            $response->getBody()->write(file_get_contents($imagePath));
            $mime = mime_content_type($imagePath) ?: 'application/octet-stream';
            return $response->withHeader('Content-Type', $mime);
        }
        return $response->withStatus(404);
    });

    $app->get('/{ignore:.*}img/{path:.+}', function ($request, $response, $args) {
        $path = $args['path'];
        $baseDir = dirname(__DIR__) . '/template/img/';
        $imagePath = realpath($baseDir . $path);
        if ($imagePath && strpos($imagePath, realpath($baseDir)) === 0 && file_exists($imagePath)) {
            $response->getBody()->write(file_get_contents($imagePath));
            $mime = mime_content_type($imagePath) ?: 'application/octet-stream';
            return $response->withHeader('Content-Type', $mime);
        }
        return $response->withStatus(404);
    });

    $app->get('/{ignore:.*}files/{path:.+}', function ($request, $response, $args) {
        $path = $args['path'];
        $baseDir = dirname(__DIR__) . '/template/files/ ';
        $filePath = realpath($baseDir . $path);
        if ($filePath && strpos($filePath, realpath($baseDir)) === 0 && file_exists($filePath)) {
            $response->getBody()->write(file_get_contents($filePath));
            $mime = mime_content_type($filePath) ?: 'application/octet-stream';
            return $response->withHeader('Content-Type', $mime);
        }
        return $response->withStatus(404);
    });
}

# 錯誤頁面 (Error Pages)
$app->get('/404', function ($request, $response) use ($view) {
    return $view->render($response, '404.php');
});

# 前台頁面路由 (Page Routes)
/**
 * 輔助函數:將 slug 轉換為 CamelCase (例如 about-us -> AboutUs)
 * @param string $string
 * @return string
 */
function toCamelCase($string) {
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
}

/**
 * 輔助函數:自動註冊帶語系前綴的路由(支援多語系)
 * @param object $app Slim App 實例
 * @param string $method HTTP 方法 (get/post)
 * @param string $pattern 路由模式
 * @param array $handler 控制器處理器
 */
function registerRoute($app, $method, $pattern, $handler) {
    // 註冊預設路由(無語系前綴,使用預設語系)
    $app->$method($pattern, $handler);
    
    // 註冊語系前綴路由(支援 2-3 個字母的語系代碼)
    // 恢復並保持原始的串接方式 (因為 slug 已經把前後斜線濾掉，不會再產生雙斜線)
    // 首頁 '/' 會變 '/{lang}/'，剛好配合 url_redirect.php 自動加上斜線的策略
    $langPattern = '/{lang:[a-z]{2,3}}' . (($pattern === '/') ? '/' : $pattern);
    // 避免萬一還是有雙斜線
    $langPattern = str_replace('//', '/', $langPattern);
    $app->$method($langPattern, $handler);
}

/**
 * 輔助函數:在 API Group 內自動註冊帶語系前綴的路由(支援多語系)
 * 使用方式: 在 $app->group('/api', function($group) use ($app) { ... }) 內呼叫
 * @param object $app Slim App 實例
 * @param object $group Slim RouteGroup 實例
 * @param string $method HTTP 方法 (get/post)
 * @param string $pattern API 路由模式 (例如: '/home-data')
 * @param array $handler 控制器處理器
 */
function registerApiRoute($app, $group, $method, $pattern, $handler) {
    // 在當前 group 內註冊路由 (預設語系)
    $group->$method($pattern, $handler);
    
    // 在 API group 內註冊語系前綴路由 (正確順序: /api/{lang}/pattern)
    $group->$method('/{lang:[a-z]{2,3}}' . $pattern, $handler);
}

// 用於記錄已註冊的路由，避免重複註冊導致 BadRouteException
$registeredSlugs = ['index', 'home', 'search'];

// 【除錯日誌】記錄動態路由註冊過程
$logFile = __DIR__ . '/../route_log.txt';
$logEntry = "[" . date('Y-m-d H:i:s') . "] --- Start Routing Registration ---" . PHP_EOL;

# -------------------------------------------------------------------------------------------------------------------------------------
# 【動態路由系統】自動註冊模組路由
# -------------------------------------------------------------------------------------------------------------------------------------
try {
    $db = $container->get(\PDO::class);
    // 抓取具有 base_type 的模組
    $sql = "SELECT menu_title, menu_type, menu_base_type FROM cms_menus 
            WHERE menu_parent_id > 0 
            AND menu_base_type IN ('news', 'product', 'info', 'list_only', 'contactus') 
            AND menu_active = 1";
    $modules = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($modules as $m) {
        $slug = trim($m['menu_type'], ' /'); // 移除前後空格及斜線
        if (empty($slug) || in_array($slug, $registeredSlugs)) continue;
        
        $baseType = $m['menu_base_type'];
        // 【修正】移除最前面的斜線，符合 PHP-DI 容器標準呼叫方式
        $className = "App\\Controllers\\" . toCamelCase($slug) . "Controller";
        
        $logEntry .= "Slug: [{$slug}] | BaseType: [{$baseType}] | Class: [{$className}]";

        // 加上 \ 以確保 class_exists 絕對路徑檢查正確
        if (class_exists("\\" . $className)) {
            $logEntry .= " -> OK (Class exists)" . PHP_EOL;
            if ($baseType === 'info' || $baseType === 'list_only') {
                registerRoute($app, 'get', "/$slug", [$className, 'index']);
            } elseif ($baseType === 'contactus') {
                registerRoute($app, 'get', "/$slug", [$className, 'index']);
                registerRoute($app, 'post', "/$slug/send", [$className, 'send']);
            } else {
                // 標準內容模組 (News/Product)
                registerRoute($app, 'get', "/$slug" . '[/{page:[0-9]+}]', [$className, 'index']);
                registerRoute($app, 'get', "/$slug/category/{slug}" . '[/{page:[0-9]+}]', [$className, 'category']);
                registerRoute($app, 'get', "/$slug/tag/{slug}" . '[/{page:[0-9]+}]', [$className, 'tag']);
                registerRoute($app, 'get', "/$slug/detail/{slug}", [$className, 'detail']);
            }
            $registeredSlugs[] = $slug;
        } else {
            $logEntry .= " -> FAILED (Class not found)" . PHP_EOL;
        }
    }
} catch (\Exception $e) {
    $logEntry .= "ERROR: " . $e->getMessage() . PHP_EOL;
}
@file_put_contents($logFile, $logEntry, FILE_APPEND);

# 首頁 (獨立出來，不被 $registeredSlugs 影響阻擋)
registerRoute($app, 'get', '/', [\App\Controllers\HomeController::class, 'index']);

# 關於我們
if (!in_array('about', $registeredSlugs) && class_exists(\App\Controllers\AboutController::class)) {
    registerRoute($app, 'get', '/about', [\App\Controllers\AboutController::class, 'index']);
    $registeredSlugs[] = 'about';
}

# 服務與聯絡 (如果尚未被動態註冊且類別存在)
if (!in_array('service', $registeredSlugs) && class_exists(\App\Controllers\ServiceController::class)) {
    registerRoute($app, 'get', '/service', [\App\Controllers\ServiceController::class, 'index']);
    registerRoute($app, 'post', '/service/send', [\App\Controllers\ServiceController::class, 'send']);
    $registeredSlugs[] = 'service';
}

# 據點
if (class_exists(\App\Controllers\LocationController::class)) {
    registerRoute($app, 'get', '/location', [\App\Controllers\LocationController::class, 'index']);
}

// -------------------------------------------------------------------------------------------------------------------------------------

# 4. API 路由 (Protected)
// $app->group('/api', function ($group) use ($app) {
//     registerApiRoute($app, $group, 'get', '/home-data', [\App\Controllers\HomeController::class, 'getData']);
// });
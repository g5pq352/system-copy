<?php
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\RateLimitMiddleware;

/**
 * Application Middleware
 * 注意：Slim 4 的 middleware 執行順序是 LIFO（後進先出）
 * 最後加入的 middleware 會最先執行
 */

# 3. 錯誤處理控器（最後加入，最先執行）
$displayErrorDetails = true;
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

# 2. 安全標頭與基礎連結處理
$app->add(\App\Middleware\BaseUrlMiddleware::class);
$app->add(new SecurityHeadersMiddleware());

# 1. CSRF 防護 (排除特定路徑)
// 自定義中間件來排除特定路徑的 CSRF 檢查
$app->add(function($request, $handler) use ($container) {
    $path = $request->getUri()->getPath();
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    $relative    = str_replace($basePath, '', $path);

    // 排除不需要 CSRF 檢查的路徑
    $excludedPaths = [
        '/portal-auth/signin', // 登入頁面
        '/admin/' // 後台 API 路徑（由後台登入驗證保護）
    ];

    foreach ($excludedPaths as $excludedPath) {
        if (strpos($relative, $excludedPath) === 0 || $relative === $excludedPath) {
            return $handler->handle($request);
        }
    }

    return $container->get('csrf')->process($request, $handler);
});

$container->set('csrf', function() use ($app) {
    $guard = new \Slim\Csrf\Guard($app->getResponseFactory());
    $guard->setPersistentTokenMode(true);
    return $guard;
});

# 0. 流量限制（在路由之前執行）
$rateLimitConfig = require __DIR__ . '/../config/rate_limit.php';
$app->add(new RateLimitMiddleware($rateLimitConfig));

# -1. 語言和路由中間件（最先加入，最後執行）
$app->add(\App\Middleware\LanguageMiddleware::class);
$app->addRoutingMiddleware();

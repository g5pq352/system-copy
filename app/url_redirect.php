<?php
/**
 * URL 重定向處理
 * 處理各種 URL 重定向規則
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path = substr($requestUri, strlen($basePath));
$path = strtok($path, '?'); // 移除查詢字串
$queryString = $_SERVER['QUERY_STRING'] ?? '';

// ============================================================================
// 1. 後台自訂轉址規則（從資料庫讀取）
// ============================================================================
if (isset($container) && $container->has(\PDO::class)) {
    try {
        $pdo = $container->get(\PDO::class);
        
        // 檢查轉址表是否存在
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'redirects_set'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            // 查詢符合當前路徑的轉址規則
            $stmt = $pdo->prepare("
                SELECT target_url, redirect_type 
                FROM redirects_set 
                WHERE source_url = :path 
                AND is_active = 1 
                LIMIT 1
            ");
            $stmt->execute([':path' => $path]);
            $redirect = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($redirect) {
                $targetUrl = $redirect['target_url'];
                $redirectType = (int)($redirect['redirect_type'] ?? 301);
                
                // 保留查詢字串
                if ($queryString) {
                    $targetUrl .= (strpos($targetUrl, '?') !== false ? '&' : '?') . $queryString;
                }
                
                header('Location: ' . $targetUrl, true, $redirectType);
                exit;
            }
        }
    } catch (Exception $e) {
        // 靜默失敗，不影響正常流程
        error_log("Redirect check failed: " . $e->getMessage());
    }
}

// ============================================================================
// 2. 語系 URL 自動補斜線（/en -> /en/）
// ============================================================================
if (preg_match('#^/([a-z]{2})$#', $path, $matches)) {
    $redirectUrl = $basePath . '/' . $matches[1] . '/' . ($queryString ? '?' . $queryString : '');
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

// ============================================================================
// 3. 移除多餘的結尾斜線 (Trailing Slash) 防止 404
// ============================================================================
// 由於 Slim 路由嚴格區分結尾斜線，除了根目錄 '/' 與語系根目錄 (如 '/tw/') 外，其他頁面結尾若有斜線則 301 轉址移除
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path !== '/' && !preg_match('#^/([a-z]{2,3})/$#', $path) && substr($path, -1) === '/') {
    $redirectUrl = $basePath . rtrim($path, '/') . ($queryString ? '?' . $queryString : '');
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

// ============================================================================
// 4. 其他自訂重定向規則可以在這裡添加
// ============================================================================
// 例如：舊網址轉新網址
// if ($path === '/old-page') {
//     header('Location: ' . $basePath . '/new-page', true, 301);
//     exit;
// }

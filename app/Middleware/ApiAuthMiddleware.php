<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class ApiAuthMiddleware {
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $response = new Response();

        // 1. 檢查 Referer (網域限制)
        // 確保請求來自我們自己的網站
        $referer = $request->getHeaderLine('Referer');
        $host = $request->getHeaderLine('Host');
        
        // 簡單檢查：Referer 必須包含 Host
        // 注意：Postman 或 curl 可以偽造 Referer，但對一般瀏覽器使用者有效
        if (empty($referer) || strpos($referer, $host) === false) {
            $response->getBody()->write(json_encode(['error' => 'Forbidden: Invalid Referer'], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // 2. 檢查 Token (從 .env 獲取設定)
        // 前端需在 Header 帶入 X-API-TOKEN
        $serverToken = $_ENV['API_TOKEN'] ?? getenv('API_TOKEN');
        
        if (!empty($serverToken)) {
            $clientToken = $request->getHeaderLine('X-API-TOKEN');
            
            if ($clientToken !== $serverToken) {
                $response->getBody()->write(json_encode(['error' => 'Forbidden: Invalid Token'], JSON_UNESCAPED_UNICODE));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        }
        
        // 3. 檢查 AJAX (選擇性，增加難度)
        
        // 3. 檢查 AJAX (選擇性，增加難度)
        // $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
        // if (!$isAjax) {
        //      // return 403...
        // }

        return $handler->handle($request);
    }
}

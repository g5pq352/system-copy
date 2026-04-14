<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Views\PhpRenderer;

class BaseUrlMiddleware {
    protected $view;
    protected $basePath;

    public function __construct(PhpRenderer $view, string $basePath) {
        $this->view = $view;
        $this->basePath = $basePath;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response {
        // 優先使用 config 中已定義的 APP_BASE_URL (避免重複計算)
        // 如果未定義則動態計算 (保持向後相容)
        if (defined('APP_BASE_URL')) {
            $baseurl = APP_BASE_URL;
        } else {
            $uri = $request->getUri();
            $baseurl = rtrim($uri->getScheme() . '://' . $uri->getAuthority() . $this->basePath, '/');
        }
        
        // 取得當前完整 URL
        $current_url = (string)$request->getUri();

        // 注入到 view 中供模板使用
        $this->view->addAttribute('baseurl', $baseurl);
        $this->view->addAttribute('current_url', $current_url);

        return $handler->handle($request);
    }
}

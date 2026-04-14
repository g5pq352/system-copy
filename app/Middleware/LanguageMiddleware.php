<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;
use Slim\Views\PhpRenderer;

class LanguageMiddleware {
    protected $view;
    protected $db;

    public function __construct(PhpRenderer $view, \DB $db) {
        $this->view = $view;
        $this->db = $db;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // 預設語系
        $lang = DEFAULT_LANG_SLUG;
        
        // 1. 從路由參數取得語系
        if ($route) {
            $langArg = $route->getArgument('lang');
            
            if ($langArg) {
                // 驗證語系是否存在於資料庫
                if (!$this->isValidLanguage($langArg)) {
                    throw new \Slim\Exception\HttpNotFoundException($request);
                }

                $lang = $langArg;
                // 更新 Global 變數供 Model 使用 (不寫入 Session/Cookie)
                $GLOBALS['frontend_lang'] = $lang;
            } else {
                // URL 沒有語系前綴，使用預設語系 (或需要的話讀取 Browser Language，這裡先單純用 Default)
                // 這裡不讀取 Session/Cookie，完全依賴 URL
                // 但如果需要保持「首頁」的語系記憶，可能需要保留一點邏輯？
                // 用戶說 "我是用網址判斷的"，那首頁通常就是預設語系。
            }
        }
        
        // 再次確保 Global 變數有值
        if (!isset($GLOBALS['frontend_lang'])) {
             $GLOBALS['frontend_lang'] = $lang;
        }
        
        // 注入語系變數到 View
        $this->view->addAttribute('lang', $lang);

        // 載入語言包並注入（一次性從 DB 讀取，建構 key 索引陣列）
        $langPack = $this->loadLangPack();
        $GLOBALS['langPack'] = $langPack;
        $this->view->addAttribute('langPack', $langPack);
        
        // 注入語系屬性到 Request (讓後續 Controller 可用)
        $request = $request->withAttribute('lang', $lang);

        return $handler->handle($request);
    }

    protected function isValidLanguage($slug) {
        $sql = "SELECT count(*) as count FROM languages WHERE l_slug = ? AND l_active = 1";
        $result = $this->db->query($sql, [$slug]);
        return isset($result[0]['count']) && $result[0]['count'] > 0;
    }

    /**
     * 從資料庫載入語言包，建構 key 索引陣列
     * 回傳格式: ['contact_us' => ['lp_tw' => '聯絡我們', 'lp_en' => 'Contact Us', ...], ...]
     */
    protected function loadLangPack(): array {
        try {
            $rows = $this->db->query("SELECT * FROM language_packs ORDER BY lp_sort ASC, lp_id ASC");
            $pack = [];
            foreach ($rows as $row) {
                $pack[$row['lp_key']] = $row;
            }
            return $pack;
        } catch (\Exception $e) {
            error_log('loadLangPack error: ' . $e->getMessage());
            return [];
        }
    }
}

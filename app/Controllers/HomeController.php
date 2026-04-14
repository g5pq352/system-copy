<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController extends Controller {

    public function index(Request $request, Response $response, $args) {
        // 1. 直覺抓取：一行代碼搞定資訊 + 所有圖片 (第三個參數傳 true)
        $this->popInfo = $this->repo->getModuleInfo('popInfo', 'popInfoCover', true);
        
        // 2. 現在你可以直接在 Controller 讀取屬性了 (因為加了 __get)
        if ($this->popInfo) {
            $id = $this->popInfo['d_id']; // 這樣現在可以跑了
            // 這裡可以做更多邏輯處理...
        }

        // 3. 最新消息快報 (首頁新聞區，抓 3 筆)
        $this->latestNews = $this->repo->getLatestItems('news', 3);

        // 4. 自訂熱門推廣區 (例如 promotion 模組)
        $this->promotion = $this->repo->getModuleList('promotion', ['limit' => 5]);

        return $this->render($response, 'index.php');
    }
}
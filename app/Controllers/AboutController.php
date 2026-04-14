<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutController extends Controller {

    public function index(Request $request, Response $response, $args) {
        // 使用 getModuleInfo 抓取關於我們
        $this->info = $this->repo->getModuleInfo('about', 'aboutCover');
        
        // 可選：抓取首頁推廣輪播等
        $this->promotionList = $this->repo->getModuleList('promotion', ['fileType' => 'image']);

        return $this->render($response, 'about.php');
    }
}
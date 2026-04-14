<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class {ClassName} extends Controller {
    protected $moduleSlug = '{Slug}';
    protected $fileType = '{Slug}Cover';
    protected $perPage = 18;

    public function index(Request $request, Response $response, $args) {
        $queryParams = $request->getQueryParams();
        $keyword = isset($queryParams['search']) ? trim($queryParams['search']) : null;
        
        // 1. 取得總筆數
        $totalCount = $this->repo->getModuleCount($this->moduleSlug, ['keyword' => $keyword]);
        
        // 2. 初始化分頁
        $this->initPagination($args, $totalCount, "/{$this->moduleSlug}/", $this->perPage);

        // 3. 撈取資料 (統一使用 getModuleList 方法)
        $this->list = $this->repo->getModuleList($this->moduleSlug, [
            'keyword' => $keyword,
            'fileType' => $this->fileType,
            'limit' => $this->perPage,
            'offset' => ($this->pages->current_page - 1) * $this->perPage
        ]);

        $this->keyword = $keyword;
        $this->setSearchAction("/{$this->moduleSlug}/");

        return $this->render($response, '{Slug}_list.php');
    }
}

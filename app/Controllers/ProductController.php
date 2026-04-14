<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductController extends Controller {
    protected $slug = 'product';
    protected $fileType = 'productCover';
    protected $perPage = 12;

    public function index(Request $request, Response $response, $args) {
        $q = $request->getQueryParams()['search'] ?? null;
        
        $total = $this->repo->getModuleCount($this->slug, ['keyword' => $q]);
        $this->initPagination($args, $total, "/{$this->slug}/", $this->perPage);

        $this->list = $this->repo->getModuleList($this->slug, [
            'keyword' => $q,
            'fileType' => $this->fileType,
            'limit' => $this->perPage,
            'offset' => ($this->pages->current_page - 1) * $this->perPage
        ]);

        // 使用巢狀分類抓取 (樹狀結構)
        $this->productCat = $this->repo->getNestedCategories($this->slug . 'C');
        $this->keyword = $q;
        $this->setSearchAction("/{$this->slug}/");

        return $this->render($response, 'product.php');
    }

    public function category(Request $request, Response $response, $args) {
        $catSlug = addslashes($args['slug']);
        $cat = $this->repo->getCategoryBySlug($catSlug);
        if (!$cat) return $response->withHeader('Location', '../404')->withStatus(302);

        $q = $request->getQueryParams()['search'] ?? null;
        
        // 此處 getModuleCount 內部已支援遞迴抓取子分類產品
        $total = $this->repo->getModuleCount($this->slug, ['categoryId' => $cat['t_id'], 'keyword' => $q]);
        $this->initPagination($args, $total, "/{$this->slug}/category/{$catSlug}/", $this->perPage);

        // 此處 getModuleList 內部已支援遞迴抓取子分類產品
        $this->list = $this->repo->getModuleList($this->slug, [
            'categoryId' => $cat['t_id'],
            'keyword' => $q,
            'fileType' => $this->fileType,
            'limit' => $this->perPage,
            'offset' => ($this->pages->current_page - 1) * $this->perPage
        ]);

        $this->categoryInfo = $cat;
        $this->productCat = $this->repo->getNestedCategories($this->slug . 'C');
        $this->keyword = $q;
        $this->setSearchAction("/{$this->slug}/", $catSlug);

        return $this->render($response, 'product.php');
    }

    public function detail(Request $request, Response $response, $args) {
        $row = $this->repo->getDetail($args['slug'], $this->slug);
        if (!$row) return $response->withHeader('Location', '../404')->withStatus(302);

        $this->repo->incrementView($row['d_id']);
        
        // 產品大圖輪播
        $this->images = $this->repo->getListFile($row['d_id'], 'image');
        
        $this->row = $row;
        $this->productCat = $this->repo->getNestedCategories($this->slug . 'C');

        return $this->render($response, 'product_detail.php');
    }
}

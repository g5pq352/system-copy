<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BlogController extends Controller {
    protected $slug = 'blog';
    protected $fileType = 'blogCover';
    protected $perPage = 6;

    public function index(Request $request, Response $response, $args) {
        $q = $request->getQueryParams()['search'] ?? null;
        
        $this->first = $this->repo->getModuleFirst($this->slug, ['keyword' => $q]);
        $excludeId = $this->first['d_id'] ?? null;

        $total = $this->repo->getModuleCount($this->slug, ['keyword' => $q]);
        $paginatedTotal = $excludeId ? max(0, $total - 1) : $total;
        $this->initPagination($args, $paginatedTotal, "/{$this->slug}/", $this->perPage);

        $this->list = $this->repo->getModuleList($this->slug, [
            'keyword' => $q,
            'excludeId' => $excludeId,
            'limit' => $this->perPage,
            'offset' => ($this->pages->current_page - 1) * $this->perPage
        ]);

        $this->categories = $this->repo->getModuleCategories($this->slug);
        $this->keyword = $q;
        $this->setSearchAction("/{$this->slug}/");

        return $this->render($response, 'blog.php');
    }

    public function category(Request $request, Response $response, $args) {
        $catSlug = addslashes($args['slug']);
        $cat = $this->repo->getCategoryBySlug($catSlug);
        if (!$cat) return $response->withHeader('Location', '../404')->withStatus(302);

        $q = $request->getQueryParams()['search'] ?? null;
        
        $this->first = $this->repo->getModuleFirst($this->slug, ['categoryId' => $cat['t_id'], 'keyword' => $q]);
        $excludeId = $this->first['d_id'] ?? null;

        $total = $this->repo->getModuleCount($this->slug, ['categoryId' => $cat['t_id'], 'keyword' => $q]);
        $paginatedTotal = $excludeId ? max(0, $total - 1) : $total;
        $this->initPagination($args, $paginatedTotal, "/{$this->slug}/category/{$catSlug}/", $this->perPage);

        $this->list = $this->repo->getModuleList($this->slug, [
            'categoryId' => $cat['t_id'],
            'keyword' => $q,
            'excludeId' => $excludeId,
            'limit' => $this->perPage,
            'offset' => ($this->pages->current_page - 1) * $this->perPage
        ]);

        $this->categoryInfo = $cat;
        $this->categories = $this->repo->getModuleCategories($this->slug);
        $this->keyword = $q;
        $this->setSearchAction("/{$this->slug}/", $catSlug);

        return $this->render($response, 'blog.php');
    }

    public function detail(Request $request, Response $response, $args) {
        $row = $this->repo->getDetail($args['slug'], $this->slug);
        if (!$row) return $response->withHeader('Location', '../404')->withStatus(302);

        $this->repo->incrementView($row['d_id']);
        $this->prev = $this->repo->getNeighbor($this->slug, $row['d_date'], 'prev');
        $this->next = $this->repo->getNeighbor($this->slug, $row['d_date'], 'next');
        $this->images = $this->repo->getListFile($row['d_id'], $this->fileType);
        
        $this->work = $row;
        $this->categories = $this->repo->getModuleCategories($this->slug);

        return $this->render($response, 'blog_detail.php');
    }
}
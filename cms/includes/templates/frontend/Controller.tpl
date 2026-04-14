<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Repositories\{RepoName};

class {ClassName} extends Controller {
    protected $slug = '{Slug}';
    protected $fileType = '{Slug}Cover';
    protected $perPage = 12;

    public function __construct(\Psr\Container\ContainerInterface $container) {
        parent::__construct($container);
        $this->repo = new {RepoName}($this->db, $this->isAdmin);
    }

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

        $this->categories = $this->repo->getHierarchyTree($this->slug . 'C', $this->slug);
        $this->keyword = $q;
        $this->setSearchAction("/{$this->slug}/");

        return $this->render($response, '{Slug}_list.php');
    }

    public function category(Request $request, Response $response, $args) {
        $catSlug = addslashes($args['slug']);
        $cat = $this->repo->getCategoryBySlug($catSlug);
        if (!$cat) return $response->withHeader('Location', '../404')->withStatus(302);

        $q = $request->getQueryParams()['search'] ?? null;
        
        $total = $this->repo->getModuleCount($this->slug, ['categoryId' => $cat['t_id'], 'keyword' => $q]);
        $this->initPagination($args, $total, "/{$this->slug}/category/{$catSlug}/", $this->perPage);

        $this->list = $this->repo->getModuleList($this->slug, [
            'categoryId' => $cat['t_id'],
            'keyword' => $q,
            'fileType' => $this->fileType,
            'limit' => $this->perPage,
            'offset' => ($this->pages->current_page - 1) * $this->perPage
        ]);

        $this->categoryInfo = $cat;
        $this->categories = $this->repo->getHierarchyTree($this->slug . 'C', $this->slug);
        $this->keyword = $q;
        $this->setSearchAction("/{$this->slug}/", $catSlug);

        return $this->render($response, '{Slug}_list.php');
    }

    public function detail(Request $request, Response $response, $args) {
        $row = $this->repo->getDetail($args['slug'], $this->slug);
        if (!$row) return $response->withHeader('Location', '../404')->withStatus(302);

        $this->repo->incrementView($row['d_id']);
        $this->prev = $this->repo->getNeighbor($this->slug, $row['d_date'], 'prev');
        $this->next = $this->repo->getNeighbor($this->slug, $row['d_date'], 'next');
        
        // 單張大圖已由 getDetail 自動攤平到 $row['file_link1']
        // 如需多圖輪播，可自行呼叫 $this->repo->getListFile($row['d_id'], 'image')
        
        $this->work = $row;
        $this->categories = $this->repo->getNestedCategories($this->slug . 'C');

        return $this->render($response, '{Slug}_detail.php');
    }
}

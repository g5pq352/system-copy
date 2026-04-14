<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class {ClassName} extends Controller {
    protected $moduleSlug = '{Slug}';
    protected $fileType = '{Slug}Cover';

    public function index(Request $request, Response $response, $args) {
        // 1. 取得模組資訊 (單筆資料)
        $this->info = $this->repo->getModuleInfo($this->moduleSlug, $this->fileType);

        return $this->render($response, '{Slug}.php');
    }
}

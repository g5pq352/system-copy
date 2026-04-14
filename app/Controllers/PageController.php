<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PageController extends Controller {

    // 前端
    public function error_four(Request $request, Response $response, $args) {
        return $this->render($response, '404.php');
    }
    
    // 後端
    // public function signin(Request $request, Response $response, $args) {
    //     require realpath(__DIR__ . '/../../cms/login.php');
    //     return $response;
    // }
    // public function dashboard(Request $request, Response $response, $args) {
    //     require realpath(__DIR__ . '/../../cms/first.php');
    //     return $response;
    // }
}
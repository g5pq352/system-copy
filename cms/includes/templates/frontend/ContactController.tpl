<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MailService;
use App\Services\ContactService;

class {ClassName} extends Controller {

    public function index(Request $request, Response $response, $args) {
        // 抓取頁面的排版資訊 (例如 SEO 標題、說明等)
        $this->info = $this->repo->getModuleInfo('{Slug}');
        
        return $this->render($response, '{Slug}.php');
    }

    /**
     * [表單發送]
     */
    public function send(Request $request, Response $response, $args) {
        $postData = $request->getParsedBody();
        
        // 1. 初始化相關服務 (注入 DB 物件)
        $contactService = new ContactService($this->db->getPDO());
        $mailService = new MailService();

        try {
            // 2. 存入資料庫 (data_set 表)
            $dbData = [
                'd_title'   => ($postData['name'] ?? 'Guest') . ' - {Name}',
                'd_class1'  => '{Slug}',
                'd_data1'   => $postData['subject'] ?? ($postData['inquiry_type'] ?? ''),
                'd_data2'   => $postData['email'] ?? '',
                'd_data3'   => $postData['phone'] ?? '',
                'd_data4'   => $postData['address'] ?? '',
                'd_content' => $postData['message'] ?? '',
                'd_active'  => 1,
                'lang'      => $this->currentLang
            ];
            $contactService->save($dbData);

            // 3. 發送郵件通知
            $mailStatus = $mailService->sendContactMail($postData);

            return $response->withJson([
                'status'  => 'success',
                'message' => '表單已成功送出'
            ]);
            
        } catch (\Exception $e) {
            return $response->withJson([
                'status'  => 'error', 
                'message' => '發送失敗：' . $e->getMessage()
            ]);
        }
    }
}

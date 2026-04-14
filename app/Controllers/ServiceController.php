<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MailService;
use App\Services\ContactService;

class ServiceController extends Controller {

    public function index(Request $request, Response $response, $args) {
        // 抓取聯絡我們頁面的排版資訊 (例如 SEO 標題、說明、大圖等)
        $this->info = $this->repo->getModuleInfo('contact');
        
        return $this->render($response, 'contact.php');
    }

    /**
     * [表單發送] 聯絡我們 / 服務諮詢
     */
    public function send(Request $request, Response $response, $args) {
        $postData = $request->getParsedBody();
        
        // 1. 初始化相關服務 (注入 DB 物件)
        $contactService = new ContactService($this->db->getPDO());
        $mailService = new MailService();

        try {
            // 2. 存入資料庫 (data_set 表)
            $dbData = [
                'd_title'   => ($postData['name'] ?? 'Guest') . ' - 聯絡表單',
                'd_class1'  => 'contactus',
                'd_class2'  => 0,
                'd_data1'   => $postData['inquiry_type'] ?? '',
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
                'status'  => $mailStatus ? 'success' : 'error',
                'message' => $mailStatus ? '表單已成功送出' : '資料已存檔，但郵件發送失敗'
            ]);
            
        } catch (\Exception $e) {
            return $response->withJson([
                'status'  => 'error', 
                'message' => '發送失敗：' . $e->getMessage()
            ]);
        }
    }
}
<?php
namespace App\Services;

use PHPMailer;

class MailService {
    private $mailer;

    public function __construct() {
        // 引用 mailer_init.php
        if (!function_exists('getConfiguredMailer')) {
            $initPath = __DIR__ . '/../../config/mailer_init.php';
            if (file_exists($initPath)) {
                require_once($initPath);
            }
        }
        
        if (function_exists('getConfiguredMailer')) {
            $this->mailer = getConfiguredMailer();
        } else {
            // Fallback just in case, though usually path is correct
            $this->mailer = new PHPMailer();
        }
        
        $this->mailer->SingleTo = true;
    }

    /**
     * 發送客戶服務聯繫表單郵件
     */
    /**
     * 通用郵件發送方法
     * @param array $config 配置矩陣
     * [
     *   'from' => ['email' => '...', 'name' => '...'],
     *   'replyTo' => ['email' => '...', 'name' => '...'],
     *   'to' => ['email' => '...', 'name' => '...'], // 或 [['email'=>'...', 'name'=>'...'], ...]
     *   'subject' => '...',
     *   'body' => '...'
     * ]
     */
    public function send(array $config) {
        $this->mailer->ClearAllRecipients();
        $this->mailer->ClearReplyTos();

        // 1. 設定 From
        if (!empty($config['from'])) {
            $this->mailer->SetFrom($config['from']['email'], $config['from']['name']);
        }

        // 2. 設定 ReplyTo
        if (!empty($config['replyTo'])) {
            $this->mailer->AddReplyTo($config['replyTo']['email'], $config['replyTo']['name']);
        }

        // 3. 設定 To
        if (!empty($config['to'])) {
            if (isset($config['to']['email'])) {
                // 單一收件人
                $this->mailer->AddAddress($config['to']['email'], $config['to']['name'] ?? '');
            } else {
                // 多收件人矩陣
                foreach ($config['to'] as $recipient) {
                    $this->mailer->AddAddress($recipient['email'], $recipient['name'] ?? '');
                }
            }
        }

        // 4. 設定內容
        $this->mailer->Subject = $config['subject'];
        $this->mailer->Body = $config['body'];
        $this->mailer->IsHTML(true);

        return $this->mailer->Send();
    }

    /**
     * 發送客戶服務聯繫表單郵件
     */
    public function sendContactMail($data) {
        $mailContent = "<div style='max-width: 500px; letter-spacing: 1px;'>"
            ."舊振南官網管理員，您好！<br><br>"
            ."==================================================<br><br><br>"
            ."詢問類型： {$data['inquiry_type']} <br><br>"
            ."姓名： {$data['name']} <br><br>"
            ."電子郵件： {$data['email']} <br><br>"
            ."手機： {$data['phone']} <br><br>"
            ."聯絡地址： {$data['address']} <br><br>"
            ."方便聯絡時間： {$data['time']} <br><br>"
            ."聯繫日期： {$data['date']} <br><br>"
            ."<div style='line-height: 2;'>"
            ."內容： {$data['message']} <br><br>"
            ."</div>"
            ."==================================================<br><br>"
            ."<br><br>"
            ."<div style='color: red;'>此為系統發信，請勿直接回覆。</div>"
            ."</div>";

        return $this->send([
            'from' => ['email' => SMTP_SET_FROM, 'name' => '舊振南'],
            'replyTo' => ['email' => SMTP_REPLY_TO, 'name' => '舊振南'],
            'to' => ['email' => SMTP_TO, 'name' => '舊振南 - 客戶服務聯繫表單'],
            'subject' => "舊振南 - {$data['name']}",
            'body' => $mailContent
        ]);
    }

    /**
     * 發送企業客制聯繫表單郵件
     */
    public function sendCorporateMail($data) {
        $mailContent = "<div style='max-width: 500px; letter-spacing: 1px;'>"
            ."舊振南官網管理員，您好！<br><br>"
            ."==================================================<br><br><br>"
            ."姓名： {$data['name']} <br><br>"
            ."電子郵件： {$data['email']} <br><br>"
            ."手機： {$data['phone']} <br><br>"
            ."聯絡地址： {$data['address']} <br><br>"
            ."公司名稱： {$data['company']} <br><br>"
            ."客製化需求： {$data['customized']} <br><br>"
            ."方便聯絡時間： {$data['time']} <br><br>"
            ."聯繫日期： {$data['date']} <br><br>"
            ."<div style='line-height: 2;'>"
            ."內容： {$data['message']} <br><br>"
            ."</div>"
            ."==================================================<br><br>"
            ."<br><br>"
            ."<div style='color: red;'>此為系統發信，請勿直接回覆。</div>"
            ."</div>";

        return $this->send([
            'from' => ['email' => SMTP_SET_FROM, 'name' => '舊振南'],
            'replyTo' => ['email' => SMTP_REPLY_TO, 'name' => '舊振南'],
            'to' => ['email' => SMTP_TO, 'name' => '舊振南 - 企業客制聯繫表單'],
            'subject' => "舊振南 - {$data['name']}",
            'body' => $mailContent
        ]);
    }

    /**
     * 發送喜餅品鑑預約表單郵件
     */
    public function sendWeddingMail($data) {
        $mailContent = "<div style='max-width: 500px; letter-spacing: 1px;'>"
            ."舊振南官網管理員，您好！<br><br>"
            ."==================================================<br><br><br>"
            ."姓名： {$data['name']} <br><br>"
            ."電子郵件： {$data['email']} <br><br>"
            ."手機： {$data['phone']} <br><br>"
            ."聯絡地址： {$data['address']} <br><br>"
            ."預算金額： {$data['budget']} <br><br>"
            ."預計婚期： {$data['date']} <br><br>"
            ."==================================================<br><br>"
            ."<br><br>"
            ."<div style='color: red;'>此為系統發信，請勿直接回覆。</div>"
            ."</div>";

        return $this->send([
            'from' => ['email' => SMTP_SET_FROM, 'name' => '舊振南'],
            'replyTo' => ['email' => SMTP_REPLY_TO, 'name' => '舊振南'],
            'to' => ['email' => SMTP_TO, 'name' => '舊振南 - 喜餅品鑑預約表單'],
            'subject' => "舊振南 - {$data['name']}",
            'body' => $mailContent
        ]);
    }

    /**
     * 取得錯誤訊息
     */
    public function getError() {
        return $this->mailer->ErrorInfo;
    }
}

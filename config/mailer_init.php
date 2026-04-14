<?php
// 引用 PHPMailer (假設路徑是固定的)
require_once(__DIR__ . '/../cms/plugin/PHPMailerAutoload.php');
require_once(__DIR__ . '/mail_config.php'); // 確保配置已載入

function getConfiguredMailer() {
    $mail = new PHPMailer();
    
    // 設定 SMTP
    $mail->IsSMTP();
    $mail->SMTPDebug = 0; // 0 = off, 1 = client messages, 2 = client and server messages
    $mail->SMTPAuth = true;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // 使用 mail_config.php 定義的常數
    $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : "smtp.gmail.com";
    $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : "system_send@goods-design.com.tw";
    $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : "hugcjvlleeovzjnq";
    
    // 預設編碼設定
    $mail->SetLanguage('zh', '/PHPMailer/language/');
    $mail->ContentType = "text/html";
    $mail->CharSet = "UTF-8";
    $mail->Encoding = "base64";
    $mail->Timeout = 60;
    
    return $mail;
}
?>

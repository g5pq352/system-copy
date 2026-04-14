<?php
require_once('../Connections/connect2data.php');
require_once('../config/config.php');

// 權限檢查 (如需放寬可註解)
if (!isset($_SESSION['MM_LoginAccountUsername'])) {
     echo json_encode(['status' => 'error', 'message' => '未登入']);
     exit;
}

// 接收參數
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$reply_subject = isset($_POST['reply_subject']) ? trim($_POST['reply_subject']) : '';
$reply_content = isset($_POST['reply_message']) ? trim($_POST['reply_message']) : '';

if ($id <= 0 || empty($reply_subject) || empty($reply_content)) {
    echo json_encode(['status' => 'error', 'message' => '參數不完整']);
    exit;
}

try {
    // 1. 為了安全，從資料庫撈取收件人 Email
    // 假設 table 是 message_set，PK 是 m_id，Email 欄位是 m_email
    $sql = "SELECT m_email, m_title FROM message_set WHERE m_id = :id AND m_reply = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => '找不到該筆資料']);
        exit;
    }

    $toEmail = $row['m_email'];
    $toName = $row['m_title'];

    if (empty($toEmail)) {
        echo json_encode(['status' => 'error', 'message' => '該筆資料沒有 Email']);
        exit;
    }

    // 2. 寄信處理 (使用 PHPMailer)
    // 由於 MailService 在 app/Services 下，我們嘗試手動引用 PHPMailer
    require_once(__DIR__ . '/../config/mailer_init.php');
    $mail = getConfiguredMailer();
    
    $mail->SingleTo = true;

    // 設定寄件/收件資訊
    $mail->SetFrom(SMTP_SET_FROM, '公司');
    $mail->AddReplyTo(SMTP_REPLY_TO, '公司');
    $mail->AddAddress($toEmail, $toName);
    $mail->Subject = "公司客服回覆 - " . $reply_subject;

    // 郵件內容 (簡易版)
    $mailContent = "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px;'>"
                 . "親愛的 {$toName} 您好：<br><br>"
                 . "感謝您聯繫公司。<br>"
                 . "關於您的詢問，我們的回覆如下：<br><br>"
                 . "<div style='background-color: #f9f9f9; padding: 15px; border-left: 4px solid #007bff;'>" . nl2br($reply_content) . "</div><br><br>"
                 . "若有任何問題，歡迎隨時與我們聯繫。<br><br>"
                 . "公司 敬上"
                 . "</div>";

    $mail->Body = $mailContent;
    $mail->IsHTML(true);

    if ($mail->Send()) {
        // 3. 寄信成功，更新資料庫 m_reply = 1
        $updateSql = "UPDATE message_set SET m_reply = 1 WHERE m_id = :id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([':id' => $id]);

        // 4. 新增回覆紀錄到 message_reply
        try {
            $insertReplySql = "INSERT INTO message_reply (m_id, r_subject, r_content, r_date, r_admin) VALUES (:m_id, :subject, :content, NOW(), :admin)";
            $insertReplyStmt = $conn->prepare($insertReplySql);
            $insertReplyStmt->execute([
                ':m_id' => $id,
                ':subject' => $reply_subject,
                ':content' => $reply_content,
                ':admin' => $_SESSION['MM_LoginAccountUsername'] ?? 'System' // 紀錄回覆者
            ]);
        } catch (Exception $e) {
            // 紀錄失敗但不影響主要流程，或可記錄 log
            error_log("Failed to insert reply record: " . $e->getMessage());
        }

        echo json_encode(['status' => 'success', 'message' => '回覆郵件發送成功，狀態已更新。']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '郵件發送失敗: ' . $mail->ErrorInfo]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '系統錯誤: ' . $e->getMessage()]);
}
?>

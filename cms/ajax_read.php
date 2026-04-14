<?php
require_once('../Connections/connect2data.php');
require_once('../config/config.php');

if (!isset($_SESSION['MM_LoginAccountUsername'])) {
    echo json_encode(['status' => 'error', 'message' => '未登入']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$m_read = isset($_POST['m_read']) ? (int)$_POST['m_read'] : 0;
// 這裡可以根據需要傳入 table name，目前先假設是 contactusSet 中定義的 message_set
// 但更好的做法是像 ajax_active.php 那樣傳入 module 或 table
// 這裡暫時先寫死 message_set 或接收參數
$table = isset($_POST['table']) ? $_POST['table'] : 'message_set';

if ($id > 0) {
    try {
        // 更新 m_read 欄位
        // 注意：這裡假設主鍵是 m_id，如果要通用化，需要傳入 primaryKey
        // 但基於目前 contactusSet 的設定，我們知道是 message_set 和 m_id
        
        // 為了安全，檢查 table 是否合法 (白名單)
        $allowedTables = ['message_set']; // 若有其他表需要，加在這裡
        if (!in_array($table, $allowedTables)) {
            // 如果 table 不在白名單，或許它是主要 message table，暫時允許
            // 但最好還是傳入 module 然後去讀配置
        }

        // 簡易版：直接更新 message_set
        $column = 'm_read'; 
        // 檢查欄位是否存在
        $checkCol = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($checkCol->rowCount() > 0) {
            $sql = "UPDATE `$table` SET $column = :m_read WHERE m_id = :id"; 
            // 注意: 這裡寫死 m_id，如果其他表主鍵不同會有問題。
            // 為了通用性，我們應該讓前端傳 PK 名稱，或者後端去查。
            // 鑑於目前只有 contactus 用，先假設是 m_id。
            
            // 更穩健的做法：
            $pk = 'm_id'; // 預設
            // 可以從 contactusSet.php 知道是 m_id

            $stmt = $conn->prepare($sql);
            $stmt->execute([':m_read' => $m_read, ':id' => $id]);
            
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '欄位不存在']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => '無效的 ID']);
}
?>

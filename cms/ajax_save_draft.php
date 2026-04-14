<?php
/**
 * AJAX Handler: Save Draft
 * 儲存草稿功能
 */
require_once('../Connections/connect2data.php');

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['MM_LoginAccountUserId'])) {
        throw new Exception('請先登入');
    }

    $userId = $_SESSION['MM_LoginAccountUserId'];
    $module = $_POST['module'] ?? '';
    $recordId = (int)($_POST['record_id'] ?? 0);
    $targetTable = $_POST['target_table'] ?? '';
    $draftData = $_POST['form_data'] ?? ''; // JSON string or array
    
    // 獲取當前網址參數 (Context) - 前端傳來的是 JSON string
    $urlParams = $_POST['url_params'] ?? '{}';

    if (empty($module) || empty($targetTable)) {
        throw new Exception('缺少必要參數 (Module or Target Table)');
    }

    // 將 form_data 確保轉為 JSON 字串存入
    if (is_array($draftData)) {
        $draftData = json_encode($draftData, JSON_UNESCAPED_UNICODE);
    }
    
    // 驗證一下 JSON 格式是否正確
    if (!json_decode($draftData) && !empty($draftData)) {
        // 如果不是有效 JSON，可能要編碼? 但前端應該傳 JSON string
        // 暫定前端已經傳好 serializeArray() 的結果
    }

    $sql = "INSERT INTO cms_drafts 
            (user_id, module, target_table, record_id, draft_data, url_params, updated_at) 
            VALUES (:uid, :mod, :table, :rid, :data, :params, NOW()) 
            ON DUPLICATE KEY UPDATE 
            draft_data = VALUES(draft_data), 
            url_params = VALUES(url_params),
            updated_at = NOW()";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':uid' => $userId,
        ':mod' => $module,
        ':table' => $targetTable,
        ':rid' => $recordId,
        ':data' => $draftData,
        ':params' => $urlParams
    ]);

    echo json_encode([
        'success' => true,
        'message' => '草稿已儲存 (' . date('H:i:s') . ')'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

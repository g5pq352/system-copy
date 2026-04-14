<?php
/**
 * AJAX Handler: Load Draft
 * 檢查並讀取草稿
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
    
    // 用於 Context 檢查 (例如確保 parent_id 一致) (可選)
    // $currentParams = $_POST['current_params'] ?? []; 

    if (empty($module)) {
        throw new Exception('缺少 Module 參數');
    }

    $sql = "SELECT * FROM cms_drafts 
            WHERE user_id = :uid 
            AND module = :mod 
            AND record_id = :rid";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':uid' => $userId,
        ':mod' => $module,
        ':rid' => $recordId
    ]);
    
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($draft) {
        echo json_encode([
            'success' => true,
            'has_draft' => true,
            'draft' => $draft,
            'message' => '發現未儲存的草稿'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_draft' => false,
            'message' => '無草稿'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

<?php
/**
 * AJAX Handler: Delete Draft
 * 刪除草稿功能
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

    if (empty($module)) {
        throw new Exception('缺少 Module 參數');
    }

    $sql = "DELETE FROM cms_drafts WHERE user_id = :uid AND module = :mod AND record_id = :rid";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':uid' => $userId,
        ':mod' => $module,
        ':rid' => $recordId
    ]);

    echo json_encode([
        'success' => true,
        'message' => '草稿已刪除'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

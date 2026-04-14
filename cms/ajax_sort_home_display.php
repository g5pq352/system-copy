<?php
/**
 * AJAX: 首頁顯示排序
 * 功能：更新首頁顯示項目的排序
 * 每個模組獨立排序
 */

require_once('../Connections/connect2data.php');
require_once('auth.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '無效的請求方法']);
    exit;
}

try {
    $sortData = $_POST['sort_data'] ?? '';
    $module = $_POST['module'] ?? '';
    $lang = $_POST['lang'] ?? DEFAULT_LANG_SLUG;

    if (!$sortData || !$module) {
        throw new Exception('缺少必要參數');
    }

    // 解析排序資料 (格式: "hd_id1,hd_id2,hd_id3")
    $ids = explode(',', $sortData);

    if (empty($ids)) {
        throw new Exception('排序資料格式錯誤');
    }

    // 開始交易
    $conn->beginTransaction();

    // 更新每個項目的排序
    $stmt = $conn->prepare("
        UPDATE home_display
        SET hd_sort = :sort
        WHERE hd_id = :id
        AND hd_module = :module
        AND lang = :lang
    ");

    foreach ($ids as $index => $id) {
        $id = trim($id);
        if ($id) {
            $stmt->execute([
                ':sort' => $index + 1,
                ':id' => $id,
                ':module' => $module,
                ':lang' => $lang
            ]);
        }
    }

    // 提交交易
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => '排序已更新'
    ]);

} catch (Exception $e) {
    // 發生錯誤時回滾
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

<?php
/**
 * AJAX: 更新首頁顯示單一項目排序
 * 功能：更新單一項目的排序，並自動調整其他項目
 */

require_once('../Connections/connect2data.php');
require_once('auth.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '無效的請求方法']);
    exit;
}

try {
    $hdId = $_POST['hd_id'] ?? 0;
    $newSort = $_POST['new_sort'] ?? 0;
    $module = $_POST['module'] ?? '';
    $lang = $_POST['lang'] ?? DEFAULT_LANG_SLUG;

    if (!$hdId || !$newSort || !$module) {
        throw new Exception('缺少必要參數');
    }

    // 開始交易
    $conn->beginTransaction();

    // 1. 取得目前的排序值
    $stmt = $conn->prepare("SELECT hd_sort FROM home_display WHERE hd_id = :id");
    $stmt->execute([':id' => $hdId]);
    $currentSort = $stmt->fetchColumn();

    if ($currentSort === false) {
        throw new Exception('找不到該項目');
    }

    // 2. 如果排序值沒有改變，直接返回成功
    if ($currentSort == $newSort) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => '排序未改變']);
        exit;
    }

    // 3. 調整其他項目的排序
    if ($newSort < $currentSort) {
        // 向上移動：將 newSort 到 currentSort-1 之間的項目排序 +1
        $stmt = $conn->prepare("
            UPDATE home_display
            SET hd_sort = hd_sort + 1
            WHERE hd_module = :module
            AND lang = :lang
            AND hd_sort >= :newSort
            AND hd_sort < :currentSort
        ");
        $stmt->execute([
            ':module' => $module,
            ':lang' => $lang,
            ':newSort' => $newSort,
            ':currentSort' => $currentSort
        ]);
    } else {
        // 向下移動：將 currentSort+1 到 newSort 之間的項目排序 -1
        $stmt = $conn->prepare("
            UPDATE home_display
            SET hd_sort = hd_sort - 1
            WHERE hd_module = :module
            AND lang = :lang
            AND hd_sort > :currentSort
            AND hd_sort <= :newSort
        ");
        $stmt->execute([
            ':module' => $module,
            ':lang' => $lang,
            ':currentSort' => $currentSort,
            ':newSort' => $newSort
        ]);
    }

    // 4. 更新目標項目的排序
    $stmt = $conn->prepare("
        UPDATE home_display
        SET hd_sort = :newSort
        WHERE hd_id = :id
    ");
    $stmt->execute([
        ':newSort' => $newSort,
        ':id' => $hdId
    ]);

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

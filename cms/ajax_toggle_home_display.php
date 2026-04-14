<?php
/**
 * AJAX: 切換首頁顯示狀態
 * 功能：將資料加入或移除首頁顯示
 */

require_once('../Connections/connect2data.php');
require_once('auth.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '無效的請求方法']);
    exit;
}

try {
    $dataId = $_POST['data_id'] ?? 0;
    $module = $_POST['module'] ?? '';
    $currentStatus = $_POST['current_status'] ?? 0;
    $lang = $_POST['lang'] ?? DEFAULT_LANG_SLUG;

    if (!$dataId || !$module) {
        throw new Exception('缺少必要參數');
    }

    // 開始交易
    $conn->beginTransaction();

    if ($currentStatus == 1) {
        // 目前已在首頁，要移除

        // 1. 先取得要刪除項目的排序值
        $stmt = $conn->prepare("
            SELECT hd_sort FROM home_display
            WHERE hd_data_id = :dataId
            AND hd_module = :module
            AND lang = :lang
        ");
        $stmt->execute([
            ':dataId' => $dataId,
            ':module' => $module,
            ':lang' => $lang
        ]);
        $deletedSort = $stmt->fetchColumn();

        // 2. 刪除該項目
        $stmt = $conn->prepare("
            DELETE FROM home_display
            WHERE hd_data_id = :dataId
            AND hd_module = :module
            AND lang = :lang
        ");
        $stmt->execute([
            ':dataId' => $dataId,
            ':module' => $module,
            ':lang' => $lang
        ]);

        // 3. 調整其他項目的排序（將大於被刪除項目排序的項目都 -1）
        if ($deletedSort !== false) {
            $stmt = $conn->prepare("
                UPDATE home_display
                SET hd_sort = hd_sort - 1
                WHERE hd_module = :module
                AND lang = :lang
                AND hd_sort > :deletedSort
            ");
            $stmt->execute([
                ':module' => $module,
                ':lang' => $lang,
                ':deletedSort' => $deletedSort
            ]);
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'new_status' => 0,
            'message' => '已從首頁移除'
        ]);

    } else {
        // 目前不在首頁，要加入
        // 先取得目前該模組的最大排序值
        $stmt = $conn->prepare("
            SELECT COALESCE(MAX(hd_sort), 0) as max_sort
            FROM home_display
            WHERE hd_module = :module
            AND lang = :lang
        ");
        $stmt->execute([
            ':module' => $module,
            ':lang' => $lang
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $newSort = $result['max_sort'] + 1;

        // 插入新記錄
        $stmt = $conn->prepare("
            INSERT INTO home_display (hd_module, hd_data_id, hd_sort, hd_active, lang)
            VALUES (:module, :dataId, :sort, 1, :lang)
        ");
        $stmt->execute([
            ':module' => $module,
            ':dataId' => $dataId,
            ':sort' => $newSort,
            ':lang' => $lang
        ]);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'new_status' => 1,
            'new_sort' => $newSort,
            'message' => '已加入首頁'
        ]);
    }

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

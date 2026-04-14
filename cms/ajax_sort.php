<?php
/**
 * AJAX 排序處理 - 通用動態版
 * 自動讀取設定檔中的欄位名稱，支援無置頂欄位的資料表
 */
session_start();
require_once '../Connections/connect2data.php';

// 【除錯】開啟錯誤顯示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 【除錯】捕捉 Fatal Error
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $error['message'] . ' on line ' . $error['line']]);
        exit;
    }
});
header('Content-Type: application/json');

// 啟用錯誤報告用於調試 (正式上線建議關閉 display_errors)
error_reporting(E_ALL);
ini_set('display_errors', 0); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$module     = $_POST['module'] ?? '';
$itemId     = intval($_POST['item_id'] ?? 0);
$newSort    = intval($_POST['new_sort'] ?? 0);
$categoryId = intval($_POST['category_id'] ?? 0);

if (!$module || !$itemId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// 載入模組配置
$configFile = __DIR__ . "/set/{$module}Set.php";
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'message' => "Module config not found: {$module}Set.php"]);
    exit;
}

// 載入設定檔，有些設定檔回傳 array，有些是用變數 $settingPage
$moduleConfig = require $configFile;
if (!is_array($moduleConfig) && isset($settingPage)) {
    $moduleConfig = $settingPage;
}

if (!is_array($moduleConfig)) {
    echo json_encode(['success' => false, 'message' => 'Invalid config format']);
    exit;
}

// ---------------------------------------------------------------------
// 【關鍵修改】動態欄位對應
// ---------------------------------------------------------------------
$tableName = $moduleConfig['tableName'];
$menuKey   = $moduleConfig['menuKey'] ?? null;
$menuValue = $moduleConfig['menuValue'] ?? null;

// 取得主鍵名稱 (例如 d_id 或 t_id)
$col_id    = $moduleConfig['primaryKey'];

// 取得自定義欄位設定
$cols      = $moduleConfig['cols'] ?? [];

// 定義排序與置頂欄位 (如果沒設定，預設為 d_ 開頭，但確保 null 被保留)
$col_sort  = array_key_exists('sort', $cols) ? $cols['sort'] : 'd_sort';
$col_top   = array_key_exists('top', $cols) ? $cols['top'] : 'd_top';

// 取得分類欄位 (如果有)
$categoryField = $moduleConfig['listPage']['categoryField'] ?? null;

// 【新增】檢查是否使用 Map Table 排序
$useTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;
// ---------------------------------------------------------------------

// ---------------------------------------------------------------------
// 【直接處理】排序邏輯
// ---------------------------------------------------------------------
try {
    require_once 'includes/taxonomyMapHelper.php';
    require_once 'includes/SortReorganizer.php';
    require_once 'includes/UnifiedSortManager.php';

    $conn->beginTransaction();

    // 取得當前項目資訊
    $stmt = $conn->prepare("SELECT * FROM {$tableName} WHERE {$col_id} = :id");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('找不到資料');
    }

    $useMapTableSort = ($categoryId > 0 && hasTaxonomyMapTable($conn) && $useTaxonomyMapSort);

    // 插入式排序：將項目插入到目標位置，其他項目順移
    if ($useMapTableSort) {
        // 在分類視圖下排序
        $stmtOld = $conn->prepare("SELECT sort_num FROM data_taxonomy_map WHERE d_id = :id AND t_id = :tid");
        $stmtOld->execute([':id' => $itemId, ':tid' => $categoryId]);
        $oldSort = $stmtOld->fetchColumn();

        if ($oldSort && $oldSort != $newSort) {
            if ($oldSort < $newSort) {
                // 向下移動
                $conn->prepare("
                    UPDATE data_taxonomy_map dtm
                    INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$col_id}
                    SET dtm.sort_num = dtm.sort_num - 1
                    WHERE dtm.t_id = :tid
                    AND dtm.sort_num > :old_sort
                    AND dtm.sort_num <= :new_sort
                    AND dtm.d_id != :id
                    AND (dtm.d_top = 0 OR dtm.d_top IS NULL)
                ")->execute([
                    ':tid' => $categoryId,
                    ':old_sort' => $oldSort,
                    ':new_sort' => $newSort,
                    ':id' => $itemId
                ]);
            } else {
                // 向上移動
                $conn->prepare("
                    UPDATE data_taxonomy_map dtm
                    INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$col_id}
                    SET dtm.sort_num = dtm.sort_num + 1
                    WHERE dtm.t_id = :tid
                    AND dtm.sort_num >= :new_sort
                    AND dtm.sort_num < :old_sort
                    AND dtm.d_id != :id
                    AND (dtm.d_top = 0 OR dtm.d_top IS NULL)
                ")->execute([
                    ':tid' => $categoryId,
                    ':new_sort' => $newSort,
                    ':old_sort' => $oldSort,
                    ':id' => $itemId
                ]);
            }
        }

        // 更新當前項目的排序值
        $conn->prepare("UPDATE data_taxonomy_map SET sort_num = :new_sort WHERE d_id = :id AND t_id = :tid")
             ->execute([':new_sort' => $newSort, ':id' => $itemId, ':tid' => $categoryId]);
    } else {
        // 在全部視圖下排序
        $oldSort = $item[$col_sort] ?? null;

        if ($oldSort && $oldSort != $newSort) {
            $baseConditions = ["1=1"];
            $baseParams = [];

            // 【新增】分層級排序處理 (判斷 parent_id 與 t_level)
            $col_parent = $cols['parent_id'] ?? null;
            if ($col_parent && array_key_exists($col_parent, $item)) {
                $pVal = $item[$col_parent];
                if ($pVal === null || $pVal === '' || $pVal === 0) {
                    $baseConditions[] = "({$col_parent} = 0 OR {$col_parent} IS NULL OR {$col_parent} = '')";
                } else {
                    $baseConditions[] = "{$col_parent} = :pVal";
                    $baseParams[':pVal'] = $pVal;
                }
            }
            
            // 如果有 t_level 欄位，也要判斷 (使用者特別提到)
            if (isset($item['t_level'])) {
                $baseConditions[] = "t_level = :tLevel";
                $baseParams[':tLevel'] = $item['t_level'];
            }

            if ($menuKey) {
                $mVal = ($menuValue !== null) ? $menuValue : ($item[$menuKey] ?? null);
                if ($mVal !== null) {
                    $baseConditions[] = "{$menuKey} = :menuValue";
                    $baseParams[':menuValue'] = $mVal;
                }
            }

            if (isset($item['lang'])) {
                $baseConditions[] = "lang = :lang";
                $baseParams[':lang'] = $item['lang'];
            }

            // 排除置頂項目
            if ($col_top) {
                try {
                    $checkTopCol = $conn->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
                    $checkTopCol->execute([$col_top]);
                    if ($checkTopCol->fetch()) {
                        $baseConditions[] = "({$col_top} = 0 OR {$col_top} IS NULL)";
                    }
                } catch (Exception $e) {
                    // 欄位不存在，忽略
                }
            }

            // 排除軟刪除項目
            $col_delete_time = $cols['delete_time'] ?? 'd_delete_time';
            try {
                $checkCol = $conn->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
                $checkCol->execute([$col_delete_time]);
                if ($checkCol->fetch()) {
                    $baseConditions[] = "({$col_delete_time} IS NULL OR {$col_delete_time} = '0000-00-00 00:00:00')";
                }
            } catch (Exception $e) {
                // 欄位不存在，忽略
            }

            $whereBase = implode(' AND ', $baseConditions);

            if ($oldSort < $newSort) {
                // 向下移動
                $params = array_merge($baseParams, [
                    ':old_sort' => $oldSort,
                    ':new_sort' => $newSort,
                    ':id' => $itemId
                ]);
                $conn->prepare("
                    UPDATE {$tableName}
                    SET {$col_sort} = {$col_sort} - 1
                    WHERE {$whereBase}
                    AND {$col_sort} > :old_sort
                    AND {$col_sort} <= :new_sort
                    AND {$col_id} != :id
                ")->execute($params);
            } else {
                // 向上移動
                $params = array_merge($baseParams, [
                    ':new_sort' => $newSort,
                    ':old_sort' => $oldSort,
                    ':id' => $itemId
                ]);
                $conn->prepare("
                    UPDATE {$tableName}
                    SET {$col_sort} = {$col_sort} + 1
                    WHERE {$whereBase}
                    AND {$col_sort} >= :new_sort
                    AND {$col_sort} < :old_sort
                    AND {$col_id} != :id
                ")->execute($params);
            }
        }

        // 更新當前項目的排序值
        $conn->prepare("UPDATE {$tableName} SET {$col_sort} = :new_sort WHERE {$col_id} = :id")
             ->execute([':new_sort' => $newSort, ':id' => $itemId]);
    }

    // 使用統一排序管理器進行全域與分類重排
    \UnifiedSortManager::updateAfterDataChange($conn, $moduleConfig, $itemId, [
        'lang' => $item['lang'] ?? null,
        'categoryId' => $categoryId
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => '排序已更新']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '錯誤: ' . $e->getMessage()]);
}

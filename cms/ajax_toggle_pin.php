<?php
/**
 * AJAX 置頂切換處理
 * 處理項目的置頂狀態切換
 */
session_start();
require_once '../Connections/connect2data.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$module = $_POST['module'] ?? '';
$itemId = intval($_POST['item_id'] ?? 0);
$categoryId = intval($_POST['category_id'] ?? 0); // 【新增】取得分類 ID

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

require_once $configFile;
$moduleConfig = $settingPage;

$tableName = $moduleConfig['tableName'];

// ---------------------------------------------------------------------
// 【直接處理】置頂切換邏輯
// ---------------------------------------------------------------------
try {
    require_once 'includes/elements/ModuleConfigElement.php';
    require_once 'includes/taxonomyMapHelper.php';
    require_once 'includes/SortReorganizer.php';
    require_once 'includes/UnifiedSortManager.php';

    $moduleConfig = \ModuleConfigElement::loadConfig($module);
    $tableName = $moduleConfig['tableName'];
    $primaryKey = $moduleConfig['primaryKey'];
    $cols = $moduleConfig['cols'] ?? [];
    $col_top = $cols['top'] ?? 'd_top';
    $col_sort = $cols['sort'] ?? 'd_sort';
    $parentIdField = $cols['parent_id'] ?? null;
    $menuKey = $moduleConfig['menuKey'] ?? null;
    $menuValue = $moduleConfig['menuValue'] ?? null;
    $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? true;

    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT * FROM {$tableName} WHERE {$primaryKey} = :id");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('找不到資料');
    }

    $useMapPin = ($configUseTaxonomyMapSort && $categoryId > 0 && hasTaxonomyMapTable($conn));

    if ($useMapPin) {
        // 切換 Map d_top
        $stmtCheck = $conn->prepare("SELECT d_top FROM data_taxonomy_map WHERE d_id = :id AND t_id = :tid");
        $stmtCheck->execute([':id' => $itemId, ':tid' => $categoryId]);
        $currentTop = $stmtCheck->fetchColumn() ?: 0;
        $newTop = $currentTop ? 0 : 1;
        $conn->prepare("UPDATE data_taxonomy_map SET d_top = :top WHERE d_id = :id AND t_id = :tid")
             ->execute([':top' => $newTop, ':id' => $itemId, ':tid' => $categoryId]);
    } else {
        // 切換主表 d_top
        $currentTop = $item[$col_top] ?? 0;
        $newTop = $currentTop ? 0 : 1;
        $conn->prepare("UPDATE {$tableName} SET {$col_top} = :top WHERE {$primaryKey} = :id")
             ->execute([':top' => $newTop, ':id' => $itemId]);
    }

    // 使用統一排序管理器進行全域與分類重排
    \UnifiedSortManager::updateAfterDataChange($conn, $moduleConfig, $itemId, [
        'lang' => $item['lang'] ?? null,
        'categoryId' => $categoryId
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => '置頂狀態已更新']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '錯誤: ' . $e->getMessage()]);
}

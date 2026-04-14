<?php
require_once '../Connections/connect2data.php';
require_once __DIR__ . '/includes/elements/ModuleConfigElement.php';
require_once __DIR__ . '/includes/elements/FormProcessElement.php';

header('Content-Type: application/json');

// 開啟錯誤日誌（開發階段）
error_reporting(E_ALL);
ini_set('display_errors', 0); // 不直接顯示錯誤，避免破壞 JSON 格式
ini_set('log_errors', 1);

try {
    $module = $_POST['module'] ?? '';
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $currentId = intval($_POST['currentId'] ?? 0);
    $formData = $_POST['formData'] ?? []; // 其他表單資料 (用於分類/語系判斷)

    // 調試日誌
    error_log("=== ajax_check_duplicate.php ===");
    error_log("Module: $module");
    error_log("Field: $field");
    error_log("Value: $value");
    error_log("Current ID: $currentId");

    if (empty($module) || empty($field)) {
        throw new Exception('缺少參數: module=' . $module . ', field=' . $field);
    }

    $moduleConfig = ModuleConfigElement::loadConfig($module);
    $tableName = $moduleConfig['tableName'];

    // 取得欄位配置
    $fieldConfig = null;
    foreach ($moduleConfig['detailPage'] as $sheet) {
        $items = isset($sheet['items']) ? $sheet['items'] : [$sheet];
        foreach ($items as $item) {
            if (isset($item['field']) && $item['field'] === $field) {
                $fieldConfig = $item;
                break 2;
            }
        }
    }

    if (!$fieldConfig) {
        // 嘗試從全域配置獲取
        if (isset($moduleConfig['checkDuplicateTitle']) && $field === ($moduleConfig['cols']['title'] ?? 'd_title')) {
            $fieldConfig = [
                'field' => $field,
                'checkDuplicate' => true
            ];
        }
    }

    if (!$fieldConfig || empty($fieldConfig['checkDuplicate'])) {
        error_log("欄位未設定檢查重複: $field");
        echo json_encode(['isDuplicate' => false, 'message' => '此欄位未設定檢查重複']);
        exit;
    }

    // 整合全域與欄位配置
    $checkConfig = array_merge($moduleConfig['checkDuplicateTitle'] ?? [], $fieldConfig);
    $checkConfig['enabled'] = true;

    error_log("開始檢查重複...");
    $result = FormProcessElement::checkDuplicateField(
        $conn,
        $tableName,
        $field,
        $value,
        $checkConfig,
        $formData,
        $moduleConfig,
        $currentId
    );

    error_log("檢查結果: " . json_encode($result));
    echo json_encode($result);

} catch (Exception $e) {
    error_log("錯誤: " . $e->getMessage());
    error_log("堆疊追蹤: " . $e->getTraceAsString());

    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'isDuplicate' => false
    ]);
}
?>

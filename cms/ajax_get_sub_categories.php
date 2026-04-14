<?php
require_once('../Connections/connect2data.php');
require_once('auth.php');
require_once(__DIR__ . '/includes/categoryHelper.php');

$parentId = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
$categoryName = isset($_GET['category']) ? $_GET['category'] : '';

if (empty($categoryName)) {
    echo json_encode([]);
    exit;
}

// 獲取目前語系
$currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;

try {
    $results = getSubCategoryOptions($categoryName, $parentId);

    header('Content-Type: application/json');
    echo json_encode($results);

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>

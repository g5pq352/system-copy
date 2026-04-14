<?php
/**
 * AJAX 取得子分類
 * 用於連動分類下拉選單
 */
session_start();
require_once '../../Connections/connect2data.php';
require_once 'categoryHelper.php';

header('Content-Type: application/json');

$category = $_GET['category'] ?? '';
$parentId = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
$lang = $_GET['lang'] ?? DEFAULT_LANG_SLUG;

if (!$category) {
    echo json_encode(['success' => false, 'message' => 'Missing category parameter']);
    exit;
}

try {
    // 設定語系 session (讓 getSubCategoryOptions 能正確使用)
    $_SESSION['editing_lang'] = $lang;
    
    // 使用 getSubCategoryOptions 取得子分類
    // 這個函數會正確處理 taxonomies 表的階層結構
    $childCategories = getSubCategoryOptions($category, $parentId);
    
    echo json_encode([
        'success' => true,
        'categories' => $childCategories
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

<?php
/**
 * Generic List Page
 * 通用列表頁面 - 完全客製化欄位名稱版本
 */

require_once('../Connections/connect2data.php');
require_once('../config/config.php');
require_once 'auth.php';

// 載入 Element 模組
require_once(__DIR__ . '/includes/elements/ModuleConfigElement.php');
require_once(__DIR__ . '/includes/elements/PermissionElement.php');
require_once(__DIR__ . '/includes/elements/SwalConfirmElement.php');

// 載入其他輔助函數
require_once(__DIR__ . '/includes/permissionCheck.php');
require_once(__DIR__ . '/includes/categoryHelper.php');
require_once(__DIR__ . '/includes/buttonElement.php');
require_once(__DIR__ . '/includes/SortCountHelper.php');

// 獲取模組名稱
$module = $_GET['module'] ?? '';

try {
    // 載入模組配置（使用 Element）
    $moduleConfig = ModuleConfigElement::loadConfig($module);

    // 檢查使用者對此模組的權限（使用 Element）
    list($canView, $canAdd, $canEdit, $canDelete) = PermissionElement::checkModulePermission($conn, $module);

    // 要求檢視權限
    PermissionElement::requireViewPermission($canView);

} catch (Exception $e) {
    die($e->getMessage());
}

$menu_is = $moduleConfig['module'];
$_SESSION['nowMenu'] = $menu_is;

// 【首頁顯示管理】檢查是否為首頁顯示管理模組
$isHomeDisplayModule = $moduleConfig['isHomeDisplayModule'] ?? false;
$targetModule = $moduleConfig['targetModule'] ?? null;

// 設定每頁顯示筆數與當前頁碼
$maxRows = $moduleConfig['listPage']['itemsPerPage'] ?? 20;
$pageNum = isset($_GET['pageNum']) ? (int) $_GET['pageNum'] : 0;
$startRow = $pageNum * $maxRows;

// -----------------------------------------------------------------------
// 【關鍵修改】讀取設定檔中的欄位對應 (若沒設定則使用預設值 d_xxx)
// -----------------------------------------------------------------------
$tableName = $moduleConfig['tableName'];
$col_id = $moduleConfig['primaryKey'];
$primaryKey = $moduleConfig['primaryKey'];  // 【新增】供按鈕函數使用

// 嘗試從設定檔讀取 cols，如果沒有就用空陣列
$customCols = $moduleConfig['cols'] ?? [];

// 定義系統欄位變數 (優先使用設定檔，否則使用預設 d_ 開頭)
$col_date = array_key_exists('date', $customCols) ? $customCols['date'] : 'd_date';
$col_title = array_key_exists('title', $customCols) ? $customCols['title'] : 'd_title';
$col_sort = array_key_exists('sort', $customCols) ? $customCols['sort'] : 'd_sort';
$col_top = array_key_exists('top', $customCols) ? $customCols['top'] : 'd_top';
$col_active = array_key_exists('active', $customCols) ? $customCols['active'] : 'd_active';
$col_delete_time = array_key_exists('delete_time', $customCols) ? $customCols['delete_time'] : 'd_delete_time';
$col_read = array_key_exists('read', $customCols) ? $customCols['read'] : 'd_read'; // 新增
$col_reply = array_key_exists('reply', $customCols) ? $customCols['reply'] : 'd_reply'; // 新增回覆狀態
$col_status = array_key_exists('status', $customCols) ? $customCols['status'] : 'm_status'; // 新增處理狀態
$col_file_fk = $customCols['file_fk'] ?? 'file_d_id';
// -----------------------------------------------------------------------

// 【新增】語系處理
$langField = 'lang';
$activeLanguages = $conn->query("SELECT * FROM languages WHERE l_active = 1 ORDER BY l_sort ASC, l_id ASC")->fetchAll(PDO::FETCH_ASSOC);
$defaultLang = DEFAULT_LANG_SLUG; // 預設值
foreach ($activeLanguages as $al) {
    if ($al['l_is_default']) $defaultLang = $al['l_slug'];
}

// 優先順序：網址參數 > Session > 預設語系
$currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? $defaultLang;
$_SESSION['editing_lang'] = $currentLang;

// 3. 分類處理
$hasCategory = $moduleConfig['listPage']['hasCategory'] ?? false;
$categoryName = $hasCategory ? $moduleConfig['listPage']['categoryName'] : null;
$categoryField = $hasCategory ? $moduleConfig['listPage']['categoryField'] : null;
// 【重構】動態處理分類選取與階層自動補全
$selectedCategory = null;
$selectedCategories = []; // 儲存所有層級的選擇
if ($hasCategory) {
    // 1. 找出目前獲器的所有層級 (支援無限層)
    $maxScanDepth = 20; 
    for ($i = 1; $i <= $maxScanDepth; $i++) {
        $param = 'selected' . $i;
        if (isset($_GET[$param]) && $_GET[$param] !== '' && $_GET[$param] !== 'all' && $_GET[$param] !== '0') {
            $val = (int)$_GET[$param];
            $selectedCategories[$i-1] = $val;
            $selectedCategory = $val; // 最深層的值
        } else {
            break; // 中斷即停止，確保連續性
        }
    }

    $maxSelectedIdx = count($selectedCategories);

    if ($maxSelectedIdx > 0) {
        $deepestVal = $selectedCategory;
        
        // 【向上補全】如果只提供了一個參數，但它可能不是第一層，則自動向上補路徑
        if ($maxSelectedIdx == 1 && is_array($categoryField)) {
            $path = [];
            $currentId = $deepestVal;
            while ($currentId > 0) {
                $stmt = $conn->prepare("SELECT t_id, parent_id FROM taxonomies WHERE t_id = :id");
                $stmt->execute([':id' => $currentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) break;
                array_unshift($path, $row['t_id']);
                $currentId = $row['parent_id'];
            }
            
            if (count($path) > 1) {
                // 需要重定向補全路徑
                $redirectUrl = $_SERVER['REQUEST_URI'];
                $newParams = [];
                foreach ($path as $idx => $id) {
                    $newParams[] = 'selected' . ($idx + 1) . '=' . $id;
                }
                $newQuery = implode('&', $newParams);
                $redirectUrl = preg_replace('/selected1=\d+/', $newQuery, $redirectUrl);
                header("Location: {$redirectUrl}");
                exit;
            }
        }

        // 【向下鑽取 (Auto Drill-down)】如果當前選取的分類有子分類，直接跳到第一個子分類
        // 這解決了「父層無法排序」的問題，強制使用者進入最底層或有資料的層級
        $children = ($deepestVal > 0) ? getSubCategoryOptions($categoryName, $deepestVal) : [];
        if (!empty($children)) {
            $nextLevel = $maxSelectedIdx + 1;
            $firstChildId = $children[0]['id'];
            
            // 檢查 URL 是否已經有下一層，如果沒有才跳轉
            if (!isset($_GET['selected' . $nextLevel])) {
                $redirectUrl = $_SERVER['REQUEST_URI'];
                $sep = (strpos($redirectUrl, '?') === false) ? '?' : '&';
                header("Location: {$redirectUrl}{$sep}selected{$nextLevel}={$firstChildId}");
                exit;
            }
        }

        $selectedCategory = $deepestVal;
    }
}

// 如果有分類，載入分類選項
$categories = [];
$isLinkedCategory = false;
$categoryFields = [];
$selectedCategories = []; // 儲存所有層級的選擇

if ($hasCategory && $categoryName) {
    // 檢查是否為連動分類 (linked categories)
    if (is_array($categoryField)) {
        $isLinkedCategory = true;
        $categoryFields = $categoryField;
        
        // 【選項 B】載入第一層分類 (只要頂層，parent_id = 0)
        $categories = getSubCategoryOptions($categoryName, 0);
        
        // 收集所有層級的選擇，並決定最後的 $selectedCategory
        foreach ($categoryFields as $index => $field) {
            $paramName = 'selected' . ($index + 1);
            if (isset($_GET[$paramName]) && $_GET[$paramName] !== '' && $_GET[$paramName] !== 'all') {
                $val = (int)$_GET[$paramName];
                $selectedCategories[$index] = $val;
                $selectedCategory = $val; // 越後面的層級覆蓋前面的
            }
        }
    } else {
        // 單一分類欄位
        $categories = getCategoryOptions($categoryName, null, null, true);
    }
}

// 建立查詢條件
$menuKey = $moduleConfig['menuKey'] ?? null;
$menuValue = $moduleConfig['menuValue'] ?? null;
$orderBy = $moduleConfig['listPage']['orderBy'] ?? "{$col_id} DESC";

// 判斷是否為回收桶模式
$isTrashMode = isset($_GET['trash']) && $_GET['trash'] == '1';

// 檢查資料表是否有回收桶欄位
// 【修正】如果 col_delete_time 為 null，直接設為 false
if ($col_delete_time === null || $col_delete_time === '') {
    $columnExists = false;
} else {
    $checkColumnQuery = "SHOW COLUMNS FROM {$tableName} LIKE '{$col_delete_time}'";
    $stmt = $conn->prepare($checkColumnQuery);
    $stmt->execute();
    $columnExists = ($stmt->rowCount() > 0);
}

// 檢查是否支援回收桶（設定優先於資料表檢測）
$hasTrashConfig = $moduleConfig['listPage']['hasTrash'] ?? null;
if ($hasTrashConfig === false) {
    $hasTrash = false;
    $isTrashMode = false; // 如果模組不支援回收桶，強制關閉回收桶模式
} else {
    $hasTrash = $columnExists ? true : false;
}

$hasTrashData = false;

if ($hasTrash) {
    // 【修改】檢查是否有語系欄位
    $checkLangColQuery = "SHOW COLUMNS FROM {$tableName} LIKE '{$langField}'";
    $langColStmt = $conn->prepare($checkLangColQuery);
    $langColStmt->execute();
    $hasLangField = ($langColStmt->rowCount() > 0);
    
    // 【修改】使用變數，並加入語系過濾
    $checkTrashDataQuery = "SELECT 1 FROM {$tableName} 
                            WHERE {$menuKey} = :menuValue 
                            AND {$col_delete_time} IS NOT NULL";
    
    // 如果有語系欄位且不是 languages 表，加入語系過濾
    if ($hasLangField && $tableName !== 'languages') {
        $checkTrashDataQuery .= " AND {$langField} = :currentLang";
    }
    
    $checkTrashDataQuery .= " LIMIT 1";

    $trashStmt = $conn->prepare($checkTrashDataQuery);
    $trashParams = [':menuValue' => $menuValue];
    
    if ($hasLangField && $tableName !== 'languages') {
        $trashParams[':currentLang'] = $currentLang;
    }
    
    $trashStmt->execute($trashParams);

    if ($trashStmt->fetchColumn()) {
        $hasTrashData = true;
    }
}

// 【階層導航】檢查是否有 parent_id 參數（用於階層式選單）
$parentId = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : null;
$hasHierarchy = $moduleConfig['listPage']['hasHierarchy'] ?? false;
$hasHierarchicalNav = $hasHierarchy && isset($moduleConfig['cols']['parent_id']);
$parentIdField = $customCols['parent_id'] ?? null;

// 【垃圾桶功能】檢查模組是否支援軟刪除
$hasTrash = !empty($customCols['delete_time']);

// 5. 構建查詢條件
$conditions = [];
$params = [];

// 【首頁顯示管理】如果使用 customQuery，需要使用表別名
$tableAlias = $tableName;
if ($isHomeDisplayModule && !empty($moduleConfig['listPage']['customQuery'])) {
    $tableAlias = 'ds'; // customQuery 中使用的別名
}

// 基本條件：模組過濾
if ($menuKey && $menuValue !== null) {
    if ($menuKey === 'd_class1' && $tableName === 'taxonomies') {
        // 特殊處理：taxonomies 表實際上是用 taxonomy_type_id
        $conditions[] = "{$tableAlias}.taxonomy_type_id = :menuValue";
    } else {
        $conditions[] = "{$tableAlias}.{$menuKey} = :menuValue";
    }
    $params[':menuValue'] = $menuValue;
}

// 【階層導航】如果支援階層，添加過濾條件
if ($hasHierarchicalNav) {
    $parentCol = $moduleConfig['cols']['parent_id'];
    if ($parentId > 0) {
        $conditions[] = "{$tableAlias}.{$parentCol} = :parentId";
        $params[':parentId'] = $parentId;
    } else {
        // 如果支援階層但沒有指定 parent_id (或指定為0)，顯示頂層 (0 或 NULL)
        $conditions[] = "({$tableAlias}.{$parentCol} = 0 OR {$tableAlias}.{$parentCol} IS NULL)";
    }
}

// 【多語系】加入語系過濾 (排除 languages 表及其它不支援多語系的表)
// 【修正】也要檢查 languageEnabled 設定
$languageEnabled = $moduleConfig['languageEnabled'] ?? true;
if ($tableName !== 'languages' && $languageEnabled !== false) {
    // 檢查資料表是否有 lang 欄位
    $checkLangColQuery = "SHOW COLUMNS FROM {$tableName} LIKE '{$langField}'";
    $langColStmt = $conn->prepare($checkLangColQuery);
    $langColStmt->execute();
    if ($langColStmt->rowCount() > 0) {
        $conditions[] = "{$tableAlias}.{$langField} = :currentLang";
        $params[':currentLang'] = $currentLang;
    }
}

// 【新增】過濾已刪除分類下的資料（在「全部」模式下也生效）
if ($hasCategory && $categoryField && !$isTrashMode) {
    // 【修正】如果是連動分類（陣列），使用第一個欄位
    $actualCategoryField = is_array($categoryField) ? $categoryField[0] : $categoryField;
    
    // 找出分類表 (從 cms_menus 找)
    $catCheckStmt = $conn->prepare("SELECT menu_table, taxonomy_type_id FROM cms_menus WHERE menu_type = :type LIMIT 1");
    $catCheckStmt->execute([':type' => $moduleConfig['listPage']['categoryName'] ?? '']);
    $catMenuRow = $catCheckStmt->fetch();

    // 【修正】判斷分類表：優先使用 menu_table，如果為空但有 taxonomy_type_id，則使用 taxonomies
    $cTable = null;
    if ($catMenuRow) {
        if (!empty($catMenuRow['menu_table'])) {
            $cTable = $catMenuRow['menu_table'];
        } elseif (!empty($catMenuRow['taxonomy_type_id'])) {
            $cTable = 'taxonomies';
        }
    }

    if ($cTable) {
        $cPK = ($cTable === 'taxonomies') ? 't_id' : 'd_id';

        // 判斷分類表的軟刪除欄位類型
        if ($cTable === 'taxonomies') {
            $catDeleteCol = 'deleted_at';
            // TIMESTAMP 類型只檢查 IS NULL
            $catDeleteCondition = "{$catDeleteCol} IS NULL";
        } else {
            $catDeleteCol = 'd_delete_time';
            // VARCHAR 類型可以檢查空字串
            $catDeleteCondition = "({$catDeleteCol} IS NULL OR {$catDeleteCol} = '')";
        }

        // 【關鍵修正】加入條件：只顯示分類未被刪除的資料，或沒有分類的資料
        // 使用子查詢來確保：1) 沒有分類 OR 2) 有分類且分類未被刪除
        $conditions[] = "({$tableAlias}.{$actualCategoryField} IS NULL OR {$tableAlias}.{$actualCategoryField} = 0 OR EXISTS (
            SELECT 1 FROM {$cTable}
            WHERE {$cTable}.{$cPK} = {$tableAlias}.{$actualCategoryField}
            AND {$catDeleteCondition}
        ))";

        // 【調試】記錄條件
        error_log("Added category filter: hasCategory={$hasCategory}, actualCategoryField={$actualCategoryField}, cTable={$cTable}, condition added");
    } else {
        error_log("Category filter NOT added: menu_table and taxonomy_type_id both empty for categoryName=" . ($moduleConfig['listPage']['categoryName'] ?? 'NULL'));
    }
} else {
    $actualCategoryField = is_array($categoryField) ? $categoryField[0] : $categoryField;
    error_log("Category filter skipped: hasCategory={$hasCategory}, actualCategoryField={$actualCategoryField}, isTrashMode={$isTrashMode}");
}

// 分類過濾 (垃圾桶模式下通常顯示全部，除非有特別選定)
if ($hasCategory && $selectedCategory && !$isTrashMode) {
    if ($categoryField) {
        // 【防呆】檢查該分類是否屬於當前語系，避免跨語系切換時顯示空白
        // 【新增】同時檢查分類是否已被刪除
        $isValidCategory = true;
        if ($tableName !== 'languages') {
            // 找出分類表 (從 cms_menus 找)
            $catCheckStmt = $conn->prepare("SELECT menu_table FROM cms_menus WHERE menu_type = :type LIMIT 1");
            $catCheckStmt->execute([':type' => $moduleConfig['listPage']['categoryName'] ?? '']);
            $catMenuRow = $catCheckStmt->fetch();

            if ($catMenuRow && !empty($catMenuRow['menu_table'])) {
                $cTable = $catMenuRow['menu_table'];
                $cPK = ($cTable === 'taxonomies') ? 't_id' : 'd_id';

                // 【新增】檢查分類表的軟刪除欄位
                $catDeleteCol = null;
                $catDeleteCondition = '';
                if ($cTable === 'taxonomies') {
                    $catDeleteCol = 'deleted_at';
                    // TIMESTAMP 類型只檢查 IS NULL
                    $catDeleteCondition = "AND {$catDeleteCol} IS NULL";
                } else {
                    $catDeleteCol = 'd_delete_time';
                    // VARCHAR 類型可以檢查空字串
                    $catDeleteCondition = "AND ({$catDeleteCol} IS NULL OR {$catDeleteCol} = '')";
                }

                // 檢查欄位是否存在 (防呆)
                try {
                    $checkCCQuery = "SHOW COLUMNS FROM `{$cTable}` LIKE '{$langField}'";
                    $ccStmt = $conn->query($checkCCQuery);
                    if ($ccStmt && $ccStmt->rowCount() > 0) {
                        // 【修改】同時檢查語系和刪除狀態
                        $verifyQuery = "SELECT COUNT(*) FROM `{$cTable}`
                                       WHERE {$cPK} = :cid
                                       AND lang = :lang
                                       {$catDeleteCondition}";
                        $vStmt = $conn->prepare($verifyQuery);
                        $vStmt->execute([':cid' => $selectedCategory, ':lang' => $currentLang]);
                        if ($vStmt->fetchColumn() == 0) {
                            $isValidCategory = false; // 該語系下找不到此分類，或分類已被刪除
                        }
                    }
                } catch (PDOException $e) {
                    // 如果查詢失敗（例如表不存在），忽略錯誤
                    error_log("Category validation error: " . $e->getMessage());
                }
            }
        }

        if ($isValidCategory) {
            // 【修改】使用 data_taxonomy_map 表進行多對多分類查詢
            // 檢查是否有 data_taxonomy_map 表（新系統）
            $checkMapTable = "SHOW TABLES LIKE 'data_taxonomy_map'";
            $mapTableStmt = $conn->query($checkMapTable);

            // 【新增】檢查設定檔是否啟用 useTaxonomyMapSort (預設為 true)
            $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? true;

            if ($mapTableStmt && $mapTableStmt->rowCount() > 0 && $configUseTaxonomyMapSort) {
                // 使用 data_taxonomy_map 表
                $conditions[] = "EXISTS (
                    SELECT 1 FROM data_taxonomy_map
                    WHERE data_taxonomy_map.d_id = {$tableAlias}.{$col_id}
                    AND data_taxonomy_map.t_id = :categoryId
                )";
                $params[':categoryId'] = $selectedCategory;
            } else {
                // 動態處理多個分類欄位 (將 selected1, 2, 3... 對應到 d_class1, 2, 3...)
                if (is_array($categoryField)) {
                    foreach ($selectedCategories as $idx => $catId) {
                        $col = $categoryField[$idx] ?? null;
                        if ($col) {
                            $conditions[] = "{$tableAlias}.{$col} = :cid{$idx}";
                            $params[":cid{$idx}"] = $catId;
                        }
                    }
                } else {
                    $conditions[] = "{$tableAlias}.{$categoryField} = :categoryId";
                    $params[':categoryId'] = $selectedCategory;
                }
            }
        } else {
            // 如果不合法，清空變數以便 UI 顯示「全部」
            $selectedCategory = "";
        }
    }
}

// 回收桶模式
if ($columnExists) { // Only apply trash filter if the column exists
    if ($isTrashMode) {
        $conditions[] = "{$tableAlias}.{$col_delete_time} IS NOT NULL";
    } else {
        $conditions[] = "{$tableAlias}.{$col_delete_time} IS NULL";
    }
}


// 組合 WHERE 子句
$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// 【調試】記錄查詢條件
error_log("Module: {$module}, Table: {$tableName}, WHERE: {$whereClause}, Params: " . json_encode($params));

// 6. 查詢總筆數
// 【首頁顯示管理】如果使用 customQuery，需要特殊處理
$customQuery = $moduleConfig['listPage']['customQuery'] ?? null;
if ($customQuery) {
    // 使用 customQuery 的子查詢來計算總數
    $countQuery = "SELECT COUNT(*) as total FROM ({$customQuery} {$whereClause}) as count_table";
} else {
    $countQuery = "SELECT COUNT(*) as total FROM {$tableName} {$whereClause}";
}

$countStmt = $conn->prepare($countQuery);

// 【首頁顯示管理】如果使用 customQuery，需要綁定額外參數
if ($isHomeDisplayModule && $targetModule) {
    $countStmt->bindValue(':targetModule', $targetModule);
    $countStmt->bindValue(':currentLang', $currentLang);
}

foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}

$countStmt->execute();
$totalRows = $countStmt->fetch()['total'];

error_log("Total rows found: {$totalRows}");

// 查詢資料（分頁）
// 【防呆】檢查欄位是否存在於資料表中
$tableColumns = [];
$columnsQuery = "SHOW COLUMNS FROM {$tableName}";
$columnsStmt = $conn->prepare($columnsQuery);
$columnsStmt->execute();
while ($colInfo = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
    $tableColumns[] = $colInfo['Field'];
}

// 【防呆】只在欄位存在時才使用
$safeOrderBy = $orderBy;
// 檢查 ORDER BY 中的欄位是否存在
// 【首頁顯示管理】跳過檢查，因為 orderBy 中包含計算欄位（is_in_home）
if (!$isHomeDisplayModule) {
    preg_match('/^(\w+)/', $orderBy, $matches);
    if (isset($matches[1]) && !in_array($matches[1], $tableColumns)) {
        // 如果排序欄位不存在，使用主鍵
        $safeOrderBy = "{$col_id} DESC";
    }
}

// 【修正】如果有 d_top 欄位，從 orderBy 中移除它，因為我們會在 sortSql 中統一加入
if ($col_top !== null && in_array($col_top, $tableColumns)) {
    // 從 safeOrderBy 中移除 d_top 的排序（避免重複）
    $safeOrderBy = preg_replace('/\b' . preg_quote($col_top, '/') . '\s+(DESC|ASC)\s*,?\s*/i', '', $safeOrderBy);
    $safeOrderBy = trim($safeOrderBy, ', ');

    // 【修改】恢復無條件置頂排序。只要有 d_top 欄位，就應該排在最前面。
    // 無論是否在分類下，置頂項目都應該優先顯示（全域置頂）。
    $sortSql = "{$tableAlias}.{$col_top} DESC, ";
} else {
    $sortSql = "";
}

// 【新增】為了避免 JOIN 查詢導致欄位衝突 (Ambiguous column)，對 safeOrderBy 也加上 Table Name 前綴
// 【新增】為了避免 JOIN 查詢導致欄位衝突 (Ambiguous column)，對 safeOrderBy 也加上 Table Name 前綴
if (!empty($safeOrderBy)) {
    // 【首頁顯示管理】不要自動加前綴，因為 orderBy 中已經包含了正確的表別名
    if (!$isHomeDisplayModule) {
        $safeOrderByParts = explode(',', $safeOrderBy);
        foreach ($safeOrderByParts as &$part) {
            $part = trim($part);
            if (!empty($part) && !strpos($part, '.')) { // 如果還沒有點號 (表示未指定 Table)
                $part = "{$tableAlias}.{$part}";
            }
        }
        $safeOrderBy = implode(', ', $safeOrderByParts);
    }
}

// 【新增】支援 customQuery（用於 JOIN 查詢）
$customQuery = $moduleConfig['listPage']['customQuery'] ?? null;

// 【修改】當有選擇分類時，使用 data_taxonomy_map 進行 JOIN 和排序
$useMapTableSort = false;
// 【新增】檢查設定檔是否啟用 useTaxonomyMapSort (預設為 true)
$configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? true;

if ($hasCategory && $selectedCategory > 0 && !$isTrashMode && $configUseTaxonomyMapSort) {
    $checkMapTable = "SHOW TABLES LIKE 'data_taxonomy_map'";
    $mapTableStmt = $conn->query($checkMapTable);
    if ($mapTableStmt && $mapTableStmt->rowCount() > 0) {
        $useMapTableSort = true;
    }
}

if ($useMapTableSort) {
    // 使用 data_taxonomy_map JOIN 查詢，並用 sort_num 排序
    // 【關鍵】將 sort_num 別名為 d_sort，讓後續程式碼統一使用
    // 【修正】使用 data_taxonomy_map 的 d_top (如果有) 作為置頂依據
    // SELECT 中覆蓋 d_top，這樣列表顯示的置頂狀態就是該分類下的狀態
        // 【修改】JOIN 增加 COALESCE 預防剛過渡時 mapping 尚未建立的情況
        $dataQuery = "SELECT {$tableName}.*, 
                             COALESCE(data_taxonomy_map.sort_num, {$tableName}.{$col_sort}) AS {$col_sort}, 
                             COALESCE(data_taxonomy_map.d_top, {$tableName}.{$col_top}) AS {$col_top}
                      FROM {$tableName}
                      LEFT JOIN data_taxonomy_map ON {$tableName}.{$col_id} = data_taxonomy_map.d_id 
                                                 AND data_taxonomy_map.t_id = :map_category_id
                      {$whereClause} 
                      ORDER BY COALESCE(data_taxonomy_map.{$col_top}, 0) DESC, 
                               COALESCE(data_taxonomy_map.sort_num, 99999) ASC
                      LIMIT :offset, :limit";
        $params[':map_category_id'] = !empty($selectedCategory) ? $selectedCategory : 0;
} elseif ($customQuery) {
    // 使用自訂查詢（已包含 SELECT 和 FROM）
    // 【首頁顯示管理】如果是首頁顯示模組，加入 targetModule 和 currentLang 參數
    if ($isHomeDisplayModule && $targetModule) {
        $params[':targetModule'] = $targetModule;
        $params[':currentLang'] = $currentLang;
    }
    $dataQuery = "{$customQuery} {$whereClause} ORDER BY {$sortSql}{$safeOrderBy} LIMIT :offset, :limit";

    // 【調試】輸出 SQL 查詢
    if ($isHomeDisplayModule) {
        echo "<!-- DEBUG SQL: {$dataQuery} -->";
        echo "<!-- DEBUG sortSql: '{$sortSql}' -->";
        echo "<!-- DEBUG safeOrderBy: '{$safeOrderBy}' -->";
        echo "<!-- DEBUG orderBy from config: '{$orderBy}' -->";
    }
} else {
    // 使用預設查詢
    $dataQuery = "SELECT * FROM {$tableName} {$whereClause} ORDER BY {$sortSql}{$safeOrderBy} LIMIT :offset, :limit";
    echo "<!-- DEBUG SQL (Default): {$dataQuery} -->";
}

$dataStmt = $conn->prepare($dataQuery);

// --- 3. 綁定參數 ---
foreach ($params as $key => $value) {
    $dataStmt->bindValue($key, $value);
}

// 分頁參數必須使用 PARAM_INT
$dataStmt->bindValue(':offset', (int) $startRow, PDO::PARAM_INT);
$dataStmt->bindValue(':limit', (int) $maxRows, PDO::PARAM_INT);
$dataStmt->execute();

// 【調試】檢查查詢結果
$debugRow = $dataStmt->fetch(PDO::FETCH_ASSOC);
if ($debugRow) {
    error_log("First row data: d_id={$debugRow[$col_id]}, {$col_sort}=" . ($debugRow[$col_sort] ?? 'NULL'));
    // 重新執行查詢以取得所有資料
    $dataStmt->execute();
}

$totalPages = ceil($totalRows / $maxRows) - 1;

// 建立查詢字串 (保持原樣)
$queryString = "";
if (!empty($_SERVER['QUERY_STRING'])) {
    $params = explode("&", $_SERVER['QUERY_STRING']);
    $newParams = array();
    foreach ($params as $param) {
        if (stristr($param, "pageNum") == false && stristr($param, "totalRows") == false) {
            array_push($newParams, $param);
        }
    }
    if (count($newParams) != 0) {
        $queryString = "&" . htmlentities(implode("&", $newParams));
    }
}
$queryString = sprintf("&totalRows=%d%s", $totalRows, $queryString);

require_once('display_page.php');
?>

<!DOCTYPE html>
<html class="sidebar-left-big-icons">

<head>
    <title><?php require_once('cmsTitle.php'); ?></title>
    <?php require_once('head.php'); ?>
    <?php require_once('script.php'); ?>
</head>

<body>
    <section class="body">
        <!-- start: header -->
        <?php require_once('header.php'); ?>
        <!-- end: header -->

        <div class="inner-wrapper">
            <!-- start: sidebar -->
            <?php require_once('sidebar.php'); ?>
            <!-- end: sidebar -->

            <section role="main" class="content-body">
                <header class="page-header">
                    <h2><?php echo $isTrashMode ? '回收桶' : $moduleConfig['moduleName']; ?></h2>

                    <div class="right-wrapper text-end">
                        <ol class="breadcrumbs">
                            <?php 
                            require_once(__DIR__ . '/includes/menuHelper.php');
                            $currentPageTitle = $isTrashMode ? '回收桶' : '列表';
                            echo renderBreadcrumbsHtml($conn, $module, $currentPageTitle);
                            ?>
                        </ol>

                        <a class="sidebar-right-toggle" data-open="sidebar-right" style="pointer-events: none;"></a>
                    </div>
                </header>

                <!-- start: page -->
                <div class="row">
                    <div class="col">

                        <div class="row align-items-center mb-3">
                            <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                                <?php
                                if ($hasHierarchicalNav && !$isTrashMode) {
                                    if ($parentId !== null && $parentId > 0) {
                                        $parentCol = $moduleConfig['cols']['parent_id'];
                                        $primaryKey = $moduleConfig['primaryKey'];
                                        $titleCol = $moduleConfig['cols']['title'];

                                        $breadcrumbQuery = "SELECT {$primaryKey}, {$parentCol}, {$titleCol} FROM {$tableName} WHERE {$primaryKey} = :currentId";
                                        $breadcrumbStmt = $conn->prepare($breadcrumbQuery);
                                        $breadcrumbStmt->execute([':currentId' => $parentId]);
                                        $currentItem = $breadcrumbStmt->fetch(PDO::FETCH_ASSOC);

                                        if ($currentItem) {
                                            $backParentId = $currentItem[$parentCol];
                                            $backUrl = PORTAL_AUTH_URL."tpl={$module}/list?language={$currentLang}" . ($backParentId > 0 ? "&parent_id={$backParentId}" : "");
                                            echo "<span class='me-3'>當前位置：{$currentItem[$titleCol]}</span>";
                                            echo "<a href=\"{$backUrl}\" class=\"btn btn-primary btn-md font-weight-semibold btn-py-2 px-4\"><i class=\"fas fa-arrow-left\"></i> 返回上一層</a>";
                                        }
                                    } else {
                                        echo "<span class='me-3'>當前位置：頂層選單</span>";
                                    }
                                } 
                                // elseif ($hasCategory && !empty($selectedCategories) && !$isTrashMode) {
                                //     // 【新增】連動分類麵包屑
                                //     $breadcrumbParts = [];
                                //     foreach ($selectedCategories as $index => $catId) {
                                //         $stmt = $conn->prepare("SELECT t_id, t_name FROM taxonomies WHERE t_id = :id");
                                //         $stmt->execute([':id' => $catId]);
                                //         $tRow = $stmt->fetch(PDO::FETCH_ASSOC);
                                //         if ($tRow) {
                                //             $breadcrumbParts[] = $tRow['t_name'];
                                //         }
                                //     }
                                //     if (!empty($breadcrumbParts)) {
                                //         echo "<span class='me-3' style='font-size: 1.1rem; color: #555;'>當前分類：<strong>" . implode(' <i class="fas fa-angle-right mx-1"></i> ', array_map('htmlspecialchars', $breadcrumbParts)) . "</strong></span>";
                                //     }
                                // }
                                ?>

                                <?php if ($isTrashMode): ?>
                                    <a href="<?=PORTAL_AUTH_URL?>tpl=<?=$module?>/list"
                                        class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4"><i
                                            class="fas fa-arrow-left"></i> 返回</a>
                                <?php else: ?>
                                    <?php
                                    $showAddButton = $moduleConfig['listPage']['showAddButton'] ?? true;

                                    if ($showAddButton && $canAdd) {
                                        $addUrl = PORTAL_AUTH_URL."tpl={$module}/detail";
                                        $urlParams = [];
                                        if ($hasHierarchicalNav && isset($_GET['parent_id'])) {
                                            $urlParams[] = "parent_id=" . urlencode($_GET['parent_id']);
                                        }
                                        
                                        // 【修改】收集所有層級的選擇參數 (selected1, selected2, ...)
                                        foreach ($_GET as $key => $value) {
                                            if (strpos($key, 'selected') === 0 && $value !== '' && $value !== 'all') {
                                                $urlParams[] = $key . "=" . urlencode($value);
                                            }
                                        }
                                        
                                        // 確保 trash 狀態也傳遞
                                        if ($isTrashMode) {
                                            $urlParams[] = "trash=1";
                                        }

                                        // 【多語系】加入語系參數
                                        $urlParams[] = "language=" . urlencode($currentLang);

                                        if (!empty($urlParams)) {
                                            $addUrl .= "?" . implode('&', $urlParams);
                                        }
                                        
                                        echo "<a href=\"{$addUrl}\" class=\"btn btn-primary btn-md font-weight-semibold btn-py-2 px-4\"><i class=\"fas fa-plus-circle\"></i> 新增</a>";
                                    }
                                    ?>
                                    <?php if ($hasTrashData) {
                                        echo " " . renderTrashButton($module);
                                    } ?>
                                <?php endif; ?>
                            </div>

                            <?php if (($moduleConfig['listPage']['hasLanguage'] ?? true) && count($activeLanguages) > 1): ?>
                                <div class="col-12 col-lg-auto ms-auto mb-3 mb-lg-0">
                                    <ul class="nav nav-pills nav-pills-primary">
                                        <?php 
                                        $urlParams = $_GET;
                                        unset($urlParams['language'], $urlParams['pageNum'], $urlParams['totalRows'], $urlParams['module'], $urlParams['trash'], $urlParams['selected1'], $urlParams['selected2']);
                                        if (isset($categoryField)) {
                                            // 【修正】如果是連動分類（陣列），移除所有相關欄位
                                            if (is_array($categoryField)) {
                                                foreach ($categoryField as $field) {
                                                    unset($urlParams[$field]);
                                                }
                                            } else {
                                                unset($urlParams[$categoryField]);
                                            }
                                        }
                                        $baseQuery = http_build_query($urlParams);
                                        
                                        foreach ($activeLanguages as $lang): 
                                            $activeClass = ($lang['l_slug'] == $currentLang) ? 'active' : '';
                                            $langUrl = PORTAL_AUTH_URL."tpl={$module}/list?" . ($baseQuery ? $baseQuery . "&" : "") . "language=" . $lang['l_slug'];
                                        ?>
                                            <li class="nav-item">
                                                <a class="nav-link <?= $activeClass ?> py-1 px-3" href="<?= $langUrl ?>"><?= $lang['l_name'] ?></a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card card-modern">
                            <div class="card-body">
                                <div class="datatables-header-footer-wrapper mt-2">
                                    <div class="datatable-header">
                                        <!-- 擴充: excel匯入 / excel匯出 -->
                                        <!-- <div class="row align-items-center mb-3">
                                            <div class="col-12 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0">
                                                <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                                    <div class="me-2">1</div>
                                                    <div class="me-2">2</div>
                                                    <div class="me-2">3</div>
                                                </div>
                                            </div>
                                        </div> -->
                                        <!-- 篩選 / Show幾篇 / 搜尋 -->
                                        <div class="row align-items-center mb-3">
                                            <div class="col-8 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0">
                                                <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                                    <?php if (!$isTrashMode && ($moduleConfig['listPage']['hasCategory'] ?? false)): ?>
                                                        <label class="ws-nowrap me-3 mb-0">Filter By:</label>
                                                        
                                                        <?php if ($isLinkedCategory): ?>
                                                            <?php 
                                                            $level = 1;
                                                            $prevVal = 0;
                                                            $continueLoop = true;
                                                            while ($continueLoop):
                                                                $currentVal = $selectedCategories[$level-1] ?? null;
                                                                
                                                                // 找出這一層的選項
                                                                $levelOptions = [];
                                                                if ($level == 1) {
                                                                    $levelOptions = $categories; // 頂層
                                                                } elseif (!empty($prevVal) && $prevVal !== 'all') {
                                                                    $levelOptions = getSubCategoryOptions($categoryName, $prevVal);
                                                                }

                                                                // 如果沒有選項，停止渲染
                                                                if ($level > 1 && empty($levelOptions)) break;
                                                            ?>
                                                                <select name="select<?= $level ?>" id="select<?= $level ?>" 
                                                                    class="form-control select-style-1 me-2 category-filter" 
                                                                    data-category="<?= $categoryName ?>" 
                                                                    data-level="<?= $level ?>" 
                                                                    style="width: auto;">
                                                                    <?php if ($level == 1): ?>
                                                                        <option value="all">全部</option>
                                                                    <?php endif; ?>

                                                                    <?php foreach ($levelOptions as $opt): ?>
                                                                        <option value="<?= $opt['id'] ?>" <?= ($currentVal == $opt['id']) ? 'selected' : '' ?>><?= $opt['name'] ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            <?php 
                                                                if (empty($currentVal) || $currentVal === 'all') {
                                                                    $continueLoop = false;
                                                                } else {
                                                                    $prevVal = $currentVal;
                                                                    $level++;
                                                                }
                                                                if ($level > 20) break;
                                                            endwhile; ?>
                                                        <?php else: ?>
                                                            <!-- 單一分類 -->
                                                            <select name="select1" id="select1" class="form-control select-style-1 filter-by">
                                                                <?php foreach ($categories as $cat): ?>
                                                                    <?php $selected = ($cat['id'] == $selectedCategory) ? "selected" : ""; ?>
                                                                    <option value="<?php echo $cat['id']; ?>" <?php echo $selected; ?>>
                                                                        <?php echo $cat['name']; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <select name="select1" id="select1" class="form-control select-style-1 filter-by" style="display: none;">
                                                            <option value="all">all</option>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-4 col-lg-auto ps-lg-1 mb-3 mb-lg-0">
                                                <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                                    <label class="ws-nowrap me-3 mb-0">Show:</label>
                                                    <select class="form-control select-style-1 results-per-page" name="results-per-page">
                                                        <option value="12" selected>12</option>
                                                        <option value="24">24</option>
                                                        <option value="36">36</option>
                                                        <option value="100">100</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-auto ps-lg-1">
                                                <div class="search search-style-1 search-style-1-lg mx-lg-auto">
                                                    <div class="input-group">
                                                        <input type="text" class="search-term form-control" name="search-term"
                                                            id="search-term" placeholder="Search Category">
                                                        <button class="btn btn-default" type="submit"><i
                                                                class="bx bx-search"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <table class="table table-ecommerce-simple table-striped mb-0" id="datatable-ecommerce-list" style="min-width: 550px;">
                                        <thead>
                                            <tr>
                                                <?php
                                                // 檢查是否顯示選擇框
                                                $showCheckbox = $moduleConfig['listPage']['showCheckbox'] ?? true;
                                                if ($showCheckbox):
                                                ?>
                                                <th width="3%"><input type="checkbox" name="select-all" class="select-all checkbox-style-1 p-relative top-2" value="" /></th>
                                                <?php endif; ?>
                                                <?php
                                                if ($isTrashMode) {
                                                    // 1. 先判斷原本的配置中有沒有圖片欄位
                                                    $hasImageColumn = false;
                                                    foreach ($moduleConfig['listPage']['columns'] as $col) {
                                                        if ($col['type'] === 'image') {
                                                            $hasImageColumn = true;
                                                            break;
                                                        }
                                                    }

                                                    // 2. 定義基礎的回收桶欄位
                                                    $trashColumns = [
                                                        ['field' => $col_date, 'label' => '日期', 'width' => '142'],
                                                        ['field' => $col_title, 'label' => '標題', 'width' => '470']
                                                    ];

                                                    // 3. 如果原本有圖片欄位，才加入圖片顯示
                                                    if ($hasImageColumn) {
                                                        $trashColumns[] = ['field' => 'image', 'label' => '圖片', 'width' => '140'];
                                                    }

                                                    // 4. 加入功能按鈕
                                                    $trashColumns[] = ['field' => 'view', 'label' => '查看', 'width' => '30'];
                                                    $trashColumns[] = ['field' => 'restore', 'label' => '還原', 'width' => '30'];
                                                    $trashColumns[] = ['field' => 'delete', 'label' => '刪除', 'width' => '30'];

                                                    $displayColumns = $trashColumns;
                                                } else {
                                                    $displayColumns = $moduleConfig['listPage']['columns'];

                                                    // 【修改】如果選擇「全部」分類，隱藏置頂和排序欄位
                                                    // 【修改】不再隱藏排序欄位，由 useTaxonomyMapSort 控制邏輯
                                                    /*
                                                    if ($hasCategory && empty($selectedCategory)) {
                                                        $displayColumns = array_filter($displayColumns, function ($col) {
                                                            if ($col['type'] === 'sort')
                                                                return false;
                                                            if ($col['type'] === 'button' && $col['field'] === 'pin')
                                                                return false;
                                                            return true;
                                                        });
                                                    }
                                                    */
                                                }

                                                foreach ($displayColumns as $col):
                                                    ?>
                                                    <td width="<?php echo $col['width'] ?? 'auto'; ?>" align="center">
                                                        <?php echo $col['label']; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)):
                                                $rowId = $row[$moduleConfig['primaryKey']];
                                                
                                            ?>
                                                <tr>
                                                <?php if ($showCheckbox): ?>
                                                <td width="30"><input type="checkbox" name="checkboxRow1" class="row-checkbox checkbox-style-1 p-relative top-2" value="<?php echo $rowId; ?>" /></td>
                                                <?php endif; ?>
                                                    <?php foreach ($displayColumns as $col): ?>
                                                        <td align="center">
                                                            <?php
                                                            if ($isTrashMode) {
                                                                // 【修改】回收桶內文顯示使用變數比對
                                                                if ($col['field'] == $col_date) {
                                                                    echo htmlspecialchars($row[$col_date] ?? '', ENT_QUOTES, 'UTF-8');
                                                                } elseif ($col['field'] == $col_title) {
                                                                    echo htmlspecialchars($row[$col_title] ?? '', ENT_QUOTES, 'UTF-8');
                                                                } elseif ($col['field'] == 'image') {
                                                                    // 從配置讀起 imageFileType，預設為 'image'
                                                                    $imageFileType = $moduleConfig['listPage']['imageFileType'] ?? 'image';
                                                                    $imgQuery = "SELECT * FROM file_set WHERE file_type=:file_type AND {$col_file_fk} = :id ORDER BY file_sort ASC LIMIT 1";
                                                                    $imgStmt = $conn->prepare($imgQuery);
                                                                    $imgStmt->execute([':file_type' => $imageFileType, ':id' => $rowId]);
                                                                    $imgRow = $imgStmt->fetch();
                                                                    if ($imgRow) {
                                                                        echo "<img src=\"../{$imgRow['file_link1']}\" style=\"width: 100px;height: 70px;object-fit: cover;\">";
                                                                    } else {
                                                                        echo "<img src=\"image/default_image_s.jpg\">";
                                                                    }
                                                                } elseif ($col['field'] == 'view') {
                                                                    $extraParams = [];
                                                                    foreach ($_GET as $key => $value) {
                                                                        if (strpos($key, 'selected') === 0 || $key === 'parent_id' || $key === 'language') {
                                                                            if ($value !== '' && $value !== 'all') $extraParams[$key] = $value;
                                                                        }
                                                                    }
                                                                    echo renderViewButton($rowId, $module, $primaryKey, true, $extraParams);
                                                                } elseif ($col['field'] == 'edit') {
                                                                    $extraParams = [];
                                                                    foreach ($_GET as $key => $value) {
                                                                        if (strpos($key, 'selected') === 0 || $key === 'parent_id' || $key === 'language') {
                                                                            if ($value !== '' && $value !== 'all') $extraParams[$key] = $value;
                                                                        }
                                                                    }
                                                                    echo renderEditButton($rowId, $module, $primaryKey, true, $extraParams);
                                                                } elseif ($col['field'] == 'restore') {
                                                                    echo renderRestoreButton($rowId, $module);
                                                                } elseif ($col['field'] == 'delete') {
                                                                    echo renderPermanentDeleteButton($rowId, $module);
                                                                }
                                                            } else {
                                                                // 正常模式
                                                                 switch ($col['type']) {
                                                                    case 'category_path':
                                                                        $cName = $col['category'] ?? $moduleConfig['listPage']['categoryName'] ?? null;
                                                                        $cFields = $moduleConfig['listPage']['categoryField'] ?? null;
                                                                        $tId = 0;
                                                                        
                                                                        // 【修正】優先從連動分類配置 (categoryField) 中尋找最深層的值
                                                                        if (is_array($cFields)) {
                                                                            foreach (array_reverse($cFields) as $cf) {
                                                                                if (!empty($row[$cf])) {
                                                                                    $tId = $row[$cf];
                                                                                    break;
                                                                                }
                                                                            }
                                                                        }
                                                                        
                                                                        // 如果連動分類中沒找到，才使用該欄位本身的值
                                                                        if (!$tId && !empty($row[$col['field']])) {
                                                                            $tId = $row[$col['field']];
                                                                        }
                                                                        
                                                                        if ($tId && $cName) {
                                                                            echo getCategoryPathHtml($cName, $tId, $module);
                                                                        } else {
                                                                            echo '-';
                                                                        }
                                                                        break;
                                                                    case 'date':
                                                                    case 'text':
                                                                        // 【首頁顯示管理】不顯示連結
                                                                        if ($isHomeDisplayModule) {
                                                                            echo htmlspecialchars($row[$col['field']] ?? '', ENT_QUOTES, 'UTF-8');
                                                                        } else {
                                                                            // 【修改】加入多層次分類參數到詳情頁連結
                                                                            $detailParams = ["{$primaryKey}={$rowId}"];
                                                                            foreach ($_GET as $key => $value) {
                                                                                if (strpos($key, 'selected') === 0 && $value !== '' && $value !== 'all') {
                                                                                    $detailParams[] = $key . "=" . urlencode($value);
                                                                                }
                                                                            }
                                                                            if (isset($_GET['parent_id'])) $detailParams[] = "parent_id=" . urlencode($_GET['parent_id']);
                                                                            if (isset($_GET['language'])) $detailParams[] = "language=" . urlencode($_GET['language']);
                                                                            
                                                                            $detailUrl = PORTAL_AUTH_URL . "tpl={$module}/detail?" . implode('&', $detailParams);
                                                                            echo "<a href=\"{$detailUrl}\">" . htmlspecialchars($row[$col['field']] ?? '', ENT_QUOTES, 'UTF-8') . "</a>";
                                                                        }
                                                                        break;
                                                                    case 'view_count':
                                                                        echo $row['d_view'] ?? 0;
                                                                    break;
                                                                case 'sort':
                                                                    // 檢查 Map Table 是否存在 (用於 context)
                                                                    $checkMapTable = "SHOW TABLES LIKE 'data_taxonomy_map'";
                                                                    $mapTableResult = $conn->query($checkMapTable);
                                                                    $hasMapTable = ($mapTableResult && $mapTableResult->rowCount() > 0);

                                                                    // 使用 SortCountHelper 計算排序筆數
                                                                    $sortContext = [
                                                                        'tableName' => $tableName,
                                                                        'col_id' => $col_id,
                                                                        'totalRows' => $totalRows,
                                                                        'row' => $row,
                                                                        'menuKey' => $menuKey,
                                                                        'menuValue' => $menuValue,
                                                                        'col_top' => $customCols['top'] ?? null,
                                                                        'hasCategory' => $hasCategory,
                                                                        'selectedCategory' => $selectedCategory,
                                                                        'categoryField' => $categoryField,
                                                                        // 【修改】如果設定為使用 Map Table 排序，不論是否選擇分類都啟用 (All 模式下 t_id = 0)
                                                                        'useTaxonomyMapSort' => ($hasCategory && ($moduleConfig['listPage']['useTaxonomyMapSort'] ?? true) && $hasMapTable),
                                                                        'hasHierarchicalNav' => $hasHierarchicalNav,
                                                                        'parentIdField' => $parentIdField,
                                                                        'currentParentId' => $parentId,
                                                                        'col_delete_time' => $col_delete_time,
                                                                        'hasDeleteTime' => $columnExists,
                                                                        'hasMapTable' => $hasMapTable
                                                                    ];
                                                                    
                                                                    $sortRowCount = SortCountHelper::getCount($conn, $sortContext);

                                                                    // 【重要】如果項目是置頂的，不顯示排序下拉選單，顯示文字即可
                                                                    // 這樣使用者就不會嘗試去排序置頂項目，也不會混淆
                                                                    if ($col_top !== null && isset($row[$col_top]) && $row[$col_top] == 1) {
                                                                        echo "<span class='badge badge-warning'>置頂中 (原排序: {$row[$col_sort]})</span>";
                                                                    } else {
                                                                        $sortVal = $row[$col_sort] ?? 0;
                                                                        echo renderSortDropdown($sortVal, $sortRowCount, $rowId, $pageNum, $selectedCategory, $col_sort);
                                                                    }
                                                                    break;
                                                                case 'image':
                                                                    // 從配置讀取 imageFileType，預設為 'image'
                                                                    $imageFileType = $moduleConfig['listPage']['imageFileType'] ?? 'image';
                                                                    $imgQuery = "SELECT * FROM file_set WHERE file_type=:file_type AND {$col_file_fk} = :id ORDER BY file_sort ASC LIMIT 1";
                                                                    $imgStmt = $conn->prepare($imgQuery);
                                                                    $imgStmt->execute([':file_type' => $imageFileType, ':id' => $rowId]);
                                                                    $imgRow = $imgStmt->fetch();
                                                                    if ($imgRow) {
                                                                        $detailParams = ["{$primaryKey}={$rowId}"];
                                                                        foreach ($_GET as $key => $value) {
                                                                            if (strpos($key, 'selected') === 0 && $value !== '' && $value !== 'all') {
                                                                                $detailParams[] = $key . "=" . urlencode($value);
                                                                            }
                                                                        }
                                                                        if (isset($_GET['parent_id'])) $detailParams[] = "parent_id=" . urlencode($_GET['parent_id']);
                                                                        if (isset($_GET['language'])) $detailParams[] = "language=" . urlencode($_GET['language']);
                                                                        $detailUrl = PORTAL_AUTH_URL . "tpl={$module}/detail?" . implode('&', $detailParams);
                                                                        
                                                                        echo "<a href=\"{$detailUrl}\"><img src=\"../{$imgRow['file_link2']}\" style=\"width: 100px;height: 70px;object-fit: cover;\"></a>";
                                                                    } else {
                                                                        $detailParams = ["{$primaryKey}={$rowId}"];
                                                                        foreach ($_GET as $key => $value) {
                                                                            if (strpos($key, 'selected') === 0 && $value !== '' && $value !== 'all') {
                                                                                $detailParams[] = $key . "=" . urlencode($value);
                                                                            }
                                                                        }
                                                                        if (isset($_GET['parent_id'])) $detailParams[] = "parent_id=" . urlencode($_GET['parent_id']);
                                                                        if (isset($_GET['language'])) $detailParams[] = "language=" . urlencode($_GET['language']);
                                                                        $detailUrl = PORTAL_AUTH_URL . "tpl={$module}/detail?" . implode('&', $detailParams);

                                                                        echo "<a href=\"{$detailUrl}\"><img src=\"image/default_image_s.jpg\"></a>";
                                                                    }
                                                                    break;
                                                                 case 'select':
                                                                    // 【重構】支援多選欄位顯示標籤
                                                                    $fieldValue = '';
                                                                    if (is_array($col['field'])) {
                                                                        // 如果是多欄位儲存，合併所有欄位的值
                                                                        $vals = [];
                                                                        foreach ($col['field'] as $f) {
                                                                            if (!empty($row[$f])) $vals[] = $row[$f];
                                                                        }
                                                                        $fieldValue = implode(',', $vals);
                                                                    } else {
                                                                        $fieldValue = $row[$col['field']] ?? '';
                                                                    }

                                                                    if ($fieldValue === '') {
                                                                        echo '-';
                                                                        break;
                                                                    }

                                                                    // 處理多個 ID (逗號分隔)
                                                                    $ids = explode(',', (string)$fieldValue);
                                                                    $displayLabels = [];
                                                                    
                                                                    // 如果有定義 options，找出對應的 label
                                                                    if (isset($col['options']) && is_array($col['options'])) {
                                                                        foreach ($ids as $id) {
                                                                            $found = false;
                                                                            $id = trim($id);
                                                                            foreach ($col['options'] as $option) {
                                                                                if (isset($option['value']) && $option['value'] == $id) {
                                                                                    $displayLabels[] = $option['label'] ?? $id;
                                                                                    $found = true;
                                                                                    break;
                                                                                }
                                                                            }
                                                                            if (!$found) $displayLabels[] = $id;
                                                                        }
                                                                    } else {
                                                                        $displayLabels = $ids;
                                                                    }
                                                                    
                                                                    echo htmlspecialchars(implode(', ', $displayLabels), ENT_QUOTES, 'UTF-8');
                                                                    break;
                                                                case 'category':
                                                                    // 【新增】動態分類名稱顯示
                                                                    $fieldValue = '';
                                                                    if (is_array($col['field'])) {
                                                                        $vals = [];
                                                                        foreach ($col['field'] as $f) {
                                                                            if (!empty($row[$f])) $vals[] = $row[$f];
                                                                        }
                                                                        $fieldValue = implode(',', $vals);
                                                                    } else {
                                                                        $fieldValue = $row[$col['field']] ?? '';
                                                                    }

                                                                    if ($fieldValue === '') {
                                                                        echo '-';
                                                                    } else {
                                                                        $categoryLabel = getCategoryNamesByIds($col['category'] ?? '', $fieldValue);
                                                                        echo htmlspecialchars($categoryLabel ?: $fieldValue, ENT_QUOTES, 'UTF-8');
                                                                    }
                                                                    break;
                                                                case 'home_active':
                                                                    $homeActiveVal = $row['d_home_active'] ?? 0;
                                                                    echo renderHomeActiveToggle($homeActiveVal, $rowId);
                                                                    break;
                                                                case 'home_display_toggle':
                                                                    // 【首頁顯示管理】切換首頁顯示按鈕
                                                                    $isInHome = $row['is_in_home'] ?? 0;
                                                                    $hdId = $row['hd_id'] ?? null;
                                                                    $dataId = $row['d_id'] ?? 0;

                                                                    $btnClass = $isInHome ? 'btn-success' : 'btn-default';
                                                                    $btnText = $isInHome ? '已加入' : '加入首頁';
                                                                    $btnIcon = $isInHome ? 'fa-check' : 'fa-plus';

                                                                    echo "<button class='btn {$btnClass} btn-sm toggle-home-display'
                                                                            data-data-id='{$dataId}'
                                                                            data-module='{$targetModule}'
                                                                            data-status='{$isInHome}'
                                                                            data-lang='{$currentLang}'>
                                                                            <i class='fas {$btnIcon}'></i> {$btnText}
                                                                          </button>";
                                                                    break;
                                                                case 'home_sort':
                                                                    // 【首頁顯示管理】排序欄位（只有已加入首頁的才顯示）
                                                                    $isInHome = $row['is_in_home'] ?? 0;
                                                                    if ($isInHome) {
                                                                        $sortVal = $row['hd_sort'] ?? 0;
                                                                        echo "<span class='sort-handle' style='cursor: move;'><i class='fas fa-grip-vertical'></i> {$sortVal}</span>";
                                                                    } else {
                                                                        echo "<span class='text-muted'>-</span>";
                                                                    }
                                                                    break;
                                                                case 'home_sort_dropdown':
                                                                    // 【首頁顯示管理】下拉選單排序（只有已加入首頁的才顯示）
                                                                    $isInHome = $row['is_in_home'] ?? 0;
                                                                    if ($isInHome) {
                                                                        $hdId = $row['hd_id'] ?? 0;
                                                                        $sortVal = $row['hd_sort'] ?? 0;

                                                                        // 計算已加入首頁的項目總數
                                                                        $countQuery = "SELECT COUNT(*) as total FROM home_display
                                                                                      WHERE hd_module = :module AND lang = :lang AND hd_active = 1";
                                                                        $countStmt = $conn->prepare($countQuery);
                                                                        $countStmt->execute([
                                                                            ':module' => $targetModule,
                                                                            ':lang' => $currentLang
                                                                        ]);
                                                                        $totalInHome = $countStmt->fetch()['total'];

                                                                        echo "<select class='form-control home-sort-select' data-hd-id='{$hdId}' data-module='{$targetModule}' data-lang='{$currentLang}' style='width: 60px;'>";
                                                                        for ($i = 1; $i <= $totalInHome; $i++) {
                                                                            $selected = ($i == $sortVal) ? 'selected' : '';
                                                                            echo "<option value='{$i}' {$selected}>{$i}</option>";
                                                                        }
                                                                        echo "</select>";
                                                                    } else {
                                                                        echo "<span class='text-muted'>-</span>";
                                                                    }
                                                                    break;
                                                                case 'active':
                                                                    // 【修改】使用變數 $col_active
                                                                    $activeVal = $row[$col_active] ?? 1;
                                                                    echo renderActiveToggle($activeVal, $rowId, $col_active);
                                                                    break;
                                                                case 'read_toggle':
                                                                    // 【新增】已讀/未讀狀態切換
                                                                    $readVal = $row[$col_read] ?? 0;
                                                                    echo renderReadToggle($readVal, $rowId);
                                                                    break;
                                                                case 'reply_status':
                                                                    // 【新增】回覆狀態顯示
                                                                    $replyVal = $row[$col_reply] ?? 0;
                                                                    echo renderReplyStatus($replyVal);
                                                                    break;
                                                                case 'status_badge':
                                                                    // 【新增】處理狀態徽章顯示
                                                                    $statusVal = $row[$col_status] ?? 'pending';
                                                                    echo renderStatusBadge($statusVal);
                                                                    break;
                                                                case 'button':
                                                                    if ($col['field'] == 'pin') {
                                                                        // 【修改】使用變數 $col_top
                                                                        $topVal = $row[$col_top] ?? 0;
                                                                        echo renderPinButton($rowId, $module, $topVal);
                                                                    } elseif ($col['field'] == 'edit') {
                                                                        // 【權限檢查】只有有編輯權限才顯示
                                                                        if ($canEdit) {
                                                                            $extraParams = [];
                                                                            foreach ($_GET as $key => $value) {
                                                                                if (strpos($key, 'selected') === 0 || $key === 'parent_id' || $key === 'language') {
                                                                                    if ($value !== '' && $value !== 'all') $extraParams[$key] = $value;
                                                                                }
                                                                            }
                                                                            echo renderEditButton($rowId, $module, $primaryKey, false, $extraParams);
                                                                        }
                                                                    } elseif ($col['field'] == 'delete') {
                                                                        // 【權限檢查】只有有刪除權限才顯示
                                                                        if ($canDelete) {
                                                                            echo renderDeleteButton($rowId, $module, $hasTrash, $hasHierarchy, $tableName);
                                                                        }
                                                                    } elseif ($col['field'] === 'view') {
                                                                        // 回收桶的查看按鈕 OR viewOnly 的查看按鈕
                                                                        $isTrashView = isset($_GET['trash']) && $_GET['trash'] == '1';
                                                                        $extraParams = [];
                                                                        foreach ($_GET as $key => $value) {
                                                                            if (strpos($key, 'selected') === 0 || $key === 'parent_id' || $key === 'language') {
                                                                                if ($value !== '' && $value !== 'all') $extraParams[$key] = $value;
                                                                            }
                                                                        }
                                                                        echo renderViewButton($rowId, $module, $primaryKey, $isTrashView, $extraParams);
                                                                    } elseif ($col['field'] === 'restore') {
                                                                        echo renderRestoreButton($rowId, $module);
                                                                    } elseif ($col['field'] === 'preview') {
                                                                        // 【新增】子網站預覽/環境切換按鈕
                                                                        echo renderPreviewButton($rowId, $row['d_title_en'] ?? '');
                                                                    } elseif ($col['field'] === 'next_level') {
                                                                        // 【階層導航】下一層按鈕
                                                                        if ($hasHierarchicalNav) {
                                                                            $trashParam = $isTrashMode ? '&trash=1' : '';
                                                                            $nextLevelUrl = PORTAL_AUTH_URL."tpl={$module}/list?parent_id={$rowId}{$trashParam}";
                                                                            echo "<a href=\"{$nextLevelUrl}\" class=\"btn btn-primary\" title=\"下一層\"><i class=\"fas fa-level-down-alt\"></i></a>";
                                                                        }
                                                                    }
                                                                break;
                                                                break;
                                                                }
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                    <hr class="solid mt-5 opacity-4">
                                    <div class="datatable-footer">
                                        <div class="row align-items-center justify-content-between mt-3">
                                            <?php
                                            // 檢查是否顯示批次操作
                                            $showBatchActions = $moduleConfig['listPage']['showBatchActions'] ?? true;
                                            if ($showBatchActions):
                                            ?>
                                            <div class="col-md-auto order-1 mb-3 mb-lg-0">
                                                <div class="d-flex align-items-stretch">
                                                     <div class="d-grid gap-3 d-md-flex justify-content-md-end me-4">
                                                         <select class="form-control select-style-1 bulk-action" name="bulk-action" style="min-width: 170px;">
                                                             <option value="" selected>批次操作</option>
                                                             <?php if ($isTrashMode) { ?>
                                                                 <?php if ($canDelete) { ?>
                                                                     <option value="restore">還原所選</option>
                                                                     <option value="delete">永久刪除</option>
                                                                 <?php } ?>
                                                             <?php } else { ?>
                                                                 <?php if ($canDelete) { ?>
                                                                     <option value="delete">刪除所選</option>
                                                                 <?php } ?>
                                                                 <?php if ($canAdd) { ?>
                                                                     <option value="clone_local">複製資料</option>
                                                                     <?php if (count($activeLanguages) > 1) { ?>
                                                                        <option value="clone">複製到語系</option>
                                                                     <?php } ?>
                                                                 <?php } ?>
                                                             <?php } ?>
                                                         </select>
                                                         <select class="form-control select-style-1 bulk-action-lang d-none" name="bulk-action-lang" style="min-width: 140px;">
                                                            <option value="">選擇語系...</option>
                                                            <?php foreach ($activeLanguages as $lang): ?>
                                                                <?php if($lang['l_slug'] !== $currentLang): ?>
                                                                    <option value="<?= $lang['l_slug'] ?>"><?= $lang['l_name'] ?> (<?= $lang['l_slug'] ?>)</option>
                                                                 <?php endif; ?>
                                                            <?php endforeach; ?>
                                                         </select>
                                                         <a href="javascript:void(0);" class="bulk-action-apply btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3" style="min-width: 90px;">執行</a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="col-lg-auto text-center order-3 order-lg-2">
                                                <div class="results-info-wrapper"></div>
                                            </div>
                                            <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                                                <div class="pagination-wrapper"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                <!-- end: page -->
            </section>
        </div>
    </section>
    
    <script src="template-style/js/examples/examples.ecommerce.datatables.list.js"></script>
</body>

</html>

<?php
// 輸出統一的 SweetAlert2 確認提示函數
SwalConfirmElement::render();
?>

<script type="text/javascript">
    // 新的 AJAX 排序邏輯
    function changeSort(pageNum, totalRows, itemId, newSort, categoryId) {
        // 顯示載入提示
        // 【修正】這裡不能寫死 d_sort，改用 PHP 變數 $col_sort (即 'sort_order')
        // 這樣 jQuery 才能抓到正確的 ID: #sort_order_17
        const $select = $('#<?php echo $col_sort; ?>_' + itemId);

        $select.prop('disabled', true);

        $.ajax({
            url: 'ajax_sort.php',
            type: 'POST',
            data: {
                module: '<?php echo $module; ?>',
                item_id: itemId,
                new_sort: newSort,
                category_id: categoryId || 0
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // 重新載入頁面以顯示新的排序（保留當前 URL 參數）
                    window.location.href = window.location.href;
                } else {
                    alert('排序失敗: ' + response.message);
                    $select.prop('disabled', false);
                }
            },
            error: function (xhr) {
                console.group('AJAX Error Debugging');
                console.log('URL:', 'ajax_sort.php');
                console.log('Status:', xhr.status);
                console.log('Response Text:', xhr.responseText);
                console.groupEnd();

                alert('排序失敗！\n狀態碼: ' + xhr.status + '\n錯誤: ' + xhr.statusText + '\n\n請打開控制台(F12)查看詳細回傳內容');
                $select.prop('disabled', false);
            }
        });
    }

    $(document).ready(function () {
        // 【修正】分類切換 - 只在非連動分類模式下執行
        $('#select1').change(function () {
            // 如果存在 select2，表示是連動分類，不執行這個舊邏輯
            if ($('#select2').length === 0) {
                window.location.href = "<?=PORTAL_AUTH_URL?>tpl=<?php echo $module; ?>/list?selected1=" + $(this).val();
            }
        });
    });

    // 置頂切換功能
    function togglePin(element) {
        const $btn = $(element);
        const itemId = $btn.data('id');
        const module = $btn.data('module');
        const isPinned = $btn.data('pinned');

        $.ajax({
            url: 'ajax_toggle_pin.php',
            type: 'POST',
            data: {
                module: module,
                item_id: itemId,
                category_id: '<?php echo $selectedCategory ?? ""; ?>'
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // 重新載入頁面以顯示新的置頂狀態
                    location.reload();
                } else {
                    alert('操作失敗: ' + response.message);
                }
            },
            error: function (xhr, status, error) {
                alert('操作失敗 (HTTP ' + xhr.status + '): ' + error);
            }
        });
    }

    // 草稿/顯示/不顯示 切換功能
    function toggleActive(element, itemId, nextValue, field = 'd_active') {
        const $badge = $(element);
        const module = '<?php echo $module; ?>';

        $.ajax({
            url: 'ajax_toggle_active.php',
            type: 'POST',
            data: {
                module: module,
                item_id: itemId,
                new_value: nextValue,
                field: field
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // 重新載入頁面以顯示新的狀態
                    location.reload();
                } else {
                    alert('操作失敗: ' + response.message);
                }
            },
            error: function (xhr) {
                alert('操作失敗 (HTTP ' + xhr.status + ')');
            }
        });
    }
</script>

<script>
    // 全域函數：還原功能
    function restoreItem(element) {
        const $btn = $(element);
        const itemId = $btn.data('id');
        const module = $btn.data('module');

        showActionConfirm({
            action: 'restore',
            itemType: 'data',
            onConfirm: () => {
                showProcessing('正在還原資料');

                $.ajax({
                    url: 'ajax_restore.php',
                    type: 'POST',
                    data: {
                        module: module,
                        item_id: itemId
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            showSuccess('還原成功！', '', () => {
                                window.location.href = '<?=PORTAL_AUTH_URL?>tpl=' + module + '/list?trash=1';
                            });
                        } else {
                            showError('還原失敗', response.message || '發生未知錯誤');
                        }
                    },
                    error: function () {
                        showError('請求失敗', '無法連接到伺服器，請稍後再試');
                    }
                });
            }
        });
    }

    /**
     * 全域函數：永久刪除功能 (含串聯刪除防呆)
     */
    async function permanentDelete(element) {
        const $btn = $(element);
        const itemId = $btn.data('id');
        const module = $btn.data('module');

        // 第一階段：基本確認
        const firstConfirm = await showActionConfirm({
            action: 'hard_delete',
            itemType: 'data',
            customMessage: '<strong style="color: #dc3545;">⚠️ 此操作無法復原！</strong><br>資料將被永久刪除',
            useRawMessage: true
        });

        if (!firstConfirm) return;

        // 顯示處理中
        showProcessing('正在檢查並刪除資料');

        try {
            const response = await $.ajax({
                url: 'ajax_delete.php',
                type: 'POST',
                data: {
                    module: module,
                    item_id: itemId,
                    trash: '1',  // 標記為垃圾桶模式，強制硬刪除
                    force: 0
                },
                dataType: 'json'
            });

            if (response.success) {
                showSuccessAndReload(module);
            } else if (response.has_data || response.needs_force) {
                // 第二階段：發現有子資料，提示串聯刪除
                const secondConfirm = await showActionConfirm({
                    action: 'hard_delete',
                    itemType: 'data',
                    customTitle: '分類內尚有資料',
                    customMessage: response.message + '<br>是否要連同這些文章一起「永久刪除」？',
                    useRawMessage: true
                });

                if (secondConfirm) {
                    showProcessing('執行深度刪除...');

                    const forceResponse = await $.ajax({
                        url: 'ajax_delete.php',
                        type: 'POST',
                        data: {
                            module: module,
                            item_id: itemId,
                            trash: '1',  // 標記為垃圾桶模式，強制硬刪除
                            force: 1
                        },
                        dataType: 'json'
                    });

                    if (forceResponse.success) {
                        showSuccessAndReload(module);
                    } else {
                        showErrorMsg(forceResponse.message);
                    }
                }
            } else {
                showErrorMsg(response.message);
            }
        } catch (e) {
            showErrorMsg('網路通訊失敗');
        }
    }

    // CSRF Tokens (從 Session 獲取 Slim CSRF 產生的 Token)
    const CSRF_NAME = '<?= $_SESSION['csrf_name'] ?? 'csrf_name' ?>';
    const CSRF_VALUE = '<?= $_SESSION['csrf_value'] ?? '' ?>';


    function showSuccessAndReload(module) {
        showSuccess('刪除成功！', '', () => {
            window.location.href = '<?=PORTAL_AUTH_URL?>tpl=' + module + '/list?trash=1';
        });
    }

    function showErrorMsg(msg) {
        showError('操作失敗', msg);
    }

    // 全域函數：單筆刪除功能（只跳一次提醒）
    function deleteItem(element) {
        const $btn = $(element);
        const itemId = $btn.data('id');
        const module = $btn.data('module');
        const hasTrash = $btn.data('has-trash');
        const hasHierarchy = $btn.data('has-hierarchy') == '1';
        const tableName = $btn.data('table-name');
        const isTrashMode = <?= $isTrashMode ? 'true' : 'false' ?>;

        if (hasHierarchy || tableName === 'taxonomies') {
            // 【修正】多層級分類模組：強迫使用硬刪除提示 (error icon)，因為階層刪除影響較大且通常為直接刪除
            const confirmAction = hasHierarchy ? 'hard_delete' : ((hasTrash == '1' && !isTrashMode) ? 'soft_delete' : 'hard_delete');
            showActionConfirm({
                action: confirmAction,
                itemType: 'category', // 使用分類名稱
                onConfirm: () => {
                    executeDelete(module, itemId, 0); 
                }
            });
        } else {
            // 非階層式結構/非分類，使用簡單的確認流程
            const confirmAction = (hasTrash == '1' && !isTrashMode) ? 'soft_delete' : 'hard_delete';

            showActionConfirm({
                action: confirmAction,
                itemType: 'data',
                onConfirm: () => {
                    executeDelete(module, itemId, 0, false, true); // 帶 confirm=true
                }
            });
        }
    }

    function executeDelete(module, itemId, force = 0, cascade = false, confirm = false) {
        // 顯示處理中
        Swal.fire({
            title: '處理中...',
            text: cascade ? '正在刪除分類及所有子分類...' : '正在執行刪除動作',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        // 【修改】改用 AJAX 方式，支援顯示詳細提示
        $.ajax({
            url: 'ajax_batch_delete.php',
            type: 'POST',
            data: {
                module: module,
                item_ids: [itemId],
                trash: '<?= $isTrashMode ? 1 : 0 ?>',
                force: force,
                cascade: cascade ? 1 : 0,
                confirm: confirm ? 1 : 0
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('成功', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else if (response.needs_confirm) {
                    // 【新增】軟刪除確認提示（顯示分類和文章數量）
                    Swal.fire({
                        title: '確認移到垃圾桶',
                        html: '<div style="line-height: 1.8; text-align: left;">' +
                              response.message.replace(/\n/g, '<br>') +
                              '<br><br>確定要繼續嗎？' +
                              '</div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: '確定刪除',
                        cancelButtonText: '取消',
                        width: '600px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeDelete(module, itemId, 0, cascade, true); // 帶 confirm 參數
                        }
                    });
                } else if (response.needs_force || response.has_data) {
                    // 【修改】永久刪除警告（顯示分類和文章數量）
                    Swal.fire({
                        title: '分類內有關聯的文章',
                        html: '<div style="line-height: 1.8; text-align: left;">' +
                              '' +
                              response.message.replace(/\n/g, '<br>') +
                              '<strong style="color: #dc3545;">此操作無法復原，是否要繼續？</strong>' +
                              '</div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: '刪除',
                        cancelButtonText: '取消',
                        width: '600px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeDelete(module, itemId, 1, cascade); // 強制刪除
                        }
                    });
                } else if (response.has_children) {
                    // 【保留】處理有子項目的情況
                    Swal.fire({
                        title: '此分類下尚有子項目',
                        text: response.message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: '級聯刪除（包含子項目）',
                        cancelButtonText: '取消'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeDelete(module, itemId, force, true); // 級聯刪除
                        }
                    });
                } else {
                    Swal.fire('失敗', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('錯誤', '無法連接到伺服器', 'error');
            }
        });
    }

    /**
     * 新版批次操作處理
     */
    $(document).ready(function() {
        // 切換操作類型時，顯示或隱藏語系選單
        $('.bulk-action').on('change', function() {
            if ($(this).val() === 'clone') {
                $('.bulk-action-lang').removeClass('d-none');
            } else {
                $('.bulk-action-lang').addClass('d-none').val('');
            }
        });

        $('.bulk-action-apply').on('click', function(e) {
            e.preventDefault();
            const action = $('.bulk-action').val();
            const itemIds = [];
            $('.row-checkbox:checked').each(function() {
                itemIds.push($(this).val());
            });

            if (itemIds.length === 0) {
                Swal.fire('提醒', '請先勾選要處理的資料', 'warning');
                return;
            }

            if (!action) {
                Swal.fire('提醒', '請先選擇操作項目', 'warning');
                return;
            }

            if (action === 'delete') {
                // 【修改】批次刪除 - 只跳一次提醒
                const isTrashMode = <?= $isTrashMode ? 'true' : 'false' ?>;
                const isCategoryModule = <?= ($moduleConfig['tableName'] === 'taxonomies') ? 'true' : 'false' ?>;
                const hasHierarchy = <?= $hasHierarchy ? 'true' : 'false' ?>;
                
                // 1. 如果是分類模組
                if (isCategoryModule) {
                    // 【修正】多層級分類模組：強迫使用硬刪除提示
                    const isHardDelete = (hasHierarchy || isTrashMode);
                    const confirmAction = isHardDelete ? 'hard_delete' : 'soft_delete';
                    showActionConfirm({
                        action: confirmAction,
                        itemType: 'category',
                        customMessage: `確定要${isHardDelete ? '永久刪除' : '將'}選取的 ${itemIds.length} 筆分類${isHardDelete ? '' : '移到垃圾桶'}嗎？<br><br>確定要繼續嗎？`,
                        onConfirm: () => {
                            executeBatchDelete(itemIds); 
                        }
                    });
                } else {
                    // 2. 如果是一般資料模組，直接跳一個簡單的確認提示
                    const typeName = '資料';
                    // 如果在垃圾桶模式，或者是多層級結構（雖然數據模組較少見，但保持一致），強制使用硬刪除提示
                    const isHardDelete = (isTrashMode || hasHierarchy);
                    const confirmAction = isHardDelete ? 'hard_delete' : 'soft_delete';
                    
                    showActionConfirm({
                        action: confirmAction,
                        itemType: 'data',
                        customMessage: `確定要${isHardDelete ? '永久刪除' : '將'}選取的 ${itemIds.length} 筆${typeName}${isHardDelete ? '' : '移到垃圾桶'}嗎？<br><br>確定要繼續嗎？`,
                        onConfirm: () => {
                            executeBatchDelete(itemIds, 0, true); // 帶 confirm=true，避免後端再次要求確認（雖然資料模組後端通常不要求確認）
                        }
                    });
                }
            } else if (action === 'restore') {
                // 批次還原
                Swal.fire({
                    title: '確定要還原嗎？',
                    text: "所選的 " + itemIds.length + " 筆資料將被還原",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '確定還原',
                    cancelButtonText: '取消'
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeBatchRestore(itemIds);
                    }
                });
            } else if (action === 'clone') {
                // 批次複製
                const targetLang = $('.bulk-action-lang').val();
                if (!targetLang) {
                    Swal.fire('提醒', '請先選擇目標語系', 'warning');
                    return;
                }
                
                showActionConfirm({
                    action: 'clone',
                    itemType: 'data',
                    customMessage: `確定要複製所選的 ${itemIds.length} 筆資料到 <strong style="color: #3085d6;">${targetLang}</strong> 語系嗎？`,
                    onConfirm: () => {
                        executeBatchClone(itemIds, targetLang);
                    }
                });
            } else if (action === 'clone_local') {
                // 批次複製 (同語系)
                showActionConfirm({
                    action: 'clone',
                    itemType: 'data',
                    customMessage: `確定要複製所選的 ${itemIds.length} 筆資料嗎？`,
                    onConfirm: () => {
                        executeBatchClone(itemIds, '<?= $currentLang ?>');
                    }
                });
            }
        });
    });

    function executeBatchRestore(itemIds) {
        Swal.fire({
            title: '處理中...',
            text: '正在還原資料...',
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: 'ajax_batch_restore.php',
            type: 'POST',
            data: {
                module: '<?= $module ?>',
                item_ids: itemIds
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('還原成功！', '', 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('失敗', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('錯誤', '無法連接到伺服器', 'error');
            }
        });
    }

    function executeBatchDelete(itemIds, force = 0, confirm = false) {
        Swal.fire({
            title: '處理中...',
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: 'ajax_batch_delete.php',
            type: 'POST',
            data: {
                module: '<?= $module ?>',
                item_ids: itemIds,
                trash: '<?= $isTrashMode ? 1 : 0 ?>',
                force: force,
                confirm: confirm ? 1 : 0
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('成功', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else if (response.needs_confirm) {
                    // 【新增】軟刪除確認提示（顯示分類和文章數量）
                    Swal.fire({
                        title: '確認移到垃圾桶',
                        html: '<div style="line-height: 1.8; text-align: left;">' +
                              response.message.replace(/\n/g, '<br>') +
                              '<br><br>確定要繼續嗎？' +
                              '</div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: '確定刪除',
                        cancelButtonText: '取消',
                        width: '600px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeBatchDelete(itemIds, 0, true); // 帶 confirm 參數
                        }
                    });
                } else if (response.needs_force || response.has_data) {
                    // 【修改】永久刪除警告（顯示分類和文章數量）
                    Swal.fire({
                        title: '確認永久刪除',
                        html: '<div style="line-height: 1.8; text-align: left;">' +
                              '<strong style="color: #dc3545;">以下分類及其文章將被永久刪除：</strong><br><br>' +
                              response.message.replace(/\n/g, '<br>') +
                              '<br><br><strong style="color: #dc3545;">此操作無法復原，確定要繼續嗎？</strong>' +
                              '</div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: '刪除',
                        cancelButtonText: '取消',
                        width: '600px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeBatchDelete(itemIds, 1); // 強制刪除
                        }
                    });
                } else {
                    Swal.fire('失敗', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('錯誤', '無法連接到伺服器', 'error');
            }
        });
    }

    function executeBatchClone(itemIds, targetLang) {
        Swal.fire({
            title: '處理中...',
            text: '正在複製資料...',
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: 'ajax_batch_translate_clone.php',
            type: 'POST',
            data: {
                module: '<?= $module ?>',
                item_ids: itemIds,
                target_lang: targetLang
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('成功', response.message, 'success').then(() => {
                        // 【修改】跳轉到目標語系頁面
                        const urlParams = new URLSearchParams(window.location.search);
                        urlParams.set('language', targetLang);
                        // 移除分頁參數，因為目標語系可能有不同的資料量
                        urlParams.delete('pageNum');
                        urlParams.delete('totalRows');
                        window.location.href = '<?= PORTAL_AUTH_URL ?>tpl=<?= $module ?>/list?' + urlParams.toString();
                    });
                } else {
                    Swal.fire('失敗', response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('複製失敗:', error, xhr.responseText);
                Swal.fire('錯誤', '複製過程中發生連線或伺服器錯誤', 'error');
            }
        });
    }

    // ========================================
    // 【首頁顯示管理】相關功能
    // ========================================

    // 模組選擇器切換
    function changeTargetModule(module) {
        window.location.href = '<?= PORTAL_AUTH_URL ?>tpl=homeDisplay/list&target_module=' + module + '&language=<?= $currentLang ?>';
    }

    // 切換首頁顯示狀態
    $(document).on('click', '.toggle-home-display', function() {
        const btn = $(this);
        const dataId = btn.data('data-id');
        const module = btn.data('module');
        const currentStatus = btn.data('status');
        const lang = btn.data('lang');

        // 顯示載入狀態
        btn.prop('disabled', true);
        const originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> 處理中...');

        $.ajax({
            url: 'ajax_toggle_home_display.php',
            method: 'POST',
            data: {
                data_id: dataId,
                module: module,
                current_status: currentStatus,
                lang: lang
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // 更新按鈕狀態
                    const newStatus = response.new_status;
                    btn.data('status', newStatus);

                    if (newStatus == 1) {
                        btn.removeClass('btn-default').addClass('btn-success');
                        btn.html('<i class="fas fa-check"></i> 已加入');
                    } else {
                        btn.removeClass('btn-success').addClass('btn-default');
                        btn.html('<i class="fas fa-plus"></i> 加入首頁');
                    }

                    // 顯示成功訊息
                    Swal.fire({
                        icon: 'success',
                        title: response.message,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });

                    // 重新載入頁面以更新排序
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    Swal.fire('錯誤', response.error, 'error');
                    btn.html(originalHtml);
                }
                btn.prop('disabled', false);
            },
            error: function() {
                Swal.fire('錯誤', '無法連接到伺服器', 'error');
                btn.html(originalHtml);
                btn.prop('disabled', false);
            }
        });
    });

    // 【首頁顯示管理】下拉選單排序功能
    $(document).on('change', '.home-sort-select', function() {
        const select = $(this);
        const hdId = select.data('hd-id');
        const newSort = select.val();
        const module = select.data('module');
        const lang = select.data('lang');

        // 顯示載入狀態
        select.prop('disabled', true);

        $.ajax({
            url: 'ajax_update_home_sort.php',
            method: 'POST',
            data: {
                hd_id: hdId,
                new_sort: newSort,
                module: module,
                lang: lang
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '排序已更新',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    // 重新載入頁面
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                } else {
                    Swal.fire('錯誤', response.error, 'error');
                    select.prop('disabled', false);
                }
            },
            error: function() {
                Swal.fire('錯誤', '無法更新排序', 'error');
                select.prop('disabled', false);
            }
        });
    });

    // 【首頁顯示管理】拖曳排序功能
    <?php if ($isHomeDisplayModule): ?>
    $(document).ready(function() {
        // 使用 Sortable.js 實現拖曳排序
        const tbody = document.querySelector('tbody');
        if (tbody && typeof Sortable !== 'undefined') {
            Sortable.create(tbody, {
                handle: '.sort-handle',
                animation: 150,
                onEnd: function(evt) {
                    // 收集所有已加入首頁的項目 ID（按新順序）
                    const sortData = [];
                    $('tbody tr').each(function() {
                        const row = $(this);
                        const isInHome = row.find('.toggle-home-display').data('status');
                        if (isInHome == 1) {
                            const hdId = row.find('[data-hd-id]').data('hd-id');
                            if (hdId) {
                                sortData.push(hdId);
                            }
                        }
                    });

                    if (sortData.length > 0) {
                        // 發送 AJAX 更新排序
                        $.ajax({
                            url: 'ajax_sort_home_display.php',
                            method: 'POST',
                            data: {
                                sort_data: sortData.join(','),
                                module: '<?= $targetModule ?>',
                                lang: '<?= $currentLang ?>'
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '排序已更新',
                                        toast: true,
                                        position: 'top-end',
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                    // 重新載入頁面
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 300);
                                } else {
                                    Swal.fire('錯誤', response.error, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('錯誤', '無法更新排序', 'error');
                            }
                        });
                    }
                }
            });
        }
    });
    <?php endif; ?>

    // 【重構】動態連動分類篩選功能
    $(document).ready(function() {
        // 標記是否為連動分類模式
        const isLinkedCategory = $('.category-filter').length > 0;
        
        if (!isLinkedCategory) return;

        // 處理每一層分類變更
        $('.category-filter').on('change', function() {
            const $this = $(this);
            const level = $this.data('level');
            const val = $this.val();
            const categoryName = $this.data('category');
            
            // 如果選擇了「全部」或空值 (常規 'all' 或一些舊邏輯帶入的 '0')
            if (val === 'all' || val === '' || val === '0') {
                const url = new URL(window.location);
                // 移除當前層級及之後的所有層級參數
                for (let l = level; l <= 20; l++) {
                    url.searchParams.delete('selected' + l);
                }
                url.searchParams.delete('pageNum');
                window.location.href = url.toString();
                return;
            }

            // 檢查是否有下一層
            const $nextSelect = $('#select' + (level + 1));
            if ($nextSelect.length > 0) {
                // 有下一層，嘗試載入子分類
                $.ajax({
                    url: 'includes/ajax_get_child_categories.php',
                    type: 'GET',
                    data: {
                        category: categoryName,
                        parent_id: val,
                        lang: '<?= $currentLang ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.categories.length > 0) {
                            // 有子分類，跳轉到第一個子分類以維持階層完整性
                            const firstChildId = response.categories[0].id;
                            const url = new URL(window.location);
                            url.searchParams.set('selected' + level, val);
                            url.searchParams.set('selected' + (level + 1), firstChildId);
                            // 移除更深層的參數
                            for (let l = level + 2; l <= 20; l++) {
                                url.searchParams.delete('selected' + l);
                            }
                            url.searchParams.delete('pageNum');
                            window.location.href = url.toString();
                        } else {
                            // 沒有子分類，直接在當前層級過濾，並移除後續層級參數
                            const url = new URL(window.location);
                            url.searchParams.set('selected' + level, val);
                            for (let l = level + 1; l <= 20; l++) {
                                url.searchParams.delete('selected' + l);
                            }
                            url.searchParams.delete('pageNum');
                            window.location.href = url.toString();
                        }
                    },
                    error: function() {
                        // 發生錯誤，退而求其次在當前層級過濾
                        const url = new URL(window.location);
                        url.searchParams.set('selected' + level, val);
                        url.searchParams.delete('pageNum');
                        window.location.href = url.toString();
                    }
                });
            } else {
                // 沒有下一層了，直接過濾
                const url = new URL(window.location);
                url.searchParams.set('selected' + level, val);
                url.searchParams.delete('pageNum');
                window.location.href = url.toString();
            }
        });
    });
</script>

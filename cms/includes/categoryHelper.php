<?php
/**
 * Category Helper Functions
 * 簡化分類處理，只需要傳入分類名稱即可
 */

/**
 * 根據分類名稱獲取分類選項
 * @param string|array $categoryName 分類名稱或陣列 ['DataCategory', 'd_class1_value']
 * @param mixed $selectedValue 當前選中的值
 * @param array $imageConfig 圖片配置 ['fileType' => 'experienceHallTag']
 * @return array 分類選項陣列 [['id' => 1, 'name' => '分類1', 'image' => 'path/to/image.jpg'], ...]
 */
function getCategoryOptions($categoryName, $selectedValue = null, $imageConfig = null, $includeRoot = false)
{
    global $conn;
    
    // 【新增】支援陣列格式：['DataCategory', 'experienceHall'] 或 ['taxCategory', 'shopC', 3]
    if (is_array($categoryName)) {
        $sourceType = $categoryName[0];  // 'DataCategory' 或 'taxCategory'
        $filterValue = $categoryName[1]; // 'experienceHall' 或 'shopC'

        if ($sourceType === 'DataCategory') {
            // 【新增】語系處理
            $currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;

            // 從 data_set 表查詢
            $query = "SELECT d_id as id, d_title as name FROM data_set WHERE d_class1 = :filter AND lang = :lang AND d_delete_time IS NULL ORDER BY d_sort ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([':filter' => $filterValue, ':lang' => $currentLang]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 【新增】如果需要圖片，從 file_set 查詢
            if ($imageConfig && isset($imageConfig['fileType'])) {
                foreach ($results as &$item) {
                    $imgQuery = "SELECT file_link1 FROM file_set WHERE file_type = :file_type AND file_d_id = :file_d_id LIMIT 1";
                    $imgStmt = $conn->prepare($imgQuery);
                    $imgStmt->execute([
                        ':file_type' => $imageConfig['fileType'],
                        ':file_d_id' => $item['id']
                    ]);
                    $imgRow = $imgStmt->fetch(PDO::FETCH_ASSOC);
                    $item['image'] = $imgRow ? '../' . $imgRow['file_link1'] : '';
                }
            }

            return $results;
        }

        // 【新增】taxCategory: 從 taxonomies 表抓取指定層級的分類
        if ($sourceType === 'taxCategory') {
            $taxonomyType = $filterValue; // 例如 'shopC'
            $targetLevel = isset($categoryName[2]) ? (int)$categoryName[2] : null; // 例如 3

            // 【新增】語系處理
            $currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;

            // 先從 taxonomy_types 取得 taxonomy_type_id
            $typeQuery = "SELECT ttp_id FROM taxonomy_types WHERE ttp_category = :ttp_category AND ttp_active = 1 LIMIT 1";
            $typeStmt = $conn->prepare($typeQuery);
            $typeStmt->execute([':ttp_category' => $taxonomyType]);
            $typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);

            if (!$typeRow) {
                return []; // 找不到該分類類型
            }

            $taxonomyTypeId = $typeRow['ttp_id'];

            // 查詢所有該類型的分類
            $query = "SELECT t_id, t_name, parent_id FROM taxonomies
                     WHERE taxonomy_type_id = :taxonomy_type_id
                     AND lang = :lang
                     AND t_active = 1
                     ORDER BY sort_order ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':taxonomy_type_id' => $taxonomyTypeId,
                ':lang' => $currentLang
            ]);
            $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 如果沒有指定層級，回傳全部
            if ($targetLevel === null) {
                return array_map(function($item) {
                    return ['id' => $item['t_id'], 'name' => $item['t_name']];
                }, $allItems);
            }

            // 計算每個項目的層級
            $results = [];
            foreach ($allItems as $item) {
                $level = calculateTaxonomyLevel($allItems, $item['t_id']);
                if ($level === $targetLevel) {
                    $results[] = ['id' => $item['t_id'], 'name' => $item['t_name']];
                }
            }

            return $results;
        }

        // 如果是其他類型，當作 category_set 的 category_type
        $categoryName = $filterValue;
    }
    
    // 特殊處理：CMS 選單分類
    if ($categoryName === 'cmsMenu') {
        require_once(__DIR__ . '/menuHelper.php');
        return getCmsMenuOptions($conn);
    }
    
    // 特殊處理：前端選單分類
    if ($categoryName === 'menus') {
        require_once(__DIR__ . '/menuHelper.php');
        return getFrontendMenusOptions($conn);
    }

    // 【動態回退】從資料庫 (cms_menus) 尋找該分類名稱對應的模組設定
    // 這讓所有在選單管理中設定好的模組都能自動支援階層式父層選擇
    $stmt = $conn->prepare("SELECT taxonomy_type_id, menu_table, menu_pk, menu_type FROM cms_menus WHERE menu_type = :name LIMIT 1");
    $stmt->execute([':name' => $categoryName]);
    $menuRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($menuRow) {
        require_once(__DIR__ . '/menuHelper.php');
        
        $isTaxonomyTable = ($menuRow['menu_table'] === 'taxonomies');
        $taxId = (isset($menuRow['taxonomy_type_id']) && $menuRow['taxonomy_type_id'] !== '') ? (int)$menuRow['taxonomy_type_id'] : null;

        // 情況 A：標籤型分類 (有 taxonomy_type_id)
        if ($taxId !== null && $taxId > 0) {
            $options = getHierarchicalOptions(
                $conn, 
                'taxonomies', 
                't_id', 
                't_name', 
                'parent_id', 
                "AND taxonomy_type_id = " . $taxId
            );
            if ($includeRoot) {
                array_unshift($options, ['id' => 0, 'name' => '全部']);
            }
            return $options;
        }
        
        // 情況 B：一般資料型分類 (或未指定類型的標籤)
        if (!empty($menuRow['menu_table'])) {
            // 自動偵測欄位名稱
            if ($isTaxonomyTable) {
                $pKey = 't_id';
                $tCol = 't_name';
                $pCol = 'parent_id';
                $where = ($taxId === 0) ? "AND (taxonomy_type_id = 0 OR taxonomy_type_id IS NULL)" : "";
            } else {
                $pKey = $menuRow['menu_pk'] ?? 'd_id';
                $tCol = 'd_title'; // 預設標題欄位
                $pCol = 'd_parent_id';
                $where = "";
            }
            
            $options = getHierarchicalOptions($conn, $menuRow['menu_table'], $pKey, $tCol, $pCol, $where);
            if ($includeRoot) {
                array_unshift($options, ['id' => 0, 'name' => '全部']);
            }
            return $options;
        }
    }
    
    // 【新增】特殊處理：權限群組
    if ($categoryName === 'authority_groups' || $categoryName === 'authorityCate') {
        $query = "SELECT group_id as id, group_name as name 
                  FROM authority_groups 
                  WHERE group_active = 1 
                  ORDER BY group_id ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($includeRoot) {
            array_unshift($results, ['id' => 0, 'name' => '全部']);
        }
        return $results;
    }
    
    // 【新增】特殊處理：標籤類型 (taxonomy_types)
    if ($categoryName === 'taxonomyType') {
        $query = "SELECT ttp_id as id, ttp_name as name 
                  FROM taxonomy_types 
                  WHERE ttp_active = 1 
                  ORDER BY sort_order ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 增加「無」的選項
        array_unshift($results, ['id' => 0, 'name' => '無 (或不指定)']);
        
        return $results;
    }
    
    // 【最後的 fallback】從 taxonomy_types 查詢
    // 【新增】語系處理
    $currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;

    $query = "SELECT t.t_id as id, t.t_name as name, tt.ttp_name AS type_name
              FROM taxonomies t
              JOIN taxonomy_types tt ON tt.ttp_id = t.taxonomy_type_id
              WHERE tt.ttp_category = :category
              AND tt.ttp_active = 1
              AND t.lang = :lang
              AND t.deleted_at IS NULL
              ORDER BY tt.sort_order, t.sort_order";

    $stmt = $conn->prepare($query);
    $stmt->execute([':category' => $categoryName, ':lang' => $currentLang]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($includeRoot) {
        array_unshift($results, ['id' => 0, 'name' => '全部']);
    }
    return $results;
}

/**
 * 渲染分類下拉選單 HTML
 * @param string|array $categoryName 分類名稱或陣列
 * @param string $fieldName 表單欄位名稱
 * @param mixed $selectedValue 當前選中的值
 * @param array $options 額外選項 ['useChosen' => true, 'multiple' => true, 'imageConfig' => ['fileType' => 'xxx']]
 * @return string HTML 字串
 */
function renderCategorySelect($categoryName, $fieldName, $selectedValue = null, $options = [])
{
    $imageConfig = $options['imageConfig'] ?? null;
    $includeRoot = $options['includeRoot'] ?? false;
    $categories = getCategoryOptions($categoryName, $selectedValue, $imageConfig, $includeRoot);
    $useChosen = $options['useChosen'] ?? false;
    $multiple = $options['multiple'] ?? false;
    $class = $options['class'] ?? 'form-control';
    
    if ($useChosen) {
        $class .= ' chosen-select';
    }
    
    
    // 處理多選值
    $selectedValues = [];
    if ($multiple && $selectedValue) {
        $selectedValues = is_array($selectedValue) ? $selectedValue : explode(',', $selectedValue);
    }
    
    // 【修正】如果是多選，按照 selectedValues 的順序重新排列 categories
    if ($multiple && !empty($selectedValues)) {
        $orderedCategories = [];
        $unselectedCategories = [];
        
        // 先按照選擇順序添加已選擇的項目
        foreach ($selectedValues as $selectedId) {
            foreach ($categories as $cat) {
                if ($cat['id'] == $selectedId) {
                    $orderedCategories[] = $cat;
                    break;
                }
            }
        }
        
        // 再添加未選擇的項目
        foreach ($categories as $cat) {
            if (!in_array($cat['id'], $selectedValues)) {
                $unselectedCategories[] = $cat;
            }
        }
        
        // 合併：已選擇的在前（按順序），未選擇的在後
        $categories = array_merge($orderedCategories, $unselectedCategories);
    }
    
    $multipleAttr = $multiple ? 'multiple' : '';
    $nameAttr = $multiple ? "{$fieldName}[]" : $fieldName;
    $dataPlaceholder = $multiple ? 'data-placeholder="請選擇..."' : '';
    $styleAttr = $multiple ? 'style="width: 80%;"' : '';
    $tabindexAttr = $multiple ? 'tabindex="4"' : '';
    $requiredAttr = ($options['required'] ?? false) ? 'required' : '';
    $canCreateAttr = ($options['canCreate'] ?? false) ? 'data-can-create="true" data-tags="true"' : '';
    
    // 【新增】是否顯示「請選擇」空選項（預設為 true）
    $showPlaceholder = $options['showPlaceholder'] ?? false;

    $html = "<select name=\"{$nameAttr}\" id=\"{$fieldName}\" class=\"{$class}\" data-plugin-selectTwo {$multipleAttr} {$dataPlaceholder} {$styleAttr} {$tabindexAttr} {$requiredAttr} {$canCreateAttr}>";
    
    // 如果不是多選且需要顯示空選項，則添加「請選擇」
    if (!$multiple && $showPlaceholder) {
        $html .= "<option value=\"\">-- 請選擇 --</option>";
    }
    
    foreach ($categories as $cat) {
        if ($multiple) {
            $selected = in_array($cat['id'], $selectedValues) ? 'selected' : '';
        } else {
            $selected = ($cat['id'] == $selectedValue) ? 'selected' : '';
        }
        
        // 【新增】如果有圖片，添加 data-img-src 屬性
        $imgAttr = '';
        if (isset($cat['image']) && $cat['image']) {
            $imgAttr = " data-img-src=\"{$cat['image']}\"";
        }
        
        $html .= "<option value=\"{$cat['id']}\" {$selected}{$imgAttr}>{$cat['name']}</option>";
    }
    
    $html .= "</select>";
    
    // 如果是多選，添加隱藏欄位記錄順序
    if ($multiple) {
        $sortedFieldName = "sorted_{$fieldName}";
        $sortedValue = is_array($selectedValue) ? implode(',', $selectedValue) : ($selectedValue ?? '');
        $html .= "<input type=\"hidden\" name=\"{$sortedFieldName}\" id=\"{$sortedFieldName}\" value=\"{$sortedValue}\">";
        
        // 添加 JavaScript 處理順序
        $selectedValuesJson = json_encode($selectedValues);
        $html .= "
        <script>
        $(document).ready(function() {
            const \$selectElement = $('#{$fieldName}');
            const \$sortedInput = $('#{$sortedFieldName}');
            let selectedOrder = {$selectedValuesJson};
            
            // 監聽多選框的變化事件
            \$selectElement.on('change', function() {
                // 如果全部取消選擇，將 selectedOptions 設置為空數組
                const selectedOptions = \$selectElement.val() || [];
                
                // 更新選擇順序，保留用戶選擇的順序
                selectedOrder = selectedOrder.filter(value => selectedOptions.includes(value));
                selectedOrder = [...selectedOrder, ...selectedOptions.filter(value => !selectedOrder.includes(value))];
                
                // 將選擇順序記錄到隱藏輸入框
                \$sortedInput.val(selectedOrder.join(','));
            });
        });
        </script>";
    }
    
    return $html;
}

/**
 * 計算 taxonomy 的層級
 * @param array $allItems 所有的 taxonomy 項目 (包含 t_id, t_name, parent_id)
 * @param int $targetId 要計算層級的目標 ID
 * @return int 層級數 (1 = 第一層, 2 = 第二層, 依此類推)
 */
function calculateTaxonomyLevel($allItems, $targetId)
{
    // 建立 ID 到項目的對應表，方便快速查找
    $itemMap = [];
    foreach ($allItems as $item) {
        $itemMap[$item['t_id']] = $item;
    }

    // 如果找不到目標 ID，回傳 0
    if (!isset($itemMap[$targetId])) {
        return 0;
    }

    $level = 1;
    $currentId = $targetId;

    // 向上追溯父層，直到找到最頂層 (parent_id = 0 或 null)
    while (isset($itemMap[$currentId]) && $itemMap[$currentId]['parent_id'] != 0) {
        $currentId = $itemMap[$currentId]['parent_id'];
        $level++;

        // 防止無限迴圈 (例如循環引用的情況)
        if ($level > 100) {
            break;
        }
    }

    return $level;
}

/**
 * 取得分類的全路徑 (從頂層到目標節點)
 * @param string $categoryName 分類名稱
 * @param int $targetId 目標 ID
 * @return array 路徑 ID 陣列 [頂層ID, 第二層ID, ..., 目標ID]
 */
function getCategoryPath($categoryName, $targetId)
{
    global $conn;
    if (!$targetId) return [];

    $mapping = getCategoryMapping($categoryName);
    $tableName = $mapping['table'];
    $primaryKey = $mapping['pk'];
    $parentCol = $mapping['parent'];

    $path = [];
    $currentId = $targetId;

    while ($currentId > 0) {
        array_unshift($path, $currentId);
        $stmt = $conn->prepare("SELECT {$parentCol} FROM {$tableName} WHERE {$primaryKey} = :id");
        $stmt->execute([':id' => $currentId]);
        $parentId = $stmt->fetchColumn();
        
        if ($parentId === false || $parentId == 0 || $parentId == $currentId) {
            break;
        }
        $currentId = $parentId;
    }

    return $path;
}

/**
 * 取得分類的全路徑名稱字串 (例如: "分類1 > 子分類2")
 * @param string $categoryName 分類名稱
 * @param int $targetId 目標 ID
 * @param string $separator 分隔符號
 * @return string
 */
function getCategoryPathNames($categoryName, $targetId, $separator = ' > ')
{
    $pathIds = getCategoryPath($categoryName, $targetId);
    if (empty($pathIds)) return '-';
    
    $pathNames = [];
    foreach ($pathIds as $id) {
        $name = getCategoryNamesByIds($categoryName, $id);
        if ($name) $pathNames[] = $name;
    }
    
    return implode($separator, $pathNames);
}

/**
 * 取得分類的全路徑 HTML (麵包屑風格，可點擊跳轉)
 * @param string $categoryName 分類名稱
 * @param int $targetId 目標 ID
 * @param string $module 當前模組 slug (選填)
 * @param string $separator 分隔符號 (HTML)
 * @return string
 */
function getCategoryPathHtml($categoryName, $targetId, $module = null, $separator = ' <i class="fas fa-chevron-right mx-1 text-muted" style="font-size: 0.8em;"></i> ')
{
    global $conn;
    $pathIds = getCategoryPath($categoryName, $targetId);
    if (empty($pathIds)) return '<span class="text-muted">-</span>';
    
    // 如果沒給 module，嘗試從 cms_menus 找
    if (!$module) {
        $stmt = $conn->prepare("SELECT menu_id FROM cms_menus WHERE menu_type = :name OR menu_id = :name LIMIT 1");
        $stmt->execute([':name' => $categoryName]);
        $moduleTemp = $stmt->fetchColumn();
        $module = $moduleTemp ?: $categoryName;
    }

    $baseUrl = defined('PORTAL_AUTH_URL') ? PORTAL_AUTH_URL : '';
    $htmlArr = [];
    $accumulatedParams = [];
    
    foreach ($pathIds as $index => $pid) {
        $name = getCategoryNamesByIds($categoryName, $pid);
        $level = $index + 1;
        $accumulatedParams[] = "selected{$level}=" . $pid;
        
        $url = $baseUrl . "tpl={$module}/list?" . implode('&', $accumulatedParams);
        $htmlArr[] = "<a href=\"{$url}\" class=\"text-dark text-decoration-none\" style=\"font-size: 0.9em; border-bottom: 1px dashed #ccc;\" onmouseover=\"this.style.borderBottomStyle='solid'\" onmouseout=\"this.style.borderBottomStyle='dashed'\">{$name}</a>";
    }
    
    return implode($separator, $htmlArr);
}

/**
 * 取得指定父層下的分類選項 (不含遞迴，單層)
 * @param string $categoryName 分類名稱
 * @param int $parentId 父層 ID
 * @return array
 */
function getSubCategoryOptions($categoryName, $parentId = 0)
{
    global $conn;
    $currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;

    $mapping = getCategoryMapping($categoryName);
    $tableName = $mapping['table'];
    $primaryKey = $mapping['pk'];
    $titleCol = $mapping['title'];
    $parentCol = $mapping['parent'];
    $taxId = $mapping['taxId'];

    if ($parentId === 0 || $parentId === '0' || $parentId === null || $parentId === '') {
        $where = "WHERE ({$parentCol} = 0 OR {$parentCol} IS NULL OR {$parentCol} = '') AND lang = :lang";
        $params = [':lang' => $currentLang];
    } else {
        $where = "WHERE {$parentCol} = :parent_id AND lang = :lang";
        $params = [':parent_id' => $parentId, ':lang' => $currentLang];
    }

    if ($tableName === 'taxonomies') {
        // 如果有 taxonomy_type_id，直接查
        if ($taxId !== null && $taxId > 0) {
            $where .= " AND taxonomy_type_id = :tax_id";
            $params[':tax_id'] = $taxId;
        } else {
            // 如果沒有 taxId，則嘗試用 JOIN 的方式 (同原有的 getCategoryOptions 邏輯)
            try {
                // 【修正】根據 $parentId 決定查詢條件
                if ($parentId === 0 || $parentId === '0' || $parentId === null || $parentId === '') {
                    // 查詢頂層分類
                    $parentCondition = "(t.parent_id = 0 OR t.parent_id IS NULL OR t.parent_id = '')";
                } else {
                    // 查詢指定父層的子分類
                    $parentCondition = "t.parent_id = :pid";
                }
                
                $query = "SELECT t.t_id as id, t.t_name as name
                          FROM taxonomies t
                          JOIN taxonomy_types tt ON tt.ttp_id = t.taxonomy_type_id
                          WHERE tt.ttp_category = :category
                          AND t.lang = :lang
                          AND {$parentCondition}
                          AND tt.ttp_active = 1
                          AND t.t_active = 1
                          AND (t.deleted_at IS NULL)
                          ORDER BY t.sort_order ASC, t.t_id ASC";
                $stmt = $conn->prepare($query);
                $params = [':category' => $categoryName, ':lang' => $currentLang];
                if ($parentId > 0) {
                    $params[':pid'] = $parentId;
                }
                $stmt->execute($params);
                $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($res)) return $res;
            } catch (Exception $e) {
                // fallback to standard query
            }
        }
        $where .= " AND (deleted_at IS NULL) AND t_active = 1";
        $orderBy = "ORDER BY sort_order ASC, t_id ASC";
    } else {
        $where .= " AND (d_delete_time IS NULL) AND d_active = 1";
        $orderBy = "ORDER BY d_sort ASC, {$primaryKey} ASC";
    }

    try {
        $query = "SELECT {$primaryKey} as id, {$titleCol} as name FROM {$tableName} {$where} {$orderBy}";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 取得分類的表結構對應資訊
 * @param string $categoryName
 * @return array
 */
function getCategoryMapping($categoryName)
{
    global $conn;
    
    // 預設值 (Fallback to taxonomies)
    $mapping = [
        'table' => 'taxonomies',
        'pk'    => 't_id',
        'title' => 't_name',
        'parent' => 'parent_id',
        'taxId'  => null
    ];

    $stmt = $conn->prepare("SELECT taxonomy_type_id, menu_table, menu_pk, menu_type FROM cms_menus WHERE menu_type = :name OR menu_id = :name LIMIT 1");
    $stmt->execute([':name' => $categoryName]);
    $menuRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($menuRow) {
        // 【修正】先檢查是否有 taxonomy_type_id（即使 menu_table 為空）
        if (isset($menuRow['taxonomy_type_id']) && $menuRow['taxonomy_type_id'] !== '' && $menuRow['taxonomy_type_id'] !== null) {
            $mapping['taxId'] = (int)$menuRow['taxonomy_type_id'];
            // 如果有 taxonomy_type_id，表示使用 taxonomies 表
            $mapping['table'] = 'taxonomies';
            $mapping['pk'] = 't_id';
            $mapping['title'] = 't_name';
            $mapping['parent'] = 'parent_id';
        }
        
        // 如果有指定 menu_table，則覆蓋預設值
        if (!empty($menuRow['menu_table'])) {
            $mapping['table'] = $menuRow['menu_table'];
            
            if ($mapping['table'] === 'taxonomies') {
                $mapping['pk'] = 't_id';
                $mapping['title'] = 't_name';
                $mapping['parent'] = 'parent_id';
            } else {
                $mapping['pk'] = $menuRow['menu_pk'] ?? 'd_id';
                $mapping['title'] = $mapping['table'] === 'data_set' ? 'd_title' : 't_name'; 
                $mapping['parent'] = $mapping['table'] === 'data_set' ? 'd_parent_id' : 'parent_id';
            }
        }
    }

    // 如果還是沒有 taxId，或是根本沒找到 menuRow，強行去 taxonomy_types 找看看
    if ($mapping['table'] === 'taxonomies' && $mapping['taxId'] === null) {
        $stmt = $conn->prepare("SELECT ttp_id FROM taxonomy_types WHERE ttp_category = :name LIMIT 1");
        $stmt->execute([':name' => $categoryName]);
        $taxType = $stmt->fetchColumn();
        
        if ($taxType) {
            $mapping['taxId'] = (int)$taxType;
        }
    }

    return $mapping;
}

/**
 * 取得多個分類 ID 對應的名稱
 * @param string $categoryName 分類名稱
 * @param string|array $ids ID 字串 (逗號分隔) 或陣列
 * @return string 標籤名稱字串 (以逗號分隔)
 */
function getCategoryNamesByIds($categoryName, $ids)
{
    global $conn;
    if (empty($ids)) return '';

    // 處理 IDs
    if (!is_array($ids)) {
        $ids = explode(',', (string)$ids);
    }
    $ids = array_filter(array_map('trim', $ids));
    if (empty($ids)) return '';

    $mapping = getCategoryMapping($categoryName);
    $tableName = $mapping['table'];
    $primaryKey = $mapping['pk'];
    $titleCol = $mapping['title'];

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "SELECT {$titleCol} FROM {$tableName} WHERE {$primaryKey} IN ({$placeholders})";
        $stmt = $conn->prepare($query);
        $stmt->execute(array_values($ids));
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return implode(', ', $names);
    } catch (Exception $e) {
        return implode(', ', $ids); // 出錯時回傳原始 ID
    }
}

/**
 * 處理自動新增標籤
 * 如果提交的是字串而非 ID，則視為新標籤並存入資料庫
 * @param PDO $conn
 * @param string $categoryName
 * @param string $valuesComma 逗號分隔的值 (可能是 ID 也可能是新標籤名稱)
 * @param array $context 上下文 (lang)
 * @return string 回傳轉換後的 ID 字串 (逗號分隔)
 */
function processAutoCreateTags($conn, $categoryName, $valuesComma, $context = [])
{
    if (empty($valuesComma)) return '';

    $values = explode(',', $valuesComma);
    $mapping = getCategoryMapping($categoryName);
    $tableName = $mapping['table'];
    $primaryKey = $mapping['pk'];
    $titleCol = $mapping['title'];
    $parentCol = $mapping['parent'];
    $taxId = $mapping['taxId'];
    $currentLang = $context['lang'] ?? $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;

    $resultIds = [];

    foreach ($values as $val) {
        $val = trim($val);
        if ($val === '') continue;

        // 如果是數字，視為現有 ID
        if (is_numeric($val)) {
            $resultIds[] = $val;
            continue;
        }

        // 否則，檢查資料庫是否已存在同名標籤 (同一類型、同語系)
        $where = "WHERE {$titleCol} = :name AND lang = :lang";
        $params = [':name' => $val, ':lang' => $currentLang];

        if ($tableName === 'taxonomies' && $taxId !== null) {
            $where .= " AND taxonomy_type_id = :tax_id";
            $params[':tax_id'] = $taxId;
        } elseif ($mapping['table'] === 'data_set') {
            // 如果是 data_set，可能還有 d_class1 限制 (通常 categoryName 就是 d_class1)
            $where .= " AND d_class1 = :class1";
            $params[':class1'] = $categoryName;
        }
        
        $checkStmt = $conn->prepare("SELECT {$primaryKey} FROM {$tableName} {$where} LIMIT 1");
        $checkStmt->execute($params);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            $resultIds[] = $existingId;
        } else {
            // 新增標籤
            $fields = [
                $titleCol => $val,
                'lang' => $currentLang,
            ];

            if ($tableName === 'taxonomies') {
                $fields['taxonomy_type_id'] = $taxId;
                $fields['t_active'] = 1;
                $fields['parent_id'] = null; // 修正先前 integrity error
            } else {
                $fields['d_class1'] = $categoryName;
                $fields['d_active'] = 1;
                $fields['d_date'] = date('Y-m-d H:i:s');
            }

            // 處理排序 (標籤排第一點 順序設為 1)
            $sortCol = ($tableName === 'taxonomies') ? 'sort_order' : 'd_sort';
            
            // 建立排除標題條件的 WHERE 子句用於更新排序與查詢最新排序
            $sortWhere = "WHERE lang = :lang";
            $sortParams = [':lang' => $currentLang];
            if ($tableName === 'taxonomies' && $taxId !== null) {
                $sortWhere .= " AND taxonomy_type_id = :tax_id";
                $sortParams[':tax_id'] = $taxId;
            } elseif ($mapping['table'] === 'data_set') {
                $sortWhere .= " AND d_class1 = :class1";
                $sortParams[':class1'] = $categoryName;
            }
            
            // 全部往後推一位 (用於標籤由最新排至最舊)
            $updateSortStmt = $conn->prepare("UPDATE {$tableName} SET {$sortCol} = {$sortCol} + 1 {$sortWhere}");
            $updateSortStmt->execute($sortParams);
            
            $fields[$sortCol] = 1;

            $cols = implode(',', array_keys($fields));
            $placeholders = ':' . implode(',:', array_keys($fields));
            $insertSql = "INSERT INTO {$tableName} ({$cols}) VALUES ({$placeholders})";
            
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->execute($fields);
            $resultIds[] = $conn->lastInsertId();
        }
    }

    return implode(',', $resultIds);
}
?>

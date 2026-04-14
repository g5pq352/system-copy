<?php
/**
 * Menu Helper Functions
 * 選單輔助函數 - 從資料庫載入階層式選單配置
 */

/**
 * 1. 從資料庫載入選單配置（階層式結構 - 支援無限層級）
 * @param PDO $conn 資料庫連線
 * @return array 選單配置陣列
 */
function loadMenusFromDatabase($conn)
{
    try {
        // 1. 查詢所有啟用的選單
        $menuQuery = "SELECT * FROM cms_menus WHERE menu_active = 1 ORDER BY menu_sort ASC";
        $menuStmt = $conn->prepare($menuQuery);
        $menuStmt->execute();
        $allMenus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. 建立原始樹狀結構
        $rawTree = buildMenuTree($allMenus);
        
        // 3. 遞迴轉換 Key 名稱並收集 Triggers
        return convertKeysForFrontend($rawTree);
        
    } catch (Exception $e) {
        error_log("載入選單失敗: " . $e->getMessage());
        return [];
    }
}

/**
 * 2. 輔助函數：遞迴轉換資料庫欄位名稱 & 收集 Triggers
 */
function convertKeysForFrontend($items) {
    $result = [];
    foreach ($items as $item) {
        $newItem = [
            'menu_id'  => $item['menu_id'],
            'title'    => $item['menu_title'],
            'type'     => $item['menu_type'],
            // 假設資料庫有 menu_icon 欄位，如果沒有可以預設
            'icon'     => $item['menu_icon'] ?? 'bx bx-file', 
            'link'     => $item['menu_link'],
            'auth'     => $item['menu_auth'],
            'br'       => $item['menu_br'] ?? 0,
            'triggers' => [] // 初始化 triggers
        ];

        // A. 收集自己的 type
        if (!empty($item['menu_type'])) {
            $newItem['triggers'][] = $item['menu_type'];
        }

        // B. 處理子選單 (遞迴)
        if (isset($item['children']) && !empty($item['children'])) {
            $newItem['children'] = convertKeysForFrontend($item['children']);
            
            // C. 將子選單的 triggers 往上收集 (讓父選單知道包含哪些 type)
            foreach ($newItem['children'] as $child) {
                if (isset($child['triggers'])) {
                    $newItem['triggers'] = array_merge($newItem['triggers'], $child['triggers']);
                }
            }
        }

        $result[] = $newItem;
    }
    return $result;
}

/**
 * 遞迴函數：產生無限層級 HTML
 * 修正版：加入 level 參數判斷頂層 Icon
 */
function buildMenuHtml($items, $ryder_now, $userPermissions = null, $menu_is = '', $level = 0) {
    $html = '';
    $hasActiveChild = false;

    // -----------------------------------------------------------
    // 1. 決定當前的 Active Type
    // -----------------------------------------------------------
    $currentType = $menu_is;

    if (empty($currentType) && isset($_GET['module'])) {
        $currentType = $_GET['module'];
    }
    
    if (empty($currentType) && isset($_GET['tpl'])) {
        $parts = explode('/', $_GET['tpl']);
        $currentType = $parts[0] ?? '';
    }
    // -----------------------------------------------------------

    foreach ($items as $item) {
        // --- 權限檢查:一般使用者需要檢查權限 ---
        $menuId = $item['menu_id'] ?? null;
        if ($userPermissions !== null && $menuId) {
            // 如果該選單沒有權限設定,或者 view 權限為 false,則隱藏
            if (!isset($userPermissions[$menuId]) || empty($userPermissions[$menuId]['view'])) {
                continue; 
            }
        }

        $children = $item['children'] ?? null;
        $title    = $item['title'] ?? '未命名';
        $icon     = $item['icon'] ?? 'bx bx-file';
        $type     = $item['type'] ?? '';
        
        // 確保 link 有值
        $link     = !empty($item['link']) ? PORTAL_AUTH_URL.$item['link'] : 'javascript:void(0);';
        
        // -----------------------------------------------------------
        // ★ 核心邏輯 ★
        // -----------------------------------------------------------
        
        // 條件 A: 傳統的資料夾結構 (有子選單陣列)
        $hasChildrenArray = (!empty($children) && is_array($children));
        
        // 條件 B: 特殊父選單 (有 type 且 link 不是預設空值)
        $isSpecialParent = (!empty($type) && $link !== 'javascript:void(0);');

        // 判斷是否為「父層級」 (滿足 A 或 B 任一條件)
        $isFolderStructure = ($hasChildrenArray || $isSpecialParent);


        // === [情況 1] 作為父選單 / 特殊功能節點處理 ===
        if ($isFolderStructure) {
            
            $subHtml = '';
            $subActive = false;

            // 如果真的有子選單陣列，遞迴呼叫 (注意這裡傳入 level + 1)
            if ($hasChildrenArray) {
                list($subHtml, $subActive) = buildMenuHtml($children, $ryder_now, $userPermissions, $currentType, $level + 1);
            }

            // ★ 判斷自己是否為 Active ★
            $isSelfActive = false;
            
            if ($subActive) {
                $isSelfActive = true;
            } elseif ($isSpecialParent && (string)$currentType === (string)$type) {
                $isSelfActive = true;
            }

            // 組合 CSS Class — 只有真正有子選單 HTML 才需要 nav-parent (箭頭)
            $parentClass = !empty($subHtml) ? "nav-parent" : "";
            if ($isSelfActive) {
                $parentClass .= (!empty($subHtml) ? " nav-expanded" : "") . " nav-active";
                $hasActiveChild = true; // 回報給上一層
            }

            // 輸出 HTML
            $html .= '<li class="' . $parentClass . '">';
            $html .= '  <a class="nav-link" href="'.$link.'">';
            if(!empty($icon)) {
                $html .= '      <i class="' . $icon . '" aria-hidden="true"></i>';
            }
            $html .= '      <span>' . $title . '</span>';
            $html .= '  </a>';
            
            // 如果有遞迴產生的子 HTML，就放進去
            if (!empty($subHtml)) {
                $html .= '  <ul class="nav nav-children">';
                $html .=        $subHtml; 
                $html .= '  </ul>';
            }
            $html .= '</li>';

        } 
        // === [情況 2] 純子項目 (沒有子選單，也沒有 type/link 組合的普通葉節點) ===
        else {
            
            // 如果連 type 都沒有，就是最普通的純連結 (例如登出、首頁等)
            if (empty($type)) {
                // 這裡保留 nav-parent 樣式
                $html .= '<li><a class="nav-link" href="'.$link.'"><i class="'.$icon.'"></i><span>'.$title.'</span></a></li>';
                continue;
            }

            // --- 這裡處理有 type 但被視為普通子項目的邏輯 ---
            // 讀取設定檔
            $configFile = __DIR__ . "/../set/{$type}Set.php";
            $showList = true; 
            $showInfo = false;
            
            if (file_exists($configFile)) {
                $config = include $configFile;
                if (is_array($config)) {
                    $pageType = $config['pageType'] ?? 'list';
                    if ($pageType === 'info') { $showList = false; $showInfo = true; }
                    elseif ($pageType === 'both') { $showList = true; $showInfo = true; }
                }
            }

            // 連結
            $urlList = PORTAL_AUTH_URL."tpl={$type}/list";
            $urlInfo = PORTAL_AUTH_URL."tpl={$type}/info";

            // Active 判斷
            $isSameType = ((string)$currentType === (string)$type);
            $isInfoPage = (strpos($ryder_now, '/info') !== false || (isset($_GET['tpl']) && strpos($_GET['tpl'], '/info') !== false));
            
            $isListActive = ($isSameType && !$isInfoPage);
            $isInfoActive = ($isSameType && $isInfoPage);

            // 1. 列表連結
            if ($showList) {
                $class = $isListActive ? 'nav-active' : '';
                if ($isListActive) $hasActiveChild = true; 
                $html .= '<li class="'.$class.'"><a class="nav-link" href="'.$urlList.'"><span>' . $title . '列表</span></a></li>';
            }

            // 2. 設定連結
            if ($showInfo) {
                $class = $isInfoActive ? 'nav-active' : '';
                if ($isInfoActive) $hasActiveChild = true; // 修正：這裡也要回報 Active，否則父選單不會展開
                
                // --- 修正後的判斷邏輯 ---
                $iconHtml = ''; // 預設 icon 是空的

                // 判斷是否為頂層 ($level 為 0) 且 有輸入類型 (!empty($type))
                if ($level === 0 && !empty($type)) { 
                    // 這裡使用你要的齒輪 Icon，也可以改用 $item['icon']
                    $iconHtml = '<i class="fas fa-cog" aria-hidden="true"></i>';
                }
                // ----------------------

                $html .= '<li class="'.$class.'">';
                $html .= '<a class="nav-link" href="'.$urlInfo.'">';
                
                // 插入 Icon (如果是頂層才有，否則為空)
                $html .= $iconHtml; 
                
                $html .= '<span>' . $title . '設定</span>'; 
                $html .= '</a></li>';
            }
        }
    }

    return [$html, $hasActiveChild];
}

/**
 * 取得通用的階層式選單選項（用於父層選擇下拉選單）
 * @param PDO $conn 資料庫連線
 * @param string $tableName 資料表名稱
 * @param string $primaryKey 主鍵欄位
 * @param string $titleCol 標題欄位
 * @param string $parentCol 父層 ID 欄位
 * @param string $additionalWhere 額外的過濾條件 (e.g. "AND taxonomy_type_id = 1")
 * @return array 選單選項陣列
 */
function getHierarchicalOptions($conn, $tableName, $primaryKey, $titleCol, $parentCol, $additionalWhere = '')
{
    $options = [];
    // 【新增】語系處理
    $currentLang = $_GET['language'] ?? $_SESSION['editing_lang'] ?? DEFAULT_LANG_SLUG;
    
    // 檢查資料表是否有 lang 欄位
    $langFilter = "";
    if ($tableName !== 'languages') {
        $checkLangColQuery = "SHOW COLUMNS FROM {$tableName} LIKE 'lang'";
        $langColStmt = $conn->prepare($checkLangColQuery);
        $langColStmt->execute();
        if ($langColStmt->rowCount() > 0) {
            $langFilter = " AND lang = " . $conn->quote($currentLang);
        }
    }

    // 【新增】檢查資料表是否有軟刪除欄位，並排除已刪除的資料
    $deleteFilter = "";
    $deleteTimeColumns = ['deleted_at', 'd_delete_time']; // 可能的軟刪除欄位名稱
    foreach ($deleteTimeColumns as $deleteCol) {
        try {
            $checkDeleteColQuery = "SHOW COLUMNS FROM {$tableName} LIKE '{$deleteCol}'";
            $deleteColStmt = $conn->prepare($checkDeleteColQuery);
            $deleteColStmt->execute();
            if ($deleteColStmt->rowCount() > 0) {
                // 【修正】取得欄位類型，判斷是否為 TIMESTAMP 或 DATETIME
                $colInfo = $deleteColStmt->fetch(PDO::FETCH_ASSOC);
                $colType = strtoupper($colInfo['Type'] ?? '');

                // 如果是 TIMESTAMP 或 DATETIME 類型，只檢查 IS NULL
                if (strpos($colType, 'TIMESTAMP') !== false || strpos($colType, 'DATETIME') !== false) {
                    $deleteFilter = " AND {$deleteCol} IS NULL";
                } else {
                    // 其他類型（如 VARCHAR）可以檢查空字串
                    $deleteFilter = " AND ({$deleteCol} IS NULL OR {$deleteCol} = '')";
                }
                error_log("getHierarchicalOptions: Found delete column '{$deleteCol}' (type: {$colType}) in table '{$tableName}'");
                break; // 找到第一個就停止
            }
        } catch (Exception $e) {
            // 忽略錯誤，繼續檢查下一個欄位
            error_log("Check delete column '{$deleteCol}' error: " . $e->getMessage());
        }
    }

    try {
        // 【新增】檢查是否有 sort_order 欄位
        $checkSortColQuery = "SHOW COLUMNS FROM {$tableName} LIKE 'sort_order'";
        $sortColStmt = $conn->prepare($checkSortColQuery);
        $sortColStmt->execute();
        $hasSortOrder = ($sortColStmt->rowCount() > 0);

        // 根據是否有 sort_order 欄位來決定 ORDER BY
        $orderByClause = $hasSortOrder ? "sort_order ASC, {$primaryKey} ASC" : "{$primaryKey} ASC";

        $query = "SELECT {$primaryKey}, {$titleCol}, {$parentCol}
                  FROM {$tableName}
                  WHERE 1=1 {$langFilter} {$deleteFilter} {$additionalWhere}
                  ORDER BY {$orderByClause}";

        error_log("getHierarchicalOptions SQL: {$query}");

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("getHierarchicalOptions: Found " . count($items) . " items in table '{$tableName}'");
        
        // 建立原始樹狀結構 (這裡需要一個能自訂欄位的 tree builder)
        $tree = buildCustomTree($items, $primaryKey, $parentCol);
        addHierarchicalOptionsRecursive($tree, $options, $primaryKey, $titleCol, 0);
        
    } catch (Exception $e) {
        error_log("取得階層選項失敗 ({$tableName}): " . $e->getMessage());
    }
    
    return $options;
}

/**
 * 自訂欄位的樹狀結構建立器
 */
function buildCustomTree(array $elements, $primaryKey, $parentCol, $parentId = 0)
{
    $branch = [];
    foreach ($elements as $element) {
        if ($element[$parentCol] == $parentId) {
            $children = buildCustomTree($elements, $primaryKey, $parentCol, $element[$primaryKey]);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

/**
 * 遞迴添加階層選項（帶縮排）
 */
function addHierarchicalOptionsRecursive($items, &$options, $primaryKey, $titleCol, $level = 0)
{
    $indent = str_repeat('　', $level); 
    foreach ($items as $item) {
        $options[] = [
            'id' => $item[$primaryKey],
            'name' => $indent . $item[$titleCol]
        ];
        if (isset($item['children'])) {
            addHierarchicalOptionsRecursive($item['children'], $options, $primaryKey, $titleCol, $level + 1);
        }
    }
}


// ==========================================================
// 以下是你原本保留的輔助函數，完全沒動
// ==========================================================

/**
 * 建立樹狀結構
 * @param array $elements 所有選單項目
 * @param int $parentId 父選單ID
 * @return array 樹狀結構陣列
 */
function buildMenuTree(array $elements, $parentId = 0)
{
    $branch = [];
    foreach ($elements as $element) {
        if ($element['menu_parent_id'] == $parentId) {
            $children = buildMenuTree($elements, $element['menu_id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

function buildFrontendMenusTree(array $elements, $parentId = 0)
{
    $branch = [];
    foreach ($elements as $element) {
        if ($element['m_parent_id'] == $parentId) {
            $children = buildFrontendMenusTree($elements, $element['m_id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

/**
 * 取得選單選項（用於父選單下拉選單）
 * @param PDO $conn 資料庫連線
 * @return array 選單選項陣列
 */
function getCmsMenuOptions($conn)
{
    try {
        $query = "SELECT menu_id, menu_title, menu_parent_id FROM cms_menus WHERE menu_active = 1 ORDER BY menu_sort ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tree = buildMenuTree($menus);
        $options = [[
            'id' => 0,
            'name' => '頂層'
        ]];
        addMenuOptionsRecursive($tree, $options, 0);
        
        return $options;
    } catch (Exception $e) {
        error_log("取得選單選項失敗: " . $e->getMessage());
        return [['id' => 0, 'name' => '頂層']];
    }
}

function getFrontendMenusOptions($conn)
{
    try {
        $query = "SELECT m_id, m_title_ch, m_parent_id FROM menus_set WHERE m_active = 1 ORDER BY m_sort ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tree = buildFrontendMenusTree($menus);
        $options = [[
            'id' => 0,
            'name' => '頂層'
        ]];
        addFrontendMenusOptionsRecursive($tree, $options, 0);
        
        return $options;
    } catch (Exception $e) {
        error_log("取得選單選項失敗: " . $e->getMessage());
        return [['id' => 0, 'name' => '頂層']];
    }
}

/**
 * 遞迴添加選單選項（帶縮排）
 */
function addMenuOptionsRecursive($items, &$options, $level = 0)
{
    $indent = str_repeat('　', $level); 
    foreach ($items as $item) {
        $options[] = [
            'id' => $item['menu_id'],
            'name' => $indent . $item['menu_title']
        ];
        if (isset($item['children'])) {
            addMenuOptionsRecursive($item['children'], $options, $level + 1);
        }
    }
}

function addFrontendMenusOptionsRecursive($items, &$options, $level = 0)
{
    $indent = str_repeat('　', $level); 
    foreach ($items as $item) {
        $options[] = [
            'id' => $item['m_id'],
            'name' => $indent . $item['m_title_ch']
        ];
        if (isset($item['children'])) {
            addFrontendMenusOptionsRecursive($item['children'], $options, $level + 1);
        }
    }
}

/**
 * 取得階層式麵包屑路徑
 * @param PDO $conn 資料庫連線
 * @param string $module 模組名稱 (menu_type)
 * @return array 麵包屑陣列
 */
function getBreadcrumbPath($conn, $module) {
    if (empty($module)) return [];
    
    try {
        // 1. 找出當前模組對應的選單項目
        $query = "SELECT menu_id, menu_title, menu_parent_id FROM cms_menus WHERE menu_type = :module LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([':module' => $module]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) return [];
        
        $path = [];
        $path[] = ['title' => $current['menu_title'], 'link' => '']; // 當前節點暫不給連結或由呼叫端處理
        
        // 2. 向上追溯父層
        $parentId = $current['menu_parent_id'];
        while ($parentId > 0) {
            $pQuery = "SELECT menu_id, menu_title, menu_parent_id, menu_link FROM cms_menus WHERE menu_id = :pid LIMIT 1";
            $pStmt = $conn->prepare($pQuery);
            $pStmt->execute([':pid' => $parentId]);
            $parent = $pStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($parent) {
                // 放到陣列最前端
                array_unshift($path, [
                    'title' => $parent['menu_title'],
                    'link' => (!empty($parent['menu_link']) && $parent['menu_link'] !== 'javascript:void(0);') ? $parent['menu_link'] : ''
                ]);
                $parentId = $parent['menu_parent_id'];
            } else {
                break;
            }
        }
        
        return $path;
        
    } catch (Exception $e) {
        error_log("取得麵包屑路徑失敗: " . $e->getMessage());
        return [];
    }
}

/**
 * 渲染麵包屑 HTML
 */
function renderBreadcrumbsHtml($conn, $module, $currentPageTitle = '') {
    $path = getBreadcrumbPath($conn, $module);
    
    // 【優化】如果當前頁面是「列表」，將其合併到最後一個路徑節點，減少層級感
    // 範例：從 "關於舊振南 / 歷史沿革 / 列表" 變為 "關於舊振南 / 歷史沿革列表"
    if ($currentPageTitle === '列表' && !empty($path)) {
        $path[count($path) - 1]['title'] .= '列表';
        $currentPageTitle = ''; // 清空分頁標題，避免多出一層 <li>
    }
    
    $html = '<li><a href="' . PORTAL_AUTH_URL . 'dashboard"><i class="bx bx-home-alt"></i></a></li>';
    
    foreach ($path as $item) {
        if (!empty($item['link'])) {
            $html .= '<li><a href="' . $item['link'] . '"><span>' . $item['title'] . '</span></a></li>';
        } else {
            $html .= '<li><span>' . $item['title'] . '</span></li>';
        }
    }
    
    if (!empty($currentPageTitle)) {
        $html .= '<li><span>' . $currentPageTitle . '</span></li>';
    }
    
    return $html;
}
?>
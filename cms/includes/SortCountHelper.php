<?php

class SortCountHelper
{
    /**
     * 計算排序筆數
     * 
     * @param PDO $conn 資料庫連線
     * @param array $config 模組設定與上下文參數
     * @return int 筆數
     */
    public static function getCount($conn, $context)
    {
        // 解構上下文參數
        $tableName = $context['tableName'];
        $col_id = $context['col_id'] ?? 'd_id'; // 預設主鍵
        $totalRows = $context['totalRows'] ?? 0;
        $row = $context['row'] ?? [];
        
        $menuKey = $context['menuKey'] ?? null;
        $menuValue = $context['menuValue'] ?? null;
        
        $col_top = $context['col_top'] ?? null;
        
        $hasCategory = $context['hasCategory'] ?? false;
        $selectedCategory = $context['selectedCategory'] ?? null;
        $categoryField = $context['categoryField'] ?? null;
        $configUseTaxonomyMapSort = $context['useTaxonomyMapSort'] ?? true;
        
        $hasHierarchicalNav = $context['hasHierarchicalNav'] ?? false;
        $parentIdField = $context['parentIdField'] ?? null;
        
        // 額外欄位檢查
        $col_delete_time = $context['col_delete_time'] ?? 'd_delete_time';
        $hasDeleteTime = $context['hasDeleteTime'] ?? false;
        
        // 預設筆數
        $sortRowCount = $totalRows;

        // 判斷是否需要重新計算 (語系、Menu過濾等)
        $needRecalculate = false;
        $where = ["1=1"];
        $params = [];

        // DEBUG: 記錄初始狀態
        error_log("=== SortCountHelper START ===");
        error_log("Table: {$tableName}, Initial totalRows: {$totalRows}");
        error_log("hasDeleteTime: " . ($hasDeleteTime ? 'true' : 'false') . ", col_delete_time: {$col_delete_time}");

        // 1. 語系過濾（必須永遠執行，不論是否需要重新計算）
        if (isset($row['lang'])) {
            $where[] = "lang = :lang";
            $params[':lang'] = $row['lang'];
            $needRecalculate = true; // 【修正】需要重新計算
        }

        // 2. Menu (d_class1 或 taxonomy_type_id) 過濾（必須永遠執行）
        if ($menuKey) {
            // 優先使用設定檔的 menuValue，如果沒有則從當前資料行讀取
            $filterValue = $menuValue !== null ? $menuValue : ($row[$menuKey] ?? null);
            if ($filterValue !== null) {
                $where[] = "{$menuKey} = :menuValue";
                $params[':menuValue'] = $filterValue;
                $needRecalculate = true; // 【修正】需要重新計算
            }
            // DEBUG
            error_log("SortCountHelper: menuKey=$menuKey, menuValue=" . var_export($menuValue, true) . ", row[$menuKey]=" . var_export($row[$menuKey] ?? 'NOT_SET', true) . ", filterValue=" . var_export($filterValue, true));
        }

        // 3. 判斷排序模式 (核心邏輯)
        // 檢查 Map Table 是否存在 (這裡為了效能，假設 context 傳入檢查結果，或直接檢查)
        // 為了簡化，我們依賴 useTaxonomyMapSort 和 categoryId
        
        $mode = 'GLOBAL'; // 預設全域
        
        $isMapSort = ($hasCategory && $selectedCategory > 0 && $configUseTaxonomyMapSort && ($context['hasMapTable'] ?? false));
        
        if ($isMapSort) {
            $mode = 'MAP_SORT';
        } elseif ($hasCategory && $selectedCategory) {
            $mode = 'CATEGORY_FILTER';
        } elseif ($hasHierarchicalNav && $parentIdField && array_key_exists($parentIdField, $row)) {
            $mode = 'HIERARCHY';
        }

        // 根據模式構建查詢
        switch ($mode) {
            case 'MAP_SORT':
                // 【Map模式】
                // 1. 符合分類 (t_id) - 若沒選分類則為 0
                // 2. 排除該分類下置頂 (d_top=1)
                $map_cat_id = !empty($selectedCategory) ? $selectedCategory : 0;
                $where[] = "EXISTS (
                    SELECT 1 FROM data_taxonomy_map 
                    WHERE d_id = {$tableName}.{$col_id} 
                    AND t_id = :cat_id
                    AND (d_top = 0 OR d_top IS NULL)
                )";
                $params[':cat_id'] = $map_cat_id;
                $needRecalculate = true;
                break;

            case 'CATEGORY_FILTER':
                // 【普通分類模式】
                // 1. 排除全域置頂
                if ($col_top !== null) {
                    $where[] = "({$col_top} = 0 OR {$col_top} IS NULL)";
                }
                // 2. 欄位過濾
                $where[] = "FIND_IN_SET(:cat_id, {$categoryField})";
                $params[':cat_id'] = $selectedCategory;
                $needRecalculate = true;
                break;

            case 'HIERARCHY':
                // 【階層模式】
                // 1. 排除全域置頂
                if ($col_top !== null) {
                    $where[] = "({$col_top} = 0 OR {$col_top} IS NULL)";
                }
                // 2. 父層ID過濾 - 使用當前資料行的 parent_id 值來計算同層級項目數量
                // 這樣才能正確顯示該項目在其所屬層級中可以排序的位置數量
                $rowParentId = $row[$parentIdField] ?? 0;
                if ($rowParentId > 0) {
                    $where[] = "{$parentIdField} = :parent_id";
                    $params[':parent_id'] = $rowParentId;
                } else {
                    // 頂層項目 (parent_id = 0 或 NULL)
                    $where[] = "({$parentIdField} = 0 OR {$parentIdField} IS NULL)";
                }
                
                // DEBUG
                error_log("SortCountHelper HIERARCHY: parentIdField=$parentIdField, rowParentId=$rowParentId");
                $needRecalculate = true;
                break;

            case 'GLOBAL':
            default:
                // 【全域模式】
                // 1. 排除全域置頂
                if ($col_top !== null) {
                    $where[] = "({$col_top} = 0 OR {$col_top} IS NULL)";
                    $needRecalculate = true; // 因為排除置頂，筆數會變，需重算
                }
                break;
        }

        // 【重要修正】軟刪除過濾應該永遠執行，不論是否需要重新計算
        // 因為排序選項必須排除垃圾桶的資料
        if ($hasDeleteTime) {
            $where[] = "{$col_delete_time} IS NULL";
            $needRecalculate = true; // 強制重新計算以排除垃圾桶資料
        }

        // 如果需要重新計算
        if ($needRecalculate) {
            $whereSql = implode(" AND ", $where);
            $sql = "SELECT COUNT(*) FROM {$tableName} WHERE {$whereSql}";

            try {
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $sortRowCount = $stmt->fetchColumn();
                // DEBUG
                error_log("SortCountHelper SQL: $sql | Params: " . json_encode($params) . " | Result: $sortRowCount");
            } catch (Exception $e) {
                // 發生錯誤時回傳預設值或 log，這裡暫時維持原狀回傳 totalRows (如果不準確至少不報錯)
                error_log("SortCountHelper ERROR: " . $e->getMessage());
                // return 0;
            }
        } else {
            error_log("SortCountHelper: No recalculation needed, using totalRows: {$totalRows}");
        }

        error_log("=== SortCountHelper END === Final sortRowCount: {$sortRowCount}");
        return $sortRowCount;
    }
}

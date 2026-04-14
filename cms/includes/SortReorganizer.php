<?php
/**
 * 排序重整輔助類
 * 使用快速排序演算法優化刪除後的排序重整
 */

class SortReorganizer
{
    /**
     * 快速排序演算法 - 根據排序欄位值排序
     *
     * @param array $items 要排序的項目陣列 [['id' => 1, 'sort' => 5], ...]
     * @param string $sortKey 排序欄位名稱 (預設 'sort')
     * @return array 排序後的陣列
     */
    private static function quickSort($items, $sortKey = 'sort')
    {
        // 基礎情況：如果陣列長度小於等於1，直接返回
        if (count($items) <= 1) {
            return $items;
        }

        // 選擇中間元素作為基準點 (pivot)
        $pivotIndex = floor(count($items) / 2);
        $pivot = $items[$pivotIndex];
        $pivotValue = $pivot[$sortKey] ?? 0;

        // 分割陣列
        $left = [];   // 小於基準點的元素
        $middle = []; // 等於基準點的元素
        $right = [];  // 大於基準點的元素

        foreach ($items as $index => $item) {
            $itemValue = $item[$sortKey] ?? 0;

            if ($itemValue < $pivotValue) {
                $left[] = $item;
            } elseif ($itemValue > $pivotValue) {
                $right[] = $item;
            } else {
                $middle[] = $item;
            }
        }

        // 遞迴排序左右兩側，並合併結果
        return array_merge(
            self::quickSort($left, $sortKey),
            $middle,
            self::quickSort($right, $sortKey)
        );
    }

    /**
     * 重新整理排序編號（刪除後使用）
     *
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param string $col_id 主鍵欄位名稱
     * @param string $col_sort 排序欄位名稱
     * @param array $conditions WHERE 條件陣列 ['field' => 'value', ...]
     * @param bool $isSoftDelete 是否為軟刪除
     * @param string|null $col_delete_time 刪除時間欄位名稱
     * @return int 更新的筆數
     */
    public static function reorganize(
        $conn,
        $tableName,
        $col_id,
        $col_sort,
        $conditions = [],
        $isSoftDelete = false,
        $col_delete_time = null,
        $parentIdField = null,
        $updateConditions = [],
        $joinSql = '',
        $customSelect = null
    ) {
        // 1. 檢查排序欄位是否存在
        $stmtCheckSort = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
        $stmtCheckSort->execute([$col_sort]);
        if (!$stmtCheckSort->fetch()) {
            return 0; // 沒有排序欄位，不需要處理
        }
        // 2. 建立查詢條件
        $whereConditions = [];
        $params = [];

        foreach ($conditions as $field => $value) {
            if ($value === null) {
                if (strpos($field, '(') !== false || strpos($field, ' ') !== false) {
                    $whereConditions[] = $field; // Raw SQL fragment
                } else {
                    $whereConditions[] = "{$field} IS NULL";
                }
            } elseif (($field === $parentIdField || strpos($field, 'parent_id') !== false || $field === 'd_top' || strpos($field, 'top') !== false) && ($value === 0 || $value === '0' || $value === '')) {
                // 【關鍵修正】層級獲置頂為 0 時，包含 NULL 以確保完整排序
                $whereConditions[] = "({$field} = 0 OR {$field} IS NULL)";
            } else {
                $cleanField = str_replace('`', '', $field);
                $placeholderName = "cond_" . preg_replace('/[^a-zA-Z0-9_]/', '', $cleanField);
                // 如果欄位包含 table.column 格式，分別加 backtick，避免產生 `table.column` 的不合法 SQL
                if (strpos($cleanField, '.') !== false) {
                    [$tbl, $col] = explode('.', $cleanField, 2);
                    $quotedField = "`{$tbl}`.`{$col}`";
                } else {
                    $quotedField = "`{$cleanField}`";
                }
                $whereConditions[] = "{$quotedField} = :{$placeholderName}";
                $params[":{$placeholderName}"] = $value;
            }
        }

        // 排除已刪除的資料
        if ($isSoftDelete && $col_delete_time) {
            $whereConditions[] = "{$col_delete_time} IS NULL";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // 3. 查詢所有需要重新排序的資料
        $selectCols = $customSelect ?: "`{$tableName}`.{$col_id}, `{$tableName}`.{$col_sort}";
        $query = "SELECT {$selectCols} FROM `{$tableName}` {$joinSql} {$whereClause} ORDER BY `{$tableName}`.{$col_sort} ASC, `{$tableName}`.{$col_id} ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return 0;
        }

        // 4. 使用快速排序演算法排序（雖然 SQL 已經排序，但這裡展示演算法使用）
        // 實際上 SQL ORDER BY 已經排序好了，這裡主要是為了展示快速排序的實作
        // 如果資料庫排序不可靠或需要自訂排序邏輯，可以使用這個方法
        $sortedItems = self::quickSort($items, $col_sort);

        // 5. 重新分配連續的排序編號（從 1 開始）
        $updateCount = 0;
        $newSortNum = 1;

        foreach ($sortedItems as $item) {
            $updateSet = ["`{$col_sort}` = :newSort"];
            $updateParams = [':newSort' => $newSortNum, ':id' => $item[$col_id]];
            
            $whereParts = ["`{$col_id}` = :id"];
            foreach ($updateConditions as $f => $v) {
                $cleanField = str_replace('`', '', $f);
                $placeholderName = "upd_" . preg_replace('/[^a-zA-Z0-9_]/', '', $cleanField);
                $whereParts[] = "{$f} = :{$placeholderName}";
                $updateParams[":{$placeholderName}"] = $v;
            }
            
            $updateSql = "UPDATE `{$tableName}` SET " . implode(', ', $updateSet) . " WHERE " . implode(' AND ', $whereParts);
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute($updateParams);
            $updateCount++;
            $newSortNum++;
        }

        return $updateCount;
    }

    /**
     * 批次重新整理多個群組的排序
     *
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param string $col_id 主鍵欄位名稱
     * @param string $col_sort 排序欄位名稱
     * @param string $groupField 群組欄位名稱 (例如 'd_class1', 'taxonomy_type_id')
     * @param array $groupValues 要處理的群組值陣列
     * @param bool $isSoftDelete 是否為軟刪除
     * @param string|null $col_delete_time 刪除時間欄位名稱
     * @return array 每個群組的更新筆數 ['groupValue' => count, ...]
     */
    public static function reorganizeGroups(
        $conn,
        $tableName,
        $col_id,
        $col_sort,
        $groupField,
        $groupValues,
        $isSoftDelete = false,
        $col_delete_time = null
    ) {
        $results = [];

        foreach ($groupValues as $groupValue) {
            $conditions = [$groupField => $groupValue];
            $count = self::reorganize(
                $conn,
                $tableName,
                $col_id,
                $col_sort,
                $conditions,
                $isSoftDelete,
                $col_delete_time,
                null
            );
            $results[$groupValue] = $count;
        }

        return $results;
    }

    /**
     * 自動偵測並重新整理所有群組的排序
     *
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param string $col_id 主鍵欄位名稱
     * @param string $col_sort 排序欄位名稱
     * @param string|null $groupField 群組欄位名稱 (如果為 null 則不分組)
     * @param bool $isSoftDelete 是否為軟刪除
     * @param string|null $col_delete_time 刪除時間欄位名稱
     * @param string|null $categoryField 分類欄位名稱（用於雙層分類，例如 data_set 的 d_class2）
     * @return int 總更新筆數
     */
    public static function reorganizeAll(
        $conn,
        $tableName,
        $col_id,
        $col_sort,
        $groupField = null,
        $isSoftDelete = false,
        $col_delete_time = null,
        $categoryField = null,
        $parentIdField = null,
        $menuValue = null,  // 【新增】限定處理的群組值
        $lang = null        // 【新增】語系過濾
    ) {
        // DEBUG: 記錄參數
        error_log("=== SortReorganizer::reorganizeAll START ===");
        error_log("Table: {$tableName}, Sort: {$col_sort}, Lang: " . ($lang ?? 'null'));
        error_log("GroupField: " . ($groupField ?? 'null') . ", MenuValue: " . ($menuValue ?? 'null'));
        error_log("CategoryField: " . ($categoryField ?? 'null') . ", ParentIdField: " . ($parentIdField ?? 'null'));
        error_log("IsSoftDelete: " . ($isSoftDelete ? 'true' : 'false') . ", DeleteTimeCol: " . ($col_delete_time ?? 'null'));

        // 【新增】檢查是否有 d_top 欄位
        $hasTopField = false;
        $col_top = 'd_top';
        try {
            $checkTopCol = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
            $checkTopCol->execute([$col_top]);
            if ($checkTopCol->fetch()) {
                $hasTopField = true;
                error_log("Table has d_top field, will exclude pinned items from reorganization");
            }
        } catch (Exception $e) {
            // 欄位不存在，忽略
        }

        if ($groupField || $parentIdField || $categoryField || $lang) {
            // 找出所有分組組合
            $fields = array_filter([$groupField, $categoryField, $parentIdField]);
            
            // 【新增】檢查並加入 t_level 分組 (使用者要求)
            try {
                $checkTLevel = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE 't_level'");
                $checkTLevel->execute();
                if ($checkTLevel->fetch()) {
                    $fields[] = 't_level';
                }
            } catch (Exception $e) {
                // 忽略錯誤
            }

            if ($lang) $fields[] = 'lang'; // 加入語系分組

            $fieldSql = implode(', ', $fields);

            $where = ["1=1"];
            if ($isSoftDelete && $col_delete_time) {
                $where[] = "{$col_delete_time} IS NULL";
            }

            // 【新增】排除置頂項目
            if ($hasTopField) {
                $where[] = "({$col_top} = 0 OR {$col_top} IS NULL)";
            }

            // 【新增】如果有 menuValue,只處理該群組的資料
            if ($groupField && $menuValue !== null) {
                $where[] = "{$groupField} = " . $conn->quote($menuValue);
            }

            // 【新增】如果有 lang, 只處理該語系的資料
            if ($lang) {
                $where[] = "lang = " . $conn->quote($lang);
            }

            $whereSql = implode(' AND ', $where);

            $groupQuery = "SELECT DISTINCT {$fieldSql} FROM `{$tableName}` WHERE {$whereSql}";
            $groups = $conn->query($groupQuery)->fetchAll(PDO::FETCH_ASSOC);

            $totalCount = 0;
            $processedGroups = [];

            foreach ($groups as $group) {
                $conditions = [];
                $groupHash = "";
                foreach ($group as $f => $v) {
                    // 將 NULL 或 空字串 視為 0 (針對層級或分類)
                    if (($f === $parentIdField || $f === $groupField) && ($v === null || $v === '' || $v === '0')) {
                        $v = 0;
                    }
                    $conditions[$f] = $v;
                    $groupHash .= "{$f}:{$v}|";
                }

                // 【新增】加入排除置頂的條件
                if ($hasTopField) {
                    $conditions[$col_top] = 0;
                }

                // 避免重複處理相同的正規化群組
                if (in_array($groupHash, $processedGroups)) {
                    continue;
                }
                $processedGroups[] = $groupHash;

                $count = self::reorganize($conn, $tableName, $col_id, $col_sort, $conditions, $isSoftDelete, $col_delete_time, $parentIdField);
                $totalCount += $count;

                // DEBUG: 記錄每個群組的處理結果
                error_log("Reorganized group: " . json_encode($conditions) . " | Updated: {$count} rows");
            }

            error_log("=== SortReorganizer::reorganizeAll END === Total updated: {$totalCount} rows");
            return $totalCount;
        } else {
            // 沒有群組：全部一起處理
            $conditions = [];

            // 【新增】排除置頂項目
            if ($hasTopField) {
                $conditions[$col_top] = 0;
            }

            return self::reorganize(
                $conn,
                $tableName,
                $col_id,
                $col_sort,
                $conditions,
                $isSoftDelete,
                $col_delete_time,
                $parentIdField
            );
        }
    }

    /**
     * 雙層分類排序重整（例如 data_set 的 d_class1 + d_class2）
     *
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param string $col_id 主鍵欄位名稱
     * @param string $col_sort 排序欄位名稱
     * @param string $groupField 第一層群組欄位（例如 d_class1）
     * @param string $categoryField 第二層分類欄位（例如 d_class2）
     * @param bool $isSoftDelete 是否為軟刪除
     * @param string|null $col_delete_time 刪除時間欄位名稱
     * @return int 總更新筆數
     */
    private static function reorganizeWithDualGrouping(
        $conn,
        $tableName,
        $col_id,
        $col_sort,
        $groupField,
        $categoryField,
        $isSoftDelete,
        $col_delete_time
    ) {
        $totalCount = 0;

        // 1. 找出所有的群組值（第一層）
        $groupQuery = "SELECT DISTINCT {$groupField} FROM `{$tableName}`";
        $groupConditions = [];

        if ($isSoftDelete && $col_delete_time) {
            $groupConditions[] = "{$col_delete_time} IS NULL";
        }

        if (!empty($groupConditions)) {
            $groupQuery .= " WHERE " . implode(' AND ', $groupConditions);
        }

        $groupStmt = $conn->prepare($groupQuery);
        $groupStmt->execute();
        $groups = $groupStmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. 針對每個群組，找出所有的分類值（第二層）
        foreach ($groups as $groupValue) {
            $categoryQuery = "SELECT DISTINCT {$categoryField} FROM `{$tableName}` WHERE {$groupField} = :groupValue";
            $categoryConditions = [];

            if ($isSoftDelete && $col_delete_time) {
                $categoryConditions[] = "{$col_delete_time} IS NULL";
            }

            // 排除空值分類
            $categoryConditions[] = "{$categoryField} IS NOT NULL";
            $categoryConditions[] = "{$categoryField} != ''";
            $categoryConditions[] = "{$categoryField} != '0'";

            if (!empty($categoryConditions)) {
                $categoryQuery .= " AND " . implode(' AND ', $categoryConditions);
            }

            $categoryStmt = $conn->prepare($categoryQuery);
            $categoryStmt->execute([':groupValue' => $groupValue]);
            $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

            // 3. 針對每個群組+分類組合重新排序
            foreach ($categories as $categoryValue) {
                $conditions = [
                    $groupField => $groupValue,
                    $categoryField => $categoryValue
                ];

                $count = self::reorganize(
                    $conn,
                    $tableName,
                    $col_id,
                    $col_sort,
                    $conditions,
                    $isSoftDelete,
                    $col_delete_time
                );

                $totalCount += $count;
            }

            // 4. 處理該群組下沒有分類的資料
            $noCategoryQuery = "SELECT {$col_id}, {$col_sort} FROM `{$tableName}`
                                WHERE {$groupField} = :groupValue
                                AND ({$categoryField} IS NULL OR {$categoryField} = '' OR {$categoryField} = '0')";

            if ($isSoftDelete && $col_delete_time) {
                $noCategoryQuery .= " AND {$col_delete_time} IS NULL";
            }

            $noCategoryQuery .= " ORDER BY {$col_sort} ASC, {$col_id} ASC";

            $noCategoryStmt = $conn->prepare($noCategoryQuery);
            $noCategoryStmt->execute([':groupValue' => $groupValue]);
            $noCategoryItems = $noCategoryStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($noCategoryItems)) {
                $newSortNum = 1;
                foreach ($noCategoryItems as $item) {
                    $updateSql = "UPDATE `{$tableName}` SET `{$col_sort}` = :newSort WHERE `{$col_id}` = :id";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->execute([
                        ':newSort' => $newSortNum,
                        ':id' => $item[$col_id]
                    ]);
                    $newSortNum++;
                    $totalCount++;
                }
            }
        }

        return $totalCount;
    }
}

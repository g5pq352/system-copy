<?php
/**
 * 統一排序管理器
 * 處理所有與排序相關的操作，包括主表 d_sort 和 data_taxonomy_map 的 sort_num
 */

class UnifiedSortManager
{
    /**
     * 在資料變更後統一更新排序
     *
     * @param PDO $conn 資料庫連線
     * @param array $config 配置參數
     * @param int $dataId 資料 ID
     * @param array $context 上下文資訊
     * @return bool 是否成功
     */
    public static function updateAfterDataChange($conn, $config, $dataId = null, $context = [])
    {
        // 預先載入必要的輔助函式 (在此載入以確保 Step 3 的 function_exists 判斷正確)
        require_once __DIR__ . '/taxonomyMapHelper.php';
        require_once __DIR__ . '/SortReorganizer.php';

        try {
            $tableName = $config['tableName'];
            $primaryKey = $config['primaryKey'];
            $cols = $config['cols'] ?? [];
            $col_sort = $cols['sort'] ?? 'd_sort';
            $col_delete_time = $cols['delete_time'] ?? 'd_delete_time';
            $parentIdField = $cols['parent_id'] ?? null;
            $menuKey = $config['menuKey'] ?? null;
            $menuValue = $config['menuValue'] ?? null;
            $categoryField = $config['listPage']['categoryField'] ?? null;
            $useTaxonomyMapSort = $config['listPage']['useTaxonomyMapSort'] ?? false;

            // 【修正】如果 menuValue 為 null 但有 menuKey (常見於分類模組)，嘗試從資料中抓取目前群組值
            if ($menuKey && $menuValue === null && $dataId) {
                try {
                    $mValStmt = $conn->prepare("SELECT `{$menuKey}` FROM `{$tableName}` WHERE `{$primaryKey}` = :id");
                    $mValStmt->execute([':id' => $dataId]);
                    $menuValue = $mValStmt->fetchColumn();
                } catch (Exception $e) {}
            }

            // 取得語系資訊
            $lang = $context['lang'] ?? null;
            if (!$lang && $dataId) {
                try {
                    $stmt = $conn->prepare("SELECT lang FROM {$tableName} WHERE {$primaryKey} = :id");
                    $stmt->execute([':id' => $dataId]);
                    $lang = $stmt->fetchColumn();
                } catch (Exception $e) {
                    // 表格可能沒有 lang 欄位
                }
            }

            // 檢查是否有軟刪除
            $isSoftDelete = false;
            if ($col_delete_time) {
                try {
                    $check = $conn->prepare("SHOW COLUMNS FROM {$tableName} LIKE '{$col_delete_time}'");
                    $check->execute();
                    $isSoftDelete = ($check->rowCount() > 0);
                } catch (Exception $e) {
                    // 忽略錯誤
                }
            }

            // Step 1: 檢測並修復主表 d_sort 的重複數字
            self::detectAndFixDuplicates($conn, $tableName, $primaryKey, $col_sort, [
                'menuKey' => $menuKey,
                'menuValue' => $menuValue,
                'lang' => $lang,
                'isSoftDelete' => $isSoftDelete,
                'col_delete_time' => $col_delete_time,
                'parentIdField' => $parentIdField
            ]);

            // Step 2: 重新整理主表 d_sort (All 視圖)
            if (!empty($col_sort)) {
                SortReorganizer::reorganizeAll(
                    $conn,
                    $tableName,
                    $primaryKey,
                    $col_sort,
                    $menuKey,
                    $isSoftDelete,
                    $col_delete_time,
                    null, // categoryField
                    $parentIdField,
                    $menuValue,
                    $lang
                );
            }

            // Step 3: 如果使用 taxonomy map，更新分類排序
            if ($useTaxonomyMapSort && function_exists('hasTaxonomyMapTable') && hasTaxonomyMapTable($conn)) {
                // 取得受影響的分類
                $affectedCategories = [];

                if ($dataId) {
                    // 單筆資料：取得該資料的所有分類
                    $mappings = getTaxonomyMapWithLevels($conn, $dataId);
                    foreach ($mappings as $m) {
                        $key = $m['t_id'] . '_' . ($m['map_level'] ?? 1);
                        if (!isset($affectedCategories[$key])) {
                            $affectedCategories[$key] = $m;
                        }
                    }
                } else {
                    // 批次操作或全域操作：取得所有分類
                    $conditions = [];
                    if ($menuKey && $menuValue !== null) {
                        $conditions[] = "ds.{$menuKey} = " . $conn->quote($menuValue);
                    }
                    if ($lang) {
                        $conditions[] = "ds.lang = " . $conn->quote($lang);
                    }
                    if ($isSoftDelete && $col_delete_time) {
                        $conditions[] = "(ds.{$col_delete_time} IS NULL OR ds.{$col_delete_time} = '' OR ds.{$col_delete_time} = '0000-00-00 00:00:00')";
                    }

                    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

                    $sql = "SELECT DISTINCT dtm.t_id, dtm.map_level
                            FROM data_taxonomy_map dtm
                            INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$primaryKey}
                            {$whereClause}";

                    $stmt = $conn->query($sql);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $key = $row['t_id'] . '_' . ($row['map_level'] ?? 1);
                        if (!isset($affectedCategories[$key])) {
                            $affectedCategories[$key] = $row;
                        }
                    }
                }

                // 檢測並修復每個分類的重複數字
                foreach ($affectedCategories as $map) {
                    $taxId = intval($map['t_id']);
                    $mapLevel = intval($map['map_level'] ?? 1);

                    self::detectAndFixMapDuplicates($conn, $taxId, $mapLevel, [
                        'menuKey' => $menuKey,
                        'menuValue' => $menuValue,
                        'lang' => $lang,
                        'tableName' => $tableName
                    ]);
                }

                // 重新整理每個分類的排序
                foreach ($affectedCategories as $map) {
                    reorderTaxonomyMap($conn, intval($map['t_id']), intval($map['map_level'] ?? 1), [
                        $menuKey => $menuValue,
                        'lang' => $lang
                    ], $tableName);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("UnifiedSortManager::updateAfterDataChange error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 檢測並修復主表中重複的 sort 數字
     *
     * @param PDO $conn 資料庫連線
     * @param string $tableName 表名
     * @param string $primaryKey 主鍵欄位
     * @param string $col_sort 排序欄位
     * @param array $context 上下文資訊
     * @return int 修復的筆數
     */
    public static function detectAndFixDuplicates($conn, $tableName, $primaryKey, $col_sort, $context = [])
    {
        try {
            // 檢查排序欄位是否存在
            $stmtCheck = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
            $stmtCheck->execute([$col_sort]);
            if (!$stmtCheck->fetch()) {
                return 0; // 沒有排序欄位
            }

            // 建立查詢條件
            $whereConditions = [];
            $params = [];

            if (!empty($context['menuKey']) && $context['menuValue'] !== null) {
                $whereConditions[] = "`{$context['menuKey']}` = :menuValue";
                $params[':menuValue'] = $context['menuValue'];
            }

            if (!empty($context['lang'])) {
                $whereConditions[] = "lang = :lang";
                $params[':lang'] = $context['lang'];
            }

            $groupByFields = [$col_sort];
            
            // 【修正】如果沒有指定 menuValue，則必須將 menuKey 納入分組，避免跨類型干擾 (如不同 taxonomy_type_id)
            if (!empty($context['menuKey']) && $context['menuValue'] === null) {
                $groupByFields[] = "`{$context['menuKey']}`";
            }

            if (!empty($context['parentIdField'])) {
                $groupByFields[] = "`{$context['parentIdField']}`";
            }

            // 【新增】檢查並加入 t_level 分組 (使用者要求)
            try {
                $checkTLevel = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE 't_level'");
                $checkTLevel->execute();
                if ($checkTLevel->fetch()) {
                    $groupByFields[] = 't_level';
                }
            } catch (Exception $e) {}

            $groupByClause = implode(', ', $groupByFields);
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // 查找重複的 sort 數字
            $sql = "SELECT {$groupByClause}, COUNT(*) as cnt
                    FROM {$tableName}
                    {$whereClause}
                    GROUP BY {$groupByClause}
                    HAVING cnt > 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($duplicates)) {
                return 0; // 沒有重複
            }

            // 有重複，觸發完整重排
            error_log("UnifiedSortManager: 偵測到 {$tableName} 有重複的 sort 數字，執行重排");

            require_once __DIR__ . '/SortReorganizer.php';
            return SortReorganizer::reorganizeAll(
                $conn,
                $tableName,
                $primaryKey,
                $col_sort,
                $context['menuKey'] ?? null,
                $context['isSoftDelete'] ?? false,
                $context['col_delete_time'] ?? null,
                null,
                $context['parentIdField'] ?? null,
                $context['menuValue'] ?? null,
                $context['lang'] ?? null
            );
        } catch (Exception $e) {
            error_log("UnifiedSortManager::detectAndFixDuplicates error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 檢測並修復 data_taxonomy_map 中重複的 sort_num
     *
     * @param PDO $conn 資料庫連線
     * @param int $taxonomyId 分類 ID
     * @param int $mapLevel 層級
     * @param array $context 上下文資訊
     * @return int 修復的筆數
     */
    public static function detectAndFixMapDuplicates($conn, $taxonomyId, $mapLevel, $context = [])
    {
        try {
            $tableName = $context['tableName'] ?? 'data_set';
            $menuKey = $context['menuKey'] ?? null;
            $menuValue = $context['menuValue'] ?? null;
            $lang = $context['lang'] ?? null;

            // 建立查詢條件
            $whereConditions = [
                'dtm.t_id = :t_id',
                'dtm.map_level = :map_level',
                '(dtm.d_top = 0 OR dtm.d_top IS NULL)',
                "(ds.d_delete_time IS NULL OR ds.d_delete_time = '' OR ds.d_delete_time = '0000-00-00 00:00:00')"
            ];
            $params = [':t_id' => $taxonomyId, ':map_level' => $mapLevel];

            if ($menuKey && $menuValue !== null) {
                $whereConditions[] = "ds.{$menuKey} = :menuValue";
                $params[':menuValue'] = $menuValue;
            }

            if ($lang) {
                $whereConditions[] = "ds.lang = :lang";
                $params[':lang'] = $lang;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // 查找重複的 sort_num
            $sql = "SELECT dtm.sort_num, COUNT(*) as cnt
                    FROM data_taxonomy_map dtm
                    INNER JOIN {$tableName} ds ON dtm.d_id = ds.d_id
                    WHERE {$whereClause}
                    GROUP BY dtm.sort_num
                    HAVING cnt > 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($duplicates)) {
                return 0; // 沒有重複
            }

            // 有重複，觸發重排
            error_log("UnifiedSortManager: 偵測到分類 {$taxonomyId} (level {$mapLevel}) 有重複的 sort_num，執行重排");

            require_once __DIR__ . '/taxonomyMapHelper.php';
            return reorderTaxonomyMap($conn, $taxonomyId, $mapLevel, [
                $menuKey => $menuValue,
                'lang' => $lang
            ], $tableName) ? 1 : 0;
        } catch (Exception $e) {
            error_log("UnifiedSortManager::detectAndFixMapDuplicates error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 手動拖拉排序處理
     *
     * @param PDO $conn 資料庫連線
     * @param array $config 模組配置
     * @param int $itemId 項目 ID
     * @param int $newSort 新排序值
     * @param int $categoryId 分類 ID (0 表示在全部視圖)
     * @return bool 是否成功
     */
    public static function handleManualSort($conn, $config, $itemId, $newSort, $categoryId = 0)
    {
        try {
            $tableName = $config['tableName'];
            $primaryKey = $config['primaryKey'];
            $cols = $config['cols'] ?? [];
            $col_sort = $cols['sort'] ?? 'd_sort';
            $useTaxonomyMapSort = $config['listPage']['useTaxonomyMapSort'] ?? false;

            // 取得項目資訊
            $stmt = $conn->prepare("SELECT * FROM {$tableName} WHERE {$primaryKey} = :id");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new Exception('找不到資料');
            }

            $conn->beginTransaction();

            // 判斷是在「全部」還是「分類」下排序
            if ($categoryId > 0 && $useTaxonomyMapSort && function_exists('hasTaxonomyMapTable') && hasTaxonomyMapTable($conn)) {
                // 在分類下排序：更新 data_taxonomy_map 的 sort_num
                $updateSql = "UPDATE data_taxonomy_map
                              SET sort_num = :new_sort
                              WHERE d_id = :id AND t_id = :tid";
                $conn->prepare($updateSql)->execute([
                    ':new_sort' => $newSort,
                    ':id' => $itemId,
                    ':tid' => $categoryId
                ]);

                // 重排該分類
                self::updateAfterDataChange($conn, $config, $itemId, [
                    'lang' => $item['lang'] ?? null,
                    'categoryId' => $categoryId
                ]);
            } else {
                // 在全部視圖下排序：更新主表 d_sort
                $updateSql = "UPDATE {$tableName}
                              SET {$col_sort} = :new_sort
                              WHERE {$primaryKey} = :id";
                $conn->prepare($updateSql)->execute([
                    ':new_sort' => $newSort,
                    ':id' => $itemId
                ]);

                // 重排全部
                self::updateAfterDataChange($conn, $config, $itemId, [
                    'lang' => $item['lang'] ?? null
                ]);
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("UnifiedSortManager::handleManualSort error: " . $e->getMessage());
            return false;
        }
    }
}

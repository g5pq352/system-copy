<?php
/**
 * Data Taxonomy Map Helper Functions
 * 管理產品與分類的多對多關係
 */

/**
 * 儲存產品的分類關聯
 *
 * @param PDO $conn 資料庫連線
 * @param int $dataId 產品 ID (d_id)
 * @param string|array $taxonomyIds 分類 ID（可以是逗號分隔的字串 "39,40" 或陣列 [39, 40]）
 * @param int $defaultSort 預設排序值（用於新增時的初始位置）
 * @return bool 是否成功
 */
function saveTaxonomyMap($conn, $dataId, $taxonomyIds, $context = []) {
    $defaultSort = is_numeric($context) ? $context : ($context['defaultSort'] ?? 0);
    try {
        // 1. 轉換為陣列並驗證
        if (is_string($taxonomyIds)) {
            $taxonomyIds = array_filter(array_map('trim', explode(',', $taxonomyIds)));
        } else if (is_numeric($taxonomyIds)) {
            // 如果是單一整數 ID
            $taxonomyIds = [$taxonomyIds];
        } else if (!is_array($taxonomyIds)) {
            $taxonomyIds = [];
        }

        if (empty($dataId)) {
            return false;
        }

        $mappedTaxonomies = [];

        // 取得所選分類的層級資訊
        if (!empty($taxonomyIds)) {
            $placeholders = implode(',', array_fill(0, count($taxonomyIds), '?'));
            $stmt = $conn->prepare("SELECT t_id, t_level FROM taxonomies WHERE t_id IN ($placeholders)");
            $stmt->execute(array_map('intval', $taxonomyIds));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $mappedTaxonomies[] = [
                    'id' => intval($row['t_id']),
                    'level' => intval($row['t_level'] ?? 1)
                ];
            }
        }

        // 2. 取得舊的分類列表和排序值（用於判斷哪些分類需要重新排序，並保留原有排序）
        $oldTaxonomiesStmt = $conn->prepare("SELECT t_id, sort_num FROM data_taxonomy_map WHERE d_id = :d_id");
        $oldTaxonomiesStmt->execute([':d_id' => $dataId]);
        $oldTaxonomies = [];
        $oldSortNums = [];
        while ($row = $oldTaxonomiesStmt->fetch(PDO::FETCH_ASSOC)) {
            $oldTaxonomies[] = $row['t_id'];
            $oldSortNums[$row['t_id']] = $row['sort_num'];
        }

        // 2.5. 判斷是否有分類變更（新增或移除分類）
        $newTaxIds = array_column($mappedTaxonomies, 'id');
        $removedCategories = array_diff($oldTaxonomies, $newTaxIds); // 被移除的分類
        $addedCategories = array_diff($newTaxIds, $oldTaxonomies);   // 新增的分類
        $hasChangedCategories = !empty($removedCategories) || !empty($addedCategories);

        // 3. 刪除舊的關聯
        $deleteStmt = $conn->prepare("DELETE FROM data_taxonomy_map WHERE d_id = :d_id");
        $deleteStmt->execute([':d_id' => $dataId]);

        // 4. 插入新的關聯
        $insertStmt = $conn->prepare("
            INSERT INTO data_taxonomy_map (d_id, t_id, map_level, sort_num)
            VALUES (:d_id, :t_id, :map_level, :sort_num)
        ");

        $tableName = $context['tableName'] ?? 'data_set';
        $menuKey = $context['menuKey'] ?? null;
        $menuValue = $context['menuValue'] ?? null;
        $lang = $context['lang'] ?? null;

        foreach ($mappedTaxonomies as $item) {
            $taxId = $item['id'];
            $sortNum = 9999;

            if (in_array($taxId, $addedCategories)) {
                // 【關鍵】新增的分類：將項目插入到該分類的第一位
                // 先將該分類下所有現有項目的排序往後移
                $shiftConditions = [
                    "dtm.t_id = :tid",
                    "(dtm.d_top = 0 OR dtm.d_top IS NULL)",
                    "dtm.d_id != :exclude_id"
                ];
                $shiftParams = [':tid' => $taxId, ':exclude_id' => $dataId];

                // 加上額外的過濾條件
                if ($menuKey && $menuValue !== null) {
                    $shiftConditions[] = "ds.{$menuKey} = :menuValue";
                    $shiftParams[':menuValue'] = $menuValue;
                }

                if ($lang) {
                    $shiftConditions[] = "ds.lang = :lang";
                    $shiftParams[':lang'] = $lang;
                }

                $whereClause = implode(' AND ', $shiftConditions);

                $shiftStmt = $conn->prepare("
                    UPDATE data_taxonomy_map dtm
                    INNER JOIN {$tableName} ds ON dtm.d_id = ds.d_id
                    SET dtm.sort_num = dtm.sort_num + 1
                    WHERE {$whereClause}
                ");

                $shiftStmt->execute($shiftParams);

                // 設定為第一位
                $sortNum = 1;
            } elseif (isset($oldSortNums[$taxId])) {
                // 保留原有分類的排序
                $sortNum = $oldSortNums[$taxId];
            } else {
                // 其他情況使用預設值
                $sortNum = $defaultSort > 0 ? $defaultSort : 9999;
            }

            $insertStmt->execute([
                ':d_id' => $dataId,
                ':t_id' => $taxId,
                ':map_level' => $item['level'],
                ':sort_num' => $sortNum
            ]);
        }

        // 5. 只重新整理真正有變動的分類的排序
        // 只有新增或移除分類時才需要重排，保留原有分類不重排
        $affectedTaxonomies = array_unique(array_merge($removedCategories, $addedCategories));

        // DEBUG: 記錄分類變更
        error_log("saveTaxonomyMap - dataId: {$dataId}");
        error_log("saveTaxonomyMap - oldTaxonomies: " . implode(',', $oldTaxonomies));
        error_log("saveTaxonomyMap - newTaxonomies: " . implode(',', $newTaxIds));
        error_log("saveTaxonomyMap - removedCategories: " . implode(',', $removedCategories));
        error_log("saveTaxonomyMap - addedCategories: " . implode(',', $addedCategories));
        error_log("saveTaxonomyMap - affectedTaxonomies: " . implode(',', $affectedTaxonomies));

        if (!empty($affectedTaxonomies)) {
            $extraConditions = [];
            if ($menuKey && $menuValue !== null) {
                $extraConditions[$menuKey] = $menuValue;
            }
            if ($lang) {
                $extraConditions['lang'] = $lang;
            }

            foreach ($affectedTaxonomies as $taxId) {
                // 針對該分類下的所有層級進行重排
                $levelsStmt = $conn->prepare("SELECT DISTINCT map_level FROM data_taxonomy_map WHERE t_id = :t_id");
                $levelsStmt->execute([':t_id' => $taxId]);
                $levels = $levelsStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($levels as $lv) {
                    reorderTaxonomyMap($conn, intval($taxId), intval($lv), $extraConditions, $tableName);
                }
            }
        }

        return true;
    } catch (PDOException $e) {
        error_log("saveTaxonomyMap error: " . $e->getMessage());
        return false;
    }
}

/**
 * 重新整理特定分類下所有產品的排序（使其連續：1, 2, 3...）
 * 
 * @param PDO $conn 資料庫連線
 * @param int $taxonomyId 分類 ID
 * @return bool 是否成功
 */
function reorderTaxonomyMap($conn, $taxonomyId, $mapLevel = null, $conditions = [], $mainTable = 'data_set') {
    try {
        // 1. 準備條件陣列 (使用資料表別名以避免歧義)
        $reorganizeConditions = [
            '`data_taxonomy_map`.t_id' => $taxonomyId,
            '`data_taxonomy_map`.d_top' => 0,
            "(`ds`.d_delete_time IS NULL OR `ds`.d_delete_time = '0000-00-00 00:00:00')" => null
        ];

        if ($mapLevel !== null) {
            $reorganizeConditions['`data_taxonomy_map`.map_level'] = $mapLevel;
        }

        foreach ($conditions as $field => $value) {
            // 如果欄位沒有別名且不是特殊的 SQL 片段，加上 ds.
            $aliasedField = (strpos($field, '.') === false && strpos($field, '(') === false) ? "`ds`.{$field}" : $field;
            $reorganizeConditions[$aliasedField] = $value;
        }

        require_once __DIR__ . '/SortReorganizer.php';

        return \SortReorganizer::reorganize(
            $conn,
            'data_taxonomy_map',
            'd_id',
            'sort_num',
            $reorganizeConditions,
            false,
            null,
            null,
            ['t_id' => $taxonomyId],
            "INNER JOIN `{$mainTable}` ds ON `data_taxonomy_map`.d_id = ds.d_id",
            "`data_taxonomy_map`.d_id, `data_taxonomy_map`.sort_num"
        );
    } catch (Exception $e) {
        error_log("reorderTaxonomyMap error: " . $e->getMessage());
        return false;
    }
}

/**
 * 取得產品的所有分類 ID
 * 
 * @param PDO $conn 資料庫連線
 * @param int $dataId 產品 ID
 * @return array 分類 ID 陣列
 */
/**
 * 取得產品的所有分類與層級關聯
 * 
 * @param PDO $conn 資料庫連線
 * @param int $dataId 產品 ID
 * @return array 關聯陣列 [ ['t_id'=>39, 'map_level'=>1], ... ]
 */
function getTaxonomyMapWithLevels($conn, $dataId) {
    try {
        $stmt = $conn->prepare("SELECT t_id, map_level FROM data_taxonomy_map WHERE d_id = :d_id");
        $stmt->execute([':d_id' => $dataId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getTaxonomyMapWithLevels error: " . $e->getMessage());
        return [];
    }
}

/**
 * 取得產品的分類 ID 陣列 (舊版相容用)
 * 
 * @param PDO $conn 資料庫連線
 * @param int $dataId 產品 ID
 * @return array 分類 ID 陣列
 */
function getTaxonomyMap($conn, $dataId) {
    $mappings = getTaxonomyMapWithLevels($conn, $dataId);
    return array_column($mappings, 't_id');
}

/**
 * 刪除產品的所有分類關聯
 * 
 * @param PDO $conn 資料庫連線
 * @param int $dataId 產品 ID
 * @return bool 是否成功
 */
function deleteTaxonomyMap($conn, $dataId) {
    try {
        $stmt = $conn->prepare("DELETE FROM data_taxonomy_map WHERE d_id = :d_id");
        $stmt->execute([':d_id' => $dataId]);
        return true;
    } catch (PDOException $e) {
        error_log("deleteTaxonomyMap error: " . $e->getMessage());
        return false;
    }
}

/**
 * 更新特定分類下的排序
 * 
 * @param PDO $conn 資料庫連線
 * @param int $dataId 產品 ID
 * @param int $taxonomyId 分類 ID
 * @param int $sortNum 新的排序值
 * @return bool 是否成功
 */
function updateTaxonomySortNum($conn, $dataId, $taxonomyId, $sortNum) {
    try {
        $stmt = $conn->prepare("
            UPDATE data_taxonomy_map 
            SET sort_num = :sort_num 
            WHERE d_id = :d_id AND t_id = :t_id
        ");
        
        $stmt->execute([
            ':d_id' => $dataId,
            ':t_id' => $taxonomyId,
            ':sort_num' => $sortNum
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("updateTaxonomySortNum error: " . $e->getMessage());
        return false;
    }
}

/**
 * 檢查 data_taxonomy_map 表是否存在
 * 
 * @param PDO $conn 資料庫連線
 * @return bool 表是否存在
 */
function hasTaxonomyMapTable($conn) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'data_taxonomy_map'");
        return $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

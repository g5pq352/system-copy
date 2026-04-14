<?php
/**
 * Form Process Element
 * 表單處理元件
 */

class FormProcessElement
{
    /**
     * 處理密碼加密（SHA256 + Salt）
     * @param string $password 原始密碼
     * @return array|null ['password' => 加密後的密碼, 'salt' => 鹽值]
     */
    public static function processPassword($password)
    {
        if (empty($password)) {
            return ['password' => '', 'salt' => ''];
        }
        
        // 恢復使用 SHA256 + Salt (使用者要求)
        $salt = substr(md5(uniqid(rand(), true)), 0, 32); // 產生隨機 Salt
        $hashedPassword = hash('sha256', $password . $salt);
        
        return [
            'password' => $hashedPassword,
            'salt' => $salt
        ];
    }
    
    /**
     * 收集表單欄位資料 (增強版)
     * @param array $pageItems 頁面項目配置 (例如 $moduleConfig['detailPage'] 或 $moduleConfig['settingPage'])
     * @param array $postData POST 資料
     * @param array $hiddenFields 額外隱藏欄位
     * @return array 收集的欄位資料
     */
    public static function collectFormFields($pageItems, $postData, $hiddenFields = [])
    {
        $fields = [];
        
        // 1. 收集表單定義的欄位
        foreach ($pageItems as $sheet) {
            // 如果是 $moduleConfig['settingPage']，它本身可能就是一個一維陣列的項目
            // 如果是 $moduleConfig['detailPage']，它是一個包含 sheet 的二維陣列
            $items = isset($sheet['items']) ? $sheet['items'] : [$sheet];
            
            foreach ($items as $item) {
                // 跳過不需要在此階段收集的欄位類型
                if (!isset($item['field']) || in_array($item['type'] ?? '', ['title', 'divider', 'image_upload', 'image', 'file_upload', 'dynamic_fields'])) {
                    continue;
                }
                
                $field = $item['field'];
                
                if (is_array($field)) {
                    // 【多欄位模式】如果 field 是陣列，通常對應連動下拉
                    // 連動下拉的原始值會存在 hidden 欄位，但多欄位模式下，
                    // 我們可能需要將每一層的值分別存入對應欄位。
                    // 這裡我們預期前端會送出 field_0, field_1... 或者我們從主欄位拆解
                    // 實際上，最簡單的做法是让 renderLinkedSelect 渲染多個同名欄位或帶索引的欄位
                    
                    // 遍歷欄位陣列，嘗試從 POST 中抓取
                    foreach ($field as $index => $subField) {
                        if (isset($postData[$subField])) {
                            $fields[$subField] = trim($postData[$subField]);
                        }
                    }
                } elseif (isset($postData[$field])) {
                    $value = $postData[$field];
                    
                    // 處理陣列值 (複選欄位)
                    if (is_array($value)) {
                        // 優先使用 sorted_ 開頭的隱藏欄位
                        $sortedKey = 'sorted_' . $field;
                        if (isset($postData[$sortedKey]) && !empty(trim($postData[$sortedKey]))) {
                            $fields[$field] = trim($postData[$sortedKey]);
                        } else {
                            $fields[$field] = implode(',', $value);
                        }
                    } else {
                        $fields[$field] = trim($value);
                    }
                } else {
                    // 【新增】處理複選欄位被清空的情況 (瀏覽器不會送出空的 checkbox/multiselect)
                    if (!empty($item['multiple']) || (isset($item['type']) && $item['type'] === 'checkbox')) {
                        $fields[$field] = '';
                    }
                }
            }
        }
        
        // 2. 處理所有 sorted_ 開頭的隱藏欄位 (覆蓋原始欄位)
        foreach ($postData as $key => $value) {
            if (strpos($key, 'sorted_') === 0 && !empty(trim($value))) {
                $originalField = substr($key, 7);
                if (isset($fields[$originalField])) {
                    $fields[$originalField] = trim($value);
                }
            }
        }
        
        // 3. 加入隱藏欄位
        if (!empty($hiddenFields)) {
            foreach ($hiddenFields as $hField => $hValue) {
                $fields[$hField] = $hValue;
            }
        }
        
        // 【新增】4. 特殊處理：parent_id 為空時設為 NULL
        // 避免外鍵約束錯誤
        if (isset($fields['parent_id']) && ($fields['parent_id'] === '' || $fields['parent_id'] === 'all' || $fields['parent_id'] === '0')) {
            $fields['parent_id'] = null;
        }
        
        return $fields;
    }
    
    /**
     * 處理 Slug 生成
     * @param string $sourceValue 來源字串
     * @return string Slug
     */
    public static function generateSlug($sourceValue)
    {
        if (empty($sourceValue)) return "";
        if (function_exists('generate_slug')) {
            return generate_slug($sourceValue);
        }
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $sourceValue));
        return trim($slug, '-');
    }

    /**
     * 確保 Slug 唯一性 (若重複則自動加數字，如 -2, -3)
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param string $slugField 欄位名稱 (如 d_slug)
     * @param string $targetSlug 原始 Slug
     * @param int $currentId 當前 ID (排除自身)
     * @param array $context 額外上下文 (lang, module 等)
     * @param array $moduleConfig 模組配置 (用於獲取 primaryKey 等)
     * @return string 唯一的 Slug
     */
    public static function ensureUniqueSlug($conn, $tableName, $slugField, $targetSlug, $currentId = 0, $context = [], $moduleConfig = [])
    {
        if (empty($targetSlug)) return "";

        $primaryKey = $moduleConfig['primaryKey'] ?? 'd_id';
        $prefix = substr($primaryKey, 0, 1) . '_';
        
        // 1. 構築基本條件 (參考 checkDuplicateField)
        $whereBase = ["{$slugField} = :slug"];
        $paramsBase = [];

        if ($currentId > 0) {
            $whereBase[] = "{$primaryKey} != :current_id";
            $paramsBase[':current_id'] = $currentId;
        }

        // 模組過濾 (如果是分類表 taxonomies)
        $menuKey = $moduleConfig['menuKey'] ?? ($prefix . 'class1');
        $menuValue = $context[$menuKey] ?? null;
        if ($menuValue !== null) {
            $mKey = ($menuKey === 'd_class1' && $tableName === 'taxonomies') ? 'taxonomy_type_id' : $menuKey;
            $whereBase[] = "{$mKey} = :menuValue";
            $paramsBase[':menuValue'] = $menuValue;
        }

        // 語系過濾
        if (isset($context['lang'])) {
            $whereBase[] = "lang = :lang";
            $paramsBase[':lang'] = $context['lang'];
        }

        // 排除回收桶（只有當欄位存在時才加入條件）
        // 如果 cols 中有明確設定 delete_time（即使是 null），就使用該值；否則使用預設值
        if (array_key_exists('delete_time', $moduleConfig['cols'] ?? [])) {
            $col_delete_time = $moduleConfig['cols']['delete_time'];
        } else {
            $col_delete_time = $prefix . 'delete_time';
        }

        if ($col_delete_time !== null) {
            $whereBase[] = "{$col_delete_time} IS NULL";
        }

        // 2. 迴圈檢查直到唯一
        $finalSlug = $targetSlug;
        $counter = 1;
        $isUnique = false;

        while (!$isUnique) {
            $checkSql = "SELECT COUNT(*) FROM {$tableName} WHERE " . implode(' AND ', $whereBase);
            $stmt = $conn->prepare($checkSql);
            
            // 綁定參數
            $currentParams = $paramsBase;
            $currentParams[':slug'] = $finalSlug;
            
            foreach ($currentParams as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $isUnique = true;
            } else {
                $counter++;
                $finalSlug = $targetSlug . '-' . $counter;
            }
            
            // 防死迴圈
            if ($counter > 100) break;
        }

        return $finalSlug;
    }

    /**
     * 計算層級 (Level)
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param string $primaryKey 主鍵欄位
     * @param mixed $parentIdValue 父層 ID
     * @param string $levelField 層級欄位名稱
     * @return int 計算後的層級
     */
    public static function calculateLevel($conn, $tableName, $primaryKey, $parentIdValue, $levelField)
    {
        if (empty($parentIdValue) || $parentIdValue === 'all' || $parentIdValue === '0' || $parentIdValue === 0) {
            return 0;
        }

        try {
            $stmt = $conn->prepare("SELECT {$levelField} FROM {$tableName} WHERE {$primaryKey} = :pid");
            $stmt->execute([':pid' => $parentIdValue]);
            $parentLevel = $stmt->fetchColumn();

            // 如果找不到父層或父層層級為 null，預設為 0
            if ($parentLevel === false || $parentLevel === null) {
                $parentLevel = 0;
            }

            return intval($parentLevel) + 1;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 執行 INSERT 操作
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param array $fields 欄位資料
     * @return int 新增的 ID
     */
    public static function executeInsert($conn, $tableName, $fields)
    {
        // 【新增】特殊處理：parent_id 為空時設為 NULL
        // 避免外鍵約束錯誤
        if (isset($fields['parent_id']) && ($fields['parent_id'] === '' || $fields['parent_id'] === 'all')) {
            $fields['parent_id'] = null;
        }
        
        $fieldKeys = array_keys($fields);
        $insertSQL = "INSERT INTO {$tableName} (" . implode(',', $fieldKeys) . ") VALUES (:" . implode(',:', $fieldKeys) . ")";
        
        $stmt = $conn->prepare($insertSQL);
        foreach ($fields as $key => $val) {
            $stmt->bindValue(":{$key}", $val);
        }
        $stmt->execute();
        
        return $conn->lastInsertId();
    }
    
    /**
     * 執行 UPDATE 操作
     */
    public static function executeUpdate($conn, $tableName, $fields, $primaryKey, $id)
    {
        // 【新增】特殊處理：parent_id 為空時設為 NULL
        // 避免外鍵約束錯誤
        if (isset($fields['parent_id']) && ($fields['parent_id'] === '' || $fields['parent_id'] === 'all')) {
            $fields['parent_id'] = null;
        }
        
        $setParts = [];
        foreach ($fields as $key => $val) {
            $setParts[] = "{$key} = :{$key}";
        }
        
        $updateSQL = "UPDATE {$tableName} SET " . implode(', ', $setParts) . " WHERE {$primaryKey} = :id";
        
        $stmt = $conn->prepare($updateSQL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        foreach ($fields as $key => $val) {
            $stmt->bindValue(":{$key}", $val);
        }
        
        return $stmt->execute();
    }
    
    /**
     * 處理跨分類搬移的排序邏輯 (用於 UPDATE)
     */
    public static function handleCrossCategoryMove($conn, $tableName, $primaryKey, $id, $categoryField, $newCategory, $col_sort, $menuKey = null, $menuValue = null)
    {
        // 獲取原始資料
        $stmt = $conn->prepare("SELECT * FROM {$tableName} WHERE {$primaryKey} = :id");
        $stmt->execute([':id' => $id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData || $oldData[$categoryField] == $newCategory) {
            return false;
        }
        
        // A. 舊分類：將序號大於目前資料的項目 -1
        $oldSortWhere = ($menuKey && $menuValue !== null) 
            ? "{$menuKey} = :menuValue AND {$categoryField} = :old_cat AND {$col_sort} > :old_sort"
            : "{$categoryField} = :old_cat AND {$col_sort} > :old_sort";
        
        // 【修正】加入語系過濾
        if (isset($oldData['lang'])) {
            $oldSortWhere .= " AND lang = :lang";
        }
        
        $oldSortParams = [
            ':old_cat'   => $oldData[$categoryField],
            ':old_sort'  => $oldData[$col_sort]
        ];
        if ($menuKey && $menuValue !== null) $oldSortParams[':menuValue'] = $menuValue;
        if (isset($oldData['lang'])) $oldSortParams[':lang'] = $oldData['lang'];
        $conn->prepare("UPDATE {$tableName} SET {$col_sort} = {$col_sort} - 1 WHERE {$oldSortWhere}")->execute($oldSortParams);

        // B. 新分類：將新分類所有資料 +1
        $newSortWhere = ($menuKey && $menuValue !== null)
            ? "{$menuKey} = :menuValue AND {$categoryField} = :new_cat"
            : "{$categoryField} = :new_cat";
        
        // 【修正】加入語系過濾
        if (isset($oldData['lang'])) {
            $newSortWhere .= " AND lang = :lang";
        }
        
        $newSortParams = [':new_cat' => $newCategory];
        if ($menuKey && $menuValue !== null) $newSortParams[':menuValue'] = $menuValue;
        if (isset($oldData['lang'])) $newSortParams[':lang'] = $oldData['lang'];
        $conn->prepare("UPDATE {$tableName} SET {$col_sort} = {$col_sort} + 1 WHERE {$newSortWhere}")->execute($newSortParams);

        return true;
    }

    /**
     * 處理排序欄位 (用於 INSERT)
     */
    public static function handleSortOnInsert($conn, $tableName, $sortField, $conditions = [])
    {
        if ($sortField === null) return null;
        
        $where = [];
        $params = [];
        foreach ($conditions as $field => $value) {
            // 特別處理 parent_id：如果是 0, NULL, '', 'all'，視為頂層
            if (strpos($field, 'parent_id') !== false || $field === 'menu_parent_id') {
                if ($value === null || $value === 0 || $value === '0' || $value === '' || $value === 'all') {
                    $where[] = "({$field} = 0 OR {$field} IS NULL)";
                    continue;
                }
            }
            
            if ($value !== null) {
                $where[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
        }
        $whereClause = empty($where) ? "1=1" : implode(' AND ', $where);
        
        // 【依使用者要求改成 1，並將其餘 +1】
        $conn->prepare("UPDATE {$tableName} SET {$sortField} = {$sortField} + 1 WHERE {$whereClause}")->execute($params);
        return 1;
    }

    /**
     * 檢查欄位值是否重複 (增強版 - 支援多類別與 Mapping Table)
     * @param PDO $conn 資料庫連線
     * @param string $tableName 資料表名稱
     * @param string $targetField 欄位名稱 (如 d_title, d_slug)
     * @param mixed $targetValue 欄位值
     * @param array $config 檢查配置
     * @param array $fields 當前表單所有欄位資料
     * @param array $moduleConfig 完整的模組配置 (用於獲取更多上下文)
     * @param int $currentId 當前編號 (編輯模式)
     * @return array ['isDuplicate' => bool, 'message' => string]
     */
    public static function checkDuplicateField($conn, $tableName, $targetField, $targetValue, $config, $fields, $moduleConfig, $currentId = 0)
    {
        if (empty($config['enabled'])) {
            return ['isDuplicate' => false, 'message' => ''];
        }

        $primaryKey = $moduleConfig['primaryKey'] ?? 'd_id';
        $prefix = substr($primaryKey, 0, 1) . '_';
        
        // --- 1. 構築基礎條件 ---
        $where = ["{$targetField} = :target_val"];
        $params = [':target_val' => $targetValue];

        if ($currentId > 0) {
            $where[] = "{$primaryKey} != :curr_id";
            $params[':curr_id'] = $currentId;
        }

        // --- 2. 模組過濾 (class1) ---
        $menuKey = $moduleConfig['menuKey'] ?? ($prefix . 'class1');
        $menuValue = $moduleConfig['menuValue'] ?? ($fields[$menuKey] ?? null);
        if ($menuValue !== null) {
            $effectiveMKey = ($menuKey === 'd_class1' && $tableName === 'taxonomies') ? 'taxonomy_type_id' : $menuKey;
            $where[] = "{$effectiveMKey} = :m_val";
            $params[':m_val'] = $menuValue;
        }

        // --- 3. 語系過濾 ---
        $langVal = $fields['lang'] ?? null;
        if ($langVal) {
            $where[] = "lang = :l_val";
            $params[':l_val'] = $langVal;
        }

        // --- 4. 層級過濾 (同一父層下不得重複) ---
        $parentIdField = $moduleConfig['cols']['parent_id'] ?? ($prefix . 'parent_id');
        if (array_key_exists($parentIdField, $fields)) {
            $pVal = $fields[$parentIdField];
            if ($pVal === null || $pVal === '' || $pVal === 'all' || $pVal === 0 || $pVal === '0') {
                $where[] = "({$parentIdField} IS NULL OR {$parentIdField} = 0)";
            } else {
                $where[] = "{$parentIdField} = :p_id";
                $params[':p_id'] = $pVal;
            }
        }

        // --- 5. 排除回收桶（只有當欄位存在時才加入條件）---
        // 如果 cols 中有明確設定 delete_time（即使是 null），就使用該值；否則使用預設值
        if (array_key_exists('delete_time', $moduleConfig['cols'] ?? [])) {
            $col_delete_time = $moduleConfig['cols']['delete_time'];
        } else {
            $col_delete_time = $prefix . 'delete_time';
        }

        if ($col_delete_time !== null) {
            $where[] = "({$col_delete_time} IS NULL)";
        }

        // --- 6. 進階：分類關連檢查 (如果啟用) ---
        if (!empty($config['checkCategories'])) {
            $catConds = [];
            $categoryField = $moduleConfig['listPage']['categoryField'] ?? null;
            $catValue = $fields[$categoryField] ?? ($fields[$categoryField . '[]'] ?? null);
            if (!empty($catValue)) {
                $catIds = is_array($catValue) ? $catValue : explode(',', $catValue);
                $catIds = array_filter(array_map('intval', $catIds));
                
                if (!empty($catIds)) {
                    $stmtM = $conn->query("SHOW TABLES LIKE 'data_taxonomy_map'");
                    if ($stmtM->rowCount() > 0) {
                        $mParts = [];
                        foreach ($catIds as $idx => $cid) {
                            $pk = ":m_cat_" . $idx;
                            $mParts[] = $pk;
                            $params[$pk] = $cid;
                        }
                        $catConds[] = "{$primaryKey} IN (SELECT d_id FROM data_taxonomy_map WHERE t_id IN (" . implode(',', $mParts) . "))";
                    }
                }
            }
            // Columns (d_class2, d_class3...)
            foreach ($fields as $fName => $fVal) {
                $cleanName = str_replace('[]', '', $fName);
                if (strpos($cleanName, $prefix . 'class') === 0 && $cleanName !== $menuKey && !empty($fVal)) {
                    $cvs = is_array($fVal) ? $fVal : explode(',', $fVal);
                    $ops = [];
                    foreach ($cvs as $idx => $sv) {
                        $pk = ":" . $cleanName . "_" . $idx;
                        $ops[] = "FIND_IN_SET({$pk}, {$cleanName})";
                        $params[$pk] = trim($sv);
                    }
                    if (!empty($ops)) $catConds[] = "(" . implode(' OR ', $ops) . ")";
                }
            }
            if (!empty($catConds)) $where[] = "(" . implode(' AND ', $catConds) . ")";
        }

        // --- 7. 執行查詢 ---
        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE " . implode(' AND ', $where);
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($res && $res['count'] > 0) {
                $fLabel = $config['label'] ?? '此欄位';
                $msg = $config['errorMessage'] ?? "「{$fLabel}」重複了";
                return ['isDuplicate' => true, 'message' => $msg];
            }
        } catch (Exception $e) {
            error_log("Duplicate Check SQL Error: " . $e->getMessage() . " | SQL: " . $sql);
        }

        return ['isDuplicate' => false, 'message' => ''];
    }
}

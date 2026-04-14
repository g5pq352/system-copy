<?php
/**
 * AJAX Handler: Translate & Clone Record
 */

require_once('../Connections/connect2data.php');

header('Content-Type: application/json');

ob_start();

try {
    $conn->beginTransaction();
    $module = $_POST['module'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);
    $targetLang = $_POST['target_lang'] ?? '';
    
    if (empty($module) || $itemId <= 0 || empty($targetLang)) {
        throw new Exception('缺少必要參數');
    }
    
    // 1. 載入模組配置
    $configFile = __DIR__ . "/set/{$module}Set.php";
    if (!file_exists($configFile)) {
        throw new Exception("找不到模組配置檔案");
    }
    
    $moduleConfig = require $configFile;
    $tableName = $moduleConfig['tableName'];
    $primaryKey = $moduleConfig['primaryKey'];
    $langField = 'lang'; // 統一使用 lang
    
    // 2. 獲取原始資料
    $sqlSelect = "SELECT * FROM {$tableName} WHERE {$primaryKey} = :id";
    $stmtSelect = $conn->prepare($sqlSelect);
    $stmtSelect->execute([':id' => $itemId]);
    $rowData = $stmtSelect->fetch(PDO::FETCH_ASSOC);
    
    if (!$rowData) {
        throw new Exception('找不到原始資料');
    }
    
    // 3. 檢查目標語系是否已存在 (避免重複)
    // 對於 data_set，通常我們允許同一個 d_class1 下有不同語系的相同標題，但如果需要嚴格檢查可以加在這
    // 這裡我們暫不限制，讓使用者可以自由複製
    
    // 4. 準備插入資料
    unset($rowData[$primaryKey]); // 移除主鍵
    $rowData[$langField] = $targetLang; // 設定目標語系
    
    // 如果有 slug 欄位且是唯一的，可能需要處理 (這裡暫時加上語系後綴以避免衝突)
    if (isset($rowData['d_slug']) && !empty($rowData['d_slug'])) {
        $rowData['d_slug'] .= '-' . $targetLang;
    }
    if (isset($rowData['t_slug']) && !empty($rowData['t_slug'])) {
        $rowData['t_slug'] .= '-' . $targetLang;
    }

    // 【新增】智慧分類對應（支援多層分類）
    $hasCategory = $moduleConfig['listPage']['hasCategory'] ?? false;
    $categoryField = $moduleConfig['listPage']['categoryField'] ?? '';
    $categoryName = $moduleConfig['listPage']['categoryName'] ?? '';

    if ($hasCategory && $categoryField && $categoryName) {
        // 1. 找出分類表設定
        $catMenuStmt = $conn->prepare("SELECT menu_table, menu_pk FROM cms_menus WHERE menu_type = :type LIMIT 1");
        $catMenuStmt->execute([':type' => $categoryName]);
        $catMenu = $catMenuStmt->fetch();

        if ($catMenu) {
            $cTable = $catMenu['menu_table'];
            $cPK = ($cTable === 'taxonomies') ? 't_id' : 'd_id';
            $cTitleCol = ($cTable === 'taxonomies') ? 't_name' : 'd_title';

            // 檢查 categoryField 是否為陣列（多層分類）
            if (is_array($categoryField)) {
                // 處理多層分類：遍歷每個層級欄位
                foreach ($categoryField as $field) {
                    if (!empty($rowData[$field])) {
                        $oldCatId = $rowData[$field];
                        
                        // 2. 獲取原始分類的名稱
                        $oldCatSql = "SELECT {$cTitleCol} FROM {$cTable} WHERE {$cPK} = :id";
                        $oldCatStmt = $conn->prepare($oldCatSql);
                        $oldCatStmt->execute([':id' => $oldCatId]);
                        $oldCatTitle = $oldCatStmt->fetchColumn();

                        if ($oldCatTitle) {
                            // 3. 在目標語系中找同名的分類
                            $newCatSql = "SELECT {$cPK} FROM {$cTable} WHERE {$cTitleCol} = :title AND lang = :lang LIMIT 1";
                            $newCatStmt = $conn->prepare($newCatSql);
                            $newCatStmt->execute([':title' => $oldCatTitle, ':lang' => $targetLang]);
                            $newCatId = $newCatStmt->fetchColumn();

                            if ($newCatId) {
                                $rowData[$field] = $newCatId; // 成功對應到目標語系的分類
                            } else {
                                $rowData[$field] = 0;
                            }
                        } else {
                            $rowData[$field] = 0;
                        }
                    }
                }
            } else {
                // 處理單一分類欄位（向後兼容）
                if (!empty($rowData[$categoryField])) {
                    $oldCatId = $rowData[$categoryField];
                    
                    // 2. 獲取原始分類的名稱
                    $oldCatSql = "SELECT {$cTitleCol} FROM {$cTable} WHERE {$cPK} = :id";
                    $oldCatStmt = $conn->prepare($oldCatSql);
                    $oldCatStmt->execute([':id' => $oldCatId]);
                    $oldCatTitle = $oldCatStmt->fetchColumn();

                    if ($oldCatTitle) {
                        // 3. 在目標語系中找同名的分類
                        $newCatSql = "SELECT {$cPK} FROM {$cTable} WHERE {$cTitleCol} = :title AND lang = :lang LIMIT 1";
                        $newCatStmt = $conn->prepare($newCatSql);
                        $newCatStmt->execute([':title' => $oldCatTitle, ':lang' => $targetLang]);
                        $newCatId = $newCatStmt->fetchColumn();

                        if ($newCatId) {
                            $rowData[$categoryField] = $newCatId; // 成功對應到目標語系的分類
                        } else {
                            $rowData[$categoryField] = 0;
                        }
                    } else {
                        $rowData[$categoryField] = 0;
                    }
                }
            }
        }
    }

    // 【新增】重新計算目標語系的排序號碼
    $sortField = $moduleConfig['cols']['sort'] ?? 'd_sort';
    if (isset($rowData[$sortField])) {
        // 計算目標語系中的最大排序號碼
        $sortWhere = "lang = :lang";
        $sortParams = [':lang' => $targetLang];
        
        // 如果有分類，只計算同分類的排序
        if ($hasCategory && $categoryField && isset($rowData[$categoryField]) && $rowData[$categoryField] > 0) {
            $sortWhere .= " AND {$categoryField} = :cat";
            $sortParams[':cat'] = $rowData[$categoryField];
        }
        
        // 如果有 menuKey，加入條件
        $menuKey = $moduleConfig['menuKey'] ?? null;
        $menuValue = $moduleConfig['menuValue'] ?? null;
        if ($menuKey && $menuValue !== null) {
            $sortWhere .= " AND {$menuKey} = :menuValue";
            $sortParams[':menuValue'] = $menuValue;
        }
        
        $maxSortSql = "SELECT COALESCE(MAX({$sortField}), 0) FROM {$tableName} WHERE {$sortWhere}";
        $maxSortStmt = $conn->prepare($maxSortSql);
        $maxSortStmt->execute($sortParams);
        $maxSort = $maxSortStmt->fetchColumn();
        
        // 設定新的排序號碼為最大值 + 1
        $rowData[$sortField] = $maxSort + 1;
    }

    $columns = array_keys($rowData);
    $values = array_values($rowData);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $colList = implode(',', $columns);
    
    $sqlInsert = "INSERT INTO {$tableName} ($colList) VALUES ($placeholders)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->execute($values);
    
    $newId = $conn->lastInsertId();
    
    // 5. 複製關連檔案 (file_set)
    $fileFk = $moduleConfig['cols']['file_fk'] ?? 'file_d_id';
    $sqlFiles = "SELECT * FROM file_set WHERE {$fileFk} = :oldId";
    $stmtFiles = $conn->prepare($sqlFiles);
    $stmtFiles->execute([':oldId' => $itemId]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
    
    $fileIdMap = []; // 【新增】用於紀錄舊檔案 ID 與新檔案 ID 的對應關係

    foreach ($files as $file) {
        $oldFileId = $file['file_id'];
        unset($file['file_id']); // 移除檔案主鍵
        $file[$fileFk] = $newId; // 關連到新紀錄
        
        // 【修正】依據 upload_process.php 規範與使用者需求
        $destBaseName = (!empty($oldCatTitle)) ? $oldCatTitle : $module;
        // 淨化名稱 (移除不合法字元) - 修正 regex 語法
        $destBaseName = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $destBaseName ?? '');
        $destBaseName = str_replace(' ', '_', $destBaseName ?? '');
        // 如果清理後變成空字串，使用模組名稱作為備用
        if (empty($destBaseName)) {
            $destBaseName = $module;
        }
        
        foreach (['file_link1', 'file_link2', 'file_link3', 'file_link4', 'file_link5'] as $linkKey) {
            if (!empty($file[$linkKey])) {
                $srcPath = "../" . $file[$linkKey];
                
                if (file_exists($srcPath)) {
                    // 【修正】保持原始目錄結構，只複製檔案
                    $pathInfo = pathinfo($file[$linkKey]);
                    $originalDir = $pathInfo['dirname'];  // 例如: upload_image/history
                    $ext = $pathInfo['extension'];
                    
                    // 使用相同的目錄，只是檔名不同
                    $destRelDir = $originalDir . "/";
                    $destAbsDir = "../" . $destRelDir;
                    
                    if (!is_dir($destAbsDir)) {
                        @mkdir($destAbsDir, 0777, true);
                    }

                    if (is_dir($destAbsDir)) {
                        // 產生新檔名：模組名_時間戳_隨機數.ext
                        $newFileName = $module . "_" . date('YmdHis') . "_" . rand(100, 999) . "." . $ext;
                        $destPath = $destAbsDir . $newFileName;
                        
                        if (@copy($srcPath, $destPath)) {
                            $file[$linkKey] = $destRelDir . $newFileName;
                        }
                    }
                }
            }
        }

        $fCols = array_keys($file);
        $fValues = array_values($file);
        $fPlaceholders = implode(',', array_fill(0, count($fCols), '?'));
        $fColList = implode(',', $fCols);
        
        $sqlInsertFile = "INSERT INTO file_set ($fColList) VALUES ($fPlaceholders)";
        $stmtInsertFile = $conn->prepare($sqlInsertFile);
        $stmtInsertFile->execute($fValues);

        $newFileId = $conn->lastInsertId();
        $fileIdMap[$oldFileId] = $newFileId; // 紀錄對應
    }

    // 6. 【新增】複製動態欄位 (data_dynamic_fields)
    $sqlDynamic = "SELECT * FROM data_dynamic_fields WHERE df_d_id = :oldId";
    $stmtDynamic = $conn->prepare($sqlDynamic);
    $stmtDynamic->execute([':oldId' => $itemId]);
    $dynamicFields = $stmtDynamic->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dynamicFields as $df) {
        unset($df['df_id']);
        $df['df_d_id'] = $newId;
        
        // 如果有關連到檔案，更新為新檔案 ID
        if (!empty($df['df_file_id']) && isset($fileIdMap[$df['df_file_id']])) {
            $df['df_file_id'] = $fileIdMap[$df['df_file_id']];
        }

        $dfCols = array_keys($df);
        $dfValues = array_values($df);
        $dfPlaceholders = implode(',', array_fill(0, count($dfCols), '?'));
        $dfColList = implode(',', $dfCols);

        $sqlInsertDF = "INSERT INTO data_dynamic_fields ($dfColList) VALUES ($dfPlaceholders)";
        $stmtInsertDF = $conn->prepare($sqlInsertDF);
        $stmtInsertDF->execute($dfValues);
    }
    
    // 7. 【新增】處理 data_taxonomy_map 關連複製與重新排序
    require_once(__DIR__ . '/includes/taxonomyMapHelper.php');
    require_once(__DIR__ . '/includes/SortReorganizer.php');

    $useTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;
    $affectedCategories = [];

    if (hasTaxonomyMapTable($conn)) {
        // 取得原始項目的所有分類關連
        $stmtOldMaps = $conn->prepare("SELECT t_id, map_level FROM data_taxonomy_map WHERE d_id = :oldId");
        $stmtOldMaps->execute([':oldId' => $itemId]);
        $oldMaps = $stmtOldMaps->fetchAll(PDO::FETCH_ASSOC);

        foreach ($oldMaps as $om) {
            $oldTId = $om['t_id'];
            $mapLevel = $om['map_level'];

            // 智慧尋找目標語系的同名分類
            // 由於 Mapping Table 是獨立的，我們需要找到目標語系對應的 t_id
            $stmtTaxName = $conn->prepare("SELECT t_name FROM taxonomies WHERE t_id = :tid");
            $stmtTaxName->execute([':tid' => $oldTId]);
            $taxName = $stmtTaxName->fetchColumn();

            if ($taxName) {
                $stmtNewTaxId = $conn->prepare("SELECT t_id FROM taxonomies WHERE t_name = :name AND lang = :lang LIMIT 1");
                $stmtNewTaxId->execute([':name' => $taxName, ':lang' => $targetLang]);
                $newTId = $stmtNewTaxId->fetchColumn();

                if ($newTId) {
                    // 插入新的關聯 (sort_num 先填 9999，稍後由 reorderTaxonomyMap 導正)
                    $stmtInsMap = $conn->prepare("INSERT INTO data_taxonomy_map (d_id, t_id, map_level, sort_num) VALUES (:did, :tid, :ml, 9999)");
                    $stmtInsMap->execute([
                        ':did' => $newId,
                        ':tid' => $newTId,
                        ':ml'  => $mapLevel
                    ]);
                    $affectedCategories[] = $newTId;
                }
            }
        }
    }

    // Step 1: 重新整理目標語系的主表排序 (All 視圖)
    $mainSortCol = $moduleConfig['cols']['sort'] ?? 'd_sort';
    $mainDeleteCol = $moduleConfig['cols']['delete_time'] ?? 'd_delete_time';
    $hasDeleteTime = !empty($mainDeleteCol);
    $parentIdField = $moduleConfig['cols']['parent_id'] ?? null;
    $menuKey = $moduleConfig['menuKey'] ?? null;
    $menuValue = $moduleConfig['menuValue'] ?? null;

    SortReorganizer::reorganizeAll(
        $conn,
        $tableName,
        $primaryKey,
        $mainSortCol,
        $menuKey,
        $hasDeleteTime,
        $mainDeleteCol,
        null, // categoryField
        $parentIdField,
        $menuValue,
        $targetLang // 指定目標語系
    );

    // Step 2: 重新整理受影響分類的排序 (Category 視圖)
    // 如果原始資料有 t_id = 0 (全域 Map)，也要一起處理
    if ($useTaxonomyMapSort) {
        // 去重並包含受影響的分類
        $affectedCategories = array_unique($affectedCategories);
        foreach ($affectedCategories as $tid) {
            reorderTaxonomyMap($conn, intval($tid), null, [
                $menuKey => $menuValue,
                'lang'   => $targetLang
            ], $tableName);
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'new_id' => $newId,
        'message' => '複製成功 (已同步重新排序)'
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    // 確保發生錯誤時也有 JSON 回傳，避免「網路通訊失敗」
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => false,
        'message' => '複製失敗：' . $e->getMessage()
    ]);
}

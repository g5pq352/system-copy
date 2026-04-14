<?php
/**
 * AJAX Handler: Batch Translate & Clone Records
 */

require_once('../Connections/connect2data.php');
require_once('includes/elements/FormProcessElement.php');

header('Content-Type: application/json');

ob_start();

try {
    $conn->beginTransaction();
    $module = $_POST['module'] ?? '';
    $itemIds = $_POST['item_ids'] ?? []; // Expected as an array
    $targetLang = $_POST['target_lang'] ?? '';
    $overwrite = (int)($_POST['overwrite'] ?? 0);
    $isInfo = (int)($_POST['is_info'] ?? 0); // 標記是否為 info.php 的請求

    if (empty($module) || empty($itemIds) || !is_array($itemIds) || empty($targetLang)) {
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
    $langField = 'lang';
    $fileFk = $moduleConfig['cols']['file_fk'] ?? 'file_d_id';
    $hasHierarchy = $moduleConfig['listPage']['hasHierarchy'] ?? false;
    $parentIdField = $moduleConfig['cols']['parent_id'] ?? null;

    // 【新增】info.php 專用：檢查目標語系是否已存在資料
    if ($isInfo) {
        $menuKey = $moduleConfig['menuKey'] ?? 'd_class1';
        $menuValue = $moduleConfig['menuValue'] ?? $module;

        $checkSql = "SELECT {$primaryKey} FROM {$tableName} WHERE {$menuKey} = :menuValue AND {$langField} = :targetLang LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':menuValue' => $menuValue, ':targetLang' => $targetLang]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            if (!$overwrite) {
                // 不覆蓋，返回錯誤
                throw new Exception("目標語系 ({$targetLang}) 已存在資料，請勾選「覆蓋已存在的資料」後重試");
            } else {
                // 覆蓋模式：刪除舊資料（包含關聯的檔案和動態欄位）
                // 1. 刪除關聯的檔案記錄和實體檔案
                $fileStmt = $conn->prepare("SELECT * FROM file_set WHERE {$fileFk} = :id");
                $fileStmt->execute([':id' => $existingId]);
                foreach ($fileStmt->fetchAll(PDO::FETCH_ASSOC) as $file) {
                    // 刪除實體檔案
                    foreach (['file_link1', 'file_link2', 'file_link3', 'file_link4', 'file_link5'] as $linkKey) {
                        if (!empty($file[$linkKey])) {
                            $filePath = "../" . $file[$linkKey];
                            if (file_exists($filePath)) {
                                @unlink($filePath);
                            }
                        }
                    }
                }
                // 刪除檔案記錄
                $conn->prepare("DELETE FROM file_set WHERE {$fileFk} = :id")->execute([':id' => $existingId]);

                // 2. 刪除動態欄位（包含關聯的圖片）
                $dfStmt = $conn->prepare("SELECT df_file_id FROM data_dynamic_fields WHERE df_d_id = :id AND df_file_id IS NOT NULL");
                $dfStmt->execute([':id' => $existingId]);
                foreach ($dfStmt->fetchAll(PDO::FETCH_COLUMN) as $fileId) {
                    // 刪除動態欄位關聯的圖片實體檔案
                    $dfFileStmt = $conn->prepare("SELECT * FROM file_set WHERE file_id = :fileId");
                    $dfFileStmt->execute([':fileId' => $fileId]);
                    if ($dfFile = $dfFileStmt->fetch(PDO::FETCH_ASSOC)) {
                        foreach (['file_link1', 'file_link2', 'file_link3', 'file_link4', 'file_link5'] as $linkKey) {
                            if (!empty($dfFile[$linkKey])) {
                                $filePath = "../" . $dfFile[$linkKey];
                                if (file_exists($filePath)) {
                                    @unlink($filePath);
                                }
                            }
                        }
                        $conn->prepare("DELETE FROM file_set WHERE file_id = :fileId")->execute([':fileId' => $fileId]);
                    }
                }
                // 刪除動態欄位記錄
                $conn->prepare("DELETE FROM data_dynamic_fields WHERE df_d_id = :id")->execute([':id' => $existingId]);

                // 3. 刪除主資料
                $conn->prepare("DELETE FROM {$tableName} WHERE {$primaryKey} = :id")->execute([':id' => $existingId]);
            }
        }
    }
    
    $successCount = 0;
    $errors = [];
    $sortCounter = [];

    /**
     * 遞迴複製函數
     */
    $cloneItem = function($itemId, $newParentId = null) use (&$cloneItem, $conn, $tableName, $primaryKey, $langField, $targetLang, $moduleConfig, $fileFk, $module, $hasHierarchy, $parentIdField, &$successCount, &$errors, &$sortCounter) {
        try {
            // 獲取原始資料
            $sqlSelect = "SELECT * FROM {$tableName} WHERE {$primaryKey} = :id";
            $stmtSelect = $conn->prepare($sqlSelect);
            $stmtSelect->execute([':id' => $itemId]);
            $rowData = $stmtSelect->fetch(PDO::FETCH_ASSOC);
            
            if (!$rowData) {
                throw new Exception("找不到原始資料 (ID: {$itemId})");
            }
            
            $sourceLang = $rowData[$langField] ?? '';
            unset($rowData[$primaryKey]);
            $rowData[$langField] = $targetLang;

            // 如果指定了新的父 ID，則使用之 (用於遞迴複製子層)
            if ($newParentId !== null && $parentIdField) {
                $rowData[$parentIdField] = $newParentId;
            }

            // 同語系複製，標題加副本
            if ($sourceLang === $targetLang) {
                $titleField = $moduleConfig['cols']['title'] ?? 'd_title';
                if (isset($rowData[$titleField])) {
                    $rowData[$titleField] .= ' (副本)';
                }
            }

            // Slug 處理
            foreach (['d_slug', 't_slug'] as $slugField) {
                if (isset($rowData[$slugField]) && !empty($rowData[$slugField])) {
                    $rowData[$slugField] = FormProcessElement::ensureUniqueSlug(
                        $conn, $tableName, $slugField, $rowData[$slugField], 
                        0, ['lang' => $targetLang], $moduleConfig
                    );
                }
            }

            // 分類與排序處理（支援多層分類）
            $hasCategory = $moduleConfig['listPage']['hasCategory'] ?? false;
            $categoryField = $moduleConfig['listPage']['categoryField'] ?? '';
            $categoryName = $moduleConfig['listPage']['categoryName'] ?? '';
            
            if ($hasCategory && $categoryField && $categoryName) {
                $catConfigFile = __DIR__ . "/set/{$categoryName}Set.php";
                if (file_exists($catConfigFile)) {
                    $catConfig = require $catConfigFile;
                    $cTable = $catConfig['tableName'] ?? '';
                    $cPK = $catConfig['primaryKey'] ?? '';
                    $cTitleCol = $catConfig['cols']['title'] ?? ($cTable == 'taxonomies' ? 't_name' : 'd_title');
                    
                    if ($cTable && $cPK && $cTitleCol) {
                        // 檢查 categoryField 是否為陣列（多層分類）
                        if (is_array($categoryField)) {
                            // 處理多層分類：遍歷每個層級欄位
                            foreach ($categoryField as $field) {
                                if (!empty($rowData[$field])) {
                                    $oldCatId = $rowData[$field];
                                    
                                    $oldCatSql = "SELECT {$cTitleCol} FROM {$cTable} WHERE {$cPK} = :id";
                                    $oldCatStmt = $conn->prepare($oldCatSql);
                                    $oldCatStmt->execute([':id' => $oldCatId]);
                                    $oldCatTitle = $oldCatStmt->fetchColumn();
                                    
                                    if ($oldCatTitle) {
                                        $newCatSql = "SELECT {$cPK} FROM {$cTable} WHERE {$cTitleCol} = :title AND lang = :lang LIMIT 1";
                                        $newCatStmt = $conn->prepare($newCatSql);
                                        $newCatStmt->execute([':title' => $oldCatTitle, ':lang' => $targetLang]);
                                        $newCatId = $newCatStmt->fetchColumn();
                                        
                                        if ($newCatId) {
                                            $rowData[$field] = $newCatId;
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
                                
                                $oldCatSql = "SELECT {$cTitleCol} FROM {$cTable} WHERE {$cPK} = :id";
                                $oldCatStmt = $conn->prepare($oldCatSql);
                                $oldCatStmt->execute([':id' => $oldCatId]);
                                $oldCatTitle = $oldCatStmt->fetchColumn();
                                
                                if ($oldCatTitle) {
                                    $newCatSql = "SELECT {$cPK} FROM {$cTable} WHERE {$cTitleCol} = :title AND lang = :lang LIMIT 1";
                                    $newCatStmt = $conn->prepare($newCatSql);
                                    $newCatStmt->execute([':title' => $oldCatTitle, ':lang' => $targetLang]);
                                    $newCatId = $newCatStmt->fetchColumn();
                                    
                                    if ($newCatId) {
                                        $rowData[$categoryField] = $newCatId;
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
            }

            $sortField = $moduleConfig['cols']['sort'] ?? 'd_sort';
            if (isset($rowData[$sortField])) {
                // 建立排序計數器的 key (層級隔離：不同 parent_id 應該有獨立的排序計數)
                $sortKey = $targetLang;
                if ($hasHierarchy && $parentIdField && isset($rowData[$parentIdField])) {
                    $sortKey .= '_pid_' . (int)$rowData[$parentIdField];
                }
                
                // 如果該模組有區分選單/類型
                $menuKey = $moduleConfig['menuKey'] ?? null;
                $menuValue = $moduleConfig['menuValue'] ?? null;
                if ($menuKey && $menuValue !== null) {
                    $sortKey .= '_menu_' . $menuValue;
                }
                
                // 初始化計數器 (第一次執行時，從該 Scope 已有的最大序號開始)
                if (!isset($sortCounter[$sortKey])) {
                    $scopeWhere = ["{$langField} = :lang"];
                    $scopeParams = [':lang' => $targetLang];
                    
                    if ($hasHierarchy && $parentIdField) {
                        $pIdValue = (int)($rowData[$parentIdField] ?? 0);
                        if ($pIdValue > 0) {
                            $scopeWhere[] = "{$parentIdField} = :pid";
                            $scopeParams[':pid'] = $pIdValue;
                        } else {
                            $scopeWhere[] = "({$parentIdField} = 0 OR {$parentIdField} IS NULL)";
                        }
                    }
                    if ($menuKey && $menuValue !== null) {
                        $scopeWhere[] = "{$menuKey} = :menuValue";
                        $scopeParams[':menuValue'] = $menuValue;
                    }
                    
                    $sqlCount = "SELECT MAX({$sortField}) FROM {$tableName} WHERE " . implode(' AND ', $scopeWhere);
                    $stmtCount = $conn->prepare($sqlCount);
                    $stmtCount->execute($scopeParams);
                    $maxSort = (int)$stmtCount->fetchColumn();
                    $sortCounter[$sortKey] = $maxSort;
                }
                
                // 遞增並設定排序號碼
                $sortCounter[$sortKey]++;
                $rowData[$sortField] = $sortCounter[$sortKey];
            }

            // 執行插入
            $columns = array_keys($rowData);
            $values = array_values($rowData);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $colList = implode(',', $columns);
            $sqlInsert = "INSERT INTO {$tableName} ($colList) VALUES ($placeholders)";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->execute($values);
            $newId = $conn->lastInsertId();
            $successCount++;

            // 複製檔案
            $sqlFiles = "SELECT * FROM file_set WHERE {$fileFk} = :oldId";
            $stmtFiles = $conn->prepare($sqlFiles);
            $stmtFiles->execute([':oldId' => $itemId]);
            $fileIdMap = [];
            foreach ($stmtFiles->fetchAll(PDO::FETCH_ASSOC) as $file) {
                $oldFileId = $file['file_id'];
                unset($file['file_id']);
                $file[$fileFk] = $newId;

                // 【修正】為每個檔案記錄生成唯一的基礎檔名（類似 upload_process.php 的邏輯）
                $basePhotoName = md5($module . $newId . $oldFileId . time() . rand(1000, 9999));

                foreach (['file_link1', 'file_link2', 'file_link3', 'file_link4', 'file_link5'] as $linkKey) {
                    if (!empty($file[$linkKey])) {
                        $srcPath = "../" . $file[$linkKey];
                        if (file_exists($srcPath)) {
                            $pathInfo = pathinfo($file[$linkKey]);
                            $destRelDir = $pathInfo['dirname'] . "/";
                            $destAbsDir = "../" . $destRelDir;
                            if (!is_dir($destAbsDir)) @mkdir($destAbsDir, 0777, true);

                            // 【修正】根據原始檔名的後綴來生成新檔名
                            // 例如：原始 file_link1 = "popInfo_abc.png"
                            //      原始 file_link2 = "popInfo_abc_s100.png"
                            //      原始 file_link3 = "popInfo_abc_s460.png"
                            $originalBasename = $pathInfo['filename']; // 不含副檔名
                            $extension = $pathInfo['extension'];

                            // 檢查是否有尺寸後綴（_s100, _s460 等）
                            if (preg_match('/_s\d+$/', $originalBasename, $matches)) {
                                // 有尺寸後綴，保留它
                                $suffix = $matches[0]; // 例如 "_s100"
                                $newFileName = $module . "_" . $basePhotoName . $suffix . "." . $extension;
                            } else {
                                // 沒有後綴，這是原始大圖
                                $newFileName = $module . "_" . $basePhotoName . "." . $extension;
                            }

                            if (@copy($srcPath, $destAbsDir . $newFileName)) {
                                $file[$linkKey] = $destRelDir . $newFileName;
                            }
                        }
                    }
                }
                $fCols = array_keys($file);
                $fPlaceholders = implode(',', array_fill(0, count($fCols), '?'));
                $stmtInsertFile = $conn->prepare("INSERT INTO file_set (" . implode(',', $fCols) . ") VALUES ($fPlaceholders)");
                $stmtInsertFile->execute(array_values($file));
                $fileIdMap[$oldFileId] = $conn->lastInsertId();
            }

            // 複製動態欄位
            $stmtDynamic = $conn->prepare("SELECT * FROM data_dynamic_fields WHERE df_d_id = :oldId");
            $stmtDynamic->execute([':oldId' => $itemId]);
            foreach ($stmtDynamic->fetchAll(PDO::FETCH_ASSOC) as $df) {
                unset($df['df_id']);
                $df['df_d_id'] = $newId;
                if (!empty($df['df_file_id']) && isset($fileIdMap[$df['df_file_id']])) $df['df_file_id'] = $fileIdMap[$df['df_file_id']];
                $dfCols = array_keys($df);
                $dfPlaceholders = implode(',', array_fill(0, count($dfCols), '?'));
                $conn->prepare("INSERT INTO data_dynamic_fields (" . implode(',', $dfCols) . ") VALUES ($dfPlaceholders)")->execute(array_values($df));
            }

            // 複製分類關聯 (Taxonomy Map)
            if ($moduleConfig['listPage']['useTaxonomyMapSort'] ?? false) {
                require_once __DIR__ . '/includes/taxonomyMapHelper.php';
                if (hasTaxonomyMapTable($conn)) {
                    foreach (getTaxonomyMapWithLevels($conn, $itemId) as $m) {
                        $newTaxId = 0;
                        if ($sourceLang === $targetLang) {
                            $newTaxId = $m['t_id'];
                        } else if ($categoryName) {
                            $catConfigFile = __DIR__ . "/set/{$categoryName}Set.php";
                            if (file_exists($catConfigFile)) {
                                $catConfig = require $catConfigFile;
                                $cTable = $catConfig['tableName'] ?? 'taxonomies';
                                $cPK = $catConfig['primaryKey'] ?? 't_id';
                                $cTitleCol = $catConfig['cols']['title'] ?? ($cTable == 'taxonomies' ? 't_name' : 'd_title');
                                $stmtOldCat = $conn->prepare("SELECT {$cTitleCol} FROM {$cTable} WHERE {$cPK} = :id");
                                $stmtOldCat->execute([':id' => $m['t_id']]);
                                $oldName = $stmtOldCat->fetchColumn();
                                if ($oldName) {
                                    $stmtNewCat = $conn->prepare("SELECT {$cPK} FROM {$cTable} WHERE {$cTitleCol} = :title AND lang = :lang LIMIT 1");
                                    $stmtNewCat->execute([':title' => $oldName, ':lang' => $targetLang]);
                                    $newTaxId = $stmtNewCat->fetchColumn();
                                }
                            }
                        }
                        if ($newTaxId > 0) {
                            // 【新增】計算新關聯在目標分類中的初始排序 (Max + 1)
                            $stmtMax = $conn->prepare("
                                SELECT MAX(dtm.sort_num) 
                                FROM data_taxonomy_map dtm
                                INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$primaryKey}
                                WHERE dtm.t_id = ? AND dtm.map_level = ? AND ds.lang = ?
                            ");
                            $stmtMax->execute([$newTaxId, $m['map_level'], $targetLang]);
                            $newSortNum = (int)$stmtMax->fetchColumn() + 1;

                            $conn->prepare("INSERT INTO data_taxonomy_map (d_id, t_id, map_level, sort_num) VALUES (?, ?, ?, ?)")
                                 ->execute([$newId, $newTaxId, $m['map_level'], $newSortNum]);
                        }
                    }
                }
            }

            // --- ⭐ 遞迴複製子層 ⭐ ---
            if ($hasHierarchy && $parentIdField) {
                $sqlChildren = "SELECT {$primaryKey} FROM {$tableName} WHERE {$parentIdField} = :id";
                $stmtChildren = $conn->prepare($sqlChildren);
                $stmtChildren->execute([':id' => $itemId]);
                $childrenIds = $stmtChildren->fetchAll(PDO::FETCH_COLUMN);
                foreach ($childrenIds as $childId) {
                    $cloneItem($childId, $newId);
                }
            }

        } catch (Throwable $e) {
            $errorMsg = "ID {$itemId}: " . $e->getMessage();
            $errors[] = $errorMsg;
            file_put_contents('debug_clone_error.log', date('Y-m-d H:i:s') . " - " . $errorMsg . "\n", FILE_APPEND);
        }
    };

    foreach ($itemIds as $itemId) {
        $cloneItem($itemId);
    }
    
    // --- ⭐ 全域重排 (增強版：支援多層級獨立重排與 Taxonomy Map) ⭐ ---
    require_once __DIR__ . '/includes/UnifiedSortManager.php';
    UnifiedSortManager::updateAfterDataChange($conn, $moduleConfig, null, [
        'lang' => $targetLang
    ]);
    
    $conn->commit();
    echo json_encode([
        'success' => true,
        'count' => $successCount,
        'errors' => $errors,
        'message' => "成功複製 {$successCount} 筆資料 (含子層與完整排序重整)"
    ]);
    
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Exception;
use PDO;

class AdminController extends Controller
{
    /**
     * 檢查是否為管理員登入狀態
     */
    private function requireAdmin()
    {
        if (!$this->isAdmin) {
            throw new Exception('請先登入管理員帳號', 401);
        }
    }

    private function log($message)
    {
        file_put_contents(BASE_PATH_CMS . '/debug_log.txt', "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * 通用刪除 API (支援單筆與批次)
     */
    public function delete(Request $request, Response $response, array $args)
    {
        try {
            $this->requireAdmin();
            $data = $request->getParsedBody();
            $module = preg_replace('/[^a-zA-Z0-9_]/', '', $data['module'] ?? '');

            // 支援單一 ID 或批次 ID
            $idStr = $data['item_ids'] ?? ($data['id'] ?? ($data['item_id'] ?? ''));
            $itemIds = is_array($idStr) ? $idStr : (strpos($idStr, ',') !== false ? explode(',', $idStr) : [$idStr]);
            $itemIds = array_filter(array_map('intval', $itemIds));

            if (empty($module) || empty($itemIds)) return $this->jsonResponse($response, '缺少參數', 400);

            // 【新增】接收 trash 參數，判斷是否在垃圾桶內
            $isTrashMode = !empty($data['trash']) && $data['trash'] == '1';

            require_once BASE_PATH_CMS . '/includes/elements/PermissionElement.php';
            require_once BASE_PATH_CMS . '/includes/elements/ModuleConfigElement.php';
            require_once BASE_PATH_CMS . '/includes/taxonomyMapHelper.php';
            require_once BASE_PATH_CMS . '/includes/SortReorganizer.php';
            require_once BASE_PATH_CMS . '/includes/UnifiedSortManager.php';
            
            list($canView, $canAdd, $canEdit, $canDelete) = \PermissionElement::checkModulePermission($this->pdo, $module);
            if (!$canDelete) return $this->jsonResponse($response, '無刪除權限', 403);

            $moduleConfig = \ModuleConfigElement::loadConfig($module);
            $tableName = $moduleConfig['tableName'];
            $col_id = $moduleConfig['primaryKey'];
            $cols = $moduleConfig['cols'] ?? [];
            $col_delete_time = $cols['delete_time'] ?? 'd_delete_time';
            $col_sort = $cols['sort'] ?? 'd_sort';
            $col_file_fk = $cols['file_fk'] ?? 'file_d_id';
            $parentIdField = $cols['parent_id'] ?? null;
            $menuKey = $moduleConfig['menuKey'] ?? null;
            $menuValue = $moduleConfig['menuValue'] ?? null;

            $force = !empty($data['force']);

            $this->pdo->beginTransaction();

            // 收集受影響的分類資訊 (用於後續重排)
            $affectedMappings = [];
            $itemLang = null;
            $itemsToDelete = []; // 收集要刪除的項目資訊
            $categoriesWithArticles = []; // 初始化，用於收集有文章的分類
            $databasesToDrop = []; // 【新增】收集要刪除的資料庫
            $foldersToDelete = []; // 【新增】收集要刪除的資料夾

            // 第一階段：收集資訊並判斷刪除類型
            foreach ($itemIds as $id) {
                $stmt = $this->pdo->prepare("SELECT * FROM {$tableName} WHERE {$col_id} = :id");
                $stmt->execute([':id' => $id]);
                $itemToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$itemToDelete) continue;

                $itemLang = $itemToDelete['lang'] ?? null;

                // 判斷軟硬刪
                $hasTrashConfig = $moduleConfig['listPage']['hasTrash'] ?? null;
                $hasHierarchy = $moduleConfig['listPage']['hasHierarchy'] ?? false;

                // 【還原】階層結構不使用軟刪除，避免複雜的垃圾桶管理問題
                // 【新增】在垃圾桶內時，強制使用硬刪除（永久刪除）
                $isSoftDelete = ($hasTrashConfig !== false && !empty($col_delete_time) && !$hasHierarchy && !$isTrashMode);
                if ($isSoftDelete) {
                    $check = $this->pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE '{$col_delete_time}'");
                    $check->execute();
                    if ($check->rowCount() == 0) $isSoftDelete = false;
                }
                
                // 【新增】批次刪除時，檢查文章關聯（只在非強制模式下檢查）
                // 只有當刪除的是分類資料表(taxonomies)時才需要檢查文章關聯
                if (!$force && hasTaxonomyMapTable($this->pdo) && $tableName === 'taxonomies') {
                    $articleQuery = "SELECT COUNT(*) as article_count FROM data_taxonomy_map WHERE t_id = :id";
                    $articleStmt = $this->pdo->prepare($articleQuery);
                    $articleStmt->execute([':id' => $id]);
                    $articleResult = $articleStmt->fetch(PDO::FETCH_ASSOC);
                    $articleCount = (int)$articleResult['article_count'];

                    if ($articleCount > 0) {
                        // 收集有文章的分類資訊
                        $titleField = $cols['title'] ?? 't_name';
                        $categoryName = $itemToDelete[$titleField] ?? "ID: {$id}";

                        $categoriesWithArticles[] = "{$categoryName}（{$articleCount} 篇文章）";
                    }
                }

                // 【修改】支援級聯軟刪除
                $cascade = !empty($data['cascade']);
                $descendantIds = [];
                
                if ($hasHierarchy && $parentIdField && $id === $itemIds[0]) {
                    // 檢查是否有子項目
                    $stmtChild = $this->pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE {$parentIdField} = :id");
                    $stmtChild->execute([':id' => $id]);
                    $childCount = $stmtChild->fetchColumn();
                    
                    if ($childCount > 0) {
                        if ($cascade || $isSoftDelete) {
                            // 級聯刪除或軟刪除：遞迴收集所有子孫項目
                            $descendantIds = $this->getDescendantIds($tableName, $id, $parentIdField);
                        } else {
                            // 硬刪除且未指定 cascade：阻止刪除
                            return $this->jsonResponse($response, ['message' => "此分類下尚有子項目。", 'has_data' => true], 200);
                        }
                    }
                }

                // 【核心】取得受影響的 Mapping 資訊 (用於後續重排)
                if (hasTaxonomyMapTable($this->pdo)) {
                    $mappings = getTaxonomyMapWithLevels($this->pdo, $id);
                    foreach ($mappings as $m) $affectedMappings[] = $m;
                }

                // 記錄要刪除的項目
                $itemsToDelete[] = [
                    'id' => $id,
                    'isSoftDelete' => $isSoftDelete
                ];
                
                // 【新增】如果有子孫項目，也加入刪除列表
                if (!empty($descendantIds)) {
                    foreach ($descendantIds as $descendantId) {
                        // 收集子孫的 mapping 資訊
                        if (hasTaxonomyMapTable($this->pdo)) {
                            $mappings = getTaxonomyMapWithLevels($this->pdo, $descendantId);
                            foreach ($mappings as $m) $affectedMappings[] = $m;
                        }
                        
                        // 加入刪除列表（使用相同的刪除類型）
                        $itemsToDelete[] = [
                            'id' => $descendantId,
                            'isSoftDelete' => $isSoftDelete
                        ];
                    }
                }
            }

            // 【修改】如果發現有文章關聯,根據刪除類型決定處理方式
            if (!empty($categoriesWithArticles)) {
                // 檢查是否為軟刪除
                $firstItem = $itemsToDelete[0] ?? null;
                $isSoftDeleteMode = $firstItem['isSoftDelete'] ?? false;

                if ($isSoftDeleteMode) {
                    // 【新增】軟刪除模式：顯示提示訊息，詢問是否繼續
                    // 如果沒有 confirm 參數，先顯示提示訊息
                    if (empty($data['confirm'])) {
                        $message = implode("\n", $categoriesWithArticles);
                        return $this->jsonResponse($response, [
                            'success' => false,
                            'needs_confirm' => true,
                            'is_soft_delete' => true,
                            'message' => $message,
                            'categories_info' => $categoriesWithArticles
                        ], 200);
                    }
                    // 如果有 confirm 參數，繼續執行軟刪除
                } else {
                    // 硬刪除模式:需要 force 才能永久刪除
                    if (!$force) {
                        $message = "以下分類有關聯的文章，將被永久刪除：\n" . implode("\n", $categoriesWithArticles);
                        return $this->jsonResponse($response, [
                            'success' => false,
                            'has_data' => true,
                            'needs_force' => true,
                            'is_soft_delete' => false,
                            'message' => $message,
                            'categories_info' => $categoriesWithArticles
                        ], 200);
                    }
                }
            }

            // 【修改】處理關聯的文章 - 混合方案
            if (hasTaxonomyMapTable($this->pdo) && $tableName === 'taxonomies') {
                foreach ($itemIds as $categoryId) {
                    // 查詢使用此分類的文章 ID
                    $articleQuery = "SELECT DISTINCT d_id FROM data_taxonomy_map WHERE t_id = :categoryId";
                    $articleStmt = $this->pdo->prepare($articleQuery);
                    $articleStmt->execute([':categoryId' => $categoryId]);
                    $articleIds = $articleStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($articleIds)) {
                        // 找出文章所屬的資料表
                        $dataTableName = 'data_set';
                        $dataDeleteTimeCol = 'd_delete_time';

                        // 檢查第一個項目的刪除類型
                        $firstItem = $itemsToDelete[0] ?? null;
                        $isSoftDeleteMode = $firstItem['isSoftDelete'] ?? false;

                        foreach ($articleIds as $articleId) {
                            // 【關鍵】檢查文章屬於幾個分類
                            $categoryCountQuery = "SELECT COUNT(DISTINCT t_id) as category_count
                                                   FROM data_taxonomy_map
                                                   WHERE d_id = :articleId";
                            $categoryCountStmt = $this->pdo->prepare($categoryCountQuery);
                            $categoryCountStmt->execute([':articleId' => $articleId]);
                            $categoryCountResult = $categoryCountStmt->fetch(PDO::FETCH_ASSOC);
                            $categoryCount = (int)$categoryCountResult['category_count'];

                            if ($categoryCount <= 1) {
                                // 文章只屬於一個分類,跟著分類一起處理
                                if ($isSoftDeleteMode) {
                                    // 【軟刪除】將文章移到垃圾桶
                                    $checkCol = $this->pdo->prepare("SHOW COLUMNS FROM {$dataTableName} LIKE '{$dataDeleteTimeCol}'");
                                    $checkCol->execute();
                                    if ($checkCol->rowCount() > 0) {
                                        $this->pdo->prepare("UPDATE {$dataTableName} SET {$dataDeleteTimeCol} = NOW() WHERE d_id = :id")
                                                  ->execute([':id' => $articleId]);
                                    }
                                } else {
                                    // 【硬刪除】永久刪除文章(需要 force)
                                    if ($force) {
                                        // 先刪除文章的附件檔案
                                        $this->cleanupFiles($articleId, 'file_d_id');

                                        // 刪除 file_set 中的記錄
                                        $deleteFileStmt = $this->pdo->prepare("DELETE FROM file_set WHERE file_d_id = :id");
                                        $deleteFileStmt->execute([':id' => $articleId]);

                                        // 硬刪除文章
                                        $deleteArticleStmt = $this->pdo->prepare("DELETE FROM {$dataTableName} WHERE d_id = :id");
                                        $deleteArticleStmt->execute([':id' => $articleId]);

                                        // 刪除文章的 mapping
                                        $deleteMappingStmt = $this->pdo->prepare("DELETE FROM data_taxonomy_map WHERE d_id = :id");
                                        $deleteMappingStmt->execute([':id' => $articleId]);
                                    }
                                }
                            } else {
                                // 文章屬於多個分類,只移除該分類的關聯,文章保留
                                // 不做任何處理,mapping 會在後面的階段被刪除
                            }
                        }
                    }
                }
            }

            // 第二階段：先執行刪除操作（軟刪除會保留 mapping，硬刪除會刪除 mapping）
            foreach ($itemsToDelete as $item) {
                $id = $item['id'];
                $isSoftDelete = $item['isSoftDelete'];

                if ($isSoftDelete) {
                    // 軟刪除：只更新 delete_time，保留 mapping
                    $this->pdo->prepare("UPDATE {$tableName} SET {$col_delete_time} = NOW() WHERE {$col_id} = :id")->execute([':id' => $id]);
                    
                    // 調整 taxonomy map 排序，填補空洞
                    $stmtMap = $this->pdo->prepare("SELECT t_id, sort_num FROM data_taxonomy_map WHERE d_id = :id");
                    $stmtMap->execute([':id' => $id]);
                    $mapItems = $stmtMap->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($mapItems as $m) {
                        $tid = $m['t_id'];
                        $deletedSort = $m['sort_num'];
                        $sql = "UPDATE data_taxonomy_map dtm
                                INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$col_id}
                                SET dtm.sort_num = dtm.sort_num - 1
                                WHERE dtm.t_id = :tid
                                  AND dtm.sort_num > :deletedSort
                                  AND dtm.d_id != :id
                                  AND (ds.{$col_delete_time} IS NULL OR ds.{$col_delete_time} = '0000-00-00 00:00:00')";
                        $this->pdo->prepare($sql)->execute([
                            ':tid' => $tid,
                            ':deletedSort' => $deletedSort,
                            ':id' => $id
                        ]);
                    }
                } else {
                    // 硬刪除：先標記為軟刪除（讓重排邏輯能正確過濾），稍後再真正刪除
                    if (!empty($col_delete_time)) {
                        $checkCol = $this->pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE '{$col_delete_time}'");
                        $checkCol->execute();
                        if ($checkCol->rowCount() > 0) {
                            $this->pdo->prepare("UPDATE {$tableName} SET {$col_delete_time} = NOW() WHERE {$col_id} = :id")->execute([':id' => $id]);
                        }
                    }
                }
            }

            // 第三階段：重排序（此時軟刪除的資料會被過濾，硬刪除的資料也被標記為軟刪除）
            $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;

            if ($configUseTaxonomyMapSort && !empty($affectedMappings)) {
                // 重排受影響的分類
                require_once BASE_PATH_CMS . '/includes/taxonomyMapHelper.php';
                $processedCategories = [];
                foreach ($affectedMappings as $m) {
                    $key = $m['t_id'] . '_' . ($m['map_level'] ?? 1);
                    if (!isset($processedCategories[$key])) {
                        reorderTaxonomyMap($this->pdo, intval($m['t_id']), intval($m['map_level'] ?? 1), [
                            $menuKey => $menuValue,
                            'lang' => $itemLang
                        ], $tableName);
                        $processedCategories[$key] = true;
                    }
                }
            }

            // 【重要】無論是否使用 taxonomy map，都要重排主表排序（用於「全部」視圖）
            \UnifiedSortManager::updateAfterDataChange($this->pdo, $moduleConfig, null, [
                'lang' => $itemLang
            ]);

            // 第四階段：真正執行硬刪除（刪除檔案、file_set、主表資料、mapping）
            foreach ($itemsToDelete as $item) {
                $id = $item['id'];
                $isSoftDelete = $item['isSoftDelete'];

                // 【新增強制刪除】如果是網站管理模組，無論軟刪除或硬刪除都要「強制刪除」子網站的資料庫與實體資料夾
                if ($module === 'websites') {
                    $stmtDb = $this->pdo->prepare("SELECT d_title_en, d_slug, d_data2 FROM {$tableName} WHERE {$col_id} = :id");
                    $stmtDb->execute([':id' => $id]);
                    $siteData = $stmtDb->fetch(PDO::FETCH_ASSOC);

                    if ($siteData) {
                        $targetDbName = $siteData['d_data2'];
                        // 優先使用 d_slug，若無則從英文標題產生
                        if (!class_exists('\SubsiteHelper')) {
                            require_once BASE_PATH_CMS . '/includes/SubsiteHelper.php';
                        }
                        $targetSlug = !empty($siteData['d_slug']) ? $siteData['d_slug'] : \SubsiteHelper::sanitizeSlug($siteData['d_title_en'] ?? '');

                        // 1. 記錄資料庫 (DROP DATABASE IF EXISTS 是安全的)
                        if ($targetDbName && !$isSoftDelete) {
                            $currentDb = $this->pdo->query("SELECT DATABASE()")->fetchColumn();
                            // 絕對不可以把自己主系統的 DB 刪掉
                            if ($targetDbName !== $currentDb && $targetDbName !== 'template_ver3') {
                                $databasesToDrop[] = $targetDbName;
                            }
                        }

                        // 2. 記錄資料夾 (recursiveRmdir 會檢查是否存在)
                        if ($targetSlug && !$isSoftDelete) {
                            // 直接從根目錄 (ROOT_PATH) 推算 WAMP www 同級目錄
                            $targetDir = realpath(ROOT_PATH . '/../') . DIRECTORY_SEPARATOR . $targetSlug;
                            $foldersToDelete[] = $targetDir;
                        }
                    }
                }

                if (!$isSoftDelete) {


                    // 硬刪除：清理檔案和資料
                    $this->cleanupFiles($id, $col_file_fk);
                    $this->pdo->prepare("DELETE FROM file_set WHERE {$col_file_fk} = :id")->execute([':id' => $id]);
                    $this->pdo->prepare("DELETE FROM {$tableName} WHERE {$col_id} = :id")->execute([':id' => $id]);
                    if (hasTaxonomyMapTable($this->pdo)) {
                        deleteTaxonomyMap($this->pdo, $id);
                    }
                }
            }

            $this->pdo->commit();

            // 【新增】在交易成功提交後，再執行 DDL 刪除資料庫與資料夾
            foreach ($databasesToDrop as $dbName) {
                try {
                    $this->pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
                } catch (Exception $e) {
                    $this->log("Warning: Failed to drop database {$dbName}: " . $e->getMessage());
                }
            }

            foreach ($foldersToDelete as $folderPath) {
                $this->log("[FOLDER DELETE] Attempting: {$folderPath} | exists=" . (is_dir($folderPath) ? 'YES' : 'NO'));
                if (is_dir($folderPath)) {
                    $result = $this->recursiveRmdir($folderPath);
                    $stillExists = is_dir($folderPath) ? 'STILL EXISTS' : 'GONE';
                    $this->log("[FOLDER DELETE] Result: {$stillExists} | rmdir_return=" . var_export($result, true));
                } else {
                    $this->log("[FOLDER DELETE] SKIP: directory does not exist at {$folderPath}");
                }
            }

            return $this->jsonResponse($response, '刪除成功');
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->log("Error in " . __METHOD__ . ": " . $e->getMessage());
            return $this->jsonResponse($response, $e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * 還原資料 API (支援單筆與批次)
     */
    public function restore(Request $request, Response $response, array $args)
    {
        try {
            $this->requireAdmin();
            $data = $request->getParsedBody();
            $module = preg_replace('/[^a-zA-Z0-9_]/', '', $data['module'] ?? '');
            
            // 支援單一 ID 或批次 ID
            $idStr = $data['item_ids'] ?? ($data['id'] ?? ($data['item_id'] ?? ''));
            $itemIds = is_array($idStr) ? $idStr : (strpos($idStr, ',') !== false ? explode(',', $idStr) : [$idStr]);
            $itemIds = array_filter(array_map('intval', $itemIds));

            if (empty($module) || empty($itemIds)) return $this->jsonResponse($response, '缺少參數', 400);

            require_once BASE_PATH_CMS . '/includes/elements/PermissionElement.php';
            require_once BASE_PATH_CMS . '/includes/elements/ModuleConfigElement.php';
            require_once BASE_PATH_CMS . '/includes/taxonomyMapHelper.php';
            require_once BASE_PATH_CMS . '/includes/SortReorganizer.php';
            require_once BASE_PATH_CMS . '/includes/UnifiedSortManager.php';
            
            list($canView, $canAdd, $canEdit, $canDelete) = \PermissionElement::checkModulePermission($this->pdo, $module);
            if (!$canEdit) return $this->jsonResponse($response, '無編輯權限', 403);

            $moduleConfig = \ModuleConfigElement::loadConfig($module);
            $tableName = $moduleConfig['tableName'];
            $col_id = $moduleConfig['primaryKey'];
            $cols = $moduleConfig['cols'] ?? [];
            $col_delete_time = $cols['delete_time'] ?? 'd_delete_time';
            $col_sort = $cols['sort'] ?? 'd_sort';
            $parentIdField = $cols['parent_id'] ?? null;
            $menuKey = $moduleConfig['menuKey'] ?? null;
            $menuValue = $moduleConfig['menuValue'] ?? null;

            $this->pdo->beginTransaction();

            $affectedMappings = [];
            $itemLang = null;
            $categoriesToRestore = []; // 收集需要還原的分類
            $articlesToRestore = []; // 收集需要還原的文章

            foreach ($itemIds as $id) {
                // 取得項目資訊
                $stmt = $this->pdo->prepare("SELECT * FROM {$tableName} WHERE {$col_id} = :id");
                $stmt->execute([':id' => $id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) continue;

                $itemLang = $item['lang'] ?? null;

                // 收集受影響的分類資訊
                if (hasTaxonomyMapTable($this->pdo)) {
                    $mappings = getTaxonomyMapWithLevels($this->pdo, $id);
                    foreach ($mappings as $m) $affectedMappings[] = $m;

                    // 【新增】如果還原的是文章，檢查關聯的分類是否在垃圾桶
                    if ($tableName === 'data_set') {
                        foreach ($mappings as $m) {
                            $categoryId = $m['t_id'];

                            // 檢查分類是否在垃圾桶（使用正確的欄位名稱）
                            $checkStmt = $this->pdo->prepare("
                                SELECT t_id, t_name, deleted_at FROM taxonomies
                                WHERE t_id = :id
                                AND (deleted_at IS NOT NULL AND deleted_at != '0000-00-00 00:00:00')
                            ");
                            $checkStmt->execute([':id' => $categoryId]);
                            $deletedCategory = $checkStmt->fetch(PDO::FETCH_ASSOC);

                            if ($deletedCategory) {
                                // 記錄需要還原的分類（避免重複）
                                if (!isset($categoriesToRestore[$categoryId])) {
                                    $categoriesToRestore[$categoryId] = $deletedCategory['t_name'];
                                }
                            }
                        }
                    }

                    // 【新增】如果還原的是分類，檢查關聯的文章是否在垃圾桶
                    if ($tableName === 'taxonomies') {
                        // 查詢使用此分類的文章
                        $articleQuery = "
                            SELECT DISTINCT ds.d_id, ds.d_title
                            FROM data_taxonomy_map dtm
                            INNER JOIN data_set ds ON dtm.d_id = ds.d_id
                            WHERE dtm.t_id = :categoryId
                            AND (ds.d_delete_time IS NOT NULL AND ds.d_delete_time != '0000-00-00 00:00:00')
                        ";
                        $articleStmt = $this->pdo->prepare($articleQuery);
                        $articleStmt->execute([':categoryId' => $id]);
                        $deletedArticles = $articleStmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($deletedArticles as $article) {
                            // 檢查文章是否只屬於這個分類
                            $categoryCountQuery = "
                                SELECT COUNT(DISTINCT t_id) as category_count
                                FROM data_taxonomy_map
                                WHERE d_id = :articleId
                            ";
                            $categoryCountStmt = $this->pdo->prepare($categoryCountQuery);
                            $categoryCountStmt->execute([':articleId' => $article['d_id']]);
                            $categoryCountResult = $categoryCountStmt->fetch(PDO::FETCH_ASSOC);
                            $categoryCount = (int)$categoryCountResult['category_count'];

                            // 只還原只屬於這個分類的文章
                            if ($categoryCount <= 1) {
                                if (!isset($articlesToRestore[$article['d_id']])) {
                                    $articlesToRestore[$article['d_id']] = $article['d_title'];
                                }
                            }
                        }
                    }
                }

                // 在還原前，幫它在 taxonomy map 中騰出原有排序的空位
                $stmtMap = $this->pdo->prepare("SELECT t_id, sort_num FROM data_taxonomy_map WHERE d_id = :id");
                $stmtMap->execute([':id' => $id]);
                $mapItems = $stmtMap->fetchAll(PDO::FETCH_ASSOC);
                foreach ($mapItems as $m) {
                    $tid = $m['t_id'];
                    $restoredSort = $m['sort_num'];
                    $sql = "UPDATE data_taxonomy_map dtm
                            INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$col_id}
                            SET dtm.sort_num = dtm.sort_num + 1
                            WHERE dtm.t_id = :tid
                              AND dtm.sort_num >= :restoredSort
                              AND dtm.d_id != :id
                              AND (ds.{$col_delete_time} IS NULL OR ds.{$col_delete_time} = '0000-00-00 00:00:00')";
                    $this->pdo->prepare($sql)->execute([
                        ':tid' => $tid,
                        ':restoredSort' => $restoredSort,
                        ':id' => $id
                    ]);
                }

                // 也幫它在主表騰出空位
                if (!empty($col_sort)) {
                    $itemSort = $item[$col_sort] ?? 0;
                    if ($itemSort > 0) {
                        $menuCond = "";
                        $params = [':restoredSort' => $itemSort, ':id' => $id];
                        if ($menuKey && $menuValue !== null) {
                            $menuCond .= " AND {$menuKey} = :menuValue ";
                            $params[':menuValue'] = $menuValue;
                        }
                        if ($itemLang) {
                            $menuCond .= " AND lang = :lang ";
                            $params[':lang'] = $itemLang;
                        }
                        $sqlMain = "UPDATE {$tableName} 
                                    SET {$col_sort} = {$col_sort} + 1 
                                    WHERE {$col_sort} >= :restoredSort 
                                      AND {$col_id} != :id
                                      AND ({$col_delete_time} IS NULL OR {$col_delete_time} = '0000-00-00 00:00:00')
                                      {$menuCond}";
                        $this->pdo->prepare($sqlMain)->execute($params);
                    }
                }

                // 執行還原
                $this->pdo->prepare("UPDATE {$tableName} SET {$col_delete_time} = NULL WHERE {$col_id} = :id")
                          ->execute([':id' => $id]);
            }

            // 【新增】自動還原關聯的分類
            if (!empty($categoriesToRestore)) {
                $this->log("Auto-restoring categories for restored articles: " . implode(', ', array_keys($categoriesToRestore)));

                foreach (array_keys($categoriesToRestore) as $categoryId) {
                    // 還原分類
                    $this->pdo->prepare("UPDATE taxonomies SET deleted_at = NULL WHERE t_id = :id")
                              ->execute([':id' => $categoryId]);

                    // 收集分類的 mapping 資訊（用於後續重排）
                    if (hasTaxonomyMapTable($this->pdo)) {
                        $categoryMappings = getTaxonomyMapWithLevels($this->pdo, $categoryId);
                        foreach ($categoryMappings as $m) {
                            $affectedMappings[] = $m;
                        }
                    }
                }
            }

            // 【新增】自動還原關聯的文章
            if (!empty($articlesToRestore)) {
                $this->log("Auto-restoring articles for restored category: " . implode(', ', array_keys($articlesToRestore)));

                foreach (array_keys($articlesToRestore) as $articleId) {
                    // 還原文章
                    $this->pdo->prepare("UPDATE data_set SET d_delete_time = NULL WHERE d_id = :id")
                              ->execute([':id' => $articleId]);

                    // 收集文章的 mapping 資訊（用於後續重排）
                    if (hasTaxonomyMapTable($this->pdo)) {
                        $articleMappings = getTaxonomyMapWithLevels($this->pdo, $articleId);
                        foreach ($articleMappings as $m) {
                            $affectedMappings[] = $m;
                        }
                    }
                }
            }

            // -------------------------------------------------------------
            // 【重要】還原後使用統一排序管理器進行全域與分類重排
            // -------------------------------------------------------------
            $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;

            if ($configUseTaxonomyMapSort && !empty($affectedMappings)) {
                // 重排受影響的分類
                require_once BASE_PATH_CMS . '/includes/taxonomyMapHelper.php';
                $processedCategories = [];
                foreach ($affectedMappings as $m) {
                    $key = $m['t_id'] . '_' . ($m['map_level'] ?? 1);
                    if (!isset($processedCategories[$key])) {
                        reorderTaxonomyMap($this->pdo, intval($m['t_id']), intval($m['map_level'] ?? 1), [
                            $menuKey => $menuValue,
                            'lang' => $itemLang
                        ], $tableName);
                        $processedCategories[$key] = true;
                    }
                }
            }

            // 【重要】無論是否使用 taxonomy map，都要重排主表排序（用於「全部」視圖）
            \UnifiedSortManager::updateAfterDataChange($this->pdo, $moduleConfig, null, [
                'lang' => $itemLang
            ]);

            $this->pdo->commit();

            // 【新增】組合還原成功訊息
            $messages = [];
            if (!empty($categoriesToRestore)) {
                $categoryNames = implode('、', $categoriesToRestore);
                $messages[] = "同時已自動還原關聯的分類：{$categoryNames}";
            }
            if (!empty($articlesToRestore)) {
                $articleCount = count($articlesToRestore);
                $messages[] = "同時已自動還原 {$articleCount} 篇關聯的文章";
            }

            if (!empty($messages)) {
                $message = "還原成功！" . implode('；', $messages);
                return $this->jsonResponse($response, $message);
            }

            return $this->jsonResponse($response, '還原成功');
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->log("Error in " . __METHOD__ . ": " . $e->getMessage());
            return $this->jsonResponse($response, $e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * 切換顯示狀態 API
     */
    public function toggleActive(Request $request, Response $response, array $args)
    {
        try {
            $this->requireAdmin();
            $data = $request->getParsedBody();
            $module = preg_replace('/[^a-zA-Z0-9_]/', '', $data['module'] ?? '');
            $itemId = intval($data['item_id'] ?? 0);
            $newValue = intval($data['new_value'] ?? 0);

            if (empty($module) || $itemId <= 0) return $this->jsonResponse($response, '缺少參數', 400);

            require_once BASE_PATH_CMS . '/includes/elements/ModuleConfigElement.php';
            $moduleConfig = \ModuleConfigElement::loadConfig($module);
            $tableName = $moduleConfig['tableName'];
            $primaryKey = $moduleConfig['primaryKey'];
            $col_active = preg_replace('/[^a-zA-Z0-9_]/', '', $data['field'] ?? ($moduleConfig['cols']['active'] ?? 'd_active'));

            $sql = "UPDATE {$tableName} SET {$col_active} = :new_value WHERE {$primaryKey} = :item_id";
            $this->pdo->prepare($sql)->execute([':new_value' => $newValue, ':item_id' => $itemId]);

            return $this->jsonResponse($response, '狀態已更新');
        } catch (Exception $e) {
            return $this->jsonResponse($response, $e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * 排序調整 API
     */
    public function changeSort(Request $request, Response $response, array $args)
    {
        try {
            $this->requireAdmin();
            $data = $request->getParsedBody();
            $module = preg_replace('/[^a-zA-Z0-9_]/', '', $data['module'] ?? '');
            $itemId = intval($data['item_id'] ?? 0);
            $newSort = intval($data['new_sort'] ?? 0);
            $categoryId = intval($data['category_id'] ?? 0);

            if (empty($module) || $itemId <= 0 || $newSort <= 0) return $this->jsonResponse($response, '缺少參數', 400);

            require_once BASE_PATH_CMS . '/includes/elements/ModuleConfigElement.php';
            require_once BASE_PATH_CMS . '/includes/taxonomyMapHelper.php';
            require_once BASE_PATH_CMS . '/includes/SortReorganizer.php';
            require_once BASE_PATH_CMS . '/includes/UnifiedSortManager.php';

            $moduleConfig = \ModuleConfigElement::loadConfig($module);
            $tableName = $moduleConfig['tableName'];
            $primaryKey = $moduleConfig['primaryKey'];
            $cols = $moduleConfig['cols'] ?? [];
            $col_sort = $cols['sort'] ?? 'd_sort';
            $parentIdField = $cols['parent_id'] ?? null;
            $menuKey = $moduleConfig['menuKey'] ?? null;
            $menuValue = $moduleConfig['menuValue'] ?? null;
            $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? false;

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT * FROM {$tableName} WHERE {$primaryKey} = :id");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception('找不到資料');

            $useMapTableSort = ($categoryId > 0 && hasTaxonomyMapTable($this->pdo) && $configUseTaxonomyMapSort);

            // 【關鍵修正】插入式排序：將項目插入到目標位置，其他項目順移
            if ($useMapTableSort) {
                // 在分類視圖下排序
                // 1. 取得當前項目的舊排序值
                $stmtOld = $this->pdo->prepare("SELECT sort_num FROM data_taxonomy_map WHERE d_id = :id AND t_id = :tid");
                $stmtOld->execute([':id' => $itemId, ':tid' => $categoryId]);
                $oldSort = $stmtOld->fetchColumn();

                if ($oldSort && $oldSort != $newSort) {
                    // 2. 根據移動方向調整其他項目的排序
                    if ($oldSort < $newSort) {
                        // 向下移動：將 oldSort+1 到 newSort 之間的項目往上移（-1）
                        $this->pdo->prepare("
                            UPDATE data_taxonomy_map dtm
                            INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$primaryKey}
                            SET dtm.sort_num = dtm.sort_num - 1
                            WHERE dtm.t_id = :tid
                            AND dtm.sort_num > :old_sort
                            AND dtm.sort_num <= :new_sort
                            AND dtm.d_id != :id
                            AND (dtm.d_top = 0 OR dtm.d_top IS NULL)
                        ")->execute([
                            ':tid' => $categoryId,
                            ':old_sort' => $oldSort,
                            ':new_sort' => $newSort,
                            ':id' => $itemId
                        ]);
                    } else {
                        // 向上移動：將 newSort 到 oldSort-1 之間的項目往下移（+1）
                        $this->pdo->prepare("
                            UPDATE data_taxonomy_map dtm
                            INNER JOIN {$tableName} ds ON dtm.d_id = ds.{$primaryKey}
                            SET dtm.sort_num = dtm.sort_num + 1
                            WHERE dtm.t_id = :tid
                            AND dtm.sort_num >= :new_sort
                            AND dtm.sort_num < :old_sort
                            AND dtm.d_id != :id
                            AND (dtm.d_top = 0 OR dtm.d_top IS NULL)
                        ")->execute([
                            ':tid' => $categoryId,
                            ':new_sort' => $newSort,
                            ':old_sort' => $oldSort,
                            ':id' => $itemId
                        ]);
                    }
                }

                // 3. 更新當前項目的排序值
                $this->pdo->prepare("UPDATE data_taxonomy_map SET sort_num = :new_sort WHERE d_id = :id AND t_id = :tid")
                          ->execute([':new_sort' => $newSort, ':id' => $itemId, ':tid' => $categoryId]);
            } else {
                // 在全部視圖下排序
                // 1. 取得當前項目的舊排序值
                $oldSort = $item[$col_sort] ?? null;

                if ($oldSort && $oldSort != $newSort) {
                    // 2. 建立查詢條件
                    $baseConditions = ["1=1"];
                    $baseParams = [];

                    if ($menuKey && $menuValue !== null) {
                        $baseConditions[] = "{$menuKey} = :menuValue";
                        $baseParams[':menuValue'] = $menuValue;
                    }

                    if (isset($item['lang'])) {
                        $baseConditions[] = "lang = :lang";
                        $baseParams[':lang'] = $item['lang'];
                    }

                    // 排除置頂項目（檢查欄位是否存在）
                    $col_top = $cols['top'] ?? 'd_top';
                    try {
                        $checkTopCol = $this->pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
                        $checkTopCol->execute([$col_top]);
                        if ($checkTopCol->fetch()) {
                            $baseConditions[] = "({$col_top} = 0 OR {$col_top} IS NULL)";
                        }
                    } catch (Exception $e) {
                        // 欄位不存在，忽略
                    }

                    // 排除軟刪除項目（檢查欄位是否存在）
                    $col_delete_time = $cols['delete_time'] ?? 'd_delete_time';
                    try {
                        $checkCol = $this->pdo->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
                        $checkCol->execute([$col_delete_time]);
                        if ($checkCol->fetch()) {
                            $baseConditions[] = "({$col_delete_time} IS NULL OR {$col_delete_time} = '0000-00-00 00:00:00')";
                        }
                    } catch (Exception $e) {
                        // 欄位不存在，忽略
                    }

                    $whereBase = implode(' AND ', $baseConditions);

                    // 3. 根據移動方向調整其他項目的排序
                    if ($oldSort < $newSort) {
                        // 向下移動：將 oldSort+1 到 newSort 之間的項目往上移（-1）
                        $params = array_merge($baseParams, [
                            ':old_sort' => $oldSort,
                            ':new_sort' => $newSort,
                            ':id' => $itemId
                        ]);
                        $this->pdo->prepare("
                            UPDATE {$tableName}
                            SET {$col_sort} = {$col_sort} - 1
                            WHERE {$whereBase}
                            AND {$col_sort} > :old_sort
                            AND {$col_sort} <= :new_sort
                            AND {$primaryKey} != :id
                        ")->execute($params);
                    } else {
                        // 向上移動：將 newSort 到 oldSort-1 之間的項目往下移（+1）
                        $params = array_merge($baseParams, [
                            ':new_sort' => $newSort,
                            ':old_sort' => $oldSort,
                            ':id' => $itemId
                        ]);
                        $this->pdo->prepare("
                            UPDATE {$tableName}
                            SET {$col_sort} = {$col_sort} + 1
                            WHERE {$whereBase}
                            AND {$col_sort} >= :new_sort
                            AND {$col_sort} < :old_sort
                            AND {$primaryKey} != :id
                        ")->execute($params);
                    }
                }

                // 4. 更新當前項目的排序值
                $this->pdo->prepare("UPDATE {$tableName} SET {$col_sort} = :new_sort WHERE {$primaryKey} = :id")
                          ->execute([':new_sort' => $newSort, ':id' => $itemId]);
            }

            // -------------------------------------------------------------
            // 【重要】排序後使用統一排序管理器進行全域與分類重排
            // -------------------------------------------------------------
            \UnifiedSortManager::updateAfterDataChange($this->pdo, $moduleConfig, $itemId, [
                'lang' => $item['lang'] ?? null,
                'categoryId' => $categoryId
            ]);

            $this->pdo->commit();
            return $this->jsonResponse($response, '排序已更新');
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->log("Error in changeSort: " . $e->getMessage() . " | Code: " . $e->getCode() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            // 確保狀態碼在有效範圍內 (100-599)
            $code = $e->getCode();
            $statusCode = ($code >= 100 && $code < 600) ? $code : 500;
            return $this->jsonResponse($response, $e->getMessage(), $statusCode);
        }
    }

    /**
     * 置頂切換 API
     */
    public function togglePin(Request $request, Response $response, array $args)
    {
        try {
            $this->requireAdmin();
            $data = $request->getParsedBody();
            $module = preg_replace('/[^a-zA-Z0-9_]/', '', $data['module'] ?? '');
            $itemId = intval($data['item_id'] ?? 0);
            $categoryId = intval($data['category_id'] ?? 0);

            if (empty($module) || $itemId <= 0) return $this->jsonResponse($response, '缺少參數', 400);

            require_once BASE_PATH_CMS . '/includes/elements/ModuleConfigElement.php';
            require_once BASE_PATH_CMS . '/includes/taxonomyMapHelper.php';
            require_once BASE_PATH_CMS . '/includes/SortReorganizer.php';
            require_once BASE_PATH_CMS . '/includes/UnifiedSortManager.php';

            $moduleConfig = \ModuleConfigElement::loadConfig($module);
            $tableName = $moduleConfig['tableName'];
            $primaryKey = $moduleConfig['primaryKey'];
            $cols = $moduleConfig['cols'] ?? [];
            $col_top = $cols['top'] ?? 'd_top';
            $col_sort = $cols['sort'] ?? 'd_sort';
            $parentIdField = $cols['parent_id'] ?? null;
            $menuKey = $moduleConfig['menuKey'] ?? null;
            $menuValue = $moduleConfig['menuValue'] ?? null;
            $configUseTaxonomyMapSort = $moduleConfig['listPage']['useTaxonomyMapSort'] ?? true;

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT * FROM {$tableName} WHERE {$primaryKey} = :id");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception('找不到資料');

            $useMapPin = ($configUseTaxonomyMapSort && $categoryId > 0 && hasTaxonomyMapTable($this->pdo));

            $col_delete_time_val = $cols['delete_time'] ?? 'd_delete_time';

            if ($useMapPin) {
                // 切換 Map d_top（分類視圖）
                $stmtCheck = $this->pdo->prepare("SELECT d_top FROM data_taxonomy_map WHERE d_id = :id AND t_id = :tid");
                $stmtCheck->execute([':id' => $itemId, ':tid' => $categoryId]);
                $currentTop = $stmtCheck->fetchColumn() ?: 0;
                $newTop = $currentTop ? 0 : 1;

                $this->pdo->prepare("UPDATE data_taxonomy_map SET d_top = :top WHERE d_id = :id AND t_id = :tid")
                          ->execute([':top' => $newTop, ':id' => $itemId, ':tid' => $categoryId]);
                // 同步主表
                $this->pdo->prepare("UPDATE {$tableName} SET {$col_top} = :top WHERE {$primaryKey} = :id")
                          ->execute([':top' => $newTop, ':id' => $itemId]);

                // 取得此分類的 map_level 再重排
                $stmtLevel = $this->pdo->prepare("SELECT map_level FROM data_taxonomy_map WHERE d_id = :id AND t_id = :tid LIMIT 1");
                $stmtLevel->execute([':id' => $itemId, ':tid' => $categoryId]);
                $mapLevel = $stmtLevel->fetchColumn() ?: 1;

                // 重新排序此分類下所有非置頂項目 (d_top = 0)
                $reorderCond = array_filter([$menuKey => $menuValue, 'lang' => $item['lang'] ?? null], fn($v) => $v !== null);
                reorderTaxonomyMap($this->pdo, $categoryId, intval($mapLevel), $reorderCond, $tableName);
            } else {
                // 切換主表 d_top（全部視圖）
                $currentTop = $item[$col_top] ?? 0;
                $newTop = $currentTop ? 0 : 1;
                $this->pdo->prepare("UPDATE {$tableName} SET {$col_top} = :top WHERE {$primaryKey} = :id")
                          ->execute([':top' => $newTop, ':id' => $itemId]);

                // 同步更新 taxonomy map 所有分類的 d_top
                if ($configUseTaxonomyMapSort && hasTaxonomyMapTable($this->pdo)) {
                    $this->pdo->prepare("UPDATE data_taxonomy_map SET d_top = :top WHERE d_id = :id")
                              ->execute([':top' => $newTop, ':id' => $itemId]);

                    // 重新排序此 item 所有分類下的非置頂項目（含 map_level）
                    $stmtMaps = $this->pdo->prepare("SELECT DISTINCT t_id, map_level FROM data_taxonomy_map WHERE d_id = :id");
                    $stmtMaps->execute([':id' => $itemId]);
                    $allMaps = $stmtMaps->fetchAll(PDO::FETCH_ASSOC);
                    $reorderCond = array_filter([$menuKey => $menuValue, 'lang' => $item['lang'] ?? null], fn($v) => $v !== null);
                    foreach ($allMaps as $m) {
                        reorderTaxonomyMap($this->pdo, intval($m['t_id']), intval($m['map_level'] ?? 1), $reorderCond, $tableName);
                    }
                }
            }

            // -------------------------------------------------------------
            // 【重要】置頂後使用統一排序管理器進行全域與分類重排
            // -------------------------------------------------------------
            \UnifiedSortManager::updateAfterDataChange($this->pdo, $moduleConfig, $itemId, [
                'lang' => $item['lang'] ?? null,
                'categoryId' => $categoryId
            ]);

            $this->pdo->commit();
            return $this->jsonResponse($response, '置頂狀態已更新');
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return $this->jsonResponse($response, $e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * 遞迴取得所有子孫項目的 ID
     */
    private function getDescendantIds($tableName, $parentId, $parentIdField)
    {
        $descendants = [];
        
        // 查詢直接子項目
        $stmt = $this->pdo->prepare("SELECT t_id FROM {$tableName} WHERE {$parentIdField} = :parentId");
        $stmt->execute([':parentId' => $parentId]);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($children as $childId) {
            $descendants[] = $childId;
            // 遞迴查詢子項目的子項目
            $grandchildren = $this->getDescendantIds($tableName, $childId, $parentIdField);
            $descendants = array_merge($descendants, $grandchildren);
        }
        
        return $descendants;
    }

    private function cleanupFiles($id, $col_file_fk)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM file_set WHERE {$col_file_fk} = :id");
        $stmt->execute([':id' => $id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($images as $img) {
            for ($i = 1; $i <= 5; $i++) {
                $link = $img["file_link{$i}"] ?? '';
                if ($link && file_exists(BASE_PATH_CMS . "/../" . $link)) @unlink(BASE_PATH_CMS . "/../" . $link);
            }
        }
    }

    private function jsonResponse(Response $response, $message, $status = 200)
    {
        if (is_array($message)) {
            $payload = json_encode(array_merge(['success' => ($status < 300)], $message), JSON_UNESCAPED_UNICODE);
        } else {
            $payload = json_encode(['success' => ($status < 300), 'message' => $message], JSON_UNESCAPED_UNICODE);
        }
        if (ob_get_level() > 0) ob_clean();
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /**
     * 遞迴刪除目錄
     */
    private function recursiveRmdir($path)
    {
        if (empty($path) || !is_dir($path)) return false;
        
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                $this->recursiveRmdir($fullPath);
            } else {
                if (!@unlink($fullPath)) {
                    $this->log("[UNLINK FAIL] {$fullPath} | error=" . (error_get_last()['message'] ?? 'unknown'));
                }
            }
        }
        
        $result = @rmdir($path);
        if (!$result) {
            $this->log("[RMDIR FAIL] {$path} | error=" . (error_get_last()['message'] ?? 'unknown'));
        }
        return $result;
    }
}

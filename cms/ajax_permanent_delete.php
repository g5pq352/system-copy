<?php
session_start();
require_once '../Connections/connect2data.php';
header('Content-Type: application/json');

$module = $_POST['module'] ?? '';
$itemId = intval($_POST['item_id'] ?? 0);
$force  = intval($_POST['force'] ?? 0); // 是否執意刪除

if (!$module || !$itemId) {
    echo json_encode(['success' => false, 'message' => '缺少參數']);
    exit;
}

$configFile = __DIR__ . "/set/{$module}Set.php";
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'message' => '找不到模組配置']);
    exit;
}

$moduleConfig = require $configFile;
if (!is_array($moduleConfig) && isset($settingPage)) $moduleConfig = $settingPage;

$tableName   = $moduleConfig['tableName'];
$col_id      = $moduleConfig['primaryKey'];
$col_file_fk = $moduleConfig['cols']['file_fk'] ?? 'file_d_id';

try {
    $conn->beginTransaction();

    $skipRelationCheck = $moduleConfig['listPage']['skipRelationCheck'] ?? false;

    // --- 防呆檢查：如果是分類模組，檢查是否有關聯文章 ---
    if (strpos($module, 'Cate') !== false && !$skipRelationCheck) {
        $mainModule = str_replace('Cate', '', $module);
        $mainConfigFile = __DIR__ . "/set/{$mainModule}Set.php";
        
        if (file_exists($mainConfigFile)) {
            unset($settingPage);
            $mainConfig = require $mainConfigFile;
            if (!is_array($mainConfig) && isset($settingPage)) $mainConfig = $settingPage;
            
            $articleTable = $mainConfig['tableName'];
            $articleCategoryField = $mainConfig['listPage']['categoryField'] ?? null;
            $articleFileFk = $mainConfig['cols']['file_fk'] ?? 'file_d_id';

            if ($articleTable && $articleCategoryField) {
                // 找出該分類下所有的文章 ID
                $stmt = $conn->prepare("SELECT {$mainConfig['primaryKey']} as id FROM {$articleTable} WHERE {$articleCategoryField} = :cat_id");
                $stmt->execute([':cat_id' => $itemId]);
                $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $articleCount = count($articles);

                if ($articleCount > 0 && $force == 0) {
                    // 有資料但未執意刪除：攔截並回傳 has_data
                    echo json_encode([
                        'success' => false, 
                        'has_data' => true, 
                        'message' => "此分類下尚有 {$articleCount} 筆文章。"
                    ]);
                    exit;
                }

                if ($articleCount > 0 && $force == 1) {
                    // 執意刪除：開始清理子文章所有檔案
                    foreach ($articles as $art) {
                        deleteRelatedFiles($conn, $articleFileFk, $art['id']);
                        // 刪除文章檔案紀錄
                        $conn->prepare("DELETE FROM file_set WHERE {$articleFileFk} = :id")->execute([':id' => $art['id']]);
                    }
                    // 刪除文章主資料
                    $conn->prepare("DELETE FROM {$articleTable} WHERE {$articleCategoryField} = :cat_id")->execute([':cat_id' => $itemId]);
                }
            }
        }
    }

    // --- 【websites 模組】永久刪除時才清除子網站資料庫 & 移至資源回收桶 ---
    if ($module === 'websites') {
        if (!class_exists('SubsiteHelper')) {
            require_once __DIR__ . '/includes/SubsiteHelper.php';
        }
        $stmtSite = $conn->prepare("SELECT d_title_en, d_slug, d_data2 FROM {$tableName} WHERE {$col_id} = :id");
        $stmtSite->execute([':id' => $itemId]);
        $siteData = $stmtSite->fetch(PDO::FETCH_ASSOC);

        if ($siteData) {
            $targetDbName = $siteData['d_data2'] ?? '';
            $targetSlug   = !empty($siteData['d_slug']) ? $siteData['d_slug'] : SubsiteHelper::sanitizeSlug($siteData['d_title_en'] ?? '');

            // 1. 刪除子網站資料庫 (確保不是本站 DB)
            if ($targetDbName) {
                $currentDb = $conn->query("SELECT DATABASE()")->fetchColumn();
                if ($targetDbName !== $currentDb && $targetDbName !== 'template_ver3') {
                    try {
                        $conn->exec("DROP DATABASE IF EXISTS `{$targetDbName}`");
                    } catch (Exception $e) {
                        error_log("Warning: Failed to drop database {$targetDbName}: " . $e->getMessage());
                    }
                }
            }

            // 2. 刪除子網站目錄 (整個資料夾)
            if ($targetSlug) {
                $rootPath  = realpath(__DIR__ . '/../../');
                // 不用 realpath 建構目標路徑（避免不存在時回傳 false）
                $targetDir = $rootPath . DIRECTORY_SEPARATOR . $targetSlug;
                $selfDir   = realpath(__DIR__ . '/../');

                // Debug log
                $logFile = realpath(__DIR__ . '/../') . '/subsite_delete_log.txt';
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " slug={$targetSlug} | rootPath={$rootPath} | targetDir={$targetDir} | exists=" . (is_dir($targetDir) ? 'YES' : 'NO') . "\n", FILE_APPEND);

                if (
                    is_dir($targetDir)
                    && realpath($targetDir) !== $rootPath
                    && realpath($targetDir) !== $selfDir
                    && strpos(realpath($targetDir), $rootPath) === 0
                ) {
                    recursiveDeleteDir($targetDir);
                    @file_put_contents($logFile, date('Y-m-d H:i:s') . " RESULT: " . (is_dir($targetDir) ? 'FAILED' : 'DELETED OK') . "\n", FILE_APPEND);
                } else {
                    @file_put_contents($logFile, date('Y-m-d H:i:s') . " SKIPPED: safety check failed\n", FILE_APPEND);
                }
            }

        }
    }

    // --- 刪除分類本身的檔案與資料 ---
    deleteRelatedFiles($conn, $col_file_fk, $itemId);
    $conn->prepare("DELETE FROM file_set WHERE {$col_file_fk} = :id")->execute([':id' => $itemId]);

    $stmt = $conn->prepare("DELETE FROM {$tableName} WHERE {$col_id} = :item_id");
    $stmt->execute([':item_id' => $itemId]);

    // 【新增】刪除後重新整理排序編號（讓排序連續）
    $col_sort = $moduleConfig['cols']['sort'] ?? 'd_sort';
    $col_delete_time = $moduleConfig['cols']['delete_time'] ?? 'd_delete_time';

    // 檢查是否有排序欄位
    $stmtCheckSort = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
    $stmtCheckSort->execute([$col_sort]);
    $hasSortColumn = (bool)$stmtCheckSort->fetch();

    // 檢查是否有刪除時間欄位（用於判斷是否支援軟刪除）
    $stmtCheckDelete = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
    $stmtCheckDelete->execute([$col_delete_time]);
    $hasDeleteTime = (bool)$stmtCheckDelete->fetch();

    if ($hasSortColumn) {
        require_once(__DIR__ . '/includes/SortReorganizer.php');
        
        // 【新增】判斷是否為階層式結構
        $hasHierarchy = $moduleConfig['listPage']['hasHierarchy'] ?? false;
        $parentIdField = $moduleConfig['cols']['parent_id'] ?? null;
        $categoryField = $moduleConfig['listPage']['categoryField'] ?? null;
        $menuKey = $moduleConfig['menuKey'] ?? null;
        $menuValue = $moduleConfig['menuValue'] ?? null;
        
        // 【修正】如果 menuValue 為 null 且有 menuKey,從被刪除的資料中讀取
        if ($menuValue === null && $menuKey && $tableName === 'taxonomies') {
            // 查詢被刪除項目的 menuKey 值 (例如 taxonomy_type_id)
            $stmt = $conn->prepare("SELECT {$menuKey} FROM {$tableName} WHERE {$col_id} = :item_id");
            $stmt->execute([':item_id' => $itemId]);
            $deletedItem = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($deletedItem) {
                $menuValue = $deletedItem[$menuKey];
            }
        }
        
        if ($hasHierarchy && $parentIdField) {
            // 【多層分類】按 menuKey + parent_id 分組排序
            SortReorganizer::reorganizeAll(
                $conn,
                $tableName,
                $col_id,
                $col_sort,
                $menuKey,
                $hasDeleteTime,
                $col_delete_time,
                null,              // categoryField 不使用
                $parentIdField,    // 使用 parent_id 分層
                $menuValue         // 限定只處理當前模組
            );
        } else {
            // 【單層分類】只按 menuKey 分組排序
            SortReorganizer::reorganizeAll(
                $conn,
                $tableName,
                $col_id,
                $col_sort,
                $menuKey,
                $hasDeleteTime,
                $col_delete_time,
                $categoryField,
                null,              // 不使用 parent_id
                $menuValue         // 限定只處理當前模組
            );
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * 輔助函式：刪除實體檔案 + 清理空目錄
 */
function deleteRelatedFiles($conn, $fkColumn, $id) {
    $stmt = $conn->prepare("SELECT * FROM file_set WHERE {$fkColumn} = :id");
    $stmt->execute([':id' => $id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $affectedDirs = []; // 記住哪些目錄有被刪除過檔案

    foreach ($files as $f) {
        for ($i = 1; $i <= 5; $i++) {
            $link = "file_link{$i}";
            if (!empty($f[$link])) {
                $filePath = "../" . $f[$link];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                    // 記住這個檔案所在的目錄
                    $dir = dirname($filePath);
                    $affectedDirs[$dir] = true;
                }
            }
        }
    }

    // 清理空目錄 (由內往外遞迴刪除)
    foreach (array_keys($affectedDirs) as $dir) {
        removeEmptyDirs($dir);
    }
}

/**
 * 輔助函式：遞迴刪除空目錄 (由內往外)
 * 只會刪除 upload_image/ 或 upload_file/ 底下的空目錄，不會刪到更上層
 */
function removeEmptyDirs($dir) {
    if (!is_dir($dir)) return;

    // 安全邊界：只處理 upload_image 或 upload_file 底下的目錄
    $realDir = realpath($dir);
    $safeRoots = [
        realpath("../upload_image"),
        realpath("../upload_file"),
    ];

    $isSafe = false;
    foreach ($safeRoots as $root) {
        if ($root && strpos($realDir, $root) === 0 && $realDir !== $root) {
            $isSafe = true;
            break;
        }
    }
    if (!$isSafe) return;

    // 檢查目錄是否為空 (排除 . 和 ..)
    $entries = @scandir($dir);
    if ($entries === false) return;
    $entries = array_diff($entries, ['.', '..']);

    if (empty($entries)) {
        @rmdir($dir);
        // 繼續往上清理父目錄 (如果也是空的)
        removeEmptyDirs(dirname($dir));
    }
}

/**
 * 遞迴刪除整個目錄 (含所有子檔案與子目錄)
 * 用於永久刪除子網站時清除 www/{slug}/ 整個資料夾
 */
function recursiveDeleteDir($dir) {
    if (!is_dir($dir)) return;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            recursiveDeleteDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}
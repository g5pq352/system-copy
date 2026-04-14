<?php
// restore_folder.php - 資料庫同步版 (含遞迴重建結構)
header("Content-Type: application/json; charset=utf-8");

// 1. 引入資料庫與設定
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . '/thumbs_helper.php';

if (ob_get_level() > 0) ob_clean();

try {
    if (!isset($conn)) throw new Exception("資料庫連線失敗");

    // --- 路徑設定 ---
    $base      = $UPLOAD_BASE ?? realpath(__DIR__ . '/../../uploads');
    $trashBase = $TRASH_BASE  ?? realpath(__DIR__ . '/../../_trash');
    $trashFolders = $trashBase . "/folders";

    // --- 接收參數 ---
    $id = $_POST["id"] ?? "";
    $id = trim(str_replace(["..", "\\"], "", $id)); // 安全過濾

    if ($id === "") throw new Exception("ID 無效");

    $folderTrashPath = $trashFolders . "/" . $id;
    $metaFile = $folderTrashPath . "/meta.json";

    if (!file_exists($metaFile)) throw new Exception("找不到 meta 檔案，無法確認還原資訊");

    $meta = json_decode(file_get_contents($metaFile), true);
    if (!$meta) throw new Exception("Meta 資料毀損");

    // -----------------------------------------------------
    // 1. 決定還原目標 (Parent ID & Path)
    // -----------------------------------------------------
    $origFolderId = $meta['folder_id'] ?? null;
    $origPathName = basename($meta['original_path'] ?? 'restored_folder'); // 原始資料夾名稱

    // 檢查原本的父資料夾是否還存在
    $targetParentId = null;
    $targetParentPath = ""; // 實體路徑前綴

    if ($origFolderId !== null) {
        $stmtCheck = $conn->prepare("SELECT id FROM media_folders WHERE id = :id");
        $stmtCheck->execute([':id' => $origFolderId]);
        if ($stmtCheck->fetchColumn()) {
            $targetParentId = $origFolderId;
            // 取得父資料夾的實體路徑
            $targetParentPath = get_folder_path_by_id($conn, $targetParentId);
        }
        // 若父資料夾已死，則 $targetParentId 維持 null (還原到根目錄)
    }

    // -----------------------------------------------------
    // 2. 計算不重複的資料夾名稱 (實體路徑)
    // -----------------------------------------------------
    // 目標基礎路徑 (例如 uploads/news)
    $destBasePath = $base . ($targetParentPath ? '/' . $targetParentPath : '');
    
    // 確保目標父目錄存在
    if (!is_dir($destBasePath)) {
        mkdir($destBasePath, 0777, true);
    }

    // 計算最終名稱 (例如 "event (1)")
    $finalFolderName = findAvailableName($destBasePath, $origPathName);
    
    // 最終實體路徑
    $destOriginal = $destBasePath . "/" . $finalFolderName;
    
    // 最終縮圖路徑
    $relPathForThumb = ($targetParentPath ? $targetParentPath . '/' : '') . $finalFolderName;
    $destThumbs = thumb_abs($relPathForThumb);


    // -----------------------------------------------------
    // 3. 執行物理還原 (沿用你的邏輯)
    // -----------------------------------------------------
    $srcOriginal = $folderTrashPath . "/original";
    $srcThumbs   = $folderTrashPath . "/thumbs";
    
    // 檢查是否為巢狀結構 (你的 delete_folder.php 產生的是巢狀)
    if (!is_dir($srcOriginal)) {
        throw new Exception("垃圾桶結構異常 (找不到 original 資料夾)");
    }

    if (!rename($srcOriginal, $destOriginal)) {
        throw new Exception("無法還原資料夾 (權限不足或目標已存在)");
    }

    // 還原縮圖 (如果有的話)
    if (is_dir($srcThumbs)) {
        ensure_dir_for($destThumbs); // Helper 函數確保父層存在
        @rename($srcThumbs, $destThumbs);
    } else {
        // 如果沒有縮圖備份，可能需要重建 (視需求，這裡暫略，因為掃描 DB 時會處理)
    }

    // -----------------------------------------------------
    // 4. 執行資料庫還原 (遞迴重建結構) ★★★ 核心重點
    // -----------------------------------------------------
    
    // 4-1. 先插入「主資料夾」
    $sqlFolder = "INSERT INTO media_folders (parent_id, name) VALUES (:pid, :name)";
    $stmtFolder = $conn->prepare($sqlFolder);
    $stmtFolder->execute([
        ':pid'  => $targetParentId,
        ':name' => $finalFolderName
    ]);
    $newMainFolderId = $conn->lastInsertId();

    // 4-2. 遞迴掃描內容並寫入 DB
    // 我們需要把還原後的實體目錄掃描一遍，把所有檔案和子資料夾塞回 DB
    recursive_db_restore($conn, $destOriginal, $newMainFolderId);


    // -----------------------------------------------------
    // 5. 清理垃圾桶
    // -----------------------------------------------------
    @unlink($metaFile);
    // 遞迴刪除垃圾桶殘留 (因為 original 已經移走了，剩下 thumbs 或空資料夾)
    delete_dir_recursive($folderTrashPath);


    echo json_encode([
        "success" => true,
        "msg"     => "資料夾已還原"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "msg" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;


// ======================================================
// 核心函式：遞迴掃描實體目錄並寫入資料庫
// ======================================================
function recursive_db_restore($pdo, $physicalPath, $parentId) {
    // 取得目錄下所有檔案與資料夾 (排除 . 和 ..)
    $items = array_diff(scandir($physicalPath), ['.', '..']);

    foreach ($items as $item) {
        $fullPath = $physicalPath . '/' . $item;

        if (is_dir($fullPath)) {
            // --- 遇到子資料夾 ---
            // 1. 寫入 DB
            $stmt = $pdo->prepare("INSERT INTO media_folders (parent_id, name) VALUES (:pid, :name)");
            $stmt->execute([':pid' => $parentId, ':name' => $item]);
            $newFolderId = $pdo->lastInsertId();

            // 2. 遞迴呼叫 (繼續掃描下一層)
            recursive_db_restore($pdo, $fullPath, $newFolderId);

        } elseif (is_file($fullPath)) {
            // --- 遇到檔案 ---
            // 1. 取得檔案資訊
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            
            // 過濾非圖片檔案 (視需求調整)
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) continue;

            $fileSize = filesize($fullPath);
            $mimeType = mime_content_type($fullPath);
            $width = null;
            $height = null;

            if (strpos($mimeType, 'image/') === 0 && $ext !== 'svg') {
                $sizeInfo = getimagesize($fullPath);
                if ($sizeInfo) {
                    $width = $sizeInfo[0];
                    $height = $sizeInfo[1];
                }
            }

            // 2. 寫入 DB
            $stmtFile = $pdo->prepare("INSERT INTO media_files 
                (folder_id, filename_disk, filename_original, file_type, file_size, width, height) 
                VALUES (:fid, :fname, :fname_orig, :type, :size, :w, :h)");
            
            $stmtFile->execute([
                ':fid'        => $parentId,
                ':fname'      => $item,   // 實體檔名
                ':fname_orig' => $item,   // 假設原檔名與實體檔名相同
                ':type'       => $mimeType,
                ':size'       => $fileSize,
                ':w'          => $width,
                ':h'          => $height
            ]);
        }
    }
}


// ======================================================
// 輔助函式
// ======================================================

// 計算可用名稱 (避免實體覆蓋)
function findAvailableName($baseDir, $name) {
    $candidate = $name;
    $i = 1;
    while (file_exists($baseDir . '/' . $candidate)) {
        $candidate = $name . " ($i)";
        $i++;
    }
    return $candidate;
}

// 透過 ID 取得相對路徑 (用於決定還原到哪裡)
function get_folder_path_by_id($pdo, $id) {
    $pathParts = [];
    $currentId = $id;
    while ($currentId !== null) {
        $stmt = $pdo->prepare("SELECT name, parent_id FROM media_folders WHERE id = :id");
        $stmt->execute([':id' => $currentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) break;
        array_unshift($pathParts, $row['name']);
        $currentId = $row['parent_id'];
    }
    return implode('/', $pathParts);
}

// 遞迴刪除資料夾 (用於清理垃圾桶)
function delete_dir_recursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delete_dir_recursive("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}
?>
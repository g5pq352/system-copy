<?php
// trash_restore_file.php - 從垃圾桶資料夾中還原單一檔案
// 設定回傳 JSON 格式
header("Content-Type: application/json; charset=utf-8");

// 1. 引入資料庫與設定
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . "/thumbs_helper.php"; 

if (ob_get_level() > 0) ob_clean();

try {
    // 檢查 DB
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    // --- 路徑設定 ---
    $base       = $UPLOAD_BASE ?? realpath(__DIR__ . '/../../uploads');
    $trashBase  = $TRASH_BASE  ?? realpath(__DIR__ . '/../../_trash');
    $trashFolders = $trashBase . "/folders";

    // --- 接收參數 ---
    $folderId = $_POST['folder_id'] ?? ''; // 垃圾桶資料夾 ID
    $subPath  = $_POST['sub_path'] ?? '';  // 檔案相對路徑

    $folderId = trim(str_replace(['..', '\\', '/'], '', $folderId));
    $subPath  = normalize_rel_path($subPath);

    if (!$folderId || !$subPath) {
        throw new Exception("錯誤: 參數無效");
    }

    // --- 1. 取得 Meta 資料 (得知原本的父資料夾是誰) ---
    $metaFile = $trashFolders . "/" . $folderId . "/meta.json";
    if (!file_exists($metaFile)) {
        throw new Exception("錯誤: 找不到原始資料夾的 Meta 資訊");
    }
    
    $meta = json_decode(file_get_contents($metaFile), true);
    if (!$meta) throw new Exception("Meta 檔案損壞");

    // 取得原本資料夾的資訊
    // 注意：原本的 folder_id 指的是「被刪除的那個資料夾」在 DB 裡的 ID (已刪除)
    // 我們需要的是「它的父資料夾」
    $deletedFolderOriginalPath = $meta['original_path'] ?? ''; // e.g. "news/2024"
    $deletedFolderParentPath   = dirname($deletedFolderOriginalPath); // e.g. "news"
    if ($deletedFolderParentPath === '.') $deletedFolderParentPath = '';

    // --- 2. 決定還原的目標資料夾 (Target Folder) ---
    // 策略：嘗試還原到「原本資料夾的父層」。如果父層也不見了，就還原到根目錄。
    
    $targetFolderId = null;
    $targetRelDir   = ''; // 實體路徑前綴

    if ($deletedFolderParentPath !== '') {
        // 嘗試在 DB 找父資料夾
        $targetFolderId = get_folder_id_by_path($conn, $deletedFolderParentPath);
        if ($targetFolderId !== false && $targetFolderId !== null) {
            $targetRelDir = $deletedFolderParentPath;
        } else {
            // 父資料夾也不存在，還原到根目錄
            $targetFolderId = null;
            $targetRelDir = '';
        }
    }

    // --- 3. 確定來源與目標路徑 ---
    // 來源 (在垃圾桶內)
    $srcAbsPath = $trashFolders . "/" . $folderId . "/original/" . $subPath;
    $srcThumbAbsPath = $trashFolders . "/" . $folderId . "/thumbs/" . $subPath;

    if (!is_file($srcAbsPath)) {
        throw new Exception("錯誤: 來源圖片檔案不存在");
    }

    // 目標檔名 (直接用原本的檔名，扁平化處理)
    $fileName = basename($subPath);
    
    // 組合目標相對路徑
    $targetRelPath = $targetRelDir === '' ? $fileName : ($targetRelDir . '/' . $fileName);

    // 取得唯一路徑 (處理重名)
    $finalRelPath = get_unique_path_rel($targetRelPath, false);
    $finalAbsPath = original_abs($finalRelPath);
    $finalFilename = basename($finalRelPath);

    // --- 4. 執行物理還原 ---
    ensure_dir_for($finalAbsPath);

    if (!@rename($srcAbsPath, $finalAbsPath)) {
        throw new Exception("無法將原圖移動到還原路徑 (權限不足?)");
    }

    // 還原縮圖
    $finalThumbPath = thumb_abs($finalRelPath);
    if (is_file($srcThumbAbsPath)) {
        ensure_dir_for($finalThumbPath);
        @rename($srcThumbAbsPath, $finalThumbPath);
    } else {
        create_thumb_for($finalAbsPath, $finalRelPath);
    }

    // --- 5. 執行資料庫還原 (INSERT) ---
    // 重新取得檔案資訊
    $ext = strtolower(pathinfo($finalAbsPath, PATHINFO_EXTENSION));
    $fileSize = filesize($finalAbsPath);
    $mimeType = mime_content_type($finalAbsPath);
    $width = null;
    $height = null;

    if (strpos($mimeType, 'image/') === 0 && $ext !== 'svg') {
        $sizeInfo = getimagesize($finalAbsPath);
        if ($sizeInfo) {
            $width = $sizeInfo[0];
            $height = $sizeInfo[1];
        }
    }

    // 寫入 DB
    $sql = "INSERT INTO media_files 
            (folder_id, filename_disk, filename_original, file_type, file_size, width, height) 
            VALUES (:fid, :fname, :fname_orig, :type, :size, :w, :h)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':fid'        => $targetFolderId,
        ':fname'      => $finalFilename,
        ':fname_orig' => $fileName, // 原始檔名
        ':type'       => $mimeType,
        ':size'       => $fileSize,
        ':w'          => $width,
        ':h'          => $height
    ]);

    // --- 6. 清理垃圾桶內的空目錄 ---
    // 因為移走了檔案，垃圾桶內的資料夾可能變空了，順手清一下
    clean_empty_dirs(dirname($srcAbsPath), $trashFolders . "/" . $folderId . "/original");
    if (file_exists(dirname($srcThumbAbsPath))) {
        clean_empty_dirs(dirname($srcThumbAbsPath), $trashFolders . "/" . $folderId . "/thumbs");
    }

    echo json_encode([
        "success" => true,
        "msg"     => "檔案已還原至: " . ($targetRelDir ? $targetRelDir : "根目錄")
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "msg" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;


// ======================================================
// 輔助函式：透過路徑反查 ID
// ======================================================
function get_folder_id_by_path($pdo, $path) {
    if ($path === '' || $path === '.') return null; 

    $parts = explode('/', $path);
    $parentId = null;

    foreach ($parts as $part) {
        if ($part === '') continue;
        
        $sql = "SELECT id FROM media_folders WHERE name = :name";
        $sql .= ($parentId === null) ? " AND parent_id IS NULL" : " AND parent_id = :pid";
        
        $stmt = $pdo->prepare($sql);
        $params = [':name' => $part];
        if ($parentId !== null) $params[':pid'] = $parentId;
        
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $parentId = $row['id'];
        } else {
            return false;
        }
    }
    return $parentId;
}

// ======================================================
// 輔助函式：產生唯一路徑
// ======================================================
function get_unique_path_rel(string $targetRelPath, bool $isFolder): string 
{
    global $UPLOAD_BASE; 
    
    $targetRelPath = normalize_rel_path($targetRelPath);
    $parentDir = dirname($targetRelPath);
    $name = basename($targetRelPath);
    
    $nameBase = pathinfo($name, PATHINFO_FILENAME);
    $extStr = pathinfo($name, PATHINFO_EXTENSION);
    $ext = $extStr ? '.' . $extStr : '';
    
    $finalRelPath = $targetRelPath;
    $finalAbsPath = rtrim($UPLOAD_BASE, '/') . '/' . $finalRelPath;
    $counter = 1;

    while (file_exists($finalAbsPath)) {
        $newName = $nameBase . ' (' . $counter . ')' . $ext;
        if ($parentDir === '.' || $parentDir === '') {
            $finalRelPath = $newName;
        } else {
            $finalRelPath = trim($parentDir, '/') . '/' . $newName;
        }
        $finalRelPath = normalize_rel_path($finalRelPath);
        $finalAbsPath = rtrim($UPLOAD_BASE, '/') . '/' . $finalRelPath;
        $counter++;
    }
    return $finalRelPath;
}

// 向上遞迴清理空目錄
function clean_empty_dirs($currentDir, $stopRoot) {
    $currentDir = realpath($currentDir);
    $stopRoot   = realpath($stopRoot);
    while ($currentDir && $currentDir !== $stopRoot && strpos($currentDir, $stopRoot) === 0) {
        if (!is_dir($currentDir)) break;
        $items = array_diff(scandir($currentDir), ['.', '..']);
        if (empty($items)) {
            @rmdir($currentDir);
            $currentDir = dirname($currentDir);
        } else {
            break; 
        }
    }
}
?>
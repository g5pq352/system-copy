<?php
// 設定回傳格式為 JSON
header("Content-Type: application/json; charset=utf-8");

// 引入資料庫連線和 helper
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . '/thumbs_helper.php';

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // 檢查資料庫連線
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    $trashImages = $TRASH_BASE . '/images';
    $trashThumbs = $TRASH_BASE . '/thumbs';
    $trashMeta   = $TRASH_BASE . '/meta';

    $id = $_POST['id'] ?? '';
    $id = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', $id);

    if ($id === '') {
        throw new Exception('ID 錯誤');
    }

    $metaFile = $trashMeta . '/' . $id . '.json';
    if (!file_exists($metaFile)) {
        throw new Exception('找不到對應的 meta');
    }

    $meta = json_decode(file_get_contents($metaFile), true);
    if (!$meta) {
        throw new Exception('meta 損壞');
    }

    $ext        = $meta['ext'] ?? '';
    $relPath    = $meta['original_path'] ?? '';
    $origName   = $meta['original_name'] ?? '';
    $folderId   = $meta['folder_id'] ?? null; // 原本的資料夾 ID
    $relPath    = normalize_rel_path($relPath);

    if ($relPath === '') {
        throw new Exception('原始路徑不正確');
    }

    $imgSrc   = $trashImages . '/' . $id . '.' . $ext;
    $thumbSrc = $trashThumbs . '/' . $id . '.' . $ext;

    $destAbs = original_abs($relPath);
    ensure_dir_for($destAbs);

    // 還原原圖
    if (is_file($imgSrc)) {
        if (!@rename($imgSrc, $destAbs)) {
            throw new Exception('無法移動原圖');
        }
    } else {
        throw new Exception('原圖檔案不存在');
    }

    // 還原中央縮圖（若有），沒有的話就重建
    if (is_file($thumbSrc)) {
        $thumbDest = thumb_abs($relPath);
        ensure_dir_for($thumbDest);
        @rename($thumbSrc, $thumbDest);
    } else {
        // 若舊版沒有存縮圖到垃圾桶，就直接重建
        create_thumb_for($destAbs, $relPath);
    }

    // --- 重新插入資料庫記錄 (關鍵!) ---
    $fileSize = filesize($destAbs);
    $width = null;
    $height = null;

    if (is_image_ext($ext) && $ext !== 'svg') {
        $sizeInfo = @getimagesize($destAbs);
        if ($sizeInfo) {
            $width = $sizeInfo[0];
            $height = $sizeInfo[1];
        }
    }

    // 取得 MIME type
    $mimeType = 'image/' . $ext;
    if (function_exists('mime_content_type')) {
        $detectedMime = @mime_content_type($destAbs);
        if ($detectedMime) $mimeType = $detectedMime;
    }

    // 插入新記錄
    $insertSQL = "INSERT INTO media_files 
                  (folder_id, filename_disk, filename_original, file_type, file_size, width, height) 
                  VALUES (:folder_id, :filename_disk, :filename_original, :file_type, :file_size, :width, :height)";
    
    $stmt = $conn->prepare($insertSQL);
    
    // 處理 folder_id (NULL 或整數)
    $folderIdType = is_int($folderId) && $folderId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL;
    
    $stmt->bindValue(':folder_id',         $folderId,  $folderIdType);
    $stmt->bindValue(':filename_disk',     $origName,  PDO::PARAM_STR);
    $stmt->bindValue(':filename_original', $origName,  PDO::PARAM_STR);
    $stmt->bindValue(':file_type',         $mimeType,  PDO::PARAM_STR);
    $stmt->bindValue(':file_size',         $fileSize,  PDO::PARAM_INT);
    $stmt->bindValue(':width',             $width,     PDO::PARAM_INT);
    $stmt->bindValue(':height',            $height,    PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new Exception('資料庫插入失敗');
    }

    // 刪除 meta
    @unlink($metaFile);

    echo json_encode([
        "success" => true,
        "msg" => "圖片已還原"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;

<?php
// 設定回傳格式為 JSON
header("Content-Type: application/json; charset=utf-8");

// 引入資料庫連線和 helper
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . "/thumbs_helper.php";

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // 檢查資料庫連線
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    $base = $UPLOAD_BASE;
    $trashImages = $TRASH_BASE . "/images";
    $trashThumbs = $TRASH_BASE . "/thumbs";
    $trashMeta   = $TRASH_BASE . "/meta";

    $data = json_decode($_POST['list'] ?? '[]', true);
    if (!is_array($data) || empty($data)) {
        throw new Exception("沒有選擇任何項目");
    }

    $count = 0;
    $errors = [];

    foreach ($data as $id) {
        // 安全過濾 ID
        $id = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', $id);
        if ($id === '') continue;

        $metaFile = $trashMeta . "/" . $id . ".json";
        if (!file_exists($metaFile)) {
            $errors[] = "ID {$id}: 找不到 meta 檔案";
            continue;
        }

        $meta = json_decode(file_get_contents($metaFile), true);
        if (!$meta) {
            $errors[] = "ID {$id}: meta 檔案損壞";
            continue;
        }

        $ext      = $meta['ext'] ?? '';
        $origPath = normalize_rel_path($meta['original_path'] ?? '');
        $origName = $meta['original_name'] ?? '';
        $folderId = $meta['folder_id'] ?? null; // 原本的資料夾 ID

        if ($origPath === '') {
            $errors[] = "ID {$id}: 原始路徑不正確";
            continue;
        }

        $imgSrc   = $trashImages . "/" . $id . "." . $ext;
        $thumbSrc = $trashThumbs . "/" . $id . "." . $ext;

        // 原圖目的地
        $absDest = original_abs($origPath);
        ensure_dir_for($absDest);

        // -------------------------------------
        // 1. 還原原圖
        // -------------------------------------
        if (file_exists($imgSrc)) {
            if (!@rename($imgSrc, $absDest)) {
                $errors[] = "ID {$id}: 無法移動原圖";
                continue;
            }
        } else {
            $errors[] = "ID {$id}: 原圖檔案不存在";
            continue;
        }

        // -------------------------------------
        // 2. 還原或重建中央縮圖
        // -------------------------------------
        if (file_exists($thumbSrc)) {
            // 還原舊縮圖
            $thumbDest = thumb_abs($origPath);
            ensure_dir_for($thumbDest);
            @rename($thumbSrc, $thumbDest);
        } else {
            // 重建縮圖
            create_thumb_for($absDest, $origPath, 400);
        }

        // -------------------------------------
        // 3. 重新插入資料庫記錄 (關鍵!)
        // -------------------------------------
        // 取得圖片資訊
        $fileSize = filesize($absDest);
        $width = null;
        $height = null;

        if (is_image_ext($ext) && $ext !== 'svg') {
            $sizeInfo = @getimagesize($absDest);
            if ($sizeInfo) {
                $width = $sizeInfo[0];
                $height = $sizeInfo[1];
            }
        }

        // 取得 MIME type
        $mimeType = 'image/' . $ext;
        if (function_exists('mime_content_type')) {
            $detectedMime = @mime_content_type($absDest);
            if ($detectedMime) $mimeType = $detectedMime;
        }

        // 插入新記錄 (不使用舊的 ID,讓資料庫自動生成新 ID)
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
            $errors[] = "ID {$id}: 資料庫插入失敗";
            // 即使資料庫失敗,檔案已還原,繼續處理
        }

        // -------------------------------------
        // 4. 刪除 meta 檔案
        // -------------------------------------
        @unlink($metaFile);

        $count++;
    }

    // 組合回應訊息
    $msg = "已還原 {$count} 張圖片";
    if (!empty($errors)) {
        $msg .= "，部分項目失敗：" . implode('; ', array_slice($errors, 0, 3));
        if (count($errors) > 3) {
            $msg .= " 等 " . count($errors) . " 個錯誤";
        }
    }

    echo json_encode([
        "success" => true,
        "msg" => $msg
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;


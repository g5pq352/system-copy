<?php
// 設定回傳格式為 JSON
header("Content-Type: application/json; charset=utf-8");

// 1. 引入資料庫與 Helper
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . '/thumbs_helper.php';

if (ob_get_level() > 0) {
    ob_clean();
}

try {
    // 檢查連線變數
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗：變數 \$conn 未定義");
    }

    if (!function_exists('original_abs')) {
        throw new Exception("Fatal Error: original_abs() 未定義。");
    }

    //----------------------------------------------------
    //（1）取得參數 & 修正 Folder ID 邏輯
    //----------------------------------------------------
    $relDir = $_POST['path'] ?? ''; 
    
    // 【修正 1】更嚴謹的 ID 判斷
    // 批次上傳時，有時候前端會傳送空字串 "" 或字串 "null"
    $rawFolderId = $_POST['folder_id'] ?? null;
    $folderId = null; // 預設為 NULL (根目錄)

    // 只有當它是真正的數字，且不為 0 (除非你的根目錄 ID 是 0，但通常根目錄是 NULL) 時才轉型
    if ($rawFolderId !== null && $rawFolderId !== '' && $rawFolderId !== 'null') {
        $val = intval($rawFolderId);
        if ($val > 0) {
            $folderId = $val;
        }
    }

    // 正規化相對路徑
    if (function_exists('normalize_rel_path')) {
        $relDir = normalize_rel_path($relDir);
    } else {
        $relDir = str_replace(['..', '\\'], ['', '/'], $relDir);
        $relDir = trim($relDir, '/');
    }

    //----------------------------------------------------
    //（2）運算物理絕對路徑 & 建立目錄
    //----------------------------------------------------
    $absDir = original_abs($relDir);

    if ($absDir === false) {
        throw new Exception("original_abs() 無法運算出路徑 (Path: $relDir)");
    }

    if (!is_dir($absDir)) {
        if (!mkdir($absDir, 0775, true)) {
            $error = error_get_last()['message'] ?? "unknown";
            throw new Exception("無法建立目錄: $error");
        }
    }

    //----------------------------------------------------
    //（3）接收檔案
    //----------------------------------------------------
    $file = $_FILES['file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => "檔案超過 upload_max_filesize 限制",
            UPLOAD_ERR_FORM_SIZE  => "檔案超過表單 MAX_FILE_SIZE 限制",
            UPLOAD_ERR_PARTIAL    => "檔案只上傳了一部分",
            UPLOAD_ERR_NO_FILE    => "沒有收到檔案",
            UPLOAD_ERR_NO_TMP_DIR => "伺服器缺少暫存目錄",
            UPLOAD_ERR_CANT_WRITE => "無法寫入 tmp",
            UPLOAD_ERR_EXTENSION  => "PHP 擴展中斷了上傳",
        ];
        $msg = $errMap[$file['error']] ?? "未知錯誤 code: {$file['error']}";
        throw new Exception($msg);
    }

    //----------------------------------------------------
    //（4）驗證
    //----------------------------------------------------
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($ext, $allowedExts)) {
        throw new Exception("不支援的格式 ($ext)");
    }

    //----------------------------------------------------
    //（5）生成檔名與移動
    //----------------------------------------------------
    $fileName = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    
    // 避免同名覆蓋 (雖然機率很低)
    $base = pathinfo($fileName, PATHINFO_FILENAME);
    $counter = 1;
    while (file_exists($absDir . '/' . $fileName)) {
        $fileName = $base . "_{$counter}." . $ext;
        $counter++;
    }

    $absFile = $absDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $absFile)) {
        throw new Exception("檔案移動失敗 (權限不足?)");
    }

    //----------------------------------------------------
    //（6）生成縮圖
    //----------------------------------------------------
    $relPath = ($relDir === '') ? $fileName : $relDir . '/' . $fileName;

    if (function_exists('create_thumb_for')) {
        create_thumb_for($absFile, $relPath);
    }

    //----------------------------------------------------
    //（7）寫入資料庫 (修正綁定邏輯)
    //----------------------------------------------------
    
    // 取得圖片資訊
    $width = null;
    $height = null;
    $fileSize = filesize($absFile);

    if (strpos($mimeType, 'image/') === 0 && $ext !== 'svg') {
        $sizeInfo = getimagesize($absFile);
        if ($sizeInfo) {
            $width = $sizeInfo[0];
            $height = $sizeInfo[1];
        }
    }

    $insertSQL = "INSERT INTO media_files 
                  (folder_id, filename_disk, filename_original, file_type, file_size, width, height) 
                  VALUES (:folder_id, :filename_disk, :filename_original, :file_type, :file_size, :width, :height)";
    
    $stat = $conn->prepare($insertSQL);

    // 【修正 2】使用 bindValue 並精確指定型別
    // 如果 $folderId 是 null，強制使用 PDO::PARAM_NULL，避免被轉成整數 0 導致外鍵錯誤
    $folderIdType = is_int($folderId) ? PDO::PARAM_INT : PDO::PARAM_NULL;
    
    $stat->bindValue(':folder_id',         $folderId,           $folderIdType);
    $stat->bindValue(':filename_disk',     $fileName,           PDO::PARAM_STR);
    $stat->bindValue(':filename_original', $file['name'],       PDO::PARAM_STR);
    $stat->bindValue(':file_type',         $mimeType,           PDO::PARAM_STR);
    $stat->bindValue(':file_size',         $fileSize,           PDO::PARAM_INT);
    $stat->bindValue(':width',             $width,              PDO::PARAM_INT); // 若為 null 自動處理
    $stat->bindValue(':height',            $height,             PDO::PARAM_INT);

    $stat->execute();

    $newFileId = $conn->lastInsertId();

    //----------------------------------------------------
    // 完成
    //----------------------------------------------------
    // 加上 JSON_UNESCAPED_UNICODE 讓除錯時能看懂中文
    echo json_encode([
        "success" => true,
        "msg" => "上傳成功",
        "id" => $newFileId,
        "filename" => $fileName,
        "relative" => $relPath,
        "debug_folder_id" => $folderId // 回傳這個方便你看有沒有抓對 ID
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>
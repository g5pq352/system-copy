<?php
// 設定回傳格式為 JSON
header("Content-Type: application/json; charset=utf-8");

// 引入縮圖 helper
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . '/thumbs_helper.php';

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // 檢查資料庫連線
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    // --- 路徑設定 ---
    $base        = $UPLOAD_BASE ?? realpath(__DIR__ . '/../../uploads');
    $trashBase   = $TRASH_BASE ?? realpath(__DIR__ . '/../../_trash');
    
    $trashImages = $trashBase . '/images';
    $trashThumbs = $trashBase . '/thumbs';
    $trashMeta   = $trashBase . '/meta';

    // 確保垃圾桶目錄存在
    if (!is_dir($trashImages)) mkdir($trashImages, 0777, true);
    if (!is_dir($trashThumbs)) mkdir($trashThumbs, 0777, true);
    if (!is_dir($trashMeta))   mkdir($trashMeta, 0777, true);

    // --- 接收參數 ---
    $relPath = $_POST['path'] ?? '';
    $relPath = normalize_rel_path($relPath);

    if ($relPath === '') {
        throw new Exception('路徑錯誤');
    }

    // 計算絕對路徑
    $full = original_abs($relPath);

    // 檢查檔案是否存在且位於上傳目錄內
    if (!is_file($full) || strpos(realpath($full), realpath($base)) !== 0) {
        throw new Exception('檔案不存在或路徑不合法');
    }

    $name = basename($full);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    
    // 檢查副檔名
    if (function_exists('is_image_ext')) {
        if (!is_image_ext($ext)) throw new Exception('不是圖片檔');
    }

    // --- 步驟 A: 從資料庫查詢原始資訊 (關鍵) ---
    // 我們需要知道這張圖原本屬於哪個 folder_id，以便未來還原
    $sqlSelect = "SELECT id, folder_id FROM media_files WHERE filename_disk = :name";
    $stmtSelect = $conn->prepare($sqlSelect);
    $stmtSelect->execute([':name' => $name]);
    $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    $folderId = null;
    $dbId = null;

    if ($row) {
        $folderId = $row['folder_id'];
        $dbId = $row['id'];
    }

    // --- 步驟 B: 執行實體搬移 ---
    $trashId = uniqid('trash_', true);
    
    // 來源縮圖
    $thumbSrc = thumb_abs($relPath);

    // 目的地路徑
    $imgDest   = $trashImages . '/' . $trashId . '.' . $ext;
    $thumbDest = $trashThumbs . '/' . $trashId . '.' . $ext;
    $metaDest  = $trashMeta . '/' . $trashId . '.json';

    // 移動原始圖片
    if (!@rename($full, $imgDest)) {
        throw new Exception('無法移動檔案至垃圾桶 (權限錯誤或檔案被佔用)');
    }

    // 移動縮圖 (如果存在)
    if (is_file($thumbSrc)) {
        @rename($thumbSrc, $thumbDest);
    }

    // --- 步驟 C: 寫入 Meta JSON ---
    $meta = [
        'trash_id'       => $trashId,
        'original_db_id' => $dbId,     // 舊的 DB ID
        'folder_id'      => $folderId, // ★ 還原關鍵：記錄原本的資料夾 ID
        'ext'            => $ext,
        'original_path'  => $relPath,
        'original_name'  => $name,
        'deleted_at'     => date('Y-m-d H:i:s')
    ];

    file_put_contents($metaDest, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // --- 步驟 D: 從資料庫刪除紀錄 ---
    if ($dbId) {
        $sqlDelete = "DELETE FROM media_files WHERE id = :id";
        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->execute([':id' => $dbId]);
    }

    // --- 回傳成功 ---
    echo json_encode([
        "success" => true,
        "msg" => "圖片已刪除，移到垃圾桶"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "msg" => "錯誤：" . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
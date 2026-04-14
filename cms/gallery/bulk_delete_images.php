<?php
// 設定回傳 JSON 格式
header("Content-Type: application/json; charset=utf-8");

// 1. 引入資料庫與設定檔
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . '/thumbs_helper.php';

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // 資料庫連線防呆
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    // --- 設定路徑 ---
    // 如果 config 沒定義，提供預設值
    $base        = $UPLOAD_BASE ?? realpath(__DIR__ . '/../../uploads');
    $trashBase   = $TRASH_BASE ?? realpath(__DIR__ . '/../../_trash');
    
    $trashImages = $trashBase . '/images';
    $trashThumbs = $trashBase . '/thumbs';
    $trashMeta   = $trashBase . '/meta';

    // 確保垃圾桶目錄存在
    if (!is_dir($trashImages)) mkdir($trashImages, 0777, true);
    if (!is_dir($trashThumbs)) mkdir($trashThumbs, 0777, true);
    if (!is_dir($trashMeta))   mkdir($trashMeta, 0777, true);

    // --- 接收資料 ---
    $data = json_decode($_POST['list'] ?? '[]', true);
    if (!is_array($data) || empty($data)) {
        exit('沒有選取任何圖片');
    }

    $count = 0;

    // --- 準備 SQL ---
    // 1. 查詢舊資料 (為了保留 folder_id 供還原使用)
    $sqlSelect = "SELECT id, folder_id FROM media_files WHERE filename_disk = :name";
    $stmtSelect = $conn->prepare($sqlSelect);

    // 2. 刪除資料
    $sqlDelete = "DELETE FROM media_files WHERE id = :id";
    $stmtDelete = $conn->prepare($sqlDelete);

    foreach ($data as $relPath) {
        $relPath = normalize_rel_path($relPath);
        if ($relPath === '') continue;

        // 取得絕對路徑
        $full = original_abs($relPath);
        
        // 安全檢查：確保檔案存在且位於 uploads 目錄內
        if (!is_file($full) || strpos(realpath($full), realpath($base)) !== 0) continue;

        $name = basename($full); // 例如：img_12345.jpg
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        // 檢查是否為圖片
        if (function_exists('is_image_ext')) {
            if (!is_image_ext($ext)) continue;
        } else {
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) continue;
        }

        // --- 步驟 A: 從資料庫查詢原始資訊 ---
        $folderId = null;
        $dbId = null;
        
        $stmtSelect->execute([':name' => $name]);
        $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $folderId = $row['folder_id'];
            $dbId = $row['id'];
        }

        // --- 步驟 B: 執行物理移動 (移到垃圾桶) ---
        $trashId = uniqid('trash_', true); // 垃圾桶專用 ID
        $thumbSrc = thumb_abs($relPath);

        $imgDest  = $trashImages . '/' . $trashId . '.' . $ext;
        $thumbDest = $trashThumbs . '/' . $trashId . '.' . $ext;
        $metaDest = $trashMeta . '/' . $trashId . '.json';

        if (!@rename($full, $imgDest)) {
            // 如果移動失敗，跳過此檔案，不刪除 DB
            continue; 
        }

        if (is_file($thumbSrc)) {
            @rename($thumbSrc, $thumbDest);
        }

        // --- 步驟 C: 建立 Meta JSON (關鍵：存入 folder_id) ---
        // 這樣未來還原時，才知道要 insert 回哪個資料夾
        $meta = [
            'trash_id'      => $trashId,
            'original_db_id'=> $dbId,       // 舊的 DB ID (參考用)
            'folder_id'     => $folderId,   // ★ 還原時的關鍵路徑
            'ext'           => $ext,
            'original_path' => $relPath,    // 原始相對路徑
            'original_name' => $name,       // 實體檔名
            'deleted_at'    => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($metaDest, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // --- 步驟 D: 從資料庫刪除紀錄 ---
        if ($dbId) {
            $stmtDelete->execute([':id' => $dbId]);
        }

        $count++;
    }

    echo "成功刪除 {$count} 張圖片 (已移至垃圾桶)";

} catch (Exception $e) {
    http_response_code(500);
    echo "錯誤：" . $e->getMessage();
}
<?php
// 設定回傳 JSON 格式
header("Content-Type: application/json; charset=utf-8");

// 1. 引入設定檔
require_once __DIR__ . '/thumbs_helper.php';


// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // --- 路徑設定 ---
    $trashBase   = isset($TRASH_BASE) ? $TRASH_BASE : realpath(__DIR__ . '/../../_trash');
    $trashImages = $trashBase . '/images';
    $trashThumbs = $trashBase . '/thumbs';
    $trashMeta   = $trashBase . '/meta';

    // --- 整合參數接收 (單張與批次) ---
    $idsToProcess = [];

    // 情況 A: 批次刪除 (傳送 list JSON 字串)
    if (isset($_POST['list'])) {
        $decoded = json_decode($_POST['list'], true);
        if (is_array($decoded)) {
            $idsToProcess = $decoded;
        }
    } 
    
    // 情況 B: 單張刪除 (傳送 id 字串)
    // 如果 list 沒東西，才檢查 id
    if (empty($idsToProcess) && isset($_POST['id'])) {
        $singleId = trim($_POST['id']);
        if ($singleId !== '') {
            $idsToProcess = [$singleId];
        }
    }

    if (empty($idsToProcess)) {
        throw new Exception('沒有選取任何圖片');
    }

    $count = 0;

    foreach ($idsToProcess as $id) {
        // 安全過濾 ID
        $id = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', $id);
        if ($id === '') continue;

        $metaFile = $trashMeta . '/' . $id . '.json';
        
        // 如果連 Meta 都不見了，嘗試直接刪除可能的殘留檔案 (盲刪)
        // 但為了安全，通常我們依賴 Meta 來得知副檔名
        if (!file_exists($metaFile)) {
            // Meta 不在，可能已經刪除過了，略過
            continue;
        }

        // 讀取 Meta 以取得副檔名
        $jsonContent = file_get_contents($metaFile);
        $meta = json_decode($jsonContent, true);
        
        // 如果 JSON 壞了，直接刪掉 Meta 檔以免卡住
        if (!$meta) {
            @unlink($metaFile);
            continue;
        }

        $ext = $meta['ext'] ?? '';

        // 定義檔案路徑
        $imgFile   = $trashImages . '/' . $id . '.' . $ext;
        $thumbFile = $trashThumbs . '/' . $id . '.' . $ext;

        // 執行刪除
        if ($ext) {
            if (is_file($imgFile))   @unlink($imgFile);
            if (is_file($thumbFile)) @unlink($thumbFile);
        }
        
        // 最後刪除 Meta
        @unlink($metaFile);

        $count++;
    }

    // --- 回傳成功 ---
    echo json_encode([
        "success" => true,
        "msg"     => "永久刪除成功：{$count} 張圖片"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "msg"     => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>
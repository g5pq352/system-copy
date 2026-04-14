<?php
// 設定回傳格式為 JSON
header("Content-Type: application/json; charset=utf-8");

require_once realpath(__DIR__ . '/../..') . '/config/config.php';

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    $base      = realpath($rootPath . "/uploads");
    $trashRoot = $base . "/_trash";
    $imgDir    = $trashRoot . "/images";
    $thumbDir  = $trashRoot . "/thumbs";
    $metaDir   = $trashRoot . "/meta";

    $data = json_decode($_POST['list'] ?? '[]', true);
    if (!is_array($data) || empty($data)) {
        throw new Exception('沒有選擇任何圖片');
    }

    $count = 0;
    $errors = [];

    foreach ($data as $id) {
        // 安全過濾 ID
        $id = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', $id);
        if ($id === '') continue;

        $metaFile = $metaDir . "/" . $id . ".json";
        $ext = null;
        
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            if ($meta && isset($meta['ext'])) {
                $ext = $meta['ext'];
            }
        }

        // 刪除圖片和縮圖
        if ($ext) {
            $imgFile   = $imgDir   . "/" . $id . "." . $ext;
            $thumbFile = $thumbDir . "/" . $id . "." . $ext;
            
            if (file_exists($imgFile) && !@unlink($imgFile)) {
                $errors[] = "ID {$id}: 無法刪除原圖";
            }
            if (file_exists($thumbFile) && !@unlink($thumbFile)) {
                $errors[] = "ID {$id}: 無法刪除縮圖";
            }
        }

        // 刪除 meta 檔案
        if (file_exists($metaFile) && !@unlink($metaFile)) {
            $errors[] = "ID {$id}: 無法刪除 meta";
        }
        
        $count++;
    }

    // 組合回應訊息
    $msg = "批次永久刪除成功：{$count} 張圖片";
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


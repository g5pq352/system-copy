<?php
// trash_delete_file_perm.php - 從垃圾桶內的資料夾中永久刪除單一檔案
// 設定回傳 JSON 格式
header("Content-Type: application/json; charset=utf-8");

// 引入 helper
require_once __DIR__ . "/thumbs_helper.php"; 

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // --- 路徑設定 ---
    $uploadBase = $UPLOAD_BASE ?? realpath(__DIR__ . '/../../uploads');
    $trashBase  = $TRASH_BASE  ?? realpath(__DIR__ . '/../../_trash');

    // --- 接收參數 ---
    // folder_id 這裡是垃圾桶內的資料夾名稱 (例如 folder_65d4e...)
    $folderId = $_POST['folder_id'] ?? '';
    // sub_path 是該資料夾內的相對路徑 (例如 2024/event/pic.jpg)
    $subPath  = $_POST['sub_path'] ?? '';

    $folderId = trim(str_replace(['..', '\\', '/'], '', $folderId)); // 嚴格過濾
    $subPath  = normalize_rel_path($subPath);

    if (!$folderId || !$subPath) {
        throw new Exception("錯誤: 參數無效 (Folder ID 或 Sub Path 為空)");
    }

    // --- 確定刪除路徑 ---
    // 垃圾桶內的結構：_trash/folders/{folder_id}/original/{sub_path}
    $trashFolderRoot = $trashBase . "/folders/" . $folderId;
    
    // 來源原圖的絕對路徑
    $srcAbsPath = $trashFolderRoot . "/original/" . $subPath;
    
    // 來源縮圖的絕對路徑
    $srcThumbAbsPath = $trashFolderRoot . "/thumbs/" . $subPath;

    // 安全檢查：確認檔案真的在垃圾桶該資料夾內
    if (!file_exists($srcAbsPath)) {
        // 可能已經被刪除了，視為成功或報錯皆可，這裡選擇報錯
        throw new Exception("錯誤: 來源圖片檔案不存在或已刪除");
    }

    // --- 執行永久刪除 ---

    // 1. 刪除原圖
    if (!@unlink($srcAbsPath)) {
        throw new Exception("無法刪除原圖 (權限不足?)");
    }

    // 2. 刪除縮圖 (如果存在)
    if (file_exists($srcThumbAbsPath)) {
        @unlink($srcThumbAbsPath);
    }

    // 3. 清理空目錄 (從下往上清理空資料夾)
    // 這是為了避免刪除檔案後，留下空的 2024/event/ 資料夾結構
    clean_empty_dirs(dirname($srcAbsPath), $trashFolderRoot . "/original");
    
    // 如果有縮圖目錄，也順便清理
    if (file_exists(dirname($srcThumbAbsPath))) {
        clean_empty_dirs(dirname($srcThumbAbsPath), $trashFolderRoot . "/thumbs");
    }

    // --- 回傳成功 ---
    echo json_encode([
        "success" => true,
        "msg"     => "檔案已永久刪除"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "msg"     => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;


// ======================================================
// 輔助函式：向上遞迴清理空目錄
// ======================================================
function clean_empty_dirs($currentDir, $stopRoot) {
    // 轉成真實路徑比對，避免路徑字串差異
    $currentDir = realpath($currentDir);
    $stopRoot   = realpath($stopRoot);

    // 當前目錄存在，且在停止點之下 (不刪除 original 根目錄)
    while ($currentDir && $currentDir !== $stopRoot && strpos($currentDir, $stopRoot) === 0) {
        if (!is_dir($currentDir)) break;

        // 掃描目錄內容 (排除 . 和 ..)
        $items = array_diff(scandir($currentDir), ['.', '..']);
        
        if (empty($items)) {
            // 如果是空的，刪除它
            @rmdir($currentDir);
            // 往上一層繼續檢查
            $currentDir = dirname($currentDir);
        } else {
            // 不為空，停止清理
            break; 
        }
    }
}
?>
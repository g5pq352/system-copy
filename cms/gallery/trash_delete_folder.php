<?php
// trash_delete_folder.php - 從垃圾桶永久刪除資料夾
header("Content-Type: application/json; charset=utf-8");

// 1. 引入設定檔 (確保這裡面有定義 $rootPath)
require_once realpath(__DIR__ . '/../..') . '/config/config.php';

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // ==========================================
    // 修正重點 1：路徑設定 (改用你驗證過可行的路徑邏輯)
    // ==========================================
    // 確保 $rootPath 存在，如果 config 沒給，就用 __DIR__ 推算
    $rootPathLocal = isset($rootPath) ? $rootPath : realpath(__DIR__ . '/../..');
    
    // 依照你成功的寫法設定路徑
    $base = realpath($rootPathLocal . "/uploads");
    if ($base === false) {
        throw new Exception("找不到 uploads 目錄，路徑設定錯誤");
    }
    
    $trashFolders = $base . "/_trash/folders";

    // ==========================================
    // 修正重點 2：ID 過濾 (放寬限制)
    // ==========================================
    $id = $_POST["id"] ?? "";
    
    // 原本的寫法 preg_replace 會把中文或空白濾掉導致找不到資料夾
    // 改用 basename() 即可防止 ../ 攻擊，但保留中文與空白
    $id = trim(basename($id));

    if (!$id) {
        throw new Exception("缺少 ID 參數");
    }

    $folderDir = $trashFolders . "/" . $id;

    // --- 檢查目錄是否存在 ---
    if (!is_dir($folderDir)) {
        // 為了避免前端誤判，如果已經不在了，我們直接回傳成功
        echo json_encode([
            "success" => true,
            "msg"     => "資料夾已不存在 (視為刪除成功)"
        ]);
        exit;
    }

    // ==========================================
    // 修正重點 3：執行刪除 (使用你提供的遞迴邏輯)
    // ==========================================
    // 我們加上錯誤捕捉，如果權限不夠刪不掉，要丟出例外
    set_error_handler(function($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        delDir($folderDir);
    } catch (Exception $e) {
        // 恢復原本的錯誤處理，並拋出錯誤給外層 catch
        restore_error_handler();
        throw new Exception("刪除失敗 (權限或路徑問題): " . $e->getMessage());
    }
    
    restore_error_handler();

    // --- 回傳成功 ---
    echo json_encode([
        "success" => true,
        "msg"     => "資料夾已永久刪除"
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
// 你的核心刪除函式 (保持你原本可行的邏輯)
// ======================================================
function delDir($dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        // 遞迴刪除
        is_dir($path) ? delDir($path) : unlink($path);
    }
    return rmdir($dir);
}
?>
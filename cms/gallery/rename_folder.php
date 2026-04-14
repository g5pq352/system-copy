<?php
// rename_folder.php - 資料庫同步版
// 設定回傳 JSON 格式
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

    // --- 接收參數 ---
    $path = trim($_POST["path"] ?? "");      // 舊路徑 (e.g. "news/2025")
    $new  = trim($_POST["new_name"] ?? "");  // 新名稱 (e.g. "2026")

    // --- 驗證輸入 ---
    if (!$path || !$new) {
        throw new Exception("錯誤: 缺少參數");
    }

    // 過濾特殊字元 (只允許中文、英文、數字、底線、減號)
    if (preg_match('/[^\p{L}\p{N}_\-]/u', $new)) {
        throw new Exception("錯誤: 名稱包含不允許的特殊字元");
    }

    // --- 計算路徑 ---
    $oldRel = normalize_rel_path($path);
    $parentDir = dirname($oldRel);
    
    // 根目錄無法改名
    if ($oldRel === '' || $oldRel === '.') {
        throw new Exception("錯誤: 根目錄無法重新命名");
    }

    // 組合新路徑 (用於實體操作)
    $newRel = $parentDir === '.' ? $new : $parentDir . '/' . $new;
    $newRel = normalize_rel_path($newRel); // 清理路徑 (e.g. 把 ./news 變成 news)

    // 取得絕對路徑
    $oldAbs = original_abs($oldRel);
    $newAbs = original_abs($newRel);
    
    $oldThumbAbs = thumb_abs($oldRel);
    $newThumbAbs = thumb_abs($newRel);

    // --- 檢查實體狀態 ---
    if (!is_dir($oldAbs)) {
        throw new Exception("錯誤: 來源資料夾實體不存在");
    }
    if (file_exists($newAbs)) {
        throw new Exception("錯誤: 該名稱 '$new' 已存在，請使用其他名稱");
    }

    // -----------------------------------------------------
    // 第一步：從資料庫取得 Folder ID
    // -----------------------------------------------------
    $folderId = get_folder_id_by_path($conn, $oldRel);

    if ($folderId === false) {
        // 如果實體存在但 DB 不存在，為了安全起見，禁止操作，避免數據脫鉤
        throw new Exception("錯誤: 資料庫中找不到此資料夾紀錄，無法同步更名。");
    }

    // -----------------------------------------------------
    // 第二步：檢查資料庫是否已存在同名 (防呆)
    // -----------------------------------------------------
    // 我們要檢查在同一個 parent_id 下，是否已經有名稱為 $new 的資料夾
    $checkSQL = "SELECT count(*) FROM media_folders WHERE parent_id = (SELECT parent_id FROM media_folders WHERE id = :id) AND name = :new_name";
    $stmtCheck = $conn->prepare($checkSQL);
    $stmtCheck->execute([':id' => $folderId, ':new_name' => $new]);
    
    if ($stmtCheck->fetchColumn() > 0) {
        throw new Exception("錯誤: 資料庫中已存在同名資料夾");
    }

    // -----------------------------------------------------
    // 第三步：執行實體 Rename (原圖 + 縮圖)
    // -----------------------------------------------------
    if (!rename($oldAbs, $newAbs)) {
        $error = error_get_last();
        throw new Exception("主資料夾重新命名失敗: " . ($error['message'] ?? '未知'));
    }

    // 處理縮圖資料夾
    if (is_dir($oldThumbAbs)) {
        ensure_dir_for($newThumbAbs); // 確保父層存在
        if (!rename($oldThumbAbs, $newThumbAbs)) {
            // 縮圖失敗僅紀錄 Log，不丟出 Exception (以免影響主流程)
            error_log("Warning: Failed to rename thumb folder: $oldThumbAbs -> $newThumbAbs");
        }
    }

    // -----------------------------------------------------
    // 第四步：更新資料庫
    // -----------------------------------------------------
    $sql = "UPDATE media_folders SET name = :name WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':name' => $new,
        ':id'   => $folderId
    ]);

    echo json_encode([
        "success" => true,
        "msg"     => "資料夾名稱已更新"
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
// 輔助函式：透過路徑反查 ID
// ======================================================
function get_folder_id_by_path($pdo, $path) {
    if ($path === '') return null; // 根目錄

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
?>
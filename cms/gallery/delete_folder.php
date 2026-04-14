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

    // --- 接收參數 ---
    // 只接受 POST 傳遞的 'path' 參數
    $path = $_POST['path'] ?? ''; 
    $path = normalize_rel_path($path);

    // --- 基本檢查 ---
    if ($path === '') {
        throw new Exception("錯誤: 缺少資料夾路徑。");
    }

    // 計算實體路徑
    $absDir = original_abs($path);
    $absThumbDir = thumb_abs($path);

    // 檢查實體資料夾是否存在
    if (!is_dir($absDir)) {
        // 如果實體不存在，但 DB 可能有髒資料，這邊選擇報錯或嘗試清理 DB
        // 為了安全起見，先報錯
        throw new Exception("錯誤: 實體資料夾不存在或已被移動。");
    }

    // --------------------------------------------------------
    // 【關鍵步驟 1】從 Path 解析出 DB 中的 folder_id
    // --------------------------------------------------------
    // 因為資料庫存的是 parent_id 關聯，我們必須拆解路徑一層一層找
    $pathParts = explode('/', $path);
    $currentParentId = null; // 根目錄 parent_id 為 null
    $targetFolderId = null;
    $found = true;

    foreach ($pathParts as $part) {
        if ($part === '') continue; // 防止空字串

        $sql = "SELECT id FROM media_folders WHERE name = :name";
        if ($currentParentId === null) {
            $sql .= " AND parent_id IS NULL";
            $params = [':name' => $part];
        } else {
            $sql .= " AND parent_id = :pid";
            $params = [':name' => $part, ':pid' => $currentParentId];
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $currentParentId = $row['id'];
        } else {
            // 資料庫找不到這個路徑（可能是手動建立的資料夾）
            $found = false;
            break;
        }
    }

    if ($found) {
        $targetFolderId = $currentParentId;
    }

    // --------------------------------------------------------
    // 【關鍵步驟 2】準備垃圾桶環境
    // --------------------------------------------------------
    $trashRoot = isset($TRASH_BASE) ? $TRASH_BASE : realpath(__DIR__ . '/../../_trash');
    $folderTrashId  = uniqid("folder_", true);
    $folderTrashDir = $trashRoot . "/folders/" . $folderTrashId;

    if (!is_dir(dirname($folderTrashDir))) {
        @mkdir(dirname($folderTrashDir), 0777, true);
    }
    
    if (!mkdir($folderTrashDir, 0777, true)) {
        throw new Exception("錯誤: 無法建立垃圾桶目錄 (權限不足)。");
    }

    // --------------------------------------------------------
    // 【關鍵步驟 3】實體搬移 (Original)
    // --------------------------------------------------------
    $trashOriginal = $folderTrashDir . "/original";
    
    // 使用 rename 進行搬移
    if (!rename($absDir, $trashOriginal)) {
        // 清理剛建立的垃圾桶目錄
        @rmdir($folderTrashDir);
        throw new Exception("錯誤: 無法移動原圖資料夾到垃圾桶 (權限錯誤或被佔用)");
    }

    // --------------------------------------------------------
    // 【關鍵步驟 4】實體搬移 (Thumbs) - 選擇性
    // --------------------------------------------------------
    $trashThumbs = $folderTrashDir . "/thumbs";
    if (is_dir($absThumbDir)) {
        @rename($absThumbDir, $trashThumbs);
    }

    // --------------------------------------------------------
    // 【關鍵步驟 5】記錄 meta.json
    // --------------------------------------------------------
    // 我們要記錄 folder_id，這樣未來還原時才知道它的結構位置
    $meta = [
        "trash_id"      => $folderTrashId,
        "original_path" => $path,
        "folder_id"     => $targetFolderId, // ★ 記住這個 ID
        "type"          => "folder",
        "deleted_at"    => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($folderTrashDir . "/meta.json", json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // --------------------------------------------------------
    // 【關鍵步驟 6】資料庫清理 (遞迴刪除)
    // --------------------------------------------------------
    // 如果資料庫中有找到這個資料夾，我們需要刪除它以及它底下所有的子資料夾和檔案
    // 因為實體檔案已經整個被移走了
    
    if ($targetFolderId) {
        // 1. 取得所有子孫資料夾 ID (包含自己)
        $allFolderIds = get_all_child_folders($conn, $targetFolderId);
        $allFolderIds[] = $targetFolderId; // 加入自己

        if (!empty($allFolderIds)) {
            // 轉成字串供 SQL IN 使用
            $inQuery = implode(',', array_map('intval', $allFolderIds));

            // 2. 刪除這些資料夾內的檔案 (media_files)
            $conn->exec("DELETE FROM media_files WHERE folder_id IN ($inQuery)");

            // 3. 刪除這些資料夾本身 (media_folders)
            $conn->exec("DELETE FROM media_folders WHERE id IN ($inQuery)");
        }
    }

    // --------------------------------------------------------
    // 回傳成功
    // --------------------------------------------------------
    echo json_encode([
        "success" => true,
        "msg" => "資料夾已刪除並移動到垃圾桶",
        "path" => $path
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;


// ==========================================
// 輔助函式：遞迴取得所有子資料夾 ID
// ==========================================
function get_all_child_folders($pdo, $parentId) {
    $children = [];
    // 找出 parent_id = $parentId 的所有資料夾
    $stmt = $pdo->prepare("SELECT id FROM media_folders WHERE parent_id = ?");
    $stmt->execute([$parentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($rows as $childId) {
        $children[] = $childId;
        // 遞迴呼叫
        $grandChildren = get_all_child_folders($pdo, $childId);
        $children = array_merge($children, $grandChildren);
    }
    return $children;
}
?>
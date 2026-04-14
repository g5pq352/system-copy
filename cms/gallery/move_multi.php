<?php
// move_multi.php - 批次移動檔案 (資料庫同步版)
header("Content-Type: application/json; charset=utf-8");

require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . '/thumbs_helper.php';

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

// 確保全域變數
if (!isset($UPLOAD_BASE)) {
    $UPLOAD_BASE = realpath(__DIR__ . '/../../uploads');
}

try {
    // 檢查 DB
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    // --- 接收參數 ---
    $toPath = $_POST["to_path"] ?? "";
    $json   = $_POST["paths"]   ?? "[]"; // 這裡接收 JSON 字串

    $toRel  = normalize_rel_path($toPath);
    $paths  = json_decode($json, true);

    if (!is_array($paths) || empty($paths)) {
        throw new Exception("沒有選取任何檔案");
    }

    // -----------------------------------------------------
    // 第一步：解析「目標資料夾」的 ID
    // -----------------------------------------------------
    $targetFolderId = null;
    if ($toRel !== '') {
        $targetFolderId = get_folder_id_by_path($conn, $toRel);
        // 如果資料庫找不到該資料夾，是否要報錯？
        // 為了數據完整性，建議報錯
        if ($targetFolderId === false) {
            throw new Exception("目標資料夾 '$toRel' 在資料庫中不存在。");
        }
    }

    // -----------------------------------------------------
    // 第二步：迴圈處理每個檔案
    // -----------------------------------------------------
    $count = 0;
    $errors = [];

    foreach ($paths as $sourcePath) {
        try {
            $sourcePath = normalize_rel_path($sourcePath);
            if ($sourcePath === '') continue;

            // 呼叫處理單一檔案的函數 (含 DB 更新)
            process_move_file($conn, $sourcePath, $toRel, $targetFolderId);
            $count++;

        } catch (Exception $e) {
            // 記錄錯誤但不中斷整個迴圈
            $errors[] = basename($sourcePath) . ": " . $e->getMessage();
        }
    }

    // -----------------------------------------------------
    // 回傳結果
    // -----------------------------------------------------
    // 如果完全失敗
    if ($count === 0 && count($errors) > 0) {
        throw new Exception("批次移動失敗: " . implode(", ", $errors));
    }

    echo json_encode([
        "success" => true,
        "msg"     => "已移動 {$count} 張圖片" . (count($errors) > 0 ? " (部分失敗)" : "")
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
// 核心邏輯函式 (與 move_item.php 共用邏輯)
// ======================================================

function process_move_file($pdo, $sourcePath, $targetRelDir, $targetFolderId) {
    // 1. 取得檔案在資料庫的紀錄
    $fileRow = get_file_record($pdo, $sourcePath);
    if (!$fileRow) {
        throw new Exception("資料庫找不到紀錄");
    }
    $fileId = $fileRow['id'];

    // 2. 執行實體搬移 (處理重名並回傳新路徑)
    $newRelPath = move_single_file_physical($sourcePath, $targetRelDir);

    // 3. 取得最終檔名
    $newFileName = basename($newRelPath);

    // 4. 更新資料庫
    $sql = "UPDATE media_files SET folder_id = :fid, filename_disk = :fname WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fid'   => $targetFolderId,
        ':fname' => $newFileName,
        ':id'    => $fileId
    ]);
}

function move_single_file_physical($oldRel, $toRelDir) {
    global $UPLOAD_BASE; // 確保引用全域變數

    $oldAbs = original_abs($oldRel);
    if (!is_file($oldAbs)) throw new Exception("來源檔案不存在");

    $fileName = basename($oldRel);
    $targetRelPath = $toRelDir === '' ? $fileName : ($toRelDir . '/' . $fileName);

    // 取得唯一路徑 (避免覆蓋)
    $newRel = get_unique_path_rel($targetRelPath);
    $newAbs = original_abs($newRel);
    
    ensure_dir_for($newAbs);

    if (!@rename($oldAbs, $newAbs)) {
        throw new Exception("搬移失敗 (權限不足?)");
    }
    
    // 搬移縮圖
    move_thumb($oldRel, $newRel);
    
    return $newRel;
}

// ======================================================
// 輔助函式：資料庫查詢
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

function get_file_record($pdo, $relPath) {
    $fileName = basename($relPath);
    $dirPath  = dirname($relPath);
    if ($dirPath === '.') $dirPath = '';

    $folderId = get_folder_id_by_path($pdo, $dirPath);
    
    // 如果資料夾都找不到，檔案肯定不在 DB (除非是根目錄)
    if ($folderId === false && $dirPath !== '') return false;

    $sql = "SELECT id FROM media_files WHERE filename_disk = :name";
    $sql .= ($folderId === null) ? " AND folder_id IS NULL" : " AND folder_id = :fid";
    
    $stmt = $pdo->prepare($sql);
    $params = [':name' => $fileName];
    if ($folderId !== null) $params[':fid'] = $folderId;
    
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ======================================================
// 輔助函式：產生唯一路徑
// ======================================================
function get_unique_path_rel(string $targetRelPath): string 
{
    global $UPLOAD_BASE; 
    
    $targetRelPath = normalize_rel_path($targetRelPath);
    $parentDir = dirname($targetRelPath);
    $name = basename($targetRelPath);
    
    $nameBase = pathinfo($name, PATHINFO_FILENAME);
    $extStr = pathinfo($name, PATHINFO_EXTENSION);
    $ext = $extStr ? '.' . $extStr : '';
    
    $finalRelPath = $targetRelPath;
    $finalAbsPath = rtrim($UPLOAD_BASE, '/') . '/' . $finalRelPath;
    $counter = 1;

    while (file_exists($finalAbsPath)) {
        $newName = $nameBase . ' (' . $counter . ')' . $ext;
        if ($parentDir === '.' || $parentDir === '') {
            $finalRelPath = $newName;
        } else {
            $finalRelPath = trim($parentDir, '/') . '/' . $newName;
        }
        $finalRelPath = normalize_rel_path($finalRelPath);
        $finalAbsPath = rtrim($UPLOAD_BASE, '/') . '/' . $finalRelPath;
        $counter++;
    }
    
    return $finalRelPath;
}
?>
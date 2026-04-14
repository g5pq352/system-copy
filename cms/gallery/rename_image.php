<?php
// rename_image.php - 資料庫同步版
// 設定回傳 JSON 格式
header("Content-Type: application/json; charset=utf-8");

// 1. 引入資料庫與設定
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once __DIR__ . '/thumbs_helper.php';

// 清除緩衝區
if (ob_get_level() > 0) ob_clean();

try {
    // 檢查 DB
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    // --- 接收參數 ---
    $relPath = $_POST['path'] ?? '';
    $newName = trim($_POST['new_name'] ?? '');

    // --- 驗證輸入 ---
    $relPath = normalize_rel_path($relPath);

    if ($relPath === '' || $newName === '') {
        throw new Exception('參數錯誤: 路徑或新名稱為空');
    }

    // 簡單的檔名驗證 (避免特殊字元)
    if (preg_match('/[^\p{L}\p{N}_\-]/u', $newName)) {
        throw new Exception("錯誤: 名稱包含不允許的特殊字元");
    }

    // --- 計算路徑資訊 ---
    $oldAbs = original_abs($relPath);
    
    // 檢查實體檔案
    if (!is_file($oldAbs)) {
        throw new Exception('錯誤: 來源檔案不存在');
    }

    // 取得副檔名 (改名通常不改變副檔名)
    $ext = strtolower(pathinfo($oldAbs, PATHINFO_EXTENSION));
    
    // 檢查是否為圖片 (選用)
    if (function_exists('is_image_ext') && !is_image_ext($ext)) {
        throw new Exception('錯誤: 不是圖片檔');
    }

    // 計算目錄路徑 (Relative Directory)
    $dirRel = dirname($relPath);
    if ($dirRel === '.') $dirRel = ''; // 處理根目錄情況

    // 組合新檔名與新路徑
    $oldFilename = basename($relPath);
    $newFilename = $newName . '.' . $ext;
    $newRel = ($dirRel === '') ? $newFilename : ($dirRel . '/' . $newFilename);
    $newAbs = original_abs($newRel);

    // 檢查實體衝突
    if (file_exists($newAbs)) {
        throw new Exception("錯誤: 目標檔名 '$newFilename' 已存在 (硬碟)");
    }

    // -----------------------------------------------------
    // 第一步：從資料庫取得 Folder ID 與 File ID
    // -----------------------------------------------------
    // 1. 先找資料夾 ID
    $folderId = get_folder_id_by_path($conn, $dirRel);
    if ($folderId === false && $dirRel !== '') {
        throw new Exception("錯誤: 資料庫中找不到所在資料夾紀錄，無法同步更名。");
    }

    // 2. 再找檔案 ID
    $sqlFind = "SELECT id FROM media_files WHERE filename_disk = :name";
    $sqlFind .= ($folderId === null) ? " AND folder_id IS NULL" : " AND folder_id = :fid";
    
    $stmtFind = $conn->prepare($sqlFind);
    $paramsFind = [':name' => $oldFilename];
    if ($folderId !== null) $paramsFind[':fid'] = $folderId;
    
    $stmtFind->execute($paramsFind);
    $fileRow = $stmtFind->fetch(PDO::FETCH_ASSOC);

    if (!$fileRow) {
        throw new Exception("錯誤: 資料庫中找不到此檔案紀錄 ($oldFilename)");
    }
    $fileId = $fileRow['id'];

    // -----------------------------------------------------
    // 第二步：檢查資料庫衝突 (Double Check)
    // -----------------------------------------------------
    $sqlCheck = "SELECT count(*) FROM media_files WHERE filename_disk = :new_name";
    $sqlCheck .= ($folderId === null) ? " AND folder_id IS NULL" : " AND folder_id = :fid";

    $stmtCheck = $conn->prepare($sqlCheck);
    $paramsCheck = [':new_name' => $newFilename];
    if ($folderId !== null) $paramsCheck[':fid'] = $folderId;

    $stmtCheck->execute($paramsCheck);
    if ($stmtCheck->fetchColumn() > 0) {
        throw new Exception("錯誤: 目標檔名 '$newFilename' 已存在 (資料庫)");
    }

    // -----------------------------------------------------
    // 第三步：執行實體 Rename
    // -----------------------------------------------------
    if (!@rename($oldAbs, $newAbs)) {
        $error = error_get_last();
        throw new Exception("重新命名失敗: " . ($error['message'] ?? '未知'));
    }

    // 改縮圖 (使用原本的 helper 邏輯)
    move_thumb($relPath, $newRel);

    // -----------------------------------------------------
    // 第四步：更新資料庫
    // -----------------------------------------------------
    // 這裡只更新 filename_disk，通常 filename_original 保持原樣或視需求更新
    $sqlUpdate = "UPDATE media_files SET filename_disk = :new_name WHERE id = :id";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':new_name' => $newFilename,
        ':id'       => $fileId
    ]);

    echo json_encode([
        "success" => true,
        "msg"     => "重新命名成功",
        "new_name"=> $newFilename,
        "new_path"=> $newRel
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
// 輔助函式：透過路徑反查資料夾 ID
// ======================================================
function get_folder_id_by_path($pdo, $path) {
    if ($path === '' || $path === '.') return null; // 根目錄

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
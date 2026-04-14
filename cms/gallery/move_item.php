<?php
// 設定回傳 JSON 格式
header("Content-Type: application/json; charset=utf-8");

// 1. 引入設定檔與資料庫連線
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
// 2. 引入縮圖與路徑處理 helper (假設 normalize_rel_path, thumb_abs 等函式在此)
require_once __DIR__ . '/thumbs_helper.php';

// 清除緩衝區，避免多餘雜訊破壞 JSON
if (ob_get_level() > 0) ob_clean();

// 確保全域變數存在 (防呆)
if (!isset($UPLOAD_BASE)) {
    $UPLOAD_BASE = realpath(__DIR__ . '/../../uploads');
}

try {
    // 檢查資料庫連線 ($conn 在 connect2data.php 中定義)
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗");
    }

    // --- 接收參數 ---
    $type   = $_POST['type']    ?? '';
    $toPath = $_POST['to_path'] ?? ''; // 目標路徑 (相對路徑字串)

    $type   = trim($type);
    $toRel  = normalize_rel_path($toPath); // 標準化路徑 (去除 ./ 或多餘斜線)

    // -----------------------------------------------------
    // 第一步：解析「目標資料夾」的 ID (Target Folder ID)
    // -----------------------------------------------------
    // 如果 $toRel 為空字串，代表搬到根目錄 (ID = NULL)
    $targetFolderId = null;
    
    if ($toRel !== '') {
        $targetFolderId = get_folder_id_by_path($conn, $toRel);
        if ($targetFolderId === false) {
            // 資料庫找不到目標資料夾，禁止搬移以確保數據一致性
            throw new Exception("錯誤: 目標資料夾 '$toRel' 在資料庫中不存在。");
        }
    }

    // -----------------------------------------------------
    // 第二步：根據類型執行搬移 (Switch Case)
    // -----------------------------------------------------
    switch ($type) {
        case 'file':
            // --- 單一檔案搬移 ---
            $path = $_POST['path'] ?? '';
            $path = normalize_rel_path($path);
            if ($path === '') throw new Exception("錯誤: 來源檔案路徑為空");

            process_move_file($conn, $path, $toRel, $targetFolderId);
            
            echo json_encode(["success" => true, "msg" => "檔案已搬移"], JSON_UNESCAPED_UNICODE);
            break;

        case 'files':
            // --- 批次檔案搬移 (接收 JSON 陣列) ---
            $pathsJson = $_POST['paths'] ?? '[]';
            $paths = json_decode($pathsJson, true);
            if (!is_array($paths) || empty($paths)) throw new Exception('錯誤: 沒有選取任何檔案');

            $count = 0;
            $errors = []; // 收集錯誤訊息

            foreach ($paths as $p) {
                try {
                    $p = normalize_rel_path($p);
                    if ($p !== '') {
                        process_move_file($conn, $p, $toRel, $targetFolderId);
                        $count++;
                    }
                } catch (Exception $e) {
                    // 這裡會捕捉到「檔案已在該資料夾」的錯誤，並記錄下來
                    $errors[] = basename($p) . ": " . $e->getMessage();
                }
            }

            // 如果全部失敗
            if ($count === 0 && count($errors) > 0) {
                throw new Exception("搬移失敗: " . implode(", ", $errors));
            }

            // 回傳結果 (若有部分失敗，顯示在 msg 中)
            echo json_encode([
                "success" => true, 
                "msg" => "已搬移 {$count} 個檔案" . (count($errors) > 0 ? " (部分失敗)" : "")
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'folder':
            // --- 資料夾搬移 ---
            $path = $_POST['path'] ?? '';
            $path = normalize_rel_path($path);
            if ($path === '') throw new Exception("錯誤: 來源資料夾路徑為空");

            process_move_folder($conn, $path, $toRel, $targetFolderId);

            echo json_encode(["success" => true, "msg" => "資料夾已搬移"], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception("錯誤: 未知的搬移類型 ($type)");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;


// ======================================================
// 核心邏輯函式 1：處理單一檔案搬移
// ======================================================
function process_move_file($pdo, $sourcePath, $targetRelDir, $targetFolderId) {
    // 1. [防呆] 檢查是否原地搬移
    $sourceDir = dirname($sourcePath);
    if ($sourceDir === '.') $sourceDir = ''; // 根目錄修正
    
    if ($sourceDir === $targetRelDir) {
        throw new Exception("檔案已在該資料夾中");
    }

    // 2. 取得檔案在資料庫的紀錄 (為了拿到 ID)
    $fileRow = get_file_record($pdo, $sourcePath);
    if (!$fileRow) {
        throw new Exception("資料庫中找不到檔案紀錄: " . basename($sourcePath));
    }
    $fileId = $fileRow['id'];

    // 3. 執行實體搬移 (若目標有重名，會自動改名並回傳新路徑)
    $newRelPath = move_single_file_physical($sourcePath, $targetRelDir);

    // 4. 取得最終的新檔名
    $newFileName = basename($newRelPath);

    // 5. 更新資料庫
    $sql = "UPDATE media_files SET folder_id = :fid, filename_disk = :fname WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fid'   => $targetFolderId,
        ':fname' => $newFileName,
        ':id'    => $fileId
    ]);
}


// ======================================================
// 核心邏輯函式 2：處理資料夾搬移
// ======================================================
function process_move_folder($pdo, $sourcePath, $targetRelDir, $targetFolderId) {
    // 1. [防呆] 檢查是否原地搬移
    $sourceParent = dirname($sourcePath);
    if ($sourceParent === '.') $sourceParent = ''; // 根目錄修正

    if ($sourceParent === $targetRelDir) {
        throw new Exception("資料夾已在該目錄中");
    }

    // 2. 取得資料夾在資料庫的 ID
    $folderId = get_folder_id_by_path($pdo, $sourcePath);
    if ($folderId === false) {
        throw new Exception("資料庫中找不到資料夾紀錄: $sourcePath");
    }

    // 3. [防呆] 不能搬到自己裡面
    if ($targetFolderId === $folderId) {
        throw new Exception("不能將資料夾搬移到自己。");
    }

    // 4. 執行實體搬移
    $newRelPath = move_folder_physical($sourcePath, $targetRelDir);
    
    // 5. 取得最終資料夾名稱
    $newFolderName = basename($newRelPath);

    // 6. 更新資料庫
    $sql = "UPDATE media_folders SET parent_id = :pid, name = :name WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pid'  => $targetFolderId,
        ':name' => $newFolderName,
        ':id'   => $folderId
    ]);
}


// ======================================================
// 輔助函式：實體檔案操作 (Physical Move)
// ======================================================
function move_single_file_physical($oldRel, $toRelDir) {
    $oldAbs = original_abs($oldRel);
    if (!is_file($oldAbs)) throw new Exception("來源檔案實體不存在: $oldRel");

    $fileName = basename($oldRel);
    // 組合目標路徑: targetDir + fileName
    $targetRelPath = $toRelDir === '' ? $fileName : ($toRelDir . '/' . $fileName);

    // 取得不重複的唯一路徑 (如果重複會變 xxx (1).jpg)
    $newRel = get_unique_path_rel($targetRelPath, false);
    $newAbs = original_abs($newRel);
    
    // 確保目標資料夾存在
    ensure_dir_for($newAbs);

    if (!@rename($oldAbs, $newAbs)) {
        $err = error_get_last()['message'] ?? '未知';
        throw new Exception("檔案搬移失敗 (rename error): $err");
    }
    
    // 同步搬移縮圖
    move_thumb($oldRel, $newRel);
    
    return $newRel;
}

function move_folder_physical($oldFolderRel, $toRelDir) {
    $oldAbs = original_abs($oldFolderRel);
    if (!is_dir($oldAbs)) throw new Exception("來源資料夾實體不存在: $oldFolderRel");

    $folderName = basename($oldFolderRel);
    $targetRelPath = $toRelDir === '' ? $folderName : ($toRelDir . '/' . $folderName);

    // 檢查是否搬到自己子層
    if ($toRelDir === $oldFolderRel || strpos($toRelDir . '/', $oldFolderRel . '/') === 0) {
        throw new Exception('錯誤: 不能將資料夾搬到自己的子層');
    }

    // 取得不重複的唯一路徑
    $newFolderRel = get_unique_path_rel($targetRelPath, true);
    $newAbs = original_abs($newFolderRel);
    
    ensure_dir_for($newAbs);

    if (!@rename($oldAbs, $newAbs)) {
        $err = error_get_last()['message'] ?? '未知';
        throw new Exception("資料夾搬移失敗: $err");
    }

    // 同步搬移縮圖資料夾
    $oldThumbAbs = thumb_abs($oldFolderRel);
    $newThumbAbs = thumb_abs($newFolderRel);
    if (is_dir($oldThumbAbs)) {
        ensure_dir_for($newThumbAbs);
        @rename($oldThumbAbs, $newThumbAbs);
    }
    
    return $newFolderRel;
}


// ======================================================
// 輔助函式：資料庫查詢 (Lookup IDs)
// ======================================================
function get_folder_id_by_path($pdo, $path) {
    if ($path === '') return null; // 根目錄

    $parts = explode('/', $path);
    $parentId = null; // 從根目錄開始找

    foreach ($parts as $part) {
        if ($part === '') continue;
        
        // 查詢當前層級下，名稱為 $part 的資料夾 ID
        $sql = "SELECT id FROM media_folders WHERE name = :name";
        $sql .= ($parentId === null) ? " AND parent_id IS NULL" : " AND parent_id = :pid";
        
        $stmt = $pdo->prepare($sql);
        $params = [':name' => $part];
        if ($parentId !== null) $params[':pid'] = $parentId;
        
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $parentId = $row['id']; // 找到這層的 ID，設為下一層的 parent
        } else {
            return false; // 路徑斷裂，找不到
        }
    }
    return $parentId; // 回傳最後一層的 ID
}

function get_file_record($pdo, $relPath) {
    $fileName = basename($relPath);
    $dirPath  = dirname($relPath);
    if ($dirPath === '.') $dirPath = ''; // 根目錄

    // 1. 先找出檔案所在的資料夾 ID
    $folderId = get_folder_id_by_path($pdo, $dirPath);
    if ($folderId === false && $dirPath !== '') {
        return false; // 資料夾都不存在，檔案肯定不在 DB
    }
    
    // 2. 再找檔案
    $sql = "SELECT id FROM media_files WHERE filename_disk = :name";
    $sql .= ($folderId === null) ? " AND folder_id IS NULL" : " AND folder_id = :fid";
    
    $stmt = $pdo->prepare($sql);
    $params = [':name' => $fileName];
    if ($folderId !== null) $params[':fid'] = $folderId;
    
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// ======================================================
// 輔助函式：產生唯一路徑 (Get Unique Path)
// ======================================================
function get_unique_path_rel(string $targetRelPath, bool $isFolder): string 
{
    global $UPLOAD_BASE; 
    
    $targetRelPath = normalize_rel_path($targetRelPath);
    $parentDir = dirname($targetRelPath);
    $name = basename($targetRelPath);
    
    // 分離檔名與副檔名
    if ($isFolder) {
        $nameBase = $name;
        $ext = '';
    } else {
        $nameBase = pathinfo($name, PATHINFO_FILENAME);
        $extStr = pathinfo($name, PATHINFO_EXTENSION);
        $ext = $extStr ? '.' . $extStr : '';
    }
    
    $finalRelPath = $targetRelPath;
    $finalAbsPath = rtrim($UPLOAD_BASE, '/') . '/' . $finalRelPath;
    $counter = 1;

    // 如果目標路徑已存在，就加數字 (1), (2)...
    while (file_exists($finalAbsPath)) {
        $newName = $nameBase . ' (' . $counter . ')' . $ext;
        
        // 組合新路徑
        if ($parentDir === '.' || $parentDir === '') {
            $finalRelPath = $newName;
        } else {
            $finalRelPath = trim($parentDir, '/') . '/' . $newName;
        }
        
        $finalRelPath = normalize_rel_path($finalRelPath);
        $finalAbsPath = rtrim($UPLOAD_BASE, '/') . '/' . $finalRelPath;
        
        $counter++;
        if ($counter > 1000) throw new Exception("無法產生唯一名稱 (重試次數過多)");
    }
    
    return $finalRelPath;
}
?>
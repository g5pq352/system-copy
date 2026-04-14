<?php
header("Content-Type: application/json; charset=utf-8");

// 1. 引入資料庫連線
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');

// 2. 引入全域設定 (如果有定義全域路徑變數)
require_once realpath(__DIR__ . '/../../config/config.php'); 

// 確保輸出緩衝區乾淨
if (ob_get_level() > 0) ob_clean();

try {
    // 檢查資料庫連線
    if (!isset($conn)) {
        throw new Exception("資料庫連線失敗 (\$conn 未定義)");
    }

    // --- 接收參數 ---
    $folderName = trim($_POST['folder_name'] ?? '');
    $parentId   = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $parentPath = trim($_POST['parent_path'] ?? '');

    // --- 驗證輸入 ---
    if ($folderName === '') {
        throw new Exception('資料夾名稱不可為空');
    }
    
    // 過濾特殊字元
    $folderName = preg_replace('/[^\p{L}\p{N}_\-]/u', '_', $folderName);
    
    // 清理 parent_path
    $parentPath = str_replace(['..', '\\'], '', $parentPath);
    $parentPath = trim($parentPath, '/');

    // --- 準備路徑 (優化點) ---
    // 如果 config.php 有定義 $upload_path 或類似變數，優先使用它
    // 否則使用相對路徑推算
    if (isset($upload_path) && is_dir($upload_path)) {
        $baseDir = $upload_path;
    } else {
        // 假設 uploads 在網站根目錄 (視你的結構調整)
        // 這邊用 realpath 確保路徑是乾淨的絕對路徑
        $baseDir = realpath(__DIR__ . '/../../uploads'); 
    }

    if (!$baseDir || !is_dir($baseDir)) {
        throw new Exception("系統錯誤：找不到上傳根目錄 uploads");
    }
    
    // 組合完整實體路徑
    $relPath = ($parentPath ? $parentPath . '/' : '') . $folderName;
    $fullPath = $baseDir . '/' . $relPath;

    // --- 檢查 1: 資料庫是否已存在 ---
    $checkSQL = "SELECT count(*) FROM media_folders WHERE name = :name";
    if ($parentId === null) {
        $checkSQL .= " AND parent_id IS NULL";
        $checkParams = [':name' => $folderName];
    } else {
        $checkSQL .= " AND parent_id = :parent_id";
        $checkParams = [':name' => $folderName, ':parent_id' => $parentId];
    }
    
    $stmt = $conn->prepare($checkSQL);
    $stmt->execute($checkParams);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("資料庫中已存在同名資料夾");
    }

    // --- 檢查 2: 實體硬碟是否已存在 ---
    if (is_dir($fullPath)) {
        throw new Exception("伺服器硬碟上已存在同名資料夾");
    }

    // --- 執行動作 1: 建立實體資料夾 ---
    // 確保父目錄存在
    if (!is_dir(dirname($fullPath))) {
        if (!mkdir(dirname($fullPath), 0777, true)) {
            throw new Exception("無法建立父層目錄");
        }
    }

    if (!mkdir($fullPath, 0777)) {
        $error = error_get_last()['message'] ?? '未知錯誤';
        throw new Exception("無法建立資料夾 (mkdir 失敗): $error");
    }
    
    chmod($fullPath, 0777);

    // --- 執行動作 2: 寫入資料庫 ---
    $insertSQL = "INSERT INTO media_folders (parent_id, name) VALUES (:parent_id, :name)";
    $stat = $conn->prepare($insertSQL);
    
    $stat->bindParam(':parent_id', $parentId, PDO::PARAM_INT);
    $stat->bindParam(':name',      $folderName, PDO::PARAM_STR);
    
    if (!$stat->execute()) {
        // Rollback: 資料庫失敗則刪除剛建立的資料夾
        rmdir($fullPath); 
        throw new Exception("資料庫寫入失敗");
    }

    // 取得新建立的 ID
    $newFolderId = $conn->lastInsertId();

    echo json_encode([
        "success" => true, 
        "msg" => "資料夾 '{$folderName}' 建立成功",
        "id" => $newFolderId,
        "name" => $folderName,
        "parent_id" => $parentId // 多回傳這個方便前端除錯
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode([
        "success" => false, 
        "msg" => "錯誤：" . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
?>
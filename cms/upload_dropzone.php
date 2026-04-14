<?php
// upload_dropzone.php - PHP 8 陣列結構相容版

// 1. 開啟緩衝區
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../Connections/connect2data.php';
    require_once 'upload_process.php';
    
    session_start();

    if (!empty($_FILES)) {
        
        // =======================================================
        // 【關鍵修復】: 將單一檔案結構轉換為陣列結構 (PHP 8 Fix)
        // =======================================================
        // 原因：image_process 在 multiple 模式下預期收到陣列，
        // 但 Dropzone 傳送的是單一檔案字串。PHP 8 禁止對字串使用 count()。
        
        if (isset($_FILES['file']['name']) && is_string($_FILES['file']['name'])) {
            // 把所有欄位都變成陣列，例如: 'name' => 'a.jpg' 變成 'name' => ['a.jpg']
            $fixed_file = [];
            $fixed_file['name']     = [$_FILES['file']['name']];
            $fixed_file['type']     = [$_FILES['file']['type']];
            $fixed_file['tmp_name'] = [$_FILES['file']['tmp_name']];
            $fixed_file['error']    = [$_FILES['file']['error']];
            $fixed_file['size']     = [$_FILES['file']['size']];
            
            // 覆蓋原本的 $_FILES
            $_FILES['file'] = $fixed_file;
        }

        $d_id = $_POST['d_id'] ?? 0;
        $file_type = $_POST['file_type'] ?? 'image';
        $menu = $_SESSION['nowMenu'] ?? 'default';
        $imageConfig = $imagesSize[$file_type] ?? $imagesSize[$menu] ?? ['IW' => 0, 'IH' => 0];

        // 執行圖片處理
        // 因為上面已經把 $_FILES['file'] 變成陣列了，這裡就不會再報 count() 錯誤
        $image_result = image_process($conn, $_FILES['file'], [], $menu, "multiple", $imageConfig['IW'], $imageConfig['IH'], $d_id);

        // 檢查回傳結果
        if (is_string($image_result)) {
            // 處理錯誤訊息編碼
            $msg = $image_result;
            if (mb_detect_encoding($msg, ['UTF-8', 'Big5'], true) === 'Big5') {
                $msg = mb_convert_encoding($msg, 'UTF-8', 'Big5');
            }
            throw new Exception("圖片處理回傳錯誤: " . $msg);
        }

        if (!is_array($image_result)) {
            throw new Exception("系統錯誤: image_process 回傳型態不正確");
        }

        // ==========================================
        //  寫入資料庫邏輯 (恢復執行)
        // ==========================================
        $uploadedFiles = [];
        
        for ($j = 1; $j < count($image_result); $j++) {
            
            if (!isset($image_result[$j]) || !is_array($image_result[$j])) continue;

            $insertSQL = "INSERT INTO file_set (file_name, file_link1, file_link2, file_link3, file_type, file_d_id, file_title, file_show_type) VALUES (:file_name, :file_link1, :file_link2, :file_link3, :file_type, :file_d_id, :file_title, :file_show_type)";

            $stat = $conn->prepare($insertSQL);
            // 注意：這裡加上了 ?? '' 防止 undefined array key
            $stat->bindValue(':file_name', $image_result[$j][0] ?? '');
            $stat->bindValue(':file_link1', $image_result[$j][1] ?? '');
            $stat->bindValue(':file_link2', $image_result[$j][2] ?? '');
            $stat->bindValue(':file_link3', $image_result[$j][3] ?? '');
            $stat->bindValue(':file_type', $file_type);
            $stat->bindValue(':file_d_id', $d_id);
            $stat->bindValue(':file_title', $image_result[$j][4] ?? '');
            $stat->bindValue(':file_show_type', $image_result[$j][5] ?? 1);
            $stat->execute();

            $uploadedFiles[] = [
                'name' => $image_result[$j][0] ?? '',
                'link' => $image_result[$j][1] ?? ''
            ];
        }

        // 成功回傳
        ob_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => '上傳成功',
            'files' => $uploadedFiles
        ]);

    } else {
        echo json_encode(['status' => 'error', 'message' => '沒有接收到檔案']);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => '錯誤: ' . $e->getMessage()]);
} catch (Error $e) {
    ob_clean();
    // 這次如果還有錯，我們會知道是哪一行
    echo json_encode([
        'status' => 'error', 
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
exit;
?>
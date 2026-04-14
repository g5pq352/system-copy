<?php
/**
 * CMS 後台專用的 Media Helper 函數
 * 不使用 namespace,可直接在傳統 PHP 中使用
 */

/**
 * 根據 media ID 取得圖片的當前完整 URL
 */
function cms_get_media_url_by_id($mediaId) {
    global $conn;
    
    if (!$mediaId || !is_numeric($mediaId)) {
        return null;
    }
    
    try {
        $sql = "SELECT filename_disk, folder_id FROM media_files WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        $filename = $row['filename_disk'];
        $folderId = $row['folder_id'];
        
        // 建構完整路徑
        if ($folderId) {
            $folderPath = cms_get_folder_full_path($folderId);
            $relativePath = $folderPath . '/' . $filename;
        } else {
            $relativePath = $filename;
        }
        
        // URL 編碼處理中文檔名
        $pathParts = explode('/', $relativePath);
        $encodedPath = implode('/', array_map('rawurlencode', $pathParts));
        
        return UPLOAD_URL . '/' . $encodedPath;
        
    } catch (Exception $e) {
        error_log("Error getting media URL: " . $e->getMessage());
        return null;
    }
}

/**
 * 遞迴取得資料夾的完整路徑
 */
function cms_get_folder_full_path($folderId) {
    global $conn;
    static $cache = [];
    
    if (!$folderId) {
        return '';
    }
    
    if (isset($cache[$folderId])) {
        return $cache[$folderId];
    }
    
    try {
        $sql = "SELECT name, parent_id FROM media_folders WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$folderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return '';
        }
        
        $name = $row['name'];
        $parentId = $row['parent_id'];
        
        if ($parentId) {
            $parentPath = cms_get_folder_full_path($parentId);
            $fullPath = $parentPath ? $parentPath . '/' . $name : $name;
        } else {
            $fullPath = $name;
        }
        
        $cache[$folderId] = $fullPath;
        return $fullPath;
        
    } catch (Exception $e) {
        return '';
    }
}

/**
 * 根據檔名和路徑取得 media ID
 * @param string $filename 檔案名稱
 * @param string $folderPath 資料夾相對路徑 (例如 "folder1/folder2" 或 "" 表示根目錄)
 * @return int|null 找到的 media ID，找不到則回傳 null
 */
function get_media_id_by_filename($filename, $folderPath = '') {
    global $conn;
    
    if (!$filename) {
        return null;
    }
    
    try {
        // 如果是根目錄
        if ($folderPath === '') {
            $sql = "SELECT id FROM media_files WHERE filename_disk = :filename AND (folder_id IS NULL OR folder_id = 0) LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':filename' => $filename]);
        } else {
            // 需要先找到資料夾的 ID
            $parts = explode('/', $folderPath);
            $parentId = null;
            
            foreach ($parts as $part) {
                if ($part === '') continue;
                
                $sql = "SELECT id FROM media_folders WHERE name = :name";
                $sql .= ($parentId === null) ? " AND (parent_id IS NULL OR parent_id = 0)" : " AND parent_id = :pid";
                
                $stmt = $conn->prepare($sql);
                $params = [':name' => $part];
                if ($parentId !== null) $params[':pid'] = $parentId;
                
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    $parentId = $row['id'];
                } else {
                    // 資料夾不存在
                    return null;
                }
            }
            
            // 找到資料夾 ID 後，查詢檔案
            $sql = "SELECT id FROM media_files WHERE filename_disk = :filename AND folder_id = :folder_id LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':filename' => $filename, ':folder_id' => $parentId]);
        }
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
        
    } catch (Exception $e) {
        error_log("Error getting media ID by filename: " . $e->getMessage());
        return null;
    }
}

/**
 * 處理混合模式內容 (CMS 後台專用)
 */
function cms_process_content_with_mixed_mode($content) {
    if (empty($content)) {
        return $content;
    }
    
    // 處理所有包含 data-media-id 的 img 標籤
    $content = preg_replace_callback(
        '/<img\s+[^>]*data-media-id\s*=\s*["\']?(\d+)["\']?[^>]*>/i',
        function($matches) {
            $fullImgTag = $matches[0];
            $mediaId = $matches[1];
            
            $newUrl = cms_get_media_url_by_id($mediaId);
            
            if ($newUrl) {
                $updatedTag = preg_replace(
                    '/\bsrc\s*=\s*["\'][^"\']*["\']/i',
                    'src="' . htmlspecialchars($newUrl, ENT_QUOTES) . '"',
                    $fullImgTag
                );
                return $updatedTag;
            } else {
                return $fullImgTag;
            }
        },
        $content
    );
    
    return $content;
}
?>

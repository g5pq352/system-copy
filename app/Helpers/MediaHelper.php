<?php
namespace App\Helpers;

/**
 * MediaHelper - 處理媒體 Token 系統
 * 
 * 這個類別提供方法來處理圖片路徑的動態解析
 */
class MediaHelper {
    protected $db;
    protected $baseUrl;
    private $folderCache = [];
    
    /**
     * 建構函數
     * 
     * @param \DB $db DB 類別實例
     * @param string $baseUrl 基礎 URL
     */
    public function __construct($db, $baseUrl = '') {
        $this->db = $db;
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * 根據 media ID 取得圖片的當前完整 URL
     * 
     * @param int $mediaId 圖片的資料庫 ID
     * @return string|null 圖片的完整 URL,如果找不到則返回 null
     */
    public function getMediaUrlById($mediaId) {
        if (!$mediaId || !is_numeric($mediaId)) {
            return null;
        }
        
        try {
            $sql = "SELECT filename_disk, folder_id FROM media_files WHERE id = ? LIMIT 1";
            $row = $this->db->row($sql, [$mediaId]);
            
            if (!$row) {
                error_log("Media ID {$mediaId} not found in database");
                return null;
            }
            
            $filename = $row['filename_disk'];
            $folderId = $row['folder_id'];
            
            // 建構完整路徑
            if ($folderId) {
                $folderPath = $this->getFolderFullPath($folderId);
                $relativePath = $folderPath . '/' . $filename;
            } else {
                $relativePath = $filename;
            }
            
            // URL 編碼處理中文檔名
            $pathParts = explode('/', $relativePath);
            $encodedPath = implode('/', array_map('rawurlencode', $pathParts));
            
            return $this->baseUrl . '/uploads/' . $encodedPath;
            
        } catch (\Exception $e) {
            error_log("Error getting media URL for ID {$mediaId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 遞迴取得資料夾的完整路徑
     * 
     * @param int $folderId 資料夾 ID
     * @return string 完整路徑 (例如: "banner/2024")
     */
    public function getFolderFullPath($folderId) {
        if (!$folderId) {
            return '';
        }
        
        // 使用快取避免重複查詢
        if (isset($this->folderCache[$folderId])) {
            return $this->folderCache[$folderId];
        }
        
        try {
            $sql = "SELECT name, parent_id FROM media_folders WHERE id = ? LIMIT 1";
            $row = $this->db->row($sql, [$folderId]);
            
            if (!$row) {
                return '';
            }
            
            $name = $row['name'];
            $parentId = $row['parent_id'];
            
            if ($parentId) {
                $parentPath = $this->getFolderFullPath($parentId);
                $fullPath = $parentPath ? $parentPath . '/' . $name : $name;
            } else {
                $fullPath = $name;
            }
            
            $this->folderCache[$folderId] = $fullPath;
            return $fullPath;
            
        } catch (\Exception $e) {
            error_log("Error getting folder path for ID {$folderId}: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * 替換內容中的所有 media token 為實際 URL
     * 
     * @param string $content HTML 內容
     * @return string 替換後的內容
     */
    public function replaceMediaTokens($content) {
        if (empty($content)) {
            return $content;
        }
        
        $pattern = '/\[media:(\d+)\]/';
        
        return preg_replace_callback($pattern, function($matches) {
            $mediaId = $matches[1];
            $url = $this->getMediaUrlById($mediaId);
            
            if ($url) {
                return $url;
            } else {
                error_log("Media token [media:{$mediaId}] could not be resolved");
                return $matches[0];
            }
        }, $content);
    }
    
    /**
     * 處理混合模式內容 (同時支援 token 和 data-media-id)
     * 
     * @param string $content HTML 內容
     * @return string 處理後的內容
     */
    public function processContentWithMixedMode($content) {
        if (empty($content)) {
            return $content;
        }
        
        // 步驟 1: 先處理 [media:ID] token
        $content = $this->replaceMediaTokens($content);
        
        // 步驟 2: 處理所有包含 data-media-id 的 img 標籤
        $content = preg_replace_callback(
            '/<img\s+[^>]*data-media-id\s*=\s*["\']?(\d+)["\']?[^>]*>/i',
            function($matches) {
                $fullImgTag = $matches[0];
                $mediaId = $matches[1];
                
                $newUrl = $this->getMediaUrlById($mediaId);
                
                if ($newUrl) {
                    $updatedTag = preg_replace(
                        '/\bsrc\s*=\s*["\'][^"\']*["\']/i',
                        'src="' . htmlspecialchars($newUrl, ENT_QUOTES) . '"',
                        $fullImgTag
                    );
                    return $updatedTag;
                } else {
                    error_log("process_content_with_mixed_mode: Could not resolve media ID {$mediaId}");
                    return $fullImgTag;
                }
            },
            $content
        );
        
        return $content;
    }
}

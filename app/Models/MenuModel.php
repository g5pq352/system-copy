<?php
namespace App\Models;

class MenuModel extends Model {

    public function getMenu($baseUrl = '', $currentLang = null) {
        $baseUrl = rtrim($baseUrl, '/');
        
        // 獲取預設語系
        $defaultLang = $this->getDefaultLanguage();
        
        // 如果沒有傳入當前語系，從 Session 獲取
        if ($currentLang === null) {
            $currentLang = $_SESSION['frontend_lang'] ?? $defaultLang;
        }

        $sql = "SELECT * FROM menus_set WHERE m_active = 1 ORDER BY m_parent_id ASC, m_sort ASC";
        $rows = $this->db->query($sql);
        
        if (!$rows) return [];
        if (is_object($rows)) $rows = $rows->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            // 處理連結邏輯
            $link = $row['m_link'];
            if ($link !== 'javascript:;' && strpos($link, 'http') !== 0) {
                // 如果不是預設語系，添加語系前綴
                if ($currentLang !== $defaultLang) {
                    $link = $baseUrl . '/' . $currentLang . '/' . ltrim($link, '/');
                } else {
                    $link = $baseUrl . '/' . ltrim($link, '/');
                }
            }

            // 轉成乾淨的格式
            $item = [
                'id'       => $row['m_id'],
                'parent_id'=> $row['m_parent_id'],
                'link'     => $link,
                // 為了相容你原本的結構 (第一層用 ch/en，內層用 title)
                // 我們這裡統一都給，前端想用哪個就用哪個
                'ch'       => $row['m_title_ch'],
                'en'       => $row['m_title_en'],
                'title'    => $row['m_title_ch'], // 通用欄位
                'submenu'  => [], // 預設空陣列
            ];

            // 依照 parent_id 分組
            $grouped[$row['m_parent_id']][] = $item;
        }

        // 3. 開始遞迴組裝 (從 root: 0 開始)
        return $this->buildTree($grouped, 0);
    }
    
    /**
     * 獲取預設語系
     */
    private function getDefaultLanguage() {
        try {
            $result = $this->db->row("SELECT l_slug FROM languages WHERE l_is_default = 1 AND l_active = 1 LIMIT 1");
            return $result['l_slug'] ?? DEFAULT_LANG_SLUG;
        } catch (\Exception $e) {
            return DEFAULT_LANG_SLUG;
        }
    }

    /**
     * [遞迴核心] 組裝樹狀結構
     * @param array $grouped 已經依照 parent_id 分組的資料
     * @param int $parentId 目前要找誰的孩子
     * @return array|null
     */
    private function buildTree(&$grouped, $parentId) {
        // 如果這個 ID 沒有小孩，就回傳 null (配合你的 Vue 結構 submenu: null)
        if (!isset($grouped[$parentId])) {
            return null;
        }

        $branch = [];
        foreach ($grouped[$parentId] as $node) {
            // ★ 遞迴關鍵：自己呼叫自己，找自己的小孩
            $children = $this->buildTree($grouped, $node['id']);
            
            if ($children) {
                $node['submenu'] = $children;
            } else {
                $node['submenu'] = null;
            }

            $branch[] = $node;
        }

        return $branch;
    }

    public function getDescription() {
        $sql = "SELECT * FROM data_set WHERE d_class1='keywordsInfo' AND d_active = 1";
        $description = $this->db->row($sql);

        return $description;
    }
}
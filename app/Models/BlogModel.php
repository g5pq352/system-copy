<?php
namespace App\Models;

use App\Repositories\DataRepository;

use App\Traits\NeighborTrait;
class BlogModel extends Model {
    use NeighborTrait;
    protected $repo;
    protected $currentLang; // 【新增】當前語系

    public function __construct() {
        parent::__construct();
        $this->repo = new DataRepository();
        
        // 【新增】獲取當前語系
        // 【新增】獲取當前語系
        // 優先從 Global 獲取 (由 Middleware 設定)
        if (isset($GLOBALS['frontend_lang'])) {
            $this->currentLang = $GLOBALS['frontend_lang'];
        } else {
            $this->currentLang = $_SESSION['frontend_lang'] ?? $this->getDefaultLanguage();
        }
    }
    
    /**
     * 【新增】獲取預設語系
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
     * 取得 Blog 列表 (支援分類與搜尋)
     * 不需要排除第一筆
     */
    public function getBlogList($class1, $fileType, $limitStr, $categoryId = null, $keyword = null) {
        $safeClass = addslashes($class1);
        $lang = addslashes($this->currentLang);
        
        $activeCondition = $this->isAdmin ? "data_set.d_active IN (1, 2)" : "data_set.d_active = 1";

        $sql = "SELECT data_set.*, taxonomies.t_name as category_name
                FROM data_set
                LEFT JOIN taxonomies ON data_set.d_class2 = taxonomies.t_id
                WHERE data_set.d_class1='$safeClass' 
                AND $activeCondition
                AND data_set.lang = '$lang'
                AND (data_set.d_delete_time IS NULL)";

        // 分類篩選
        if ($categoryId) {
            $cat = addslashes($categoryId);
            $sql .= " AND data_set.d_class2 = '$cat'";
        }

        // 搜尋篩選
        if ($keyword) {
            $safeKey = addslashes($keyword);
            $sql .= " AND (data_set.d_title LIKE '%$safeKey%' OR data_set.d_content LIKE '%$safeKey%')";
        }

        // 排序 (通常 Blog 是照日期新到舊)
        $sql .= " ORDER BY data_set.d_date DESC $limitStr";

        $queryResults = $this->db->query($sql);
        $finalResults = [];

        if (is_array($queryResults)) {
            foreach ($queryResults as $itemObject) {
                $item = (array) $itemObject; 
                // 補上圖片 (使用 Repo)
                $item['cover_image'] = $this->repo->getOneFile($item['d_id'], $fileType, 'file_link1, file_title');
                
                $finalResults[] = $item;
            }
        }

        return $finalResults;
    }

    /**
     * 計算總筆數 (支援搜尋)
     * 回傳 int
     */
    public function getSearchCount($class1, $categoryId = null, $keyword = null) {
        $safeClass = addslashes($class1);
        $lang = addslashes($this->currentLang);
        
        $activeCondition = $this->isAdmin ? "d_active IN (1, 2)" : "d_active = 1";
        
        $sql = "SELECT COUNT(*) as count 
                FROM data_set 
                WHERE d_class1='$safeClass' AND $activeCondition AND lang = '$lang'";

        if ($categoryId) {
            $cat = addslashes($categoryId);
            $sql .= " AND d_class2 = '$cat'";
        }

        if ($keyword) {
            $safeKey = addslashes($keyword);
            $sql .= " AND (d_title LIKE '%$safeKey%' OR d_content LIKE '%$safeKey%')";
        }

        $row = $this->db->row($sql);
        return (int)($row['count'] ?? 0);
    }
}
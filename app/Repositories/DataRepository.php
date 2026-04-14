<?php
namespace App\Repositories;

use App\Models\Model;

/**
 * DataRepository - 全站模組查詢引擎 (完全版)
 */
class DataRepository extends Model {
    protected $isAdmin;
    protected $currentLang; 
    
    public function __construct($db = null, $isAdmin = null) {
        parent::__construct();
        if ($db) $this->db = $db;
        if ($isAdmin !== null) $this->isAdmin = $isAdmin;
        
        if (isset($GLOBALS['frontend_lang']) && !empty($GLOBALS['frontend_lang'])) {
             $this->currentLang = $GLOBALS['frontend_lang'];
        } elseif (isset($_SESSION['frontend_lang']) && !empty($_SESSION['frontend_lang'])) {
            $this->currentLang = $_SESSION['frontend_lang'];
        } else {
            $this->currentLang = $this->getDefaultLanguage();
        }
    }
    
    protected function getDefaultLanguage() {
        try {
            $sql = "SELECT l_slug FROM languages WHERE l_is_default = 1 AND l_active = 1 LIMIT 1";
            $row = $this->db->row($sql);
            return $row['l_slug'] ?? 'tw';
        } catch (\Exception $e) {
            return 'tw';
        }
    }

    protected function getActiveCondition($alias = '') {
        $prefix = $alias ? $alias . '.' : '';
        return $this->isAdmin ? "{$prefix}d_active IN (1, 2)" : "{$prefix}d_active = 1";
    }
    
    protected function escape($value) {
        return $value === null ? '' : addslashes($value);
    }

    /**
     * [通用] 獲取巢狀分類
     */
    public function getNestedCategories($ttpCategory) {
        $cats = $this->getCategory($ttpCategory);
        if (!$cats) return [];
        return $this->buildTree($cats, 0);
    }

    public function getHierarchyTree($ttpCategory, $moduleSlug = null) {
        return $this->getNestedCategories($ttpCategory);
    }

    protected function buildTree(array $elements, $parentId = 0) {
        $branch = [];
        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['t_id']);
                if ($children) $element['children'] = $children;
                else $element['children'] = [];
                $branch[] = $element;
            }
        }
        return $branch;
    }

    public function getAllCategoryIds($parentId) {
        if (!$parentId) return [];
        $ids = [(int)$parentId];
        $lang = $this->escape($this->currentLang);
        $sql = "SELECT t_id FROM taxonomies WHERE parent_id = $parentId AND t_active = 1 AND lang = '$lang'";
        $rows = $this->db->query($sql);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $ids = array_merge($ids, $this->getAllCategoryIds($row['t_id']));
            }
        }
        return array_unique($ids);
    }

    public function getCategory($ttpCategory, $columns = 'taxonomies.*') {
        $catName = $this->escape($ttpCategory);
        $lang = $this->escape($this->currentLang);
        $sql = "SELECT DISTINCT $columns FROM taxonomies 
                INNER JOIN taxonomy_types ON taxonomies.taxonomy_type_id = taxonomy_types.ttp_id
                WHERE taxonomy_types.ttp_category = '$catName' AND taxonomy_types.ttp_active = 1
                AND taxonomies.t_active = 1 AND taxonomies.lang = '$lang' 
                ORDER BY taxonomies.sort_order ASC";
        return $this->db->query($sql);
    }

    public function getOneFile($d_id, $fileType, $columns = '*') {
        $id = $this->escape($d_id);
        $ft = $this->escape($fileType);
        $sql = "SELECT $columns FROM file_set WHERE file_d_id = '$id' AND file_type = '$ft' ORDER BY file_sort ASC LIMIT 1";
        $row = $this->db->row($sql);
        return $row ? $row : null;
    }

    public function getListFile($d_id, $fileType, $columns = '*') {
        $id = $this->escape($d_id);
        $ft = $this->escape($fileType);
        return $this->db->query("SELECT $columns FROM file_set WHERE file_d_id = '$id' AND file_type = '$ft' ORDER BY file_sort ASC");
    }

    public function getCategoryBySlug($slug) {
        $s = $this->escape($slug);
        $lang = $this->escape($this->currentLang);
        $sql = "SELECT * FROM taxonomies WHERE t_slug = '$s' AND t_active = 1 AND lang = '$lang' LIMIT 1";
        $row = $this->db->row($sql);
        if (!$row && is_numeric($slug)) {
            $sql = "SELECT * FROM taxonomies WHERE t_id = " . (int)$slug . " AND t_active = 1 AND lang = '$lang' LIMIT 1";
            $row = $this->db->row($sql);
        }
        return $row;
    }

    /**
     * [全站引擎] getModuleList - 支援分頁、多層分類過濾、封面圖攤平
     */
    public function getModuleList($moduleSlug, $options = []) {
        $c1     = $this->escape($moduleSlug);
        $lang   = $this->escape($this->currentLang);
        $active = $this->getActiveCondition('data_set');
        
        $limit      = isset($options['limit']) ? (int)$options['limit'] : null;
        $offset     = isset($options['offset']) ? (int)$options['offset'] : 0;
        $categoryId = isset($options['categoryId']) ? (int)$options['categoryId'] : null;
        $keyword    = !empty($options['keyword']) ? $options['keyword'] : null;
        $fileType   = !empty($options['fileType']) ? $this->escape($options['fileType']) : ($c1 . 'Cover');
        $orderBy    = $options['orderBy'] ?? "data_set.d_sort ASC, data_set.d_date DESC";

        $select = "data_set.*, taxonomies.t_name as category_name, file_set.file_link1, file_set.file_title";
        $sql = "SELECT $select FROM data_set 
                LEFT JOIN taxonomies ON data_set.d_class2 = taxonomies.t_id 
                LEFT JOIN file_set ON (data_set.d_id = file_set.file_d_id AND file_set.file_type = '$fileType') 
                WHERE data_set.d_class1 = '$c1' AND data_set.lang = '$lang' AND $active AND (data_set.d_delete_time IS NULL)";
        
        if ($categoryId) {
            $allIds = $this->getAllCategoryIds($categoryId);
            $sql .= " AND data_set.d_class2 IN (" . implode(',', $allIds) . ")";
        }
        
        if ($keyword) {
            $k = $this->escape($keyword);
            $sql .= " AND (data_set.d_title LIKE '%$k%' OR data_set.d_content LIKE '%$k%')";
        }

        $sql .= " GROUP BY data_set.d_id ORDER BY $orderBy";
        if ($limit) $sql .= " LIMIT $offset, $limit";

        $results = $this->db->query($sql);
        return is_array($results) ? $results : [];
    }

    public function getModuleCount($moduleSlug, $options = []) {
        $c1     = $this->escape($moduleSlug);
        $lang   = $this->escape($this->currentLang);
        $active = $this->getActiveCondition('data_set');
        $categoryId = isset($options['categoryId']) ? (int)$options['categoryId'] : null;
        $keyword    = !empty($options['keyword']) ? $options['keyword'] : null;
        
        $sql = "SELECT COUNT(*) as total FROM data_set WHERE d_class1 = '$c1' AND lang = '$lang' AND $active AND (d_delete_time IS NULL)";
        if ($categoryId) {
            $allIds = $this->getAllCategoryIds($categoryId);
            $sql .= " AND data_set.d_class2 IN (" . implode(',', $allIds) . ")";
        }
        if ($keyword) {
            $k = $this->escape($keyword);
            $sql .= " AND (d_title LIKE '%$k%' OR d_content LIKE '%$k%')";
        }
        $row = $this->db->row($sql);
        return (int)($row['total'] ?? 0);
    }

    public function getModuleInfo($slug, $fileType = null) {
        $s = $this->escape($slug);
        $ft = $fileType ? $this->escape($fileType) : $s . 'Cover';
        $lang = $this->escape($this->currentLang);
        $active = $this->getActiveCondition('data_set');
        $sql = "SELECT data_set.*, file_set.file_link1 FROM data_set 
                LEFT JOIN file_set ON (data_set.d_id = file_set.file_d_id AND file_set.file_type = '$ft')
                WHERE d_class1 = '$s' AND lang = '$lang' AND $active AND (data_set.d_delete_time IS NULL) 
                ORDER BY d_sort ASC LIMIT 1";
        return $this->db->row($sql);
    }

    /**
     * [通用] 抓取單筆詳情 (含分類名稱與第一張封面圖)
     */
    public function getDetail($slug, $moduleSlug) {
        $s  = $this->escape($slug);
        $c1 = $this->escape($moduleSlug);
        $lang = $this->escape($this->currentLang);
        $active = $this->getActiveCondition('data_set');
        $fileType = $c1 . 'Cover';

        $sql = "SELECT data_set.*, taxonomies.t_name as category_name, file_set.file_link1, file_set.file_title
                FROM data_set 
                LEFT JOIN taxonomies ON data_set.d_class2 = taxonomies.t_id
                LEFT JOIN file_set ON (data_set.d_id = file_set.file_d_id AND file_set.file_type = '$fileType')
                WHERE d_slug = '$s' AND d_class1 = '$c1' AND data_set.lang = '$lang' AND $active LIMIT 1";
        
        return $this->db->row($sql);
    }

    public function getLatestItems($moduleSlug, $limit = 5, $fileType = null) {
        return $this->getModuleList($moduleSlug, [
            'limit' => (int)$limit,
            'fileType' => $fileType,
            'orderBy' => 'data_set.d_date DESC'
        ]);
    }

    public function getNeighbor($moduleSlug, $currentDate, $direction = 'next') {
        $c1 = $this->escape($moduleSlug);
        $d = $this->escape($currentDate);
        $active = $this->getActiveCondition('data_set');
        $lang = $this->escape($this->currentLang);
        $comp = ($direction === 'next') ? '>' : '<';
        $order = ($direction === 'next') ? 'ASC' : 'DESC';

        $sql = "SELECT d_id, d_title, d_slug FROM data_set WHERE d_class1 = '$c1' AND d_date $comp '$d' AND lang = '$lang' AND $active AND (data_set.d_delete_time IS NULL) ORDER BY d_date $order LIMIT 1";
        return $this->db->row($sql);
    }

    public function incrementView($id) {
        $id = (int)$id;
        if ($id > 0) $this->db->query("UPDATE data_set SET d_view = d_view + 1 WHERE d_id = $id");
    }
}
<?php
namespace App\Models;

use App\Repositories\DataRepository;

class ProductModel extends Model {

    public $repo;

    public function __construct() {
        parent::__construct(); 
        $this->repo = new DataRepository();
    }

    /**
     * 取得產品列表 (僅支援基礎分類與搜尋)
     */
    public function getProductList($class1, $fileType, $limitStr, $categoryId = null, $keyword = null) {
        $safeClass = addslashes($class1);
        $activeCondition = $this->isAdmin ? "data_set.d_active IN (1, 2)" : "data_set.d_active = 1";

        $sql = "SELECT DISTINCT data_set.*, taxonomies.t_name as category_name
                FROM data_set
                LEFT JOIN taxonomies ON data_set.d_class2 = taxonomies.t_id
                WHERE data_set.d_class1='$safeClass' 
                AND $activeCondition";

        if ($categoryId) {
            $cat = addslashes($categoryId);
            $sql .= " AND data_set.d_class2 = '$cat'";
        }

        if ($keyword) {
            $safeKey = addslashes($keyword);
            $sql .= " AND (
                data_set.d_title LIKE '%$safeKey%' 
                OR data_set.d_title_en LIKE '%$safeKey%' 
                OR data_set.d_content LIKE '%$safeKey%'
            )";
        }

        $sql .= " ORDER BY data_set.d_sort ASC, data_set.d_date DESC $limitStr";

        $queryResults = $this->db->query($sql);
        $finalResults = [];

        if (is_array($queryResults)) {
            foreach ($queryResults as $itemObject) {
                $item = (array) $itemObject; 
                $item['cover_image'] = $this->repo->getOneFile($item['d_id'], $fileType, 'file_link1, file_title');
                $finalResults[] = $item;
            }
        }
        return $finalResults;
    }

    /**
     * 計算搜尋結果數量
     */
    public function getSearchCount($class1, $categoryId = null, $keyword = null) {
        $safeClass = addslashes($class1);
        $activeCondition = $this->isAdmin ? "data_set.d_active IN (1, 2)" : "data_set.d_active = 1";

        $sql = "SELECT COUNT(DISTINCT data_set.d_id) as total 
                FROM data_set
                WHERE data_set.d_class1='$safeClass' AND $activeCondition";

        if ($categoryId) {
            $cat = addslashes($categoryId);
            $sql .= " AND data_set.d_class2 = '$cat'";
        }

        if ($keyword) {
            $safeKey = addslashes($keyword);
            $sql .= " AND (
                data_set.d_title LIKE '%$safeKey%' 
                OR data_set.d_title_en LIKE '%$safeKey%' 
                OR data_set.d_content LIKE '%$safeKey%'
            )";
        }

        $result = $this->db->row($sql);
        return isset($result['total']) ? (int)$result['total'] : 0;
    }
}
<?php
namespace App\Models;

use App\Repositories\DataRepository;

class FoundingjourneyModel extends Model {

    protected $repo;

    public function __construct() {
        parent::__construct(); 
        $this->repo = new DataRepository();
    }
    
    public function getHistory($mainClass = 'history', $categorySlug = null) {
        $safeClass = addslashes($mainClass);
        $fileType = 'image';
        
        $historyCats = $this->repo->getCategory('historyC');
        $historyYears = $this->repo->getCategory('years');
        
        $targetCategoryId = null;
        if ($categorySlug) {
            $categoryInfo = $this->repo->getCategoryBySlug($categorySlug);
            $targetCategoryId = $categoryInfo['t_id'] ?? null;
        } else {
            $targetCategoryId = $historyCats[0]['t_id'] ?? null;
        }

        if ($targetCategoryId) {
            $safeCategoryId = addslashes($targetCategoryId);
            $filterCondition = " AND d_class2 = '" . $safeCategoryId . "' ";
        }

        $activeCondition = $this->isAdmin ? "d_active IN (1, 2)" : "d_active = 1";
        
        $sql = "SELECT 
                    d_title,
                    d_content,
                    d_id,
                    d_date,
                    tax_year.t_name AS year 
                FROM data_set
                LEFT JOIN taxonomies AS tax_year ON d_class3 = tax_year.t_id
                
                WHERE d_class1 = '$safeClass' 
                AND $activeCondition
                {$filterCondition}
                ORDER BY tax_year.t_name ASC, d_date ASC";
        
        $results = $this->db->query($sql);
    
        $structuredList = []; 
        if (is_array($results)) {
            foreach ($results as $itemObject) {
                $eventData = (array) $itemObject; 
                $eventData['images'] = $this->repo->getListFile($eventData['d_id'], $fileType, 'file_link1, file_title');
                $structuredList[] = $eventData;
            }
        }
        
        return [
            'historyCats' => $historyCats,
            'historyYears' => $historyYears, 
            'structuredList' => $structuredList,
        ];
    }
}
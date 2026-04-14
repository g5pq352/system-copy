<?php
namespace App\Models;

use App\Repositories\DataRepository;

class ExperienceModel extends Model {

    protected $repo;

    public function __construct() {
        parent::__construct();
        $this->repo = new DataRepository();
    }
    
    public function getExperience($mainClass = 'experience', $categorySlug = null) {
        $safeClass = addslashes($mainClass);
        $activeCondition = $this->isAdmin ? "d_active IN (1, 2)" : "d_active = 1";
    
        $sql = "SELECT * FROM data_set WHERE d_class1 = '$safeClass' AND $activeCondition ORDER BY d_sort ASC";
        $results = $this->db->query($sql);
    
        if (empty($results)) {
            return [];
        }
    
        $allTagIds = [];
        foreach ($results as $row) {
            if (!empty($row['d_tag'])) {
                $allTagIds[] = $row['d_tag'];
            }
        }
        $fullTagIdsString = implode(',', array_unique(explode(',', implode(',', $allTagIds))));
    
        $tagsMap = $this->getRelatedItemsWithImage($fullTagIdsString, 'experienceHall', 'experienceHallTag');
    
        foreach ($results as &$row) {
            $row['cover_image'] = $this->repo->getOneFile($row['d_id'], 'experienceCover', 'file_link1, file_title');
    
            $row['tags'] = [];
            $tagIds = explode(',', $row['d_tag']);
            
            foreach ($tagIds as $tid) {
                if (isset($tagsMap[$tid])) {
                    $row['tags'][] = $tagsMap[$tid];
                }
            }
        }
        unset($row);
    
        return $results;
    }
    
    public function getExperienceHall($mainClass = 'experience', $id = null) {
        $safeClass = addslashes((string)$mainClass); 
        $d_id = addslashes((string)$id); 
        $activeCondition = $this->isAdmin ? "d_active IN (1, 2)" : "d_active = 1";
    
        $sql = "SELECT * FROM data_set WHERE d_class1 = '$safeClass' AND d_id='$d_id' AND $activeCondition";
        $mainData = (array)$this->db->row($sql);
    
        if (empty($mainData) || empty($mainData['d_tag'])) {
            return [];
        }
    
        $fullTagIdsString = trim($mainData['d_tag']);
    
        $hallMap = $this->getRelatedItemsWithImage($fullTagIdsString, 'experienceHall', 'experienceHallCover');
        
        $finalTagsList = [];
        $orderedTagIds = explode(',', $fullTagIdsString);
    
        foreach ($orderedTagIds as $tagId) {
            $tagId = (int)$tagId;
    
            if ($tagId > 0 && isset($hallMap[$tagId])) {
                $tagData = $hallMap[$tagId];
    
                $tagData['cover_image'] = [
                    'file_link1' => $tagData['file_link1'],
                    'file_title' => $tagData['file_title']
                ];

                $tagData['tag_image'] = $this->repo->getOneFile($tagId, 'experienceHallTag', 'file_link1, file_title');
                $tagData['coverOne_image'] = $this->repo->getOneFile($tagId, 'experienceHallCoverOne', 'file_link1, file_title');
                $tagData['download_file'] = $this->repo->getOneFile($tagId, 'file', 'file_link1, file_title');
                
                $finalTagsList[] = $tagData;
            }
        }
    
        return $finalTagsList;
    }

    /**
     * [通用批量關聯查詢] 根據 ID 字串抓取資料並包含第一張圖片
     * 回傳格式：[ID => [資料陣列], ID => [資料陣列]...]
     */
    public function getRelatedItemsWithImage($idsString, $targetClass1, $tagFileType) {
        $idsArray = array_filter(array_map('intval', explode(',', $idsString)));
        
        if (empty($idsArray)) {
            return [];
        }
        $safeIds = implode(',', $idsArray);

        $safeTagClass = addslashes($targetClass1);
        $safeFileType = addslashes($tagFileType);

        $activeCondition = $this->isAdmin ? "d.d_active IN (1, 2)" : "d.d_active = 1";

        $sql = "SELECT * FROM data_set d
                LEFT JOIN file_set f ON d.d_id = f.file_d_id 
                                    AND f.file_type = '$safeFileType'
                WHERE d.d_id IN ($safeIds) 
                AND d.d_class1 = '$safeTagClass' 
                AND $activeCondition";

        $results = $this->db->query($sql);

        $tagsData = [];
        foreach ($results as $row) {
            $tagsData[$row['d_id']] = [
                'd_id' => $row['d_id'],
                'd_title' => $row['d_title'],
                'd_title_en' => $row['d_title_en'],
                'd_data1' => $row['d_data1'],
                'd_data2' => $row['d_data2'],
                'd_data3' => $row['d_data3'],
                'd_data4' => $row['d_data4'],
                'd_data5' => $row['d_data5'],
                'd_data6' => $row['d_data6'],
                'd_data7' => $row['d_data7'],
                'd_data8' => $row['d_data8'],
                'd_content' => $row['d_content'],
                'file_link1' => $row['file_link1'] ?: null,
                'file_title' => $row['file_title'] ?: null
            ];
        }

        return $tagsData;
    }

    /**
     * [通用批量關聯查詢 - 無圖片] 
     * 回傳格式：[ID => [原始資料全部欄位]]
     */
    public function getRelatedItems($idsString, $targetClass1) {
        $idsArray = array_filter(array_map('intval', explode(',', $idsString)));
        if (empty($idsArray)) {
            return [];
        }
        $safeIds = implode(',', $idsArray);
        $safeTagClass = addslashes($targetClass1);

        $activeCondition = $this->isAdmin ? "d_active IN (1, 2)" : "d_active = 1";

        $sql = "SELECT * FROM data_set
                WHERE d_id IN ($safeIds) 
                AND d_class1 = '$safeTagClass' 
                AND $activeCondition";

        $results = $this->db->query($sql);

        return array_column($results, null, 'd_id');
    }
}
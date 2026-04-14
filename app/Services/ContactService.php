<?php
namespace App\Services;

use PDO;

class ContactService {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * 儲存資料到 data_set 資料表
     * @param array $dbData 對應資料庫欄位的陣列 (key=欄位名, value=值)
     * e.g. ['d_title' => '...', 'd_data1' => '...', 'd_class1' => 'contactus']
     * @return string Last Insert ID
     */
    public function save(array $dbData) {
        if (empty($dbData)) {
            throw new \Exception("No data to save.");
        }

        // 預設寫入時間 (若未提供)
        if (!isset($dbData['d_date'])) {
            $dbData['d_date'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($dbData);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO data_set (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
                
        $stmt = $this->db->prepare($sql);
        
        $executionResult = $stmt->execute(array_values($dbData));
        
        if ($executionResult) {
            return $this->db->lastInsertId();
        } else {
            throw new \Exception("Database insert failed.");
        }
    }
}

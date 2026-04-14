<?php
namespace App\Traits;

trait NeighborTrait {

    /**
     * [模組化功能] 取得相鄰文章 (上一篇/下一篇)
     * @param string $class1      分類 (如 'blog')
     * @param string $currentDate 當前文章日期
     * @param string $type        'prev' 或 'next'
     * @param int|null $categoryId (可選) 次分類 ID
     * @return array|mixed
     */
    public function getNeighbor($class1, $currentDate, $type = 'prev', $categoryId = null) {
        $activeCondition = (isset($this->isAdmin) && $this->isAdmin) ? "d_active IN (1, 2)" : "d_active = 1";

        // 1. 基礎 SQL，使用 ? 佔位符 (比 addslashes 更安全)
        $sql = "SELECT d_id, d_title, d_slug 
                FROM data_set 
                WHERE d_class1 = ? 
                AND $activeCondition";
        
        // 初始化參數陣列
        $params = [$class1];

        // 2. 處理次分類 (如果有的話)
        if ($categoryId) {
            $sql .= " AND d_class2 = ?";
            $params[] = (int)$categoryId;
        }

        // 3. 根據上一篇或下一篇，組合不同的邏輯
        if ($type === 'prev') {
            // 上一篇：日期小於當前，日期倒序 (最新的在最前)，ID 倒序 (輔助排序)
            $sql .= " AND d_date < ? ORDER BY d_date DESC, d_id DESC LIMIT 1";
        } else {
            // 下一篇：日期大於當前，日期正序 (最舊的在最前)，ID 正序 (輔助排序)
            $sql .= " AND d_date > ? ORDER BY d_date ASC, d_id ASC LIMIT 1";
        }

        // 將日期加入參數陣列 (對應上面 SQL 最後一個 ?)
        $params[] = $currentDate;

        // 4. 執行查詢
        return $this->db->row($sql, $params);
    }
}
<?php
/**
 * Data Query Element
 * 資料查詢與分頁元件
 */

class DataQueryElement
{
    /**
     * 建立列表查詢 SQL (增強版)
     * @param array $moduleConfig 模組配置
     * @param string $orderBy 原始排序條件
     * @param int $startRow 起始列數
     * @param int $maxRows 每頁筆數
     * @param string $whereClause 預先組合好的 WHERE 子句 (不含 WHERE 關鍵字)
     * @param array $params 查詢參數
     * @return array ['sql' => SQL 字串, 'params' => 參數陣列]
     */
    public static function buildEnhancedListQuery($moduleConfig, $orderBy, $startRow, $maxRows, $whereClause = '', $params = [])
    {
        $tableName = $moduleConfig['tableName'];
        $customCols = $moduleConfig['cols'] ?? [];
        $customQuery = $moduleConfig['listPage']['customQuery'] ?? null;
        
        // 處理 top 排序
        $sortSql = "";
        if (!empty($customCols['top'])) {
            $col_top = $customCols['top'] ?? 'd_top';
            $sortSql = "{$col_top} DESC, ";
        }
        
        $wherePart = !empty($whereClause) ? " WHERE {$whereClause}" : "";
        
        if ($customQuery) {
            $sql = "{$customQuery} {$wherePart} ORDER BY {$sortSql}{$orderBy} LIMIT :offset, :limit";
        } else {
            $sql = "SELECT * FROM {$tableName} {$wherePart} ORDER BY {$sortSql}{$orderBy} LIMIT :offset, :limit";
        }
        
        $params[':offset'] = [(int)$startRow, PDO::PARAM_INT];
        $params[':limit'] = [(int)$maxRows, PDO::PARAM_INT];
        
        return [
            'sql' => $sql,
            'params' => $params
        ];
    }

    /**
     * 取得總筆數查詢 (增強版)
     */
    public static function buildEnhancedCountQuery($moduleConfig, $whereClause = '', $params = [])
    {
        $tableName = $moduleConfig['tableName'];
        $customQuery = $moduleConfig['listPage']['customQuery'] ?? null;
        $wherePart = !empty($whereClause) ? " WHERE {$whereClause}" : "";
        
        if ($customQuery) {
            if (preg_match('/FROM\s+(.*)$/is', $customQuery, $matches)) {
                $fromPart = $matches[1];
                // 移除原有的 ORDER BY 或 LIMIT
                $fromPart = preg_replace('/ORDER\s+BY.*$/is', '', $fromPart);
                $fromPart = preg_replace('/LIMIT.*$/is', '', $fromPart);
                $sql = "SELECT COUNT(*) as total FROM {$fromPart} {$wherePart}";
            } else {
                $sql = "SELECT COUNT(*) as total FROM {$tableName} {$wherePart}";
            }
        } else {
            $sql = "SELECT COUNT(*) as total FROM {$tableName} {$wherePart}";
        }
        
        return [
            'sql' => $sql,
            'params' => $params
        ];
    }
}

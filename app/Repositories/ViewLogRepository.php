<?php
namespace App\Repositories;

use App\Models\Model;
use App\Traits\UserAgentDetectionTrait;
use App\Traits\GeoLocationTrait;

/**
 * ViewLogRepository
 * 專門處理文章瀏覽記錄與統計功能
 */
class ViewLogRepository extends Model {
    use UserAgentDetectionTrait;
    use GeoLocationTrait;

    protected $currentLang;

    public function __construct($db = null) {
        parent::__construct();
        if ($db) $this->db = $db;

        // 獲取當前語系
        if (isset($GLOBALS['frontend_lang']) && !empty($GLOBALS['frontend_lang'])) {
            $this->currentLang = $GLOBALS['frontend_lang'];
        } elseif (isset($_SESSION['frontend_lang']) && !empty($_SESSION['frontend_lang'])) {
            $this->currentLang = $_SESSION['frontend_lang'];
        } else {
            $this->currentLang = $this->getDefaultLanguage();
        }
    }

    /**
     * 獲取預設語系
     */
    private function getDefaultLanguage() {
        if (!$this->db) {
            return DEFAULT_LANG_SLUG;
        }

        try {
            $result = $this->db->row("SELECT l_slug FROM languages WHERE l_is_default = 1 AND l_active = 1 LIMIT 1");
            return $result['l_slug'] ?? DEFAULT_LANG_SLUG;
        } catch (\Exception $e) {
            return DEFAULT_LANG_SLUG;
        }
    }

    /**
     * 增加瀏覽次數 (使用資料庫記錄防止重複計數 - 5分鐘)
     *
     * @param int $d_id 文章 ID
     * @return bool 是否成功增加瀏覽次數
     */
    public function incrementView($d_id) {
        // -----------------------------------------------------------
        // [Level 2] 防機器人 (Bot/Crawler Filtering)
        // -----------------------------------------------------------
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ($this->isBot($userAgent)) {
            return false; // 判定為機器人，不動作
        }

        // -----------------------------------------------------------
        // [Level 1] 防重複觀看 (使用資料庫記錄 IP + 文章 ID)
        // -----------------------------------------------------------
        $ipAddress = $this->getClientIp();

        // 檢查此 IP 在 5 分鐘內是否已經瀏覽過此文章
        $checkSql = "SELECT id FROM view_log
                     WHERE article_id = ?
                     AND ip_address = ?
                     AND viewed_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                     LIMIT 1";
        $existing = $this->db->row($checkSql, [$d_id, $ipAddress]);

        if ($existing) {
            return false; // 5 分鐘內已經看過了，不動作
        }

        // -----------------------------------------------------------
        // [裝置資訊偵測] 解析 User-Agent
        // -----------------------------------------------------------
        $deviceType = $this->detectDeviceType($userAgent);
        $browser = $this->detectBrowser($userAgent);
        $os = $this->detectOS($userAgent);

        // -----------------------------------------------------------
        // [地理位置資訊] 根據 IP 取得國家和城市
        // -----------------------------------------------------------
        $geoData = $this->getGeoLocation($ipAddress);
        $country = $geoData['country'] ?? null;
        $city = $geoData['city'] ?? null;

        // -----------------------------------------------------------
        // [來源頁面]
        // -----------------------------------------------------------
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        // -----------------------------------------------------------
        // [Core] 執行資料庫更新
        // -----------------------------------------------------------
        $id = addslashes($d_id);
        $lang = addslashes($this->currentLang);

        $sql = "UPDATE data_set SET d_view = d_view + 1 WHERE d_id = '$id' AND lang = '$lang'";
        $result = $this->db->query($sql);

        // -----------------------------------------------------------
        // [Final] 寫入瀏覽記錄
        // -----------------------------------------------------------
        if ($result) {
            $userAgentData = substr($userAgent ?? '', 0, 500);
            $refererData   = substr($referer ?? '', 0, 500);

            $insertSql = "INSERT INTO view_log
                        (article_id, ip_address, user_agent, device_type, browser, os, country, city, referer, viewed_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $this->db->query($insertSql, [
                $d_id,
                $ipAddress,
                $userAgentData ?: null,
                $deviceType,
                $browser,
                $os,
                $country,
                $city,
                $refererData ?: null
            ]);
        }

        return $result;
    }

    /**
     * 取得文章的總瀏覽次數
     *
     * @param int $d_id 文章 ID
     * @return int 瀏覽次數
     */
    public function getViewCount($d_id) {
        $id = addslashes($d_id);
        $lang = addslashes($this->currentLang);

        $sql = "SELECT d_view FROM data_set WHERE d_id = '$id' AND lang = '$lang' LIMIT 1";
        $result = $this->db->row($sql);

        return (int)($result['d_view'] ?? 0);
    }

    /**
     * 取得熱門文章列表
     *
     * @param string $class1 文章類型 (如 'blog', 'news')
     * @param int $limit 限制筆數
     * @param int|null $categoryId 分類 ID (可選)
     * @return array 文章列表
     */
    public function getPopularArticles($class1, $limit = 10, $categoryId = null) {
        $c1 = addslashes($class1);
        $lang = addslashes($this->currentLang);
        $limit = (int)$limit;

        $sql = "SELECT d_id, d_title, d_slug, d_view, d_date
                FROM data_set
                WHERE d_class1 = '$c1'
                AND lang = '$lang'
                AND d_active = 1";

        if ($categoryId) {
            $cat = addslashes($categoryId);
            $sql .= " AND d_class2 = '$cat'";
        }

        $sql .= " ORDER BY d_view DESC LIMIT $limit";

        return $this->db->query($sql);
    }

    /**
     * 取得文章的瀏覽記錄統計
     *
     * @param int $d_id 文章 ID
     * @param int $days 統計天數 (預設 30 天)
     * @return array 統計資料
     */
    public function getViewStats($d_id, $days = 30) {
        $id = (int)$d_id;
        $days = (int)$days;

        $sql = "SELECT
                    COUNT(*) as total_views,
                    COUNT(DISTINCT ip_address) as unique_visitors,
                    device_type,
                    COUNT(*) as device_count
                FROM view_log
                WHERE article_id = $id
                AND viewed_at > DATE_SUB(NOW(), INTERVAL $days DAY)
                GROUP BY device_type";

        return $this->db->query($sql);
    }

    /**
     * 清理過期的瀏覽記錄
     * 建議定期執行此方法清理舊資料
     *
     * @param int $days 保留天數 (預設 90 天)
     * @return bool 是否成功
     */
    public function cleanOldLogs($days = 90) {
        $days = (int)$days;

        $sql = "DELETE FROM view_log
                WHERE viewed_at < DATE_SUB(NOW(), INTERVAL $days DAY)";

        return $this->db->query($sql);
    }

    /**
     * 取得地理位置統計
     *
     * @param int $d_id 文章 ID (可選，不傳則統計全站)
     * @param int $days 統計天數 (預設 30 天)
     * @param int $limit 限制筆數
     * @return array 地理位置統計
     */
    public function getGeoStats($d_id = null, $days = 30, $limit = 10) {
        $days = (int)$days;
        $limit = (int)$limit;

        $sql = "SELECT
                    country,
                    city,
                    COUNT(*) as view_count
                FROM view_log
                WHERE viewed_at > DATE_SUB(NOW(), INTERVAL $days DAY)";

        if ($d_id) {
            $id = (int)$d_id;
            $sql .= " AND article_id = $id";
        }

        $sql .= " AND country IS NOT NULL
                GROUP BY country, city
                ORDER BY view_count DESC
                LIMIT $limit";

        return $this->db->query($sql);
    }

    /**
     * 取得儀表板統計資料
     * 用於 CMS 後台儀表板顯示
     *
     * @param int $days 統計天數 (預設 7 天)
     * @return array 統計資料
     */
    public function getDashboardStats($days = 7) {
        $days = (int)$days;

        // 1. 總瀏覽次數
        $totalViewsSql = "SELECT COUNT(*) as total FROM view_log
                         WHERE viewed_at > DATE_SUB(NOW(), INTERVAL $days DAY)";
        $totalViews = $this->db->row($totalViewsSql);

        // 2. 不重複訪客數
        $uniqueVisitorsSql = "SELECT COUNT(DISTINCT ip_address) as unique_count FROM view_log
                             WHERE viewed_at > DATE_SUB(NOW(), INTERVAL $days DAY)";
        $uniqueVisitors = $this->db->row($uniqueVisitorsSql);

        // 3. 今日瀏覽次數
        $todayViewsSql = "SELECT COUNT(*) as today_total FROM view_log
                         WHERE DATE(viewed_at) = CURDATE()";
        $todayViews = $this->db->row($todayViewsSql);

        // 4. 裝置類型分布
        $deviceStatsSql = "SELECT
                            device_type,
                            COUNT(*) as count,
                            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM view_log WHERE viewed_at > DATE_SUB(NOW(), INTERVAL $days DAY)), 1) as percentage
                          FROM view_log
                          WHERE viewed_at > DATE_SUB(NOW(), INTERVAL $days DAY)
                          GROUP BY device_type
                          ORDER BY count DESC";
        $deviceStats = $this->db->query($deviceStatsSql);

        // 5. 熱門文章 Top 5 (包含文章類型)
        $popularArticlesSql = "SELECT
                                vl.article_id,
                                ds.d_title,
                                ds.d_class1,
                                COUNT(*) as view_count
                              FROM view_log vl
                              LEFT JOIN data_set ds ON vl.article_id = ds.d_id AND ds.lang = ?
                              WHERE vl.viewed_at > DATE_SUB(NOW(), INTERVAL $days DAY)
                              GROUP BY vl.article_id, ds.d_title, ds.d_class1
                              ORDER BY view_count DESC
                              LIMIT 5";
        $popularArticles = $this->db->query($popularArticlesSql, [$this->currentLang]);

        // 6. 取得模組名稱對應表
        $moduleNames = $this->getModuleNames();

        // 7. 為每篇文章加上模組名稱
        if ($popularArticles) {
            foreach ($popularArticles as &$article) {
                $article['module_name'] = $moduleNames[$article['d_class1']] ?? $article['d_class1'];
            }
        }

        return [
            'total_views' => (int)($totalViews['total'] ?? 0),
            'unique_visitors' => (int)($uniqueVisitors['unique_count'] ?? 0),
            'today_views' => (int)($todayViews['today_total'] ?? 0),
            'device_stats' => $deviceStats ?: [],
            'popular_articles' => $popularArticles ?: [],
            'days' => $days
        ];
    }

    /**
     * 取得模組名稱對應表
     * 優先使用 config.php 中的 VIEW_LOG_MODULE_NAMES 設定
     * 如果沒有設定，則自動掃描 cms/set/ 目錄
     *
     * @return array 模組名稱對應表
     */
    private function getModuleNames() {
        // 優先使用 config.php 中的設定
        if (defined('VIEW_LOG_MODULE_NAMES') && is_array(VIEW_LOG_MODULE_NAMES)) {
            return VIEW_LOG_MODULE_NAMES;
        }

        // 如果沒有設定，則自動掃描（向後相容）
        $moduleNames = [];
        $setDir = realpath(__DIR__ . '/../../cms/set/');

        if (!$setDir || !is_dir($setDir)) {
            return $moduleNames;
        }

        $setFiles = glob($setDir . '/*Set.php');

        foreach ($setFiles as $file) {
            try {
                $config = require $file;

                // 檢查是否為 data_set 相關的模組
                if (isset($config['tableName']) && $config['tableName'] === 'data_set'
                    && isset($config['menuValue']) && isset($config['moduleName'])) {

                    $menuValue = $config['menuValue'];
                    $moduleName = $config['moduleName'];

                    // 移除「管理」兩個字，讓顯示更簡潔
                    $moduleName = str_replace('管理', '', $moduleName);

                    $moduleNames[$menuValue] = $moduleName;
                }
            } catch (\Exception $e) {
                // 忽略錯誤的設定檔
                continue;
            }
        }

        return $moduleNames;
    }
}

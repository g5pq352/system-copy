<?php
/**
 * Subsite Helper
 * 處理多租戶 (Multi-tenancy) 資料庫切換與初始化
 */

class SubsiteHelper
{
    /**
     * 獲取目前 Domain 對應的資料庫配置
     * @param array $masterConfig 主系統資料庫配置
     * @return array 資料庫配置
     */
    public static function getDynamicConfig($masterConfig)
    {
        // 進入或切換預覽模式 (不分網域)
        if (isset($_GET['preview_id'])) {
            $prevId = (int)$_GET['preview_id'];
            if ($prevId == 0) {
                unset($_SESSION['current_preview_id']);
                // 為了重新整理環境，重導向回當前頁面但移除參數
                $url = strtok($_SERVER["REQUEST_URI"], '?');
                header("Location: " . $url);
                exit;
            } else {
                $_SESSION['current_preview_id'] = $prevId;
                // 為了重新整理環境，重導向回當前頁面但移除參數
                $url = strtok($_SERVER["REQUEST_URI"], '?');
                header("Location: " . $url);
                exit;
            }
        }

        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $previewId = $_SESSION['current_preview_id'] ?? null;

        // 如果既沒有網域匹配，也沒有預覽 ID，則回傳主配置
        if (empty($previewId) && (empty($currentHost) || in_array($currentHost, ['localhost', '127.0.0.1']))) {
            return $masterConfig;
        }

        try {
            $dsn = "mysql:host={$masterConfig['host']};dbname={$masterConfig['dbname']};charset=utf8";
            $conn = new PDO($dsn, $masterConfig['username'], $masterConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2
            ]);

            $site = null;
            if ($previewId) {
                // 優先使用預覽 ID
                $stmt = $conn->prepare("SELECT * FROM data_set WHERE d_id = :id AND d_class1 = 'websites' LIMIT 1");
                $stmt->execute([':id' => $previewId]);
                $site = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$site && !empty($currentHost)) {
                // 否則使用網域匹配
                $stmt = $conn->prepare("SELECT * FROM data_set WHERE d_class1 = 'websites' AND d_slug = :host AND d_active = 1 LIMIT 1");
                $stmt->execute([':host' => $currentHost]);
                $site = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($site && !empty($site['d_data2'])) {
                return [
                    'host'     => !empty($site['d_data1']) ? $site['d_data1'] : $masterConfig['host'],
                    'dbname'   => $site['d_data2'],
                    'username' => !empty($site['d_data3']) ? $site['d_data3'] : $masterConfig['username'],
                    'password' => !empty($site['d_data4']) ? $site['d_data4'] : $masterConfig['password'],
                    'site_id'  => $site['d_id']
                ];
            }
        } catch (Exception $e) {
            error_log("Subsite Switcher Error: " . $e->getMessage());
        }

        return $masterConfig;
    }

    /**
     * 自動化建立子網站資料庫與匯入結構
     * @param PDO $masterConn 主系統連線
     * @param int $siteId 網站序號
     * @param array $postData 提交的資料
     */
    /**
     * 自動化建立子網站資料庫與匯入結構
     */
    public static function factory($masterConn, $siteId, $postData)
    {
        // === DEBUG ===
        $logFile = realpath(__DIR__ . '/../../') . '/subsite_post_log.txt';
        $logStr = "=== POST DUMP ===\n" . print_r($postData, true) . "\n================\n";
        @file_put_contents($logFile, $logStr, FILE_APPEND);
        // =============

        $dbName = $postData['d_data2'] ?? '';
        // 直接從英文標題產生資料夾名稱 (Slug)
        $slug = self::sanitizeSlug($postData['d_title_en'] ?? '');
        
        if (empty($dbName) || empty($slug)) return;

        // 1. 抓取標準/進階模組設定 (供後續資料庫初始化使用)
        $standardModules = isset($postData['d_data5']) ? (is_array($postData['d_data5']) ? $postData['d_data5'] : explode(',', $postData['d_data5'])) : [];
        
        // 直接從 $postData 抓取使用者剛才填寫的進階自訂模組
        $customModules = [];
        if (isset($postData['custom_modules']) && is_array($postData['custom_modules'])) {
            foreach ($postData['custom_modules'] as $idx => $m) {
                $mName = $m['m_name'] ?? '';
                $mSlug = $m['m_slug'] ?? '';
                $mType = $m['m_type'] ?? 'news_single';
                
                if (!empty($mName) && !empty($mSlug)) {
                    $customModules[] = [
                        'm_name' => $mName,
                        'm_slug' => $mSlug,
                        'm_type' => $mType
                    ];
                }
            }
        }

        try {
            // A. 建立目標資料夾 (WAMP www 同層)
            $src = realpath(__DIR__ . '/../../');
            $dst = realpath($src . '/../') . DIRECTORY_SEPARATOR . $slug;

            $isNewSite = !is_dir($dst);

            if ($isNewSite) {
                // 加載 .gitignore 規則
                $ignorePatterns = self::loadGitignorePatterns($src);
                
                // B. 執行全量實體克隆 (排除 .gitignore 中的檔案)
                set_time_limit(0); // 大型目錄複製不限時
                self::recursiveCopy($src, $dst, $src, $ignorePatterns);
                set_time_limit(120); // 複製完成後恢復預設
                
                // C. 修改新站點的設定檔 (.env & config.php)
                self::updateConfigs($dst, $postData, $slug, $src);
            } else {
                // 如果站點已存在，僅同步設定檔 (例如 DB 帳密異動)
                self::updateConfigs($dst, $postData, $slug, $src);
                
                // 非新站點則跳過後續 Destructive DB 初始化，避免覆蓋已開發內容
                return;
            }

            // D. 建立資料庫 (如果不存在)
            $masterConn->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci");
            
            // E. 獲取新資料庫連線
            if (empty($postData['d_data3'])) {
                $dbUser = 'root';
                $dbPass = ''; // 若帳號留空(依賴預設)，則強制密碼也為空(防堵瀏覽器亂塞亂填)
            } else {
                $dbUser = $postData['d_data3'];
                $dbPass = $postData['d_data4'] ?? '';
            }
            $dsn = "mysql:host=localhost;dbname={$dbName};charset=utf8";
            $subConn = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // F. 匯入基礎 SQL 結構
            $sqlFile = $src . '/sql/templatev1.0.3-real.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $subConn->exec($sql);
            }

            // G. 初始化內容模式與選單
            self::ensureCmsMenuColumns($subConn);
            self::initializeHybridModules($subConn, $standardModules, $customModules, $dst);
            
            // H. 自動賦予管理員群組 (Group 1) 完整權限
            self::grantFullAdminPermissions($subConn);

            // H. 建立/重置預設管理員帳號 (admin / 1234)
            self::createDefaultAdmin($subConn);

        } catch (Exception $e) {
            $logFile = realpath(__DIR__ . '/../../') . '/subsite_post_log.txt';
            @file_put_contents($logFile, "SQL ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            error_log("Website Factory Error: " . $e->getMessage());
        }
    }

    /**
     * 確保子網站的 cms_menus 資料表具備繼承所需欄位
     */
    private static function ensureCmsMenuColumns($conn)
    {
        $cols = [
            'menu_base_type' => "VARCHAR(50) DEFAULT NULL COMMENT '繼承的基礎模板'",
            'menu_config_override' => "TEXT DEFAULT NULL COMMENT '動態覆蓋設定 (JSON)'"
        ];

        foreach ($cols as $col => $def) {
            try {
                $check = $conn->query("SHOW COLUMNS FROM cms_menus LIKE '{$col}'");
                if ($check->rowCount() == 0) {
                    $conn->exec("ALTER TABLE cms_menus ADD COLUMN `{$col}` {$def}");
                }
            } catch (Exception $e) {
                error_log("Failed to add column {$col}: " . $e->getMessage());
            }
        }
    }

    /**
     * 輔助函數:將 slug 轉換為 CamelCase (例如 about-us -> AboutUs)
     */
    private static function toCamelCase($string) {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }

    /**
     * 輔助函數: 處理模板資料夾映射 (例如 news -> news_sys)
     * $isSystem 代表是否為系統功能分頁 (列表/詳細)
     */
    private static function getTemplateFolderMapping($slug, $isSystem = true) {
        if ($slug === 'news') return $isSystem ? 'news_sys' : 'news';
        if ($slug === 'contactus') return 'contact';
        if ($slug === 'info') return 'about';
        if ($slug === 'product' && $isSystem) return 'product_sys'; 
        return $slug;
    }

    private static function initializeHybridModules($conn, $standardSlugs, $customModules, $dst)
    {
        // 1. 基本模組配置定義 (參照 templatev1.0.3-real.sql)
        $templates = [
            'news' => [
                'title' => '最新消息',
                'icon'  => 'bx bx-file',
                'base'  => 'news',
                'id_num' => '8',
                'schema' => ['table' => 'data_set', 'pk' => 'd_id'],
                'hasHierarchy' => false,
                'taxonomy' => ['identifier' => 'newsC', 'slug' => '最新消息', 'ttp_name' => '最新消息']
            ],
            'product' => [
                'title' => '產品管理',
                'icon'  => 'bx bx-file',
                'base'  => 'product',
                'id_num' => '',
                'schema' => ['table' => 'data_set', 'pk' => 'd_id'],
                'hasHierarchy' => true,
                'taxonomy' => ['identifier' => 'productC', 'slug' => '產品', 'ttp_name' => '產品']
            ],
            'contactus' => [
                'title' => '聯絡我們',
                'icon'  => 'bx bx-detail',
                'base'  => 'contactus',
                'id_num' => '9',
                'schema' => ['table' => 'message_set', 'pk' => 'm_id'],
                'hasHierarchy' => false,
                'taxonomy' => null
            ]
        ];

        $sort = 1;

        // 【關鍵清理】清空 Dump 內的「範本」資料，以便完全依照網站設定重新生成
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $conn->exec("TRUNCATE TABLE taxonomy_types");
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $conn->exec("DELETE FROM cms_menus WHERE menu_link IN ('tpl=news/list', 'tpl=product/list', 'tpl=contactus/list') OR menu_base_type IN ('news', 'product', 'contactus') OR menu_type IN ('news', 'product', 'contactus') OR menu_type LIKE 'news%' OR menu_type LIKE 'product%' OR menu_type LIKE 'contactus%'");

        // A. 處理標準模組 (依照使用者是否有勾選來建立)
        foreach ($standardSlugs as $slug) {
            if (!isset($templates[$slug])) continue;
            $t = $templates[$slug];
            self::createModuleStructure($conn, $t, $slug, $sort++);
            
            // 【新增】生成前台檔案 (Controller & Views)
            self::generateFrontendModuleFiles($slug, $t['title'], $t['base'], $dst);
        }

        // B. 處理自訂模組 (將其看作 news 類型的變體)
        foreach ($customModules as $m) {
            $name = $m['m_name'] ?? '';
            $slug = $m['m_slug'] ?? '';
            $type = $m['m_type'] ?? 'news_single'; // news_single, news_multi, etc.

            if (empty($name) || empty($slug)) continue;

            $isHierarchy = (strpos($type, 'multi') !== false);

            if (strpos($type, 'contactus') !== false) {
                $base = 'contactus';
                $taxonomy = null;
            } elseif ($type === 'info') {
                $base = 'info';
                $taxonomy = null;
            } elseif ($type === 'list_only') {
                $base = 'list_only';
                $taxonomy = null;
            } else {
                $base = (strpos($type, 'news') !== false) ? 'news' : 'product';
                $taxonomy = ['identifier' => $slug . 'C', 'slug' => $name, 'ttp_name' => $name];
            }

            $t = [
                'title'        => $name,
                'icon'         => 'bx bx-grid-alt',
                'base'         => $base,
                'id_num'       => '',
                'schema'       => ['table' => ($base === 'contactus' ? 'message_set' : 'data_set'), 'pk' => ($base === 'contactus' ? 'm_id' : 'd_id')],
                'hasHierarchy' => $isHierarchy,
                'taxonomy'     => $taxonomy,
                'pageType'     => ($base === 'info') ? 'info' : 'list',  // info 型只有設定頁面
            ];
            self::createModuleStructure($conn, $t, $slug, $sort++);

            // 【新增】生成自訂模組的前台檔案 (傳入階層資訊)
            self::generateFrontendModuleFiles($slug, $name, $base, $dst, $isHierarchy);

            // 動態產生自訂模組的 Set 檔案 (含 Cate/Tag)
            // info/tag_only/contactus 都只有主 Set，不需要 Cate/Tag
            $suffixes = [''];
            if ($taxonomy !== null) {
                $suffixes[] = 'Cate';
                $suffixes[] = 'Tag';
            }

            // 映射 base => 實際要拷貝的原始檔案 (依照單層/多層架構動態選擇)
            $baseFileMap = [
                'news'      => 'news',
                'product'   => 'product',
                'contactus' => 'contactus',
                'info'      => 'popInfo',
                'list_only' => 'newsTag',
            ];
            $srcBaseName = $baseFileMap[$base] ?? $base;
            if ($base === 'news' || $base === 'product') {
                $srcBaseName = $isHierarchy ? 'product' : 'news';
            }

            foreach ($suffixes as $suffix) {
                $setFileSrc = $dst . '/cms/set/' . $srcBaseName . $suffix . 'Set.php';
                $setFileDst = $dst . '/cms/set/' . $slug . $suffix . 'Set.php';

                if (file_exists($setFileSrc) && !file_exists($setFileDst)) {
                    $content = file_get_contents($setFileSrc);

                    if ($base === 'contactus') {
                        // contactus 型：替換 $menu_is、moduleName、menuValue
                        $content = str_replace(
                            [
                                '$menu_is = "contactus";',
                                "'moduleName' => '聯絡我們表單',",
                                "'menuValue' => 'contactus',",
                            ],
                            [
                                '$menu_is = "' . $slug . '";',
                                "'moduleName' => '" . $name . "表單',",
                                "'menuValue' => '" . $slug . "',",
                            ],
                            $content
                        );
                    } elseif ($base === 'info') {
                        // info 型：替換 popInfo 相關識別碼
                        $content = str_replace(
                            [
                                '$menu_is = "popInfo";',
                                "'moduleName' => '燈箱設定',",
                                "'menuValue' => \$menu_is,",
                                "'fileType' => 'popInfoCover',",
                                "'fileType' => 'popInfoSimple',",
                                "'d_class1' => \$menu_is,",
                            ],
                            [
                                '$menu_is = "' . $slug . '";',
                                "'moduleName' => '" . $name . "設定',",
                                "'menuValue' => \$menu_is,",
                                "'fileType' => '" . $slug . "Cover',",
                                "'fileType' => '" . $slug . "Simple',",
                                "'d_class1' => \$menu_is,",
                            ],
                            $content
                        );
                    } elseif ($base === 'list_only') {
                        // list_only 型：純列表不含分類，透過 newsTag 為範本，替換識別碼
                        $content = str_replace(
                            [
                                '$menu_is = "newsTag";',
                                "'moduleName' => '房間設施管理',",
                                "'imageFileType' => 'newsTag',",
                                "'menuValue' => \$menu_is,",
                                "'d_class1' => \$menu_is",
                            ],
                            [
                                '$menu_is = "' . $slug . '";',
                                "'moduleName' => '" . $name . "管理',",
                                "'imageFileType' => '" . $slug . "',",
                                "'menuValue' => \$menu_is,",
                                "'d_class1' => \$menu_is",
                            ],
                            $content
                        );
                    } else {
                        // news / product 型
                        // 動態決定要搜尋的範本關鍵字
                        $srcKey = $srcBaseName; // 'news' 或 'product'
                        $srcTitle = ($srcBaseName === 'product') ? '產品' : '最新消息';

                        $suffixTitle = '';
                        if ($suffix === 'Cate') $suffixTitle = '分類';
                        if ($suffix === 'Tag')  $suffixTitle = '標籤';

                        $content = str_replace(
                            [
                                '$menu_is = "' . $srcKey . $suffix . '";',
                                '$category = "' . $srcKey . 'Cate";',
                                "'moduleName' => '" . $srcTitle . $suffixTitle . "管理',",
                                "'moduleName' => '" . $srcTitle . $suffixTitle . "',",
                                "'" . $srcKey . "Tag'",
                                "'imageFileType' => '" . $srcKey . "Cover'",
                                "'fileType' => '" . $srcKey . "Cover'",
                            ],
                            [
                                '$menu_is = "' . $slug . $suffix . '";',
                                '$category = "' . $slug . 'Cate";',
                                "'moduleName' => '" . $name . $suffixTitle . "管理',",
                                "'moduleName' => '" . $name . $suffixTitle . "',",
                                "'" . $slug . "Tag'",
                                "'imageFileType' => '" . $slug . "Cover'",
                                "'fileType' => '" . $slug . "Cover'",
                            ],
                            $content
                        );

                        if ($suffix === '') {
                            $hierarchyStr = $isHierarchy ? 'true' : 'false';
                            $content = preg_replace('/\$hasHierarchy\s*=\s*(true|false);/', '$hasHierarchy = ' . $hierarchyStr . ';', $content);
                        }
                    }

                    file_put_contents($setFileDst, $content);
                }
            }
        }

        // ================================================================
        // 【新增】自動產生前端導覽選單 (menus_set)
        // ================================================================
        $conn->exec("DELETE FROM menus_set WHERE 1=1"); // 清空舊的前端選單
        $frontSort = 1;
        $stmtMenu = $conn->prepare("INSERT INTO menus_set (lang, m_parent_id, m_level, m_title_ch, m_title_en, m_link, m_slug, m_target, m_sort, m_active, m_depth) VALUES ('tw', 0, 0, ?, ?, ?, ?, 0, ?, 1, 1)");

        // A. 標準模組的前端選單
        foreach ($standardSlugs as $slug) {
            if (!isset($templates[$slug])) continue;
            $t = $templates[$slug];

            // 如果有特殊硬編碼的不需要顯示（例如強制叫 popInfo），則在這裡排除
            if ($slug === 'popInfo') continue;

            // contactus → /$slug (保持與路由一致以防 404)
            if ($slug === 'contactus') {
                $stmtMenu->execute([$t['title'], 'CONTACT', '/contactus', $slug, $frontSort++]);
            } else {
                $stmtMenu->execute([$t['title'], strtoupper($slug), '/' . $slug, $slug, $frontSort++]);
            }
        }

        // B. 自訂模組的前端選單
        foreach ($customModules as $m) {
            $name = $m['m_name'] ?? '';
            $slug = $m['m_slug'] ?? '';
            $type = $m['m_type'] ?? 'news_single';
            if (empty($name) || empty($slug)) continue;

            // 移除 info 型不加入導覽的限制，讓「關於單頁(info)」也能正常顯示在選單
            if ($slug === 'popInfo') continue;

            // contactus 型也用自己的 slug 當連結
            $stmtMenu->execute([$name, strtoupper($slug), '/' . $slug, $name, $frontSort++]);
        }

        // 創建完成後，重新整理排序讓它連續
        $conn->exec("SET @i = 0; UPDATE taxonomy_types SET sort_order = (@i := @i + 1) ORDER BY sort_order ASC");
        
        // 後台選單排序：首頁 → 動態模組 → 系統選單（全站>權限管理>選單管理>圖片庫）
        $conn->exec("SET @j = 0; UPDATE cms_menus SET menu_sort = (@j := @j + 1) 
            WHERE menu_parent_id = 0 
            ORDER BY 
                CASE 
                    WHEN menu_link = 'tpl=popInfo/info' THEN 0
                    WHEN menu_type = 'keywordsInfo' THEN 90
                    WHEN menu_link = 'tpl=admin/list' THEN 91
                    WHEN menu_link = 'tpl=menus/list' THEN 92
                    WHEN menu_link = 'picture_library' THEN 93
                    ELSE 1
                END ASC, 
                menu_sort ASC");

        $conn->exec("SET @k = 0; UPDATE menus_set SET m_sort = (@k := @k + 1) WHERE m_parent_id = 0 ORDER BY m_sort ASC");
    }

    /**
     * 建立模組的完整結構 (Taxonomy + CMS Menus Tree)
     */
    private static function createModuleStructure($conn, $t, $slug, $sort)
    {
        // 1. 建立單一 Taxonomy Type
        $taxId = null;
        if (!empty($t['taxonomy'])) {
            $tax = $t['taxonomy'];
            
            $stmt = $conn->prepare("INSERT INTO taxonomy_types (ttp_name, t_slug, identifier, ttp_category, lang) VALUES (?, ?, ?, ?, 'tw')");
            $stmt->execute([$tax['ttp_name'], $tax['slug'], $tax['identifier'], $tax['identifier']]);
            $taxId = $conn->lastInsertId();
        }

        // 2. 建立 Parent Menu
        // 如果有子項目，則父項連結為主目錄用的佔位符，或直接連到第一個子項
        $isInfo = (($t['pageType'] ?? 'list') === 'info');
        $menuLink = $isInfo ? "tpl={$slug}/info" : "tpl={$slug}/list";
        
        $stmtParent = $conn->prepare("INSERT INTO cms_menus (menu_title, menu_icon, menu_sort, menu_active, menu_id_num, menu_link) VALUES (?, ?, ?, 1, ?, ?)");
        $stmtParent->execute([$t['title'], $t['icon'], $sort, $t['id_num'], $menuLink]);
        $parentId = $conn->lastInsertId();

        // 3. 建立子選單 (Content / Settings)
        $childTitle = $isInfo ? $t['title'] . '設定' : $t['title'];
        $childLink  = $isInfo ? "tpl={$slug}/info" : "tpl={$slug}/list";
        
        $override = [
            'moduleName' => $t['title'],
            'menuValue'  => $slug,
            'listPage'   => ['hasHierarchy' => $t['hasHierarchy'] ?? false]
        ];
        $stmtChild = $conn->prepare("INSERT INTO cms_menus (menu_parent_id, menu_title, menu_type, menu_link, menu_icon, menu_sort, menu_active, menu_base_type, menu_config_override, menu_table, menu_pk) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?)");
        $stmtChild->execute([
            $parentId, $childTitle, $slug, $childLink, "", $t['base'], 
            json_encode($override, JSON_UNESCAPED_UNICODE), $t['schema']['table'], $t['schema']['pk']
        ]);

        // 如果該模組有啟用 Taxonomy，就建立分類跟標籤選單並共用該 taxonomy_type_id
        if ($taxId) {
            // 4. 建立子選單 (Category)
            $catSlug = $slug . 'Cate';
            $catOverride = ['hasHierarchy' => $t['hasHierarchy'], 'taxonomy_type_id' => $taxId];
            $stmtChild->execute([
                $parentId, '分類', $catSlug, "tpl={$catSlug}/list", "", 
                'taxonomy', json_encode($catOverride, JSON_UNESCAPED_UNICODE), 'taxonomies', 't_id'
            ]);
            // 更新該選單的 taxonomy_type_id 欄位 (實體欄位)
            $lastId = $conn->lastInsertId();
            $conn->prepare("UPDATE cms_menus SET taxonomy_type_id = ? WHERE menu_id = ?")->execute([$taxId, $lastId]);

            // 5. 建立子選單 (Tag)
            $tagSlug = $slug . 'Tag';
            $tagOverride = ['hasHierarchy' => false, 'taxonomy_type_id' => $taxId];
            $stmtChild->execute([
                $parentId, '標籤', $tagSlug, "tpl={$tagSlug}/list", "", 
                'taxonomy', json_encode($tagOverride, JSON_UNESCAPED_UNICODE), 'taxonomies', 't_id'
            ]);
            $lastId = $conn->lastInsertId();
            $conn->prepare("UPDATE cms_menus SET taxonomy_type_id = ? WHERE menu_id = ?")->execute([$taxId, $lastId]);
        }
    }

    /**
     * 遞迴拷貝目錄 (支援 .gitignore 過濾)
     */
    // 硬排除清單：無論 .gitignore 怎麼寫，這些一律跳過
    private static $hardExcludes = [
        '.git',
        'node_modules',
        'public/uploads',
        'public/temp',
        'subsite_post_log.txt',
    ];

    // 強制保留清單：即使在 .gitignore 裡，也一定要複製過去
    private static $forceIncludes = [
        'views',
        'template',
        'sass',
        'src',
        'dist',
    ];

    private static function recursiveCopy($src, $dst, $root, $patterns)
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;

            $fullSrcPath = $src . '/' . $file;
            $relative    = ltrim(str_replace($root, '', $fullSrcPath), '/\\');
            $relative    = str_replace('\\', '/', $relative);

            // 硬排除優先（不管白名單）
            $skip = false;
            foreach (self::$hardExcludes as $ex) {
                if ($file === $ex || $relative === $ex || strpos($relative, $ex . '/') === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // 強制保留：bypass .gitignore 過濾
            $forceKeep = false;
            foreach (self::$forceIncludes as $inc) {
                if ($file === $inc || $relative === $inc || strpos($relative, $inc . '/') === 0) {
                    $forceKeep = true;
                    break;
                }
            }

            // .gitignore 規則排除（白名單可跳過）
            if (!$forceKeep && self::shouldIgnore($relative, $patterns)) continue;

            if (is_dir($fullSrcPath)) {
                self::recursiveCopy($fullSrcPath, $dst . '/' . $file, $root, $patterns);
            } else {
                copy($fullSrcPath, $dst . '/' . $file);
            }
        }
        closedir($dir);
    }

    /**
     * 自動賦予管理員群組 (Group 1) 對所有選單的完整權限
     */
    private static function grantFullAdminPermissions($conn)
    {
        try {
            // 找出所有啓用的選單
            $stmt = $conn->query("SELECT menu_id FROM cms_menus WHERE menu_active = 1");
            $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($menus)) return;

            // 清除可能存在的舊權限
            $conn->exec("DELETE FROM group_permissions WHERE group_id = 1");

            // 為管理員群組 (ID: 1) 插入所有選單完整權限
            $insertStmt = $conn->prepare("
                INSERT IGNORE INTO group_permissions (group_id, menu_id, can_view, can_add, can_edit, can_delete) 
                VALUES (1, :menu_id, 1, 1, 1, 1)
            ");

            foreach ($menus as $m) {
                $insertStmt->execute([':menu_id' => $m['menu_id']]);
            }
        } catch (Exception $e) {
            error_log("Failed to grant admin permissions: " . $e->getMessage());
        }
    }

    /**
     * 解析 .gitignore
     */
    private static function loadGitignorePatterns($src)
    {
        $patterns = [];
        $gitignore = $src . '/.gitignore';
        if (file_exists($gitignore)) {
            $lines = file($gitignore, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) continue;
                $patterns[] = $line;
            }
        }
        return $patterns;
    }

    /**
     * 判斷是否應該忽略
     */
    private static function shouldIgnore($relativePath, $patterns)
    {
        // 統一斜線
        $relativePath = str_replace('\\', '/', $relativePath);
        
        foreach ($patterns as $pattern) {
            $pattern = str_replace('\\', '/', $pattern);
            
            // 處理目錄結尾的斜線
            $isDirPattern = (substr($pattern, -1) === '/');
            $cleanPattern = rtrim($pattern, '/');
            
            // 簡單的 Glob 匹配
            if (fnmatch($cleanPattern, $relativePath) || fnmatch($cleanPattern . '/*', $relativePath)) {
                return true;
            }
            // 處理開頭斜線 (絕對路徑匹配)
            if (substr($cleanPattern, 0, 1) === '/') {
                if (strpos($relativePath, ltrim($cleanPattern, '/')) === 0) return true;
            }
        }
        return false;
    }

    /**
     * 淨化 Slug (從英文標題轉換)
     */
    public static function sanitizeSlug($title)
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * 自動修正子網站環境設定
     */
    private static function updateConfigs($targetPath, $postData, $slug, $src)
    {
        $dbName = $postData['d_data2'] ?? '';

        // 1. 更新 .env (如果不存在，則從 .env_example 拷貝)
        $envFile = $targetPath . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($envFile)) {
            $example = $src . DIRECTORY_SEPARATOR . '.env_example';
            if (file_exists($example)) copy($example, $envFile);
        }

        if (file_exists($envFile)) {
            $env = file_get_contents($envFile);
            // 修正為符合 .env 實際使用的變數名稱
            $env = preg_replace('/DB_NAME=.*/', 'DB_NAME=' . $dbName, $env);
            $env = preg_replace('/DEV_DB_NAME=.*/', 'DEV_DB_NAME=' . $dbName, $env);
            if (empty($postData['d_data3'])) {
                $dbUser = 'root';
                $dbPass = '';
            } else {
                $dbUser = $postData['d_data3'];
                $dbPass = $postData['d_data4'] ?? '';
            }
            $env = preg_replace('/DB_USER=.*/', 'DB_USER=' . $dbUser, $env);
            $env = preg_replace('/DB_PASS=.*/', 'DB_PASS=' . $dbPass, $env);
            
            // 強制切換為 template 模式
            if (preg_match('/SYSTEM_TEMPLATE=.*/', $env)) {
                $env = preg_replace('/SYSTEM_TEMPLATE=.*/', 'SYSTEM_TEMPLATE=template', $env);
            } else {
                $env .= "\nSYSTEM_TEMPLATE=template\n";
            }

            file_put_contents($envFile, $env);
        }

        // 2. 更新 config/config.php (修正 APP_ROOT_PATH)
        $configFile = $targetPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        if (file_exists($configFile)) {
            $config = file_get_contents($configFile);
            // 將原本的路徑替換為新的 slug (支援三元運算式格式)
            $config = preg_replace("/define\('APP_ROOT_PATH', IS_LOCAL \? '.*' : ''\);/", "define('APP_ROOT_PATH', IS_LOCAL ? '/" . $slug . "' : '');", $config);
            file_put_contents($configFile, $config);
        }

        // 3. 同步 app/template_set.php (確保新架構不報錯)
        $targetTplSet = $targetPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'template_set.php';
        if (!file_exists($targetTplSet)) {
            $srcTplSet = $src . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'template_set.php';
            if (file_exists($srcTplSet)) {
                if (!is_dir(dirname($targetTplSet))) mkdir(dirname($targetTplSet), 0755, true);
                copy($srcTplSet, $targetTplSet);
            }
        }
    }

    /**
     * 建立或重設為預設管理員
     * 帳號: admin, 密碼: 1234
     */
    private static function createDefaultAdmin($conn)
    {
        try {
            $user = 'admin';
            $pass = password_hash('1234', PASSWORD_DEFAULT);
            
            // 使用 REPLACE INTO 確保 ID 1 被更新
            $stmt = $conn->prepare("REPLACE INTO admin (user_id, user_name, user_password, user_salt, user_level, group_id, user_active) 
                                   VALUES (1, ?, ?, NULL, 1, 1, 1)");
            $stmt->execute([$user, $pass]);
        } catch (Exception $e) {
            error_log("Failed to create default admin: " . $e->getMessage());
        }
    }

    /**
     * 【重要】自動生成前台 Controllers 與 Views
     * 基於 DataRepository 的萬用撈取邏輯，生成實體檔案供後續開發微調。
     */
    private static function generateFrontendModuleFiles($slug, $name, $baseType, $dst, $isHierarchy = false)
    {
        $templateDir = __DIR__ . '/templates/frontend/';
        $controllerTmpl = '';
        $views = [];
        $viewType = is_dir($dst . '/template') ? 'template' : 'views';
        $wrapperTmpl = $templateDir . 'Wrapper.tpl';
        $bridgeTmpl  = $templateDir . 'Bridge.tpl';

        // 取得對應的模板目錄名稱 (系統分頁模式)
        $folder = self::getTemplateFolderMapping($slug, true);

        // 根據模組類型選擇樣板
        if ($baseType === 'news' || $baseType === 'product') {
            $controllerTmpl = $templateDir . 'Controller.tpl';
            if ($viewType === 'template') {
                $views = [
                    // [來源樣板, 檔名, 標籤, 配置Key]
                    [$templateDir . 'view_list.tpl',   $slug . '_list',   'list',   $slug . '_list'],
                    [$templateDir . 'view_detail.tpl', $slug . '_detail', 'detail', $slug . '_detail']
                ];
            } else {
                $views = [
                    [$templateDir . 'view_list.tpl',   $slug . '_list'],
                    [$templateDir . 'view_detail.tpl', $slug . '_detail']
                ];
            }
        } elseif ($baseType === 'info') {
            $controllerTmpl = $templateDir . 'InfoController.tpl';
            if ($viewType === 'template') {
                $views = [
                    [$templateDir . 'view_info.tpl',   $slug,             'info',    $slug]
                ];
            } else {
                $views = [
                    [$templateDir . 'view_info.tpl',   $slug]
                ];
            }
        } elseif ($baseType === 'list_only') {
            $controllerTmpl = $templateDir . 'GalleryController.tpl';
            if ($viewType === 'template') {
                $views = [
                    [$templateDir . 'view_list.tpl',   $slug . '_list',   'list',    $slug . '_list']
                ];
            } else {
                $views = [
                    [$templateDir . 'view_list.tpl',   $slug . '_list']
                ];
            }
        } elseif ($baseType === 'contactus') {
            $controllerTmpl = $templateDir . 'ContactController.tpl';
            if ($viewType === 'template') {
                $views = [
                    [$templateDir . 'view_contact.tpl', $slug,            'contact', $slug]
                ];
            } else {
                $views = [
                    [$templateDir . 'view_contact.tpl', $slug]
                ];
            }
        } else {
            return; // 其它類型暫不自動生成
        }

        if (empty($controllerTmpl) || !file_exists($controllerTmpl)) return;

        // 根據階層旗標動態選擇 Repository
        // 只有產品 (product) 或是特定的多層結構才改用 ProductRepository
        $repoName = ($isHierarchy) ? 'ProductRepository' : 'DataRepository';

        $className = self::toCamelCase($slug) . 'Controller';
        $content = file_get_contents($controllerTmpl);
        $content = str_replace(
            ['{ClassName}', '{Slug}', '{Name}', '{RepoName}'],
            [$className, $slug, $name, $repoName],
            $content
        );
        file_put_contents($dst . '/app/Controllers/' . $className . '.php', $content);

        // 2. 生成 Views (強制覆蓋)
        // 全部採用「兩層架構」(Root Wrapper -> View Fragment)
        // 這些獨立頁面的 HTML 內容直接寫在 View 層，不需要進入 Module 層
        $reservedSlugs = ['news', 'product', 'contactus', 'about', 'service', 'location', 'index', 'home'];

        foreach ($views as $vCfg) {
            // 判斷是新式 template 還是舊式 views
            if (count($vCfg) >= 4) {
                // 新式架構: [來源Tmpl, 檔名, 類型標籤, 配置Key]
                list($src, $filename, $pageType, $configKey) = $vCfg;
                
                // A. 入口包裝檔 (Root Wrapper)
                // 如果是保留的 Slug 且檔案已存在，則跳過入口檔生成，避免蓋掉母版原生的特殊頁面邏輯
                $wDst = $dst . '/template/' . $filename . '.php';
                $skipWrapper = (in_array($slug, $reservedSlugs) && file_exists($wDst));

                if (file_exists($wrapperTmpl) && !$skipWrapper) {
                    $wContent = file_get_contents($wrapperTmpl);
                    $wContent = str_replace(
                        ['{Slug}', '{Name}', '{PageType}', '{Filename}', '{Folder}'],
                        [$slug, $name, $pageType, $filename, $folder],
                        $wContent
                    );
                    file_put_contents($wDst, $wContent);
                }

                // B. 內容分段檔 (View Fragment - 直接包含 HTML)
                if (file_exists($src)) {
                    $vDst = $dst . '/template/view/' . $folder . '/' . $filename . '.php';
                    if (!is_dir(dirname($vDst))) mkdir(dirname($vDst), 0777, true);
                    
                    $vContent = file_get_contents($src);
                    $vContent = str_replace(['{Slug}', '{Name}'], [$slug, $name], $vContent);
                    file_put_contents($vDst, $vContent);
                }

                // 自動註冊到 template_set.php
                self::registerTemplateKey($dst, $configKey, '01');

            } else {
                // 舊式架構: [來源, 目的名稱]
                list($src, $filename) = $vCfg;
                $vdst = $dst . '/views/' . $filename . '.php';
                if (file_exists($src)) {
                    $vContent = file_get_contents($src);
                    $vContent = str_replace(['{Slug}', '{Name}'], [$slug, $name], $vContent);
                    file_put_contents($vdst, $vContent);
                }
            }
        }
    }

    /**
     * 自動向 template_set.php 注入預設的版本配置
     */
    private static function registerTemplateKey($dst, $key, $value) {
        $file = $dst . '/app/template_set.php';
        if (!file_exists($file)) return;
        
        $content = file_get_contents($file);
        // 如果已經有了就不重複加
        if (strpos($content, "['$key']") !== false) return;

        $newLine = "\n\$systemTemplateSet['$key'] = '$value';";
        // 尋找最後一個賦值的地方
        $content = preg_replace('/(\$systemTemplateSet\[.*\] = .*;)/', "$1$newLine", $content, 1);
        
        // 如果完全沒匹配到(空的或註釋)，則加在後面
        if (strpos($content, $newLine) === false) {
            $content .= $newLine;
        }

        file_put_contents($file, $content);
    }
}

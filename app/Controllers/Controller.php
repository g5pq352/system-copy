<?php
namespace App\Controllers;

use Slim\Views\PhpRenderer;
use App\Repositories\DataRepository;
use App\Models\MenuModel;
use App\Helpers\MediaHelper;
use Psr\Container\ContainerInterface;

abstract class Controller {
    protected $container;
    protected $view;
    protected $db;
    protected $pdo;
    protected $repo;
    protected $mediaHelper;
    protected $isAdmin = false;
    protected $data = [];
    protected $pages;
    protected $searchActionPath;
    protected $systemTemplateSet;
    protected $csrf;
    protected $currentLang = DEFAULT_LANG_SLUG; // 【新增】當前語系

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->view = $container->get(PhpRenderer::class);
        $this->db = $container->get(\DB::class);
        $this->pdo = $container->get(\PDO::class);

        // 管理員權限判斷 (Session 已在 index.php 啟動)
        $this->isAdmin = isset($_SESSION['MM_LoginAccountUsername']);

        // 語系已由 Middleware 處理，這裡從 Request 或 View 中獲取
        // 注意：Request 在構造函數中無法直接獲取，但 View 中已經注入了
        $this->currentLang = $this->view->getAttribute('lang') ?? DEFAULT_LANG_SLUG;

        // 全域資料庫連線 (相容舊版 Model)
        $GLOBALS['db'] = $this->db;
        $this->repo = new DataRepository($this->db, $this->isAdmin);
        
        // 初始化 MediaHelper
        $frontendUrl = $container->has('frontend_url') ? $container->get('frontend_url') : '';
        $this->mediaHelper = new MediaHelper($this->db, $frontendUrl);

        // 初始化模板設定 (從容器獲取)
        $this->systemTemplateSet = $container->has('systemTemplateSet') ? $container->get('systemTemplateSet') : [];

        if (!$this->db) {
            die("錯誤：Controller 抓不到資料庫連線。");
        }
    }



    /**
     * 【新增】從資料庫獲取預設語系
     */
    protected function getDefaultLanguage() {
        try {
            $query = "SELECT l_slug FROM languages WHERE l_is_default = 1 AND l_active = 1 LIMIT 1";
            $result = $this->db->query($query);

            if ($result && isset($result[0]['l_slug'])) {
                return $result[0]['l_slug'];
            }
        } catch (\Exception $e) {
            error_log("Failed to get default language: " . $e->getMessage());
        }

        // 如果資料庫查詢失敗，嘗試從 DEFAULT_LANG 反查對應的 l_slug
        try {
            $query = "SELECT l_slug FROM languages WHERE l_locale = :locale AND l_active = 1 LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':locale' => DEFAULT_LANG_LOCALE]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['l_slug'])) {
                return $result['l_slug'];
            }
        } catch (\Exception $e) {
            error_log("Failed to get language by locale: " . $e->getMessage());
        }

        // 最終回退值：使用 config 中定義的預設語系代碼
        return DEFAULT_LANG_SLUG;
    }

    /**
     * 【新增】驗證語系是否有效
     */
    protected function isValidLanguage($lang) {
        try {
            $query = "SELECT COUNT(*) as count FROM languages WHERE l_slug = :lang AND l_active = 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':lang' => $lang]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result && $result['count'] > 0;
        } catch (\Exception $e) {
            error_log("Failed to validate language: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 【新增】從資料庫獲取語系的 locale 值
     */
    protected function getLanguageLocale($lang) {
        try {
            $query = "SELECT l_locale FROM languages WHERE l_slug = :lang AND l_active = 1 LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':lang' => $lang]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['l_locale'])) {
                return $result['l_locale'];
            }
        } catch (\Exception $e) {
            error_log("Failed to get language locale: " . $e->getMessage());
        }

        // 如果找不到，回傳語系代碼作為備用
        return $lang;
    }

    public function __set($name, $value) {
        $this->data[$name] = $value;
    }

    public function __get($name) {
        return $this->data[$name] ?? null;
    }

    protected function render($response, $template) {
        $attributes = $this->view->getAttributes();
        $baseurl = $attributes['baseurl'] ?? '';

        // 生成帶語系的 URL 前綴
        $defaultLang = $this->getDefaultLanguage();
        if ($this->currentLang !== $defaultLang) {
            $langurl = rtrim($baseurl, '/') . '/' . $this->currentLang;
        } else {
            $langurl = $baseurl;
        }

        $menuModel = new MenuModel();
        $globalMenu = $menuModel->getMenu($baseurl, $this->currentLang);
        $description = $menuModel->getDescription();

        $fullSearchAction = $baseurl . '/' . ltrim($this->searchActionPath ?? '', '/');

        // 使用 config 中的圖片路徑格式
        $frontImgPath = $baseurl . str_replace('{lang}', $this->currentLang, IMG_PATH_FORMAT);

        // 使用 config 中的模板路徑
        $tpl_setting = $this->systemTemplateSet;
        $tpl_setting['Template'] = new \App\Helpers\TemplateHelper(TEMPLATE_PATH);

        // 從資料庫獲取 l_locale 作為 HTML lang 屬性
        $htmlLang = $this->getLanguageLocale($this->currentLang);

        $viewData = $this->data;
        $viewData['baseurl']           = $baseurl;
        $viewData['langurl']           = $langurl;
        $viewData['FRONT_IMG_PATH']    = $frontImgPath;
        $viewData['pages']             = $this->pages;
        $viewData['searchAction']      = $fullSearchAction;
        $viewData['globalMenu']        = $globalMenu;
        $viewData['description']       = $description;
        $viewData['isAdmin']           = $this->isAdmin;
        $viewData['systemTemplateSet'] = $this->systemTemplateSet;
        $viewData['tpl_setting']       = $tpl_setting;
        $viewData['csrf']              = $this->csrf;
        $viewData['currentLang']       = $this->currentLang;
        $viewData['htmlLang']          = $htmlLang;
        $viewData['langPack']          = $GLOBALS['langPack'] ?? [];

        // 【新增】傳遞 API Token 到前端
        $viewData['apiToken']          = $_ENV['API_TOKEN'] ?? getenv('API_TOKEN') ?? '';

        return $this->view->render($response, $template, $viewData);
    }

    protected function initPagination($args, $dataOrSql, $baseUrl, $perPage = 6, $queryString = '') {
        $page = isset($args['page']) ? (int)$args['page'] : 1;
        
        if (is_numeric($dataOrSql)) {
            $totalCount = (int)$dataOrSql;
        } else {
            $rows = $this->db->query($dataOrSql);
            if (is_object($rows) && isset($rows->total)) {
                $totalCount = $rows->total;
            } else {
                $totalCount = $rows[0]['total'] ?? 0;
            }
        }
        
        // 【新增】計算帶語系的 URL 前綴
        $defaultLang = $this->getDefaultLanguage();
        $langurl = null;
        if ($this->currentLang !== $defaultLang) {
            $attributes = $this->view->getAttributes();
            $baseurlOriginal = $attributes['baseurl'] ?? '';
            $langurl = rtrim($baseurlOriginal, '/') . '/' . $this->currentLang;
        }

        $this->pages = new \App\Utils\Paginator($totalCount, $perPage, $page, $baseUrl, $queryString, $langurl);

        $offset = ($page - 1) * $perPage;
        return " LIMIT $offset, $perPage";
    }

    /**
     * [模組化] 設定搜尋的 "相對" 路徑
     */
    protected function setSearchAction($path, $categorySlug = null) {
        $path = trim($path, '/');

        if ($categorySlug) {
            $this->searchActionPath = $path . '/category/' . $categorySlug;
        } else {
            $this->searchActionPath = $path;
        }
    }

    protected function initCsrf($request) {
        if ($this->container->has('csrf')) {
            $csrf = $this->container->get('csrf');
            $nameKey = $csrf->getTokenNameKey();
            $valueKey = $csrf->getTokenValueKey();
            $name = $request->getAttribute($nameKey);
            $value = $request->getAttribute($valueKey);

            $this->csrf = [
                'keys' => [
                    'name'  => $nameKey,
                    'value' => $valueKey
                ],
                'name'  => $name,
                'value' => $value
            ];
        }
    }
}
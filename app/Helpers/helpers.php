<?php
/**
 * Global Helper Functions
 * 全域輔助函數
 */

if (!function_exists('hsc')) {
    /**
     * 全域 HTML 跳脫函式
     * @param string|null $string
     * @return string
     */
    function hsc($string)
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('processMedia')) {
    /**
     * 處理內容中的媒體 token 和 data-media-id
     * 自動將 [media:ID] token 和 data-media-id 屬性轉換為實際的圖片 URL
     * 
     * 使用範例:
     * <?= processMedia($work['d_content']) ?>
     * 
     * @param string $content HTML 內容
     * @return string 處理後的內容
     */
    function processMedia($content) {
        if (empty($content)) {
            return $content;
        }
        
        // 取得全域資料庫連線
        global $db;
        if (!$db) {
            error_log('processMedia: Database connection not available');
            return $content;
        }
        
        // 取得前端 URL
        $frontendUrl = defined('APP_FRONTEND_PATH') ? APP_FRONTEND_PATH : '';
        
        try {
            // 建立 MediaHelper 實例
            $mediaHelper = new \App\Helpers\MediaHelper($db, $frontendUrl);
            
            // 處理內容中的媒體 token 和 data-media-id
            return $mediaHelper->processContentWithMixedMode($content);
        } catch (\Exception $e) {
            error_log('processMedia error: ' . $e->getMessage());
            return $content;
        }
    }
}

if (!function_exists('t')) {
    /**
     * 多語系文字輸出
     * 根據目前語系回傳對應文字，找不到時 fallback 到第一個值
     *
     * 使用範例:
     * <?= t(['en' => 'test', 'cn' => '测試', 'tw' => '測試'], $currentLang) ?>
     *
     * @param array  $texts ['語系代碼' => '文字', ...]
     * @param string $lang  目前語系（通常傳 $currentLang）
     * @return string
     */
    function t(array $texts, string $lang): string {
        return $texts[$lang] ?? reset($texts) ?: '';
    }
}

if (!function_exists('__')) {
    /**
     * 語言包查詢函數（資料庫驅動）
     * 根據 key 從預先載入的語言包中取得對應語系文字
     * 找不到翻譯時自動 fallback 到繁體中文，再 fallback 到 key 本身
     *
     * 使用範例:
     * <?= __('contact_us') ?>          // 自動使用當前語系
     * <?= __('contact_us', 'en') ?>    // 指定語系
     *
     * @param string $key  語言包 key（如：contact_us）
     * @param string $lang 指定語系代碼（留空則自動取當前語系）
     * @return string
     */
    function __(string $key, string $lang = ''): string {
        $langPack = $GLOBALS['langPack'] ?? [];
        $lang     = $lang ?: ($GLOBALS['frontend_lang'] ?? DEFAULT_LANG_SLUG);
        $field    = 'lp_' . $lang;

        if (!empty($langPack[$key][$field])) {
            return $langPack[$key][$field];
        }

        // Fallback: 預設語系（繁中）
        $defaultField = 'lp_' . DEFAULT_LANG_SLUG;
        if (!empty($langPack[$key][$defaultField])) {
            return $langPack[$key][$defaultField];
        }

        // 最終 fallback: 回傳 key 本身
        return $key;
    }
}

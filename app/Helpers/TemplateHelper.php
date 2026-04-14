<?php
namespace App\Helpers;

/**
 * Template Helper Class
 * 提供模板路徑解析功能,相容舊版 Template::__dir() 用法
 */
class TemplateHelper {
    private static $templateDir;
    
    public function __construct($dir) {
        self::$templateDir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    }
    
    /**
     * 取得模板檔案的完整路徑
     * @param string $path 相對路徑
     * @return string 完整路徑
     */
    public static function __dir($path) {
        return self::$templateDir . ltrim($path, '/\\');
    }
}

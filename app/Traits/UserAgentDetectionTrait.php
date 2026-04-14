<?php
namespace App\Traits;

/**
 * User Agent 偵測 Trait
 * 提供裝置類型、瀏覽器、作業系統的偵測功能
 */
trait UserAgentDetectionTrait {
    /**
     * 偵測裝置類型
     * @param string $userAgent
     * @return string Desktop|Mobile|Tablet
     */
    protected function detectDeviceType($userAgent) {
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
            return 'Mobile';
        } elseif (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
            return 'Tablet';
        }
        return 'Desktop';
    }

    /**
     * 偵測瀏覽器
     * @param string $userAgent
     * @return string 瀏覽器名稱
     */
    protected function detectBrowser($userAgent) {
        if (preg_match('/edg/i', $userAgent)) return 'Edge';
        if (preg_match('/chrome/i', $userAgent)) return 'Chrome';
        if (preg_match('/safari/i', $userAgent)) return 'Safari';
        if (preg_match('/firefox/i', $userAgent)) return 'Firefox';
        if (preg_match('/msie|trident/i', $userAgent)) return 'Internet Explorer';
        if (preg_match('/opera|opr/i', $userAgent)) return 'Opera';
        return 'Unknown';
    }

    /**
     * 偵測作業系統
     * @param string $userAgent
     * @return string 作業系統名稱
     */
    protected function detectOS($userAgent) {
        if (preg_match('/windows nt 10/i', $userAgent)) return 'Windows 10';
        if (preg_match('/windows nt 11/i', $userAgent)) return 'Windows 11';
        if (preg_match('/windows/i', $userAgent)) return 'Windows';
        if (preg_match('/macintosh|mac os x/i', $userAgent)) return 'macOS';
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) return 'iOS';
        if (preg_match('/android/i', $userAgent)) return 'Android';
        if (preg_match('/linux/i', $userAgent)) return 'Linux';
        return 'Unknown';
    }

    /**
     * 檢查是否為機器人
     * @param string $userAgent
     * @return bool
     */
    protected function isBot($userAgent) {
        $botKeywords = 'bot|crawl|slurp|spider|mediapartners|facebook|ahrefs|google|bing|yahoo';
        return preg_match("/{$botKeywords}/i", $userAgent);
    }
}

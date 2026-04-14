<?php
namespace App\Traits;

/**
 * 地理位置偵測 Trait
 * 根據 IP 位址取得地理位置資訊
 */
trait GeoLocationTrait {
    /**
     * 根據 IP 取得地理位置資訊
     * 使用免費的 ip-api.com 服務（每分鐘限制 45 個請求）
     *
     * @param string $ip IP 位址
     * @return array ['country' => string|null, 'city' => string|null]
     */
    protected function getGeoLocation($ip) {
        // 如果是本地 IP，直接返回
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0') {
            return [
                'country' => 'Local',
                'city' => 'Localhost'
            ];
        }

        try {
            // 使用 ip-api.com 的免費 API
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,city";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2  // 2 秒超時
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response) {
                $data = json_decode($response, true);

                if ($data && $data['status'] === 'success') {
                    return [
                        'country' => $data['country'] ?? null,
                        'city' => $data['city'] ?? null
                    ];
                }
            }
        } catch (\Exception $e) {
            // 如果 API 呼叫失敗，靜默失敗
        }

        return ['country' => null, 'city' => null];
    }

    /**
     * 取得訪客真實 IP 位址
     * 考慮代理和負載平衡器的情況
     *
     * @return string IP 位址
     */
    protected function getClientIp() {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 如果使用了代理或負載平衡器，從 HTTP_X_FORWARDED_FOR 取得真實 IP
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }

        return trim($ipAddress);
    }
}

<?php
namespace App\Services;

use Exception;

/**
 * Google Places API 服務
 * 用於從 Google Maps 獲取店鋪營業時間
 */
class GooglePlacesService
{
    private $apiKey;
    private $baseUrl = 'https://maps.googleapis.com/maps/api/place';

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * 從 Google Maps URL 提取 Place ID
     * 支援格式: https://maps.app.goo.gl/xxxxx 或 https://www.google.com/maps/place/...
     */
    public function extractPlaceId($googleMapsUrl)
    {
        // 如果是短網址 (goo.gl)，需要先解析重定向
        if (strpos($googleMapsUrl, 'goo.gl') !== false || strpos($googleMapsUrl, 'maps.app.goo.gl') !== false) {
            $googleMapsUrl = $this->resolveShortUrl($googleMapsUrl);
        }

        // 從 URL 中提取 Place ID
        // 格式: https://www.google.com/maps/place/...?ftid=0x...
        if (preg_match('/[?&]ftid=([^&]+)/', $googleMapsUrl, $matches)) {
            return $this->getPlaceIdFromCid($matches[1]);
        }

        // 格式: https://www.google.com/maps/place/店名/@lat,lng,zoom/data=!4m...!3m...!1s(PLACE_ID)
        if (preg_match('/!1s([A-Za-z0-9_-]+)/', $googleMapsUrl, $matches)) {
            return $matches[1];
        }

        // 使用 Find Place API 搜尋
        return null;
    }

    /**
     * 解析短網址
     */
    private function resolveShortUrl($shortUrl)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $shortUrl);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return $finalUrl ?: $shortUrl;
    }

    /**
     * 使用 CID 查詢 Place ID
     */
    private function getPlaceIdFromCid($cid)
    {
        // CID 是 Google 內部 ID，需要透過搜尋 API 轉換
        // 這裡暫時返回 null，讓上層使用其他方法
        return null;
    }

    /**
     * 透過座標或名稱查詢 Place ID
     */
    public function findPlaceId($query, $locationType = 'textquery')
    {
        $url = $this->baseUrl . '/findplacefromtext/json';
        $params = [
            'input' => $query,
            'inputtype' => $locationType,
            'fields' => 'place_id,name,formatted_address',
            'key' => $this->apiKey,
            'language' => 'zh-TW'
        ];

        $response = $this->makeRequest($url, $params);

        if ($response && isset($response['candidates'][0]['place_id'])) {
            return $response['candidates'][0]['place_id'];
        }

        return null;
    }

    /**
     * 獲取店鋪詳細資料（包含營業時間）
     */
    public function getPlaceDetails($placeId)
    {
        $url = $this->baseUrl . '/details/json';
        $params = [
            'place_id' => $placeId,
            'fields' => 'name,formatted_address,opening_hours,current_opening_hours',
            'key' => $this->apiKey,
            'language' => 'zh-TW'
        ];

        $response = $this->makeRequest($url, $params);

        if ($response && $response['status'] === 'OK') {
            return $response['result'];
        }

        throw new Exception('無法取得店鋪資料: ' . ($response['status'] ?? 'UNKNOWN_ERROR'));
    }

    /**
     * 格式化營業時間為繁體中文
     */
    public function formatOpeningHours($placeDetails)
    {
        // 優先使用 current_opening_hours (新版 API)
        $openingHours = $placeDetails['current_opening_hours'] ?? $placeDetails['opening_hours'] ?? null;

        if (!$openingHours || !isset($openingHours['weekday_text'])) {
            return null;
        }

        $weekdayText = $openingHours['weekday_text'];

        // 將英文星期轉換為中文
        $dayMapping = [
            'Monday' => '星期一',
            'Tuesday' => '星期二',
            'Wednesday' => '星期三',
            'Thursday' => '星期四',
            'Friday' => '星期五',
            'Saturday' => '星期六',
            'Sunday' => '星期日'
        ];

        $schedule = [];
        foreach ($weekdayText as $dayText) {
            // 分離星期和時間
            if (preg_match('/^([^:]+):\s*(.+)$/', $dayText, $matches)) {
                $day = trim($matches[1]);
                $hours = trim($matches[2]);

                // 轉換星期名稱
                foreach ($dayMapping as $en => $zh) {
                    if (strpos($day, $en) !== false) {
                        $day = $zh;
                        break;
                    }
                }

                // 處理營業時間格式
                if (stripos($hours, 'Closed') !== false || stripos($hours, '休息') !== false) {
                    $hours = '休息';
                } else {
                    // 轉換為 24 小時制
                    $hours = $this->convertTo24Hour($hours);
                }

                $schedule[$day] = $hours;
            }
        }

        // 智能合併相同時間的星期
        return $this->smartMergeSchedule($schedule);
    }

    /**
     * 將 12 小時制轉換為 24 小時制
     */
    private function convertTo24Hour($timeString)
    {
        // 如果已經是 24 小時制，直接返回
        if (!preg_match('/(AM|PM)/i', $timeString)) {
            return $timeString;
        }

        $timeString = preg_replace_callback(
            '/(\d{1,2}):(\d{2})\s*(AM|PM)/i',
            function ($matches) {
                $hour = (int)$matches[1];
                $minute = $matches[2];
                $period = strtoupper($matches[3]);

                if ($period === 'PM' && $hour !== 12) {
                    $hour += 12;
                } elseif ($period === 'AM' && $hour === 12) {
                    $hour = 0;
                }

                return sprintf('%02d:%s', $hour, $minute);
            },
            $timeString
        );

        return $timeString;
    }

    /**
     * 智能合併相同營業時間的星期
     */
    private function smartMergeSchedule($schedule)
    {
        $dayOrder = ['星期一', '星期二', '星期三', '星期四', '星期五', '星期六', '星期日'];
        $dayShort = [
            '星期一' => '一', '星期二' => '二', '星期三' => '三', '星期四' => '四',
            '星期五' => '五', '星期六' => '六', '星期日' => '日'
        ];

        // 檢查週一是否公休
        $mondayClosed = isset($schedule['星期一']) && $schedule['星期一'] === '休息';

        // 建立時間到星期的映射（不包含公休日）
        $timeTodays = [];
        foreach ($dayOrder as $day) {
            if (isset($schedule[$day]) && $schedule[$day] !== '休息') {
                $hours = $schedule[$day];
                if (!isset($timeTodays[$hours])) {
                    $timeTodays[$hours] = [];
                }
                $timeTodays[$hours][] = $day;
            }
        }

        // 所有天時間相同
        if (count($timeTodays) === 1) {
            $hours = array_key_first($timeTodays);
            $days = $timeTodays[$hours];

            if (count($days) === 7) {
                return "週一至週日 {$hours}";
            } elseif (count($days) === 6 && $mondayClosed) {
                return "週二至週日 {$hours} (週一公休)";
            }
        }

        // 時間不統一，智能合併
        if (count($timeTodays) > 1) {
            $resultParts = [];

            foreach ($timeTodays as $hours => $days) {
                $dayShorts = array_map(function ($d) use ($dayShort) {
                    return $dayShort[$d];
                }, $days);
                $daysStr = implode('、', array_map(function ($d) {
                    return "週{$d}";
                }, $dayShorts));
                $resultParts[] = "{$daysStr} {$hours}";
            }

            $result = implode("\n", $resultParts);

            if ($mondayClosed) {
                $result .= " (週一公休)";
            }

            return $result;
        }

        // 其他情況，返回完整列表
        $lines = [];
        foreach ($dayOrder as $day) {
            if (isset($schedule[$day])) {
                $lines[] = "{$day}: {$schedule[$day]}";
            }
        }
        return implode("\n", $lines);
    }

    /**
     * 發送 HTTP 請求
     */
    private function makeRequest($url, $params = [])
    {
        $fullUrl = $url . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('CURL 錯誤: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('HTTP 錯誤: ' . $httpCode);
        }

        return json_decode($response, true);
    }
}

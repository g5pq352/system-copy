<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Rate Limiting Middleware (Fixed & Optimized)
 * 修復並發寫入衝突 (Race Condition) 與 JSON 損毀問題
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private array $config;
    private string $storageFile;

    public function __construct(array $config = [])
    {
        // 預設配置
        $defaults = [
            'enabled' => true,
            'max_requests' => 60,           // 最大請求次數
            'time_window' => 60,            // 時間窗口（秒）
            'block_duration' => 300,        // 封鎖時長（秒）
            'whitelist' => [],              // IP 白名單
            'strict_routes' => [            // 嚴格限制的路由
                '/portal-auth/signin' => ['max_requests' => 5, 'time_window' => 60],
                '/admin/' => ['max_requests' => 10, 'time_window' => 60],
            ],
        ];

        $this->config = array_merge($defaults, $config);
        
        // 建議：正式環境請改用專案下的 storage 目錄，例如 __DIR__ . '/../../storage/rate_limit.json'
        $this->storageFile = sys_get_temp_dir() . '/rate_limit_data.json';
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // 1. 如果功能未啟用，直接通過 (不讀檔，效能最優)
        if ($this->config['enabled'] === false) {
            return $handler->handle($request);
        }

        $ip = $this->getClientIp($request);

        // 2. 白名單 IP 直接通過
        if (in_array($ip, $this->config['whitelist'])) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();

        // 3. 載入資料 (現在只需讀取一次，包含鎖定機制)
        $data = $this->loadData();
        $now = time();

        // 4. 檢查是否被封鎖
        if (isset($data['blocked'][$ip])) {
            $blockedUntil = $data['blocked'][$ip];
            if ($now < $blockedUntil) {
                return $this->createBlockedResponse();
            }
            // 封鎖時間已過，移除封鎖紀錄
            unset($data['blocked'][$ip]);
        }

        // 5. 取得該路由的限制配置
        $limits = $this->getRouteLimits($path);
        $key = md5($ip . $path); // 針對 "IP + 路徑" 進行計數

        // 初始化該 Key
        if (!isset($data['requests'][$key])) {
            $data['requests'][$key] = [];
        }

        // 清理該 Key 的過期請求
        // 使用 array_values 確保索引重置，避免 json_encode 轉成物件
        $data['requests'][$key] = array_values(array_filter(
            $data['requests'][$key],
            fn($timestamp) => ($now - $timestamp) < $limits['time_window']
        ));

        // 6. 檢查是否超過限制
        if (count($data['requests'][$key]) >= $limits['max_requests']) {
            // 觸發封鎖
            $data['blocked'][$ip] = $now + $this->config['block_duration'];
            
            // 立即存檔並回傳錯誤
            $this->saveData($data);
            return $this->createRateLimitResponse($limits);
        }

        // 7. 通過檢查，記錄這次請求
        $data['requests'][$key][] = $now;

        // 儲存資料 (使用 LOCK_EX 防止衝突)
        $this->saveData($data);

        return $handler->handle($request);
    }

    /**
     * 取得客戶端真實 IP
     */
    private function getClientIp(Request $request): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // 標準代理
            'HTTP_X_REAL_IP',           // Nginx
            'REMOTE_ADDR'               // 直接連接
        ];

        foreach ($headers as $header) {
            $serverParams = $request->getServerParams();
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * 取得路由的限制配置
     */
    private function getRouteLimits(string $path): array
    {
        foreach ($this->config['strict_routes'] as $route => $limits) {
            if (strpos($path, $route) === 0) {
                return $limits;
            }
        }

        return [
            'max_requests' => $this->config['max_requests'],
            'time_window' => $this->config['time_window']
        ];
    }

    /**
     * 載入資料 (安全性修復版)
     */
    private function loadData(): array
    {
        if (!file_exists($this->storageFile)) {
            return ['requests' => [], 'blocked' => []];
        }

        $content = file_get_contents($this->storageFile);
        
        if (empty($content)) {
            return ['requests' => [], 'blocked' => []];
        }

        $data = json_decode($content, true);

        // 關鍵修復：檢查 JSON 是否解析失敗，防止 500 錯誤
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return ['requests' => [], 'blocked' => []];
        }

        return $data;
    }

    /**
     * 儲存資料 (並發安全性修復版)
     */
    private function saveData(array $data): void
    {
        // 定期清理舊資料（保留最近 1 小時，防止檔案無限膨脹）
        $now = time();
        $maxAge = 3600;

        foreach ($data['requests'] as $key => $timestamps) {
            // 再次確保陣列索引是連續的
            $cleanTimestamps = array_values(array_filter(
                $timestamps,
                fn($timestamp) => ($now - $timestamp) < $maxAge
            ));

            if (empty($cleanTimestamps)) {
                unset($data['requests'][$key]);
            } else {
                $data['requests'][$key] = $cleanTimestamps;
            }
        }

        // 清理過期的封鎖
        if (isset($data['blocked'])) {
            foreach ($data['blocked'] as $ip => $until) {
                if ($now >= $until) {
                    unset($data['blocked'][$ip]);
                }
            }
        }

        // 關鍵修復：使用 LOCK_EX 獨佔鎖定，防止多個請求同時寫入損壞檔案
        file_put_contents($this->storageFile, json_encode($data), LOCK_EX);
    }

    /**
     * 建立流量限制回應
     */
    private function createRateLimitResponse(array $limits): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => 'Rate limit exceeded',
            'message' => '請求過於頻繁，請稍後再試',
            'retry_after' => $limits['time_window']
        ]));

        return $response
            ->withStatus(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string)$limits['time_window']);
    }

    /**
     * 建立封鎖回應
     */
    private function createBlockedResponse(): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => 'Access blocked',
            'message' => '您的 IP 已被暫時封鎖，請稍後再試',
            'blocked_duration' => $this->config['block_duration']
        ]));

        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }
}
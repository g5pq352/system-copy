<?php
/**
 * Rate Limit Configuration
 * 流量限制配置 (寬鬆除錯模式)
 */

return [
    // 1. 確保這裡是 false
    'enabled' => false,

    // 2. 把預設限制調大，避免誤觸 (例如從 60 改成 10000)
    'max_requests' => 10000,
    'time_window' => 60,

    // 3. 縮短封鎖時間，萬一誤觸只需等 1 秒
    'block_duration' => 1, 

    // 4. IP 白名單 (最重要的一步)
    'whitelist' => [
        '127.0.0.1',
        '::1',
        '59.126.31.214', 
    ],

    // 5. 暫時註解掉所有嚴格路由限制
    // 這樣可以排除是因為「特定頁面」邏輯寫壞導致的封鎖或 500 錯誤
    'strict_routes' => [
        /* // 暫時註解掉這段，等問題解決再開回來
        '/portal-auth/signin' => [
            'max_requests' => 5,
            'time_window' => 60,
        ],
        '/admin/' => [
            'max_requests' => 10,
            'time_window' => 60,
        ],
        '/api/' => [
            'max_requests' => 30,
            'time_window' => 60,
        ],
        '/contact' => [
            'max_requests' => 3,
            'time_window' => 60,
        ],
        */
    ],
];
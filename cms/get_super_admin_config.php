<?php
/**
 * 讀取超級管理員配置（僅供測試使用）
 */

header('Content-Type: application/json');

$configFile = __DIR__ . '/config/superAdminConfig.php';

if (!file_exists($configFile)) {
    echo json_encode(['error' => '配置檔案不存在']);
    exit;
}

$config = require $configFile;

// 隱藏敏感資訊
if (isset($config['api_verification']['secret_key'])) {
    $config['api_verification']['secret_key'] = '***隱藏***';
}

echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

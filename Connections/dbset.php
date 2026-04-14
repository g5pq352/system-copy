<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createMutable(__DIR__ . '/../');
$dotenv->load();

// 引入配置檔案以使用統一的環境判斷
require_once __DIR__ . '/../config/config.php';

// 使用統一的環境判斷常數
require_once __DIR__ . '/../cms/includes/SubsiteHelper.php';

// 使用統一的環境判斷常數
if (IS_LOCAL) {
    $masterConfig = [
        'host' => $_ENV['DEV_DB_HOST'],
        'dbname' => $_ENV['DEV_DB_NAME'],
        'username' => $_ENV['DEV_DB_USER'],
        'password' => $_ENV['DEV_DB_PASS'],
    ];
} else {
    $masterConfig = [
        'host' => $_ENV['DB_HOST'],
        'dbname' => $_ENV['DB_NAME'],
        'username' => $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASS'],
    ];
}

return SubsiteHelper::getDynamicConfig($masterConfig);
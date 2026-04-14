<?php
// 1. 引入資料庫連線
require_once realpath(__DIR__ . '/../../Connections/connect2data.php');
require_once realpath(__DIR__ . '/../../config/config.php'); 

/**
 * 超級管理員配置
 */

return array (
  // 使用統一配置的 IP 白名單（定義於 config/config.php）
  'allowed_ips' => ALLOWED_IPS,
  'super_admin' => 
  array (
    'user_id' => 999,
    'user_name' => 'SuperAdmin',
    'display_name' => '超級管理員',
    'group_id' => 999,
  ),
  'api_verification' => 
  array (
    'enabled' => true,
    'endpoint' => APP_BASE_URL.'/cms/api_verify_ip.php',
    'secret_key' => 'test-secret-key-12345',
  ),
);

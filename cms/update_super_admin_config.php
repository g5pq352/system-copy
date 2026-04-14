<?php
/**
 * 動態更新超級管理員配置（僅供測試使用）
 */

$configFile = __DIR__ . '/config/superAdminConfig.php';
$enableApi = isset($_POST['enable_api']) && $_POST['enable_api'] === 'true';

$config = [
    'allowed_ips' => [
        '127.0.0.1',
        '::1',
    ],
    'super_admin' => [
        'user_id' => 999,
        'user_name' => 'SuperAdmin',
        'display_name' => '超級管理員',
        'group_id' => null,
    ],
    'api_verification' => [
        'enabled' => $enableApi,
        'endpoint' => 'http://localhost/template-ver3/cms/api_verify_ip.php',
        'secret_key' => 'test-secret-key-12345',
    ],
];

$content = "<?php\n/**\n * 超級管理員配置\n */\n\nreturn " . var_export($config, true) . ";\n";

file_put_contents($configFile, $content);

echo json_encode(['success' => true, 'api_enabled' => $enableApi]);

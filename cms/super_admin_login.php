<?php
/**
 * 超級管理員快速登入 API
 */

session_start();
require_once('../Connections/connect2data.php');

header('Content-Type: application/json');

// 載入超級管理員配置
$config = require(__DIR__ . '/config/superAdminConfig.php');

// 獲取客戶端 IP
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

$clientIP = getClientIP();

// 檢查 IP 是否在允許列表中
if (!in_array($clientIP, $config['allowed_ips'])) {
    echo json_encode([
        'success' => false,
        'message' => 'IP 未授權',
        'client_ip' => $clientIP
    ]);
    exit;
}

// 如果啟用 API 驗證
if ($config['api_verification']['enabled']) {
    $apiEndpoint = $config['api_verification']['endpoint'];
    $secretKey = $config['api_verification']['secret_key'];
    
    // 呼叫外部 API 驗證
    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'ip' => $clientIP,
        'secret' => $secretKey
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false,
            'message' => 'API 驗證失敗'
        ]);
        exit;
    }
    
    $apiResult = json_decode($response, true);
    if (!$apiResult['verified']) {
        echo json_encode([
            'success' => false,
            'message' => 'IP 驗證失敗'
        ]);
        exit;
    }
}

// 驗證成功，設定 Session
$superAdmin = $config['super_admin'];

if (PHP_VERSION >= 5.1) {
    session_regenerate_id(true);
} else {
    session_regenerate_id();
}

$_SESSION['MM_LoginAccountUsername'] = $superAdmin['user_name'];
$_SESSION['MM_LoginAccountUserGroup'] = '';
$_SESSION['MM_LoginAccountUserId'] = $superAdmin['user_id'];
$_SESSION['MM_UserGroupId'] = $superAdmin['group_id'];
$_SESSION['MM_UserPermissions'] = null;  // null = 繞過所有權限檢查
$_SESSION['MM_IsSuperAdmin'] = true;     // 標記為超級管理員

// 記錄登入日誌 (Admin Login Logs)
try {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 簡易判斷裝置
    $device = 'Desktop';
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($userAgent))) {
        $device = 'Tablet';
    } elseif (preg_match('/(mobile|android|iphone|ipad|phone)/i', strtolower($userAgent))) {
        $device = 'Mobile';
    }

    $logStmt = $conn->prepare("INSERT INTO admin_login_logs (user_id, username, login_ip, login_status, login_type, user_device, user_agent, login_time) VALUES (:uid, :uname, :ip, 'success', 'super_admin', :device, :ua, NOW())");
    $logStmt->execute([
        ':uid' => $superAdmin['user_id'],
        ':uname' => $superAdmin['user_name'],
        ':ip' => $clientIP,
        ':device' => $device,
        ':ua' => $userAgent
    ]);
} catch (Exception $e) {
    error_log("Super Admin Login log error: " . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'message' => '登入成功',
    'user' => [
        'id' => $superAdmin['user_id'],
        'name' => $superAdmin['user_name'],
        'display_name' => $superAdmin['display_name']
    ],
    'redirect' => PORTAL_AUTH_URL . 'dashboard'
]);

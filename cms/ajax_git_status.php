<?php
/**
 * AJAX Git 狀態查詢
 * 供前端輪詢目前自動化進度
 */
session_start();
header('Content-Type: application/json');

$progress = $_SESSION['git_progress'] ?? '準備中...';

echo json_encode([
    'success' => true,
    'progress' => $progress
]);

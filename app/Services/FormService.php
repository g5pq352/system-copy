<?php
namespace App\Services;

use App\Exceptions\BotActionException;
use App\Exceptions\ValidationException;

class FormService {

    /**
     * 防機器人檢查 (拋出例外模式)
     * @throws BotActionException
     */
    public function guardBot($data, $sessionKey = 'last_submit_time', $limitSeconds = 60) {
        // 1. Honeypot 檢查
        if (!empty($data['nickname'])) {
            // 機器人填了隱藏欄位，拋出例外，標記為假成功
            throw new BotActionException('您已預約成功！謝謝。', true);
        }

        // 2. 時間間隔檢查
        if (isset($_SESSION[$sessionKey])) {
            $seconds = time() - $_SESSION[$sessionKey];
            if ($seconds < $limitSeconds) {
                // 真人但太頻繁，拋出錯誤
                throw new ValidationException('您寄信太頻繁了，請休息一下再試。');
            }
        }
    }

    /**
     * 驗證必填欄位 (拋出例外模式)
     * @throws ValidationException
     */
    public function guardRequired(array $data, array $requiredFields) {
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("請填寫所有必填欄位 (缺少: {$field})");
            }
        }
    }

    /**
     * 防機器人檢查 (Honeypot + 時間限制)
     * @param array $data 表單資料 (需包含 honeypot 欄位，預設為 'nickname')
     * @param string $sessionKey Session 紀錄時間的 Key
     * @param int $limitSeconds 限制秒數 (預設 60 秒)
     * @return array [success => bool, message => string]
     */
    public function checkBot($data, $sessionKey = 'last_submit_time', $limitSeconds = 60) {
        // 1. Honeypot 檢查
        if (!empty($data['nickname'])) {
            // 機器人填了隱藏欄位，假裝成功但不處理
            return ['success' => false, 'bot' => true, 'message' => '您已預約成功！謝謝。'];
        }

        // 2. 時間間隔檢查
        if (isset($_SESSION[$sessionKey])) {
            $seconds = time() - $_SESSION[$sessionKey];
            if ($seconds < $limitSeconds) {
                return ['success' => false, 'bot' => false, 'message' => '您寄信太頻繁了，請休息一下再試。'];
            }
        }

        return ['success' => true];
    }

    /**
     * 更新提交時間
     */
    public function updateSubmitTime($sessionKey = 'last_submit_time') {
        $_SESSION[$sessionKey] = time();
    }

    /**
     * 驗證必填欄位
     * @param array $data 表單資料
     * @param array $requiredFields 必填欄位名稱陣列
     * @return array [success => bool, message => string]
     */
    public function validate(array $data, array $requiredFields) {
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "請填寫所有必填欄位 (缺少: {$field})"];
            }
        }
        return ['success' => true];
    }

    /**
     * 清理資料 (Trim + Strip Tags)
     * @param array $data 原始資料
     * @param array $fields 要清理的欄位 (若為空則清理所有)
     * @return array 清理後的資料
     */
    public function sanitize(array $data, array $fields = []) {
        $cleanData = [];
        foreach ($data as $key => $value) {
            if (empty($fields) || in_array($key, $fields)) {
                if (is_string($value)) {
                    $cleanData[$key] = strip_tags(trim($value));
                } else {
                    $cleanData[$key] = $value;
                }
            } else {
                $cleanData[$key] = $value;
            }
        }
        return $cleanData;
    }

    /**
     * 回傳 JSON 成功回應
     */
    public function responseSuccess($response, $message = '操作成功') {
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * 回傳 JSON 失敗回應
     */
    public function responseError($response, $message, $statusCode = 400) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}

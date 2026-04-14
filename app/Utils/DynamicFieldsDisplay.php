<?php
/**
 * 動態欄位前端顯示輔助函數
 * 用於在網站前台顯示動態欄位資料
 */

require_once(__DIR__ . '/cms/includes/DynamicFieldsHelper.php');

/**
 * 取得動態欄位資料
 * @param PDO $conn 資料庫連線
 * @param int $d_id 主資料ID
 * @param string $fieldGroup 欄位群組名稱 (如 'd_data1')
 * @return array 分組後的欄位資料
 */
function getDynamicFields($conn, $d_id, $fieldGroup = 'd_data1') {
    $helper = new DynamicFieldsHelper($conn);
    return $helper->getFields($d_id, $fieldGroup);
}

/**
 * 顯示動態欄位 - 簡單列表格式
 * @param PDO $conn 資料庫連線
 * @param int $d_id 主資料ID
 * @param string $fieldGroup 欄位群組名稱
 * @param string $template 模板類型 ('list', 'grid', 'custom')
 * @return string HTML 字串
 */
function renderDynamicFieldsList($conn, $d_id, $fieldGroup = 'd_data1', $template = 'list') {
    $data = getDynamicFields($conn, $d_id, $fieldGroup);

    if (empty($data)) {
        return '';
    }

    $html = '';

    switch ($template) {
        case 'list':
            $html .= '<div class="dynamic-fields-list">';
            foreach ($data as $index => $group) {
                $html .= '<div class="dynamic-field-item">';
                foreach ($group as $key => $value) {
                    if (is_array($value) && isset($value['file_info'])) {
                        // 圖片欄位
                        $fileInfo = $value['file_info'];
                        $html .= '<div class="field-image">';
                        $html .= '<img src="' . htmlspecialchars($fileInfo['file_link1']) . '" alt="' . htmlspecialchars($fileInfo['file_title']) . '">';
                        $html .= '</div>';
                    } else {
                        // 文字欄位
                        $html .= '<div class="field-text">';
                        $html .= '<strong>' . htmlspecialchars($key) . ':</strong> ';
                        $html .= htmlspecialchars($value);
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
            }
            $html .= '</div>';
            break;

        case 'grid':
            $html .= '<div class="dynamic-fields-grid">';
            foreach ($data as $index => $group) {
                $html .= '<div class="dynamic-field-card">';
                foreach ($group as $key => $value) {
                    if (is_array($value) && isset($value['file_info'])) {
                        $fileInfo = $value['file_info'];
                        $html .= '<div class="card-image">';
                        $html .= '<img src="' . htmlspecialchars($fileInfo['file_link1']) . '" alt="' . htmlspecialchars($fileInfo['file_title']) . '">';
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="card-text">';
                        $html .= '<p>' . htmlspecialchars($value) . '</p>';
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
            }
            $html .= '</div>';
            break;
    }

    return $html;
}

/**
 * 取得特定欄位的值
 * @param array $group 群組資料
 * @param string $fieldName 欄位名稱
 * @param mixed $default 預設值
 * @return mixed
 */
function getDynamicFieldValue($group, $fieldName, $default = '') {
    if (!isset($group[$fieldName])) {
        return $default;
    }

    $value = $group[$fieldName];

    // 如果是圖片欄位，返回圖片路徑
    if (is_array($value) && isset($value['file_info'])) {
        return $value['file_info']['file_link1'] ?? $default;
    }

    return $value;
}

/**
 * 範例：房型資訊顯示
 * 這是一個自訂模板的範例
 */
function renderRoomInfo($conn, $d_id) {
    $data = getDynamicFields($conn, $d_id, 'd_data1');

    if (empty($data)) {
        return '<p>目前沒有房型資訊</p>';
    }

    $html = '<div class="room-info-container">';

    foreach ($data as $index => $room) {
        $roomEn = getDynamicFieldValue($room, 'room_en', '');
        $roomCh = getDynamicFieldValue($room, 'room_ch', '');
        $roomContent = getDynamicFieldValue($room, 'room_content', '');
        $roomImage = getDynamicFieldValue($room, 'room_image', '');

        $html .= '<div class="room-item">';

        if ($roomImage) {
            $html .= '<div class="room-image">';
            $html .= '<img src="' . htmlspecialchars($roomImage) . '" alt="' . htmlspecialchars($roomCh) . '">';
            $html .= '</div>';
        }

        $html .= '<div class="room-details">';

        if ($roomEn) {
            $html .= '<h3 class="room-title-en">' . htmlspecialchars($roomEn) . '</h3>';
        }

        if ($roomCh) {
            $html .= '<h4 class="room-title-ch">' . htmlspecialchars($roomCh) . '</h4>';
        }

        if ($roomContent) {
            $html .= '<p class="room-content">' . nl2br(htmlspecialchars($roomContent)) . '</p>';
        }

        $html .= '</div>'; // .room-details
        $html .= '</div>'; // .room-item
    }

    $html .= '</div>'; // .room-info-container

    return $html;
}

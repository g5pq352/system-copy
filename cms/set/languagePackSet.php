<?php
/**
 * 語言包管理 (Language Pack Set)
 * 後台 CMS 模組設定
 */

$menu_is = "languagePack";

// 從資料庫動態取得啟用的語系，生成語言欄位
global $conn;
$activeLangs = [];
try {
    $stmt = $conn->query("SELECT l_slug, l_name, l_is_default FROM languages WHERE l_active = 1 ORDER BY l_sort ASC, l_id ASC");
    $activeLangs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // fallback: 至少保留繁中
    $activeLangs = [['l_slug' => 'tw', 'l_name' => '繁體中文', 'l_is_default' => 1]];
}

// 動態建立欄位清單（列表頁用）
$langListColumns = [];
foreach ($activeLangs as $lang) {
    $langListColumns[] = [
        'field' => 'lp_' . $lang['l_slug'],
        'label' => $lang['l_name'],
        'type' => 'text',
        'width' => '200',
    ];
}

// 動態建立欄位（詳細頁用）
$langDetailItems = [];
foreach ($activeLangs as $index => $lang) {
    $langDetailItems[] = [
        'type' => 'text',
        'field' => 'lp_' . $lang['l_slug'],
        'label' => $lang['l_name'] . ' (lp_' . $lang['l_slug'] . ')',
        'required' => !empty($lang['l_is_default']),
        'note' => !empty($lang['l_is_default']) ? '預設顯示語系，必填' : '若留空，前台將顯示預設語系文字',
    ];
}

$settingPage = [
    'module' => $menu_is,
    'moduleName' => '語言包管理',
    'tableName' => 'language_packs',
    'primaryKey' => 'lp_id',

    'cols' => [
        'sort' => 'lp_sort',
        'active' => null,   // 語言包沒有 active 開關
        'delete_time' => null,   // 無軟刪除
    ],

    'listPage' => [
        'title' => '語言包列表',
        'hasCategory' => false,
        'hasTrash' => false,
        'hasLanguage' => false,
        'columns' => array_merge(
            [
                ['field' => 'lp_sort', 'label' => '排序', 'type' => 'sort', 'width' => '60'],
                ['field' => 'lp_key', 'label' => 'Key', 'type' => 'text', 'width' => '160'],
                ['field' => 'lp_note', 'label' => '備註', 'type' => 'text', 'width' => '160'],
            ],
            $langListColumns,
            [
                ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
                ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30'],
            ]
        ),
        'itemsPerPage' => 50,
        'orderBy' => 'lp_sort ASC, lp_id ASC',
    ],

    'detailPage' => [
        [
            'sheetTitle' => '語言包設定',
            'boxTitle' => '文字 Key 設定',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'lp_key',
                    'label' => 'Key 名稱',
                    'required' => true,
                    'note' => '唯一識別名稱，只能使用小寫英文、底線（如：contact_us, home, read_more）',
                    'checkDuplicate' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'lp_note',
                    'label' => '備註',
                    'note' => '說明此 Key 用途，方便後台管理（不影響前台顯示）',
                ],
            ],
        ],
        [
            'sheetTitle' => '各語系翻譯',
            'boxTitle' => '翻譯文字（根據啟用語系動態顯示）',
            'items' => $langDetailItems,
        ],
    ],

    'hiddenFields' => [],
];

return $settingPage;

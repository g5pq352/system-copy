<?php
$menu_is = "languageType";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '語系管理',
    'tableName' => 'languages',
    'primaryKey' => 'l_id',

    'cols' => [
        'title' => 'l_name',
        'sort' => 'l_sort',
        'active' => 'l_active',
        'delete_time' => 'l_delete_time'  // languages 資料表有 l_delete_time 欄位
    ],
    
    'listPage' => [
        'title' => '語系列表',
        // ----------------------------------------------------------
        'hasCategory' => false,
        'hasTrash' => false,
        'hasLanguage' => false,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'l_sort', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            ['field' => 'l_name', 'label' => '語系名稱', 'type' => 'text', 'width' => '150'],
            ['field' => 'l_name_en', 'label' => '語系名稱 (英)', 'type' => 'text', 'width' => '150'],
            ['field' => 'l_slug', 'label' => '代碼 (Slug)', 'type' => 'text', 'width' => '50'],
            ['field' => 'l_locale', 'label' => '語系地區', 'type' => 'text', 'width' => '150'],
            ['field' => 'l_is_default', 'label' => '預設', 'type' => 'select', 'width' => '80',
                'options' => [
                    ['value' => 1, 'label' => '是'],
                    ['value' => 0, 'label' => '否']
                ]
            ],
            ['field' => 'l_active', 'label' => '開啟', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30'],
        ],
        'itemsPerPage' => 99,
        'orderBy' => 'l_sort ASC, l_id ASC',
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '語系設定',
            'boxTitle' => '語系基本資訊',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'l_name',
                    'label' => '語系名稱 (繁中)',
                    'required' => true,
                    'note' => '例如：繁體中文',
                    'checkDuplicate' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'l_name_en',
                    'label' => '語系名稱 (英文)',
                    'required' => true,
                    'note' => '例如：English',
                    'checkDuplicate' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'l_slug',
                    'label' => '語系代碼',
                    'required' => true,
                    'note' => '例如：tw, en, jp',
                    'checkDuplicate' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'l_locale',
                    'label' => '語系地區',
                    'required' => true,
                    'note' => '例如：zh-Hant-TW, en-US',
                    'checkDuplicate' => true,
                ],
                [
                    'type' => 'select',
                    'field' => 'l_is_default',
                    'label' => '設為預設語系',
                    'options' => [
                        ['value' => 0, 'label' => '否'],
                        ['value' => 1, 'label' => '是']
                    ],
                    'note' => '系統預設顯示的語系'
                ],
                [
                    'type' => 'select',
                    'field' => 'l_active',
                    'label' => '啟用狀態',
                    'options' => [
                        ['value' => 1, 'label' => '啟用'],
                        ['value' => 0, 'label' => '停用']
                    ]
                ],
            ]
        ],
    ],
    
    'hiddenFields' => [],
];

return $settingPage;
?>

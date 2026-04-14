<?php
$menu_is = "taxonomyType";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '標籤類型',
    'tableName' => 'taxonomy_types',
    'primaryKey' => 'ttp_id',

    'cols' => [
        'date'  => 'created_at',
        'title' => 'ttp_name',
        'sort' => 'sort_order',
        'active' => 'ttp_active',
        'delete_time' => null,
    ],
    
    'listPage' => [
        'title' => '分類列表',
        'itemsPerPage' => 9999999,
        'hasCategory' => false,
        'hasTrash' => false,
        'useTaxonomyMapSort' => false,
        'columns' => [
            ['field' => 'sort_order', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            ['field' => 'created_at', 'label' => '建立日期', 'type' => 'date', 'width' => '142'],
            ['field' => 'ttp_name', 'label' => '分類名稱', 'type' => 'text', 'width' => '400'],
            ['field' => 'ttp_category', 'label' => '分類英文', 'type' => 'text', 'width' => '100'],
            // ['field' => 'ttp_id', 'label' => 'ID', 'type' => 'text', 'width' => '100'],
            ['field' => 'ttp_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30'],
        ],
        'orderBy' => 'sort_order ASC, created_at DESC',
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle' => '基本資訊',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'ttp_name',
                    'label' => '分類名稱 (中文)',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'ttp_category',
                    'label' => '分類英文',
                    'required' => true,
                ],
                [
                    'type' => 'datetime',
                    'field' => 'created_at',
                    'label' => '建立日期',
                    'required' => true
                ],
                [
                    'type' => 'select',
                    'field' => 'ttp_active',
                    'label' => '顯示狀態',
                    'options' => [
                        ['value' => 1, 'label' => '顯示'],
                        ['value' => 0, 'label' => '不顯示']
                    ]
                ],
            ]
        ],
    ],
    
    'hiddenFields' => [],
];

return $settingPage;
?>
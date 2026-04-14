<?php
$hasHierarchy = false;  // 開放多階層分類

$menu_is = "shopCate";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '會員分類管理',
    'tableName' => 'taxonomies',
    'primaryKey' => 't_id',
    'menuKey' => 'taxonomy_type_id',

    'cols' => [
        'date'  => 'created_at',
        'title' => 't_name',
        'slug'  => 't_slug',
        'slug_source' => 't_name_en', // 預設slug欄位
        'sort' => 'sort_order',
        'active' => 't_active',
        'delete_time' => 'deleted_at', 
        'parent_id' => 'parent_id',
        'top' => null,
        'file_fk' => 'file_t_id'
    ],
    
    'listPage' => [
        'title' => '列表',
        // ----------------------------------------------------------
        'hasCategory' => false,
        'hasHierarchy' => $hasHierarchy,
        'useTaxonomyMapSort' => false,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'sort_order', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            ['field' => 'created_at', 'label' => '建立日期', 'type' => 'date', 'width' => '142'],
            ['field' => 't_name', 'label' => '分類名稱', 'type' => 'text', 'width' => '400'],
            ...($hasHierarchy ? [
                ['field' => 'next_level', 'label' => '下一層', 'type' => 'button', 'width' => '60']
            ] : []),
            ['field' => 't_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30']
        ],
        'itemsPerPage' => 9999999,
        'orderBy' => 'sort_order ASC, created_at DESC'
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle' => '基本資訊',
            'items' => [
                ...($hasHierarchy ? [
                    [
                        'type' => 'select',
                        'field' => 'parent_id',
                        'label' => '父層分類',
                        'required' => false,
                        'category' => $menu_is,
                        'note' => '選擇「頂層」或所屬的父層分類'
                    ]
                ] : []),
                [
                    'type' => 'text',
                    'field' => 't_name',
                    'label' => '分類名稱 (中文)',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 't_name_en',
                    'label' => '分類名稱 (英文)',
                    'required' => false,
                ],
                [
                    'type' => 'datetime',
                    'field' => 'created_at',
                    'label' => '建立日期',
                    'required' => true
                ],
                [
                    'type' => 'number',
                    'field' => 'sort_order',
                    'label' => '排序 (數字越小越前面)',
                ],
                [
                    'type' => 'select',
                    'field' => 't_active',
                    'label' => '顯示狀態',
                    'options' => [
                        ['value' => 1, 'label' => '顯示'],
                        ['value' => 0, 'label' => '不顯示']
                    ]
                ],
            ]
        ]
    ],
    
    'hiddenFields' => [
        'taxonomy_type_id' => null
    ],
];

return $settingPage;
?>

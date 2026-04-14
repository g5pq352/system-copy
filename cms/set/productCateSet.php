<?php
$hasHierarchy = true;  // 開放多階層分類

$menu_is = "productCate";
$settingPage = [
    'module'     => $menu_is,
    'moduleName' => '產品分類',
    'tableName'  => 'taxonomies',
    'primaryKey' => 't_id',
    'menuKey'    => 'taxonomy_type_id',

    'cols' => [
        'date'        => 'created_at',
        'title'       => 't_name',
        'slug'        => 't_slug',
        'slug_source' => 't_name', // 預設slug欄位
        'sort'        => 'sort_order',
        'active'      => 't_active',
        'delete_time' => 'deleted_at', 
        'parent_id'   => 'parent_id',
        'top'         => null,
        'file_fk'     => 'file_t_id'
    ],
    
    'listPage' => [
        'title' => '分類列表',
        'imageFileType' => 'productCateCover',
        // ----------------------------------------------------------
        'hasCategory'       => false,
        'hasHierarchy'      => $hasHierarchy,
        'skipRelationCheck' => true,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'sort_order', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            ['field' => 'created_at', 'label' => '建立日期', 'type' => 'date', 'width' => '142'],
            ['field' => 't_name', 'label' => '分類名稱', 'type' => 'text', 'width' => '400'],
            ...($hasHierarchy ? [
                ['field' => 'next_level', 'label' => '下一層', 'type' => 'button', 'width' => '60']
            ] : []),
            ['field' => 'image', 'label' => '圖片', 'type' => 'image', 'width' => '140'],
            ['field' => 't_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30'],

            // 【標準】標準複選分類顯示方式 (從資料庫抓取名稱)
            // ['field' => 't_tag', 'label' => '自定義複選', 'type' => 'select', 'width' => '120',
            //     'options' => [
            //         ['value' => 1, 'label' => '汪汪'],
            //         ['value' => 5, 'label' => '喵喵'],
            //         ['value' => 8, 'label' => '嗚嗚']
            //     ]
            // ],
            // ['field' => 't_tag', 'label' => '系統分類', 'type' => 'category', 'category' => "newsC", 'width' => '150'],
        ],
        'itemsPerPage'      => 9999999,
        'orderBy' => 'sort_order ASC, created_at DESC'
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle'   => '基本資訊',
            'items' => [
                ...($hasHierarchy ? [
                    [
                        'type' => 'select',
                        'field' => 'parent_id',
                        'label' => '父層分類',
                        'required' => true,
                        'category' => $menu_is,
                        'note' => '選擇「頂層」或所屬的父層分類'
                    ]
                ] : []),
                [
                    'type' => 'text',
                    'field' => 't_name',
                    'label' => '分類名稱 (中文)',
                    'required' => true,
                    'checkDuplicate' => true,
                ],
                // [
                //     'type' => 'text',
                //     'field' => 't_name_en',
                //     'label' => '分類名稱 (英文)',
                //     'required' => true,
                //     'checkDuplicate' => true,
                // ],
                // [
                //     'type' => 'select',
                //     'field' => 't_tag',
                //     'label' => '標籤',
                //     'category' => ['taxCategory', 'newsC'], // ttp_category 的分類
                //     // 'multiple' => true,
                //     // 'showPlaceholder' => true,
                // ],
                [
                    'type' => 'image_upload',
                    'field' => 'productCateCover',
                    'label' => '上傳封面圖片',
                    'fileType' => 'productCateCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [
                        ['w' => 1030, 'h' => 570],
                        // 'maxSize' => 3,
                    ],
                    'note' => ''
                ],
                [
                    'type' => 'datetime',
                    'field' => 'created_at',
                    'label' => '建立日期',
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
        ],
        [
            'sheetTitle' => 'SEO設定',
            'boxTitle'   => '搜尋引擎優化',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 't_slug',
                    'label' => '網址別名 (slug)',
                    'note' => '用於網址列，留空則自動從標題產生'
                ],
                [
                    'type' => 'text',
                    'field' => 't_seo_title',
                    'label' => 'SEO 標題 (Meta Title)',
                    'note' => '建議長度：50-60 字元'
                ],
                [
                    'type' => 'textarea',
                    'field' => 't_description',
                    'label' => 'SEO 描述 (Meta Description)',
                    'note' => '建議長度：150-160 字元'
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

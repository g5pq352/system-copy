<?php
$hasHierarchy = false; // 開放多階層分類 (使用陣列欄位)

$menu_is = "news";
$category = "newsCate";
$settingPage = [
    'module'     => $menu_is,
    'moduleName' => '最新消息管理',
    'tableName'  => 'data_set',
    'primaryKey' => 'd_id',
    'menuKey'    => 'd_class1',
    'menuValue'  => $menu_is,

    'cols' => [
        'date'  => 'd_date',
        'title' => 'd_title',
        'slug'  => 'd_slug',
        'slug_source' => 'd_title', // 預設slug欄位
        'view_count' => 'd_view',
        'sort' => 'd_sort',
        'top' => 'd_top',
        'active' => 'd_active',
        'update_time' => 'd_update_time',
        'delete_time' => 'd_delete_time',
        'file_fk' => 'file_d_id'
    ],
    
    'listPage' => [
        'title'         => '列表',
        'imageFileType' => 'newsCover',
        'categoryName'  => $category,
        'categoryField' => $hasHierarchy ? ['d_class2', 'd_class3'] : 'd_class2',
        // ----------------------------------------------------------
        'hasCategory' => true, // 列表是否顯示Filter By
        'useTaxonomyMapSort' => true, // 資料與分類關聯對照表
        'globalSort' => true, // 啟用全域排序
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'd_sort', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            ['field' => 'pin', 'label' => '置頂', 'type' => 'button', 'width' => '50'],
            ['field' => 'd_date', 'label' => '日期', 'type' => 'date', 'width' => '142'],
            ['field' => 'd_title', 'label' => '標題', 'type' => 'text', 'width' => '470'],
            ['field' => 'd_view', 'label' => '瀏覽次數', 'type' => 'view_count', 'width' => '60'],
            ['field' => 'image', 'label' => '圖片', 'type' => 'image', 'width' => '140'],
            ['field' => 'd_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            // ['field' => 'd_update_time', 'label' => '更新時間', 'type' => 'plaintext', 'width' => '150'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30'],
            
            // 【標準】標準複選分類顯示方式 (從資料庫抓取名稱)
            // ['field' => 'd_tag', 'label' => '自定義複選', 'type' => 'select', 'width' => '120',
            //     'options' => [
            //         ['value' => 1, 'label' => '汪汪'],
            //         ['value' => 5, 'label' => '喵喵'],
            //         ['value' => 8, 'label' => '嗚嗚']
            //     ]
            // ],
            // ['field' => 'd_tag', 'label' => '系統分類', 'type' => 'category', 'width' => '150', 'category' => $category],
        ],
        'itemsPerPage'  => 9999999,
        'orderBy' => 'd_sort ASC, d_date DESC',
        'customQuery' => "SELECT data_set.*, taxonomies.t_name FROM data_set
                          LEFT JOIN taxonomies ON data_set.d_class2 = taxonomies.t_id
                          AND data_set.lang = taxonomies.lang
                          AND (taxonomies.deleted_at IS NULL)",
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle'   => '',
            'items' => [
                [
                    'type' => 'select',
                    'field' => $hasHierarchy ? ['d_class2', 'd_class3'] : 'd_class2',
                    'label' => $hasHierarchy ? '層級分類' : '分類',
                    'category' => $category,
                    'linked' => $hasHierarchy,
                ],
                // [
                //     'type' => 'select',
                //     'field' => 'd_class2',
                //     'label' => '分類複選',
                //     'required' => true,
                //     'category' => $category,
                //     'multiple' => true,
                //     // 'canCreate' => true,
                // ],
                [
                    'type' => 'text',
                    'field' => 'd_title',
                    'label' => '標題',
                    'required' => true,
                    'checkDuplicate' => true,
                ],
                // [
                //     'type' => 'select',
                //     'field' => 'd_tag',
                //     'label' => '標籤',
                //     'category' => ['DataCategory', 'newsTag'], // d_class1 的分類
                //     'multiple' => true,
                //     // 'imageConfig' => ['fileType' => 'newsTag'] // file_type 要不要抓圖片顯示在複選 
                // ],
                [
                    'type' => 'editor',
                    'field' => 'd_content',
                    'label' => '內容',
                    'rows' => 6,
                    'cols' => 80,
                    'useTiny' => true,
                    'hasGallery' => true,
                    'note' => '*小斷行請按Shift+Enter。<br />輸入區域的右下角可以調整輸入空間的大小。'
                ],
                [
                    'type' => 'datetime',
                    'field' => 'd_date',
                    'label' => '日期',
                ],
                // [
                //     'type' => 'updatetime',
                //     'field' => 'd_update_time',
                //     'label' => '最後更新時間',
                // ],
                [
                    'type' => 'select',
                    'field' => 'd_active',
                    'label' => '在網頁顯示',
                    'options' => [
                        ['value' => 2, 'label' => '草稿'],
                        ['value' => 1, 'label' => '顯示'],
                        ['value' => 0, 'label' => '不顯示']
                    ]
                ],
                [
                    'type' => 'image_upload',
                    'field' => 'newsCover',
                    'label' => '上傳封面圖片',
                    'fileType' => 'newsCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [
                        ['w' => 1030, 'h' => 570],
                        // 'maxSize' => 3,
                    ],
                    'note' => ''
                ],
                [
                    'type' => 'image_upload',
                    'field' => 'newsMCover',
                    'label' => '上傳封面手機圖片',
                    'fileType' => 'newsMCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [
                        ['w' => 1030, 'h' => 570],
                        // 'maxSize' => 3,
                    ],
                    'note' => ''
                ],
                // [
                //     'type' => 'image_upload',
                //     'field' => 'image',
                //     'label' => '上傳圖片',
                //     'fileType' => 'image',
                //     'multiple' => true,
                //     'dropzone' => true,
                //     'size' => [
                //         ['w' => 1030, 'h' => 570],
                //         // 'maxSize' => 3,
                //     ],
                //     'note' => ''
                // ],
                // [
                //     'type' => 'image',
                //     'field' => 'newsSimple',
                //     'label' => '圖片上傳',
                //     'fileType' => 'newsSimple',
                //     // 'multiple' => true,
                //     'format' => 'image/*',
                //     'size' => [
                //         ['w' => 0, 'h' => 0],
                //         // 'maxSize' => 3,
                //     ],
                //     'note' => ''
                // ],
                // [
                //     'type' => 'image',
                //     'field' => 'newsRoom',
                //     'label' => '房型圖片上傳',
                //     'fileType' => 'newsRoom',
                //     'multiple' => true,
                //     'format' => 'image/*',
                //     'size' => [
                //         ['w' => 0, 'h' => 0],
                //         // 'maxSize' => 3,
                //     ],
                //     'note' => ''
                // ],
                // [
                //     'type' => 'file_upload',
                //     'field' => 'file',
                //     'label' => '附件下載',
                //     // 'multiple' => true,
                //     'format' => '.pdf,.jpg,.png',
                //     'size' => [
                //         // 'maxSize' => 8,
                //     ],
                //     'fileType' => 'newsfile',
                //     'note' => ''
                // ],
            ]
        ],
        // [
        //     'sheetTitle' => '房型設定',
        //     'boxTitle'   => '',
        //     'items' => [
        //         [
        //             'type' => 'dynamic_fields',
        //             'field' => 'dynamic_rooms',
        //             'label' => '房型資訊',
        //             'fieldGroup' => 'rooms',
        //             'fields' => [
        //                 [
        //                     'name' => 'room_ch',
        //                     'label' => '中文名稱',
        //                     'type' => 'text',
        //                     'required' => true
        //                 ],
        //                 [
        //                     'name' => 'room_en',
        //                     'label' => '英文名稱',
        //                     'type' => 'text',
        //                 ],
        //                 [
        //                     'name' => 'room_type',
        //                     'label' => '房型類別',
        //                     'type' => 'select',
        //                     'options' => [
        //                         ['value' => 'single', 'label' => '單人房'],
        //                         ['value' => 'double', 'label' => '雙人房'],
        //                         ['value' => 'triple', 'label' => '三人房'],
        //                         ['value' => 'quad', 'label' => '四人房'],
        //                     ]
        //                 ],
        //                 [
        //                     'name' => 'room_content',
        //                     'label' => '房型說明',
        //                     'type' => 'textarea'
        //                 ],
        //                 [
        //                     'name' => 'room_image',
        //                     'label' => '房型圖片',
        //                     'type' => 'image', 
        //                     'fileType' => 'roomImage',
        //                     // 'multiple' => true,
        //                     'size' => [
        //                         ['w' => 800, 'h' => 600],
        //                         // 'maxSize' => 3,
        //                     ],
        //                     'note' => ''
        //                 ],
        //                 [
        //                     'name' => 'room_file',
        //                     'label' => '附件下載',
        //                     'type' => 'file',
        //                     // 'multiple' => true,
        //                     'format' => '.pdf,.jpg,.png',
        //                     'size' => [
        //                         // 'maxSize' => 8,
        //                     ],
        //                     'fileType' => 'roomfile',
        //                     'note' => ''
        //                 ],
        //             ],
        //             'note' => '點擊「+」可以新增更多項目'
        //         ],
        //     ]
        // ],
        [
            'sheetTitle' => 'SEO設定',
            'boxTitle'   => '',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'd_slug',
                    'label' => '網址別名 (slug)',
                    'note' => '留空則自動從標題產生'
                ],
                [
                    'type' => 'text',
                    'field' => 'd_seo_title',
                    'label' => 'SEO 標題',
                    'note' => '建議長度：50-60 字元'
                ],
                [
                    'type' => 'textarea',
                    'field' => 'd_description',
                    'label' => 'SEO 描述 (meta description)',
                    'rows' => 4,
                    'cols' => 80,
                    'note' => '建議長度：150-160 字元'
                ]
            ]
        ]
    ],
    
    'hiddenFields' => [
        'd_class1' => $menu_is
    ],
];

return $settingPage;
?>

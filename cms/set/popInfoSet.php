<?php
$menu_is = "popInfo";
$settingPage = [
    'pageType' => 'info',
    'module' => $menu_is,
    'moduleName' => '燈箱設定',
    'tableName' => 'data_set',
    'primaryKey' => 'd_id',
    'menuKey' => 'd_class1',
    'menuValue' => $menu_is,

    'cols' => [
        'title' => 'd_title',
        'active' => 'd_active',
        'file_fk' => 'file_d_id',
        'delete_time' => 'd_delete_time'  // data_set 資料表有 d_delete_time 欄位
    ],

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle' => '',
            'items' => [
                [
                    'type' => 'image_upload',
                    'field' => 'imageCover',
                    'label' => '上傳封面圖片',
                    'fileType' => 'popInfoCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [
                        ['w' => 1920, 'h' => 1080],
                        // 'maxSize' => 5
                    ],
                    'note' => ''
                ],
                // [
                //     'type' => 'image',
                //     'field' => 'popInfoSimple',
                //     'label' => '圖片上傳',
                //     'fileType' => 'popInfoSimple',
                //     // 'multiple' => true,
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
                //         'maxSize' => 8,
                //     ],
                //     'fileType' => 'newsfile',
                //     'note' => ''
                // ],
                [
                    'type' => 'datetime',
                    'field' => 'd_date',
                    'label' => '日期',
                    'default' => 'now'
                ],
                [
                    'type' => 'select',
                    'field' => 'd_active',
                    'label' => '在網頁顯示',
                    'options' => [
                        ['value' => 1, 'label' => '顯示'],
                        ['value' => 0, 'label' => '不顯示']
                    ],
                    'default' => 1
                ],
            ]
        ],
        // [
        //     'sheetTitle' => '房型設定',
        //     'boxTitle' => '',
        //     'items' => [
        //         [
        //             'type' => 'dynamic_fields',
        //             'field' => 'dynamic_rooms',
        //             'label' => '房型資訊',
        //             'fieldGroup' => 'rooms',  // 儲存到哪個群組
        //             'fields' => [
        //                 [
        //                     'name' => 'room_ch',
        //                     'label' => '標題',
        //                     'type' => 'text',
        //                     'required' => true
        //                 ],
        //                 [
        //                     'name' => 'room_content',
        //                     'label' => '房型說明',
        //                     'type' => 'textarea',
        //                 ],
        //                 [
        //                     'name' => 'room_image',
        //                     'label' => '房型圖片',
        //                     'type' => 'image',
        //                     'fileType' => 'roomImage',
        //                     // 'multiple' => true,
        //                     'size' => [
        //                         ['w' => 800, 'h' => 600]
        //                     ],
        //                     'note' => ''
        //                 ]
        //             ],
        //             'note' => '點擊「+」可以新增更多項目'
        //         ],
        //     ]
        // ],
        // [
        //     'sheetTitle' => 'SEO設定',
        //     'boxTitle' => '',
        //     'items' => [
        //         [
        //             'type' => 'text',
        //             'field' => 'd_slug',
        //             'label' => '網址別名 (slug)',
        //             'size' => 80,
        //             'note' => '留空則自動從標題產生'
        //         ],
        //         [
        //             'type' => 'text',
        //             'field' => 'd_seo_title',
        //             'label' => 'SEO 標題',
        //             'size' => 80,
        //             'note' => '建議長度：50-60 字元'
        //         ],
        //         [
        //             'type' => 'textarea',
        //             'field' => 'd_description',
        //             'label' => 'SEO 描述 (meta description)',
        //             'rows' => 4,
        //             'cols' => 80,
        //             'note' => '建議長度：150-160 字元'
        //         ],
        //     ]
        // ]
    ],
    
    'hiddenFields' => [
        'd_class1' => $menu_is,
    ],
];

return $settingPage;
?>

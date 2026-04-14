<?php
$menu_is = "websites";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '網站管理',
    'tableName' => 'data_set',
    'primaryKey' => 'd_id',
    'menuKey' => 'd_class1',
    'menuValue' => $menu_is,

    'cols' => [
        'date'  => 'd_date',
        'title' => 'd_title',
        'slug'  => 'd_slug',
        'slug_source' => 'd_title',
        'sort' => 'd_sort',
        'top' => 'd_top',
        'active' => 'd_active',
        'delete_time' => 'd_delete_time',
        'file_fk' => 'file_d_id'
    ],
    
    'listPage' => [
        'title' => '列表',
        'imageFileType' => 'websites',
        // ----------------------------------------------------------
        'hasCategory'   => false, // 列表是否顯示Filter By
        'showBatchActions' => false,
        'showCheckbox' => false,
        // ----------------------------------------------------------
        'columns' => [
            // ['field' => 'd_sort', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            // ['field' => 'pin', 'label' => '置頂', 'type' => 'button', 'width' => '50'],
            ['field' => 'd_date', 'label' => '日期', 'type' => 'date', 'width' => '110'],
            ['field' => 'd_data6', 'label' => '客戶', 'type' => 'text', 'width' => '130'],
            ['field' => 'd_title', 'label' => '網站名稱', 'type' => 'text', 'width' => '220'],
            ['field' => 'preview', 'label' => '網站', 'type' => 'button', 'width' => '60'],
            ['field' => 'd_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30']
        ],
        'itemsPerPage' => 9999999,
        'orderBy' => 'd_sort ASC, d_date DESC'
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '基本資訊',
            'boxTitle' => '',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'd_title',
                    'label' => '網站名稱',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'd_title_en',
                    'label' => '英文名稱 / 域名',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data6',
                    'label' => '客戶名稱',
                ],
                [
                    'type' => 'datetime',
                    'field' => 'd_date',
                    'label' => '建立日期',
                ],
                [
                    'type' => 'select',
                    'field' => 'd_active',
                    'label' => '專案狀態',
                    'options' => [
                        ['value' => 1, 'label' => '🟢 上線中'],
                        ['value' => 0, 'label' => '🔴 已下線'],
                    ]
                ],
            ]
        ],
        [
            'sheetTitle' => '資料庫連線',
            'boxTitle' => '子網站資料庫設定',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'd_data1',
                    'label' => '資料庫主機 (Host)',
                    'value' => 'localhost',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data2',
                    'label' => '資料庫名稱 (DB Name)',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data3',
                    'label' => '資料庫帳號 (User)',
                    'default' => 'root',
                    'attr' => 'autocomplete="new-password"'
                ],
                [
                    'type' => 'password',
                    'field' => 'd_data4',
                    'label' => '資料庫密碼 (Password)',
                    'default' => '',
                    'attr' => 'autocomplete="new-password"'
                ],
            ]
        ],
        [
            'sheetTitle' => '模組功能',
            'boxTitle' => '標準功能勾選',
            'items' => [
                [
                    'type' => 'checkbox',
                    'field' => 'd_data5',
                    'label' => '標準模組',
                    'options' => [
                        ['value' => 'news', 'label' => '最新消息'],
                        ['value' => 'product', 'label' => '產品管理'],
                        ['value' => 'contactus', 'label' => '聯絡我們'],
                    ],
                    'note' => '* 勾選即可啟用標準模組'
                ],
            ]
        ],
        [
            'sheetTitle' => '自訂進階功能',
            'boxTitle' => '手動新增彈性模組 (如：部落格、活動資訊)',
            'items' => [
                [
                    'type' => 'dynamic_fields',
                    'field' => 'custom_modules',
                    'label' => '自訂模組清單',
                    'fieldGroup' => 'custom_modules',
                    'fields' => [
                        [
                            'name' => 'm_name',
                            'label' => '模組名稱',
                            'type' => 'text',
                            'placeholder' => '如：部落格',
                            'required' => true
                        ],
                        [
                            'name' => 'm_slug',
                            'label' => '模組代碼',
                            'type' => 'text',
                            'placeholder' => '如：blog',
                            'required' => true
                        ],
                        [
                            'name' => 'm_type',
                            'label' => '使用範本',
                            'type' => 'select',
                            'options' => [
                                ['value' => 'single', 'label' => '一般內容模組 (單層分類)'],
                                ['value' => 'multi', 'label' => '進階內容模組 (多層分類)'],
                                ['value' => 'contactus', 'label' => '純表單模組 (不含分類與標籤)'],
                                ['value' => 'info', 'label' => '單頁設定模組 (燈箱/首頁設定型)'],
                                ['value' => 'list_only', 'label' => '純列表模組 (不含分類樹，單純資料列表)'],
                            ]
                        ],
                    ],
                    'note' => '* 你可以在此自行定義多組功能，並指定要繼承的範本'
                ],
            ]
        ],
        [
            'sheetTitle' => '連結 & 備註',
            'boxTitle' => '網站連結',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'd_data7',
                    'label' => '前台網址',
                    'placeholder' => 'https://example.com',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data8',
                    'label' => '後台網址',
                    'placeholder' => 'https://example.com/admin',
                ],
                [
                    'type' => 'textarea',
                    'field' => 'd_content',
                    'label' => '備註 / 開發筆記',
                    'placeholder' => '客製說明、特殊需求、部署資訊…',
                ],
            ]
        ],
    ],
    
    'hiddenFields' => [
        'd_class1' => $menu_is
    ],
];

return $settingPage;
?>

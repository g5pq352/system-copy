<?php
$menu_is = "keywordsInfo";
$settingPage = [
    'pageType' => 'info',
    'module' => $menu_is,
    'moduleName' => '全站設定',
    'tableName' => 'data_set',
    'primaryKey' => 'd_id',
    'menuKey' => 'd_class1',
    'menuValue' => $menu_is,

    'cols' => [
        'title' => 'd_title',
        'delete_time' => 'd_delete_time'  // data_set 資料表有 d_delete_time 欄位
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle' => '',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'd_title',
                    'label' => '網站名稱',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data1',
                    'label' => '電話',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data2',
                    'label' => '信箱',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data3',
                    'label' => '地址',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data4',
                    'label' => '統一編號',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_data5',
                    'label' => '著作權',
                ],
            ]
        ],
        [
            'sheetTitle' => 'SEO設定',
            'boxTitle' => '',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'd_seo_title',
                    'label' => 'SEO 標題 (title)',
                    'note' => '建議長度：50-60 字元'
                ],
                [
                    'type' => 'textarea',
                    'field' => 'd_description',
                    'label' => 'SEO 描述 (meta description)',
                    'rows' => 4,
                    'note' => '建議長度：150-160 字元'
                ],
                [
                    'type' => 'textarea',
                    'field' => 'd_head',
                    'label' => 'Head',
                    'rows' => 4,
                ],
                [
                    'type' => 'textarea',
                    'field' => 'd_body',
                    'label' => 'Body',
                    'rows' => 4,
                ],
                [
                    'type' => 'textarea',
                    'field' => 'd_schema',
                    'label' => 'Schema',
                    'rows' => 4,
                ],
            ]
        ]
    ],
    
    'hiddenFields' => [
        'd_class1' => $menu_is,
    ],
];

return $settingPage;
?>

<?php
$menu_is = "shopMember";
$category = "shopCate"; // 對應到 shopCateSet.php 的 menu_is
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '會員管理',
    'tableName' => 'member_set',
    'primaryKey' => 'd_id',
    'menuKey' => 'd_class2', // 如果有需要主分類可使用，若無則保留
    'menuValue' => $menu_is,

    'cols' => [
        'date'  => 'd_create_time',
        'title' => 'd_title',
        'sort' => 'd_sort',
        'active' => 'd_active',
        'file_fk' => 'file_d_id',
        'delete_time' => null, // member_set 表結構若無軟刪除欄位可移除
    ],
    
    'listPage' => [
        'title' => '列表',
        'categoryName' => $category,
        // ----------------------------------------------------------
        'hasCategory' => false,
        'useTaxonomyMapSort' => false,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'd_sort', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            ['field' => 'd_create_time', 'label' => '加入日期', 'type' => 'date', 'width' => '142'],
            ['field' => 'd_account', 'label' => '帳號', 'type' => 'text', 'width' => '200'],
            ['field' => 'd_title', 'label' => '姓名', 'type' => 'text', 'width' => '200'],
            ['field' => 'd_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30']
        ],
        'itemsPerPage' => 20, // 會員可能很多，建議分頁
        'orderBy' => 'd_sort ASC, d_create_time DESC'
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle' => '基本資訊',
            'items' => [
                [
                    'type' => 'select',
                    'field' => 'd_class2',
                    'label' => '會員分類',
                    'required' => true,
                    'category' => $category
                ],
                [
                    'type' => 'text',
                    'field' => 'd_title',
                    'label' => '姓名',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'field' => 'd_account',
                    'label' => '帳號',
                    'required' => true,
                ],
                [
                    'type' => 'password',
                    'field' => 'd_password',
                    'label' => '密碼',
                    'required' => false, // 編輯時可不填
                    'note' => '若不修改密碼請留空'
                ],
                [
                    'type' => 'datetime',
                    'field' => 'd_create_time',
                    'label' => '加入日期',
                    'required' => true
                ],
                [
                    'type' => 'number',
                    'field' => 'd_sort',
                    'label' => '排序',
                ],
                [
                    'type' => 'select',
                    'field' => 'd_active',
                    'label' => '顯示狀態',
                    'options' => [
                        ['value' => 1, 'label' => '啟用'],
                        ['value' => 0, 'label' => '停用']
                    ]
                ],
            ]
        ]
    ],
    
    'hiddenFields' => [],
];

return $settingPage;
?>

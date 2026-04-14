<?php
/**
 * Admin Management Configuration
 * 管理員管理配置
 */

$menu_is = "admin";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '管理員管理',
    'tableName' => 'admin',
    'primaryKey' => 'user_id',

    'cols' => [
        'title' => 'user_name',
        'active' => 'user_active',
        'sort' => null,  // admin 資料表沒有排序欄位
        'delete_time' => null  // admin 資料表沒有 delete_time 欄位
    ],
    
    'listPage' => [
        'title' => '管理員列表',
        // ----------------------------------------------------------
        'hasCategory' => false,
        'hasTrash' => false,
        'hasLanguage' => false,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'user_name', 'label' => '管理員名稱', 'type' => 'text', 'width' => '150'],
            ['field' => 'group_name', 'label' => '權限群組', 'type' => 'text', 'width' => '120'],
            ['field' => 'user_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '60'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30'],
        ],
        'itemsPerPage' => 9999999,
        'orderBy' => 'user_id ASC',
        'customQuery' => 'SELECT admin.user_id, admin.user_name, admin.group_id, admin.user_active, 
                          authority_groups.group_name 
                          FROM admin 
                          LEFT JOIN authority_groups ON admin.group_id = authority_groups.group_id'
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '基本資訊',
            'boxTitle' => '帳號設定',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'user_name',
                    'label' => '管理員名稱',
                    'required' => true,
                    'note' => '登入帳號（唯一）'
                ],
                [
                    'type' => 'password',
                    'field' => 'user_password',
                    'label' => '密碼',
                    'required' => false,
                    'note' => '新增時必填，編輯時留空表示不修改密碼'
                ],
                [
                    'type' => 'select',
                    'field' => 'group_id',
                    'label' => '權限群組',
                    'required' => true,
                    'category' => 'authorityCate',
                    'note' => '選擇此管理員的權限群組'
                ],
                [
                    'type' => 'radio',
                    'field' => 'user_active',
                    'label' => '帳號狀態',
                    'required' => true,
                    'options' => [
                        ['value' => '1', 'label' => '啟用'],
                        ['value' => '0', 'label' => '停用']
                    ],
                    'default' => '1',
                    'note' => '停用後該管理員無法登入'
                ]
            ]
        ]
    ],
    
    'hiddenFields' => [],
];

return $settingPage;
?>

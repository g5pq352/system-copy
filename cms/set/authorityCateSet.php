<?php
/**
 * Authority Category Configuration
 * 權限群組管理配置
 */

$menu_is = "authorityCate";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '權限群組管理',
    'tableName' => 'authority_groups',
    'primaryKey' => 'group_id',

    'cols' => [
        'title' => 'group_name',
        'active' => 'group_active',
        'sort' => null,
        'delete_time' => null  // authority_groups 資料表沒有 delete_time 欄位
    ],
    
    'listPage' => [
        'title' => '權限群組列表',
        // ----------------------------------------------------------
        'hasCategory' => false,
        'hasLanguage' => false,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'group_name', 'label' => '群組名稱', 'type' => 'text', 'width' => '200'],
            ['field' => 'group_description', 'label' => '描述', 'type' => 'text', 'width' => '300'],
            ['field' => 'group_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '80'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30']
        ],
        'itemsPerPage' => 9999999,
        'orderBy' => 'group_id ASC'
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '基本資訊',
            'boxTitle' => '群組資訊',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'group_name',
                    'label' => '群組名稱',
                    'required' => true,
                    'note' => '例如：系統管理員、編輯者、工讀生'
                ],
                [
                    'type' => 'textarea',
                    'field' => 'group_description',
                    'label' => '群組描述',
                    'required' => false,
                    'rows' => 3,
                    'cols' => 60,
                    'note' => '說明此群組的用途'
                ]
            ]
        ],
        [
            'sheetTitle' => '權限設定',
            'boxTitle' => '選單權限管理',
            'items' => [
                [
                    'type' => 'authority_matrix',
                    'field' => 'permissions',
                    'label' => '權限矩陣',
                    'note' => '勾選對應的權限以授予該群組存取權限'
                ]
            ]
        ]
    ],
    
    'hiddenFields' => [],
];

return $settingPage;
?>

<?php
$hasReply = false; // 開放回覆功能
$hasStatus = false; // 開放處理狀態功能

$menu_is = "contactus";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '聯絡我們表單',
    'tableName' => 'message_set',
    'primaryKey' => 'm_id',
    'menuKey' => 'm_type',
    'menuValue' => 'contactus',

    // 用於儀錶板顯示
    'dashboardActive' => false,
    // 用於回覆功能
    'replyActive' => $hasReply,
    // 用於處理狀態功能
    'statusActive' => $hasStatus,

    'cols' => [
        'date'  => 'm_date',
        'title' => 'm_title',
        'read'  => 'm_read',
        'reply' => 'm_reply',
        'status' => 'm_status',
        'note' => 'm_note',
        'delete_time' => null  // message_set 資料表沒有 delete_time 欄位
    ],
    
    'listPage' => [
        'title' => '列表',
        // ----------------------------------------------------------
        'hasCategory' => false,
        'showAddButton' => false,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'm_date', 'label' => '日期', 'type' => 'date', 'width' => '120'],
            ['field' => 'm_title', 'label' => '姓名', 'type' => 'text', 'width' => '200'],
            ...($hasStatus ? [
                ['field' => 'm_status', 'label' => '處理狀態', 'type' => 'status_badge', 'width' => '100'],
            ] : []),
            ...($hasReply ? [
                ['field' => 'reply', 'label' => '回覆', 'type' => 'reply_status', 'width' => '60'],
            ] : []),
            ['field' => 'read', 'label' => '已讀', 'type' => 'read_toggle', 'width' => '60'],
            ['field' => 'view', 'label' => '查看', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30']
        ],
        'itemsPerPage' => 9999999,
        'orderBy' => 'm_date DESC',
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'boxTitle' => '',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'm_inquiry',
                    'label' => '詢問類型',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'text',
                    'field' => 'm_title',
                    'label' => '姓名',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'text',
                    'field' => 'm_email',
                    'label' => '電子郵件',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'text',
                    'field' => 'm_phone',
                    'label' => '手機',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'text',
                    'field' => 'm_address',
                    'label' => '聯絡地址',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'text',
                    'field' => 'm_data1',
                    'label' => '方便聯絡時間',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'text',
                    'field' => 'm_data2',
                    'label' => '聯繫日期',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'textarea',
                    'field' => 'm_content',
                    'label' => '訊息',
                    'readonly' => 'readonly',
                ],
                [
                    'type' => 'datetime',
                    'field' => 'm_date',
                    'label' => '日期',
                    'readonly' => 'readonly',
                    'size' => 50
                ],
            ]
        ],
        ...($hasStatus ? [
            [
                'sheetTitle' => '處理資訊',
                'boxTitle' => '內部管理',
                'items' => [
                    [
                        'type' => 'select',
                        'field' => 'm_status',
                        'label' => '處理狀態',
                        'options' => [
                            ['value' => 'pending', 'label' => '待處理'],
                            ['value' => 'processing', 'label' => '處理中'],
                            ['value' => 'completed', 'label' => '已完成'],
                            ['value' => 'cancelled', 'label' => '已取消']
                        ],
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'field' => 'm_note',
                        'label' => '內部備註',
                        'placeholder' => '記錄處理過程、注意事項等（不會顯示給客戶）',
                        'rows' => 5,
                    ],
                    [
                        'type' => 'text',
                        'field' => 'm_handler',
                        'label' => '處理人員',
                        'readonly' => 'readonly',
                        'value' => $_SESSION['MM_LoginAccountUsername'] ?? 'System',
                    ],
                    [
                        'type' => 'datetime',
                        'field' => 'm_handled_at',
                        'label' => '處理時間',
                        'readonly' => 'readonly',
                    ],
                ]
            ],
        ] : []),
    ],
    
    'hiddenFields' => [],

    // 【新增】不使用 slug 功能
    'disableSlug' => true,
];

return $settingPage;
?>

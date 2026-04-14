<?php
$menu_is = "homeDisplay";
$targetModule = 'news';
$settingPage = [
    'module' => $menu_is,
    'moduleName' => '首頁顯示管理',
    'tableName' => 'data_set',  // 改為 data_set，因為主要查詢的是這個表
    'primaryKey' => 'd_id',

    // 設定 menuKey 和 menuValue 讓 list.php 自動產生 WHERE 條件
    'menuKey' => 'd_class1',
    'menuValue' => $targetModule,

    // 特殊設定：標記這是首頁顯示管理模組
    'isHomeDisplayModule' => true,
    'targetModule' => $targetModule,

    'cols' => [
        'date' => 'd_date',
        'title' => 'd_title',
        'sort' => 'd_sort',
        'active' => 'd_active',
        'delete_time' => 'd_delete_time',
    ],

    'listPage' => [
        'title' => '首頁顯示設定',
        // ----------------------------------------------------------
        'hasCategory' => false,
        'readOnly' => true, // 標記為唯讀模式（不顯示新增/刪除按鈕）
        'hasTrash' => false, // 不支援回收桶
        'showBatchActions' => false, // 不顯示批次操作
        'showAddButton' => false, // 不顯示新增按鈕
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'd_date', 'label' => '日期', 'type' => 'date', 'width' => '142'],
            ['field' => 'd_title', 'label' => '標題', 'type' => 'text', 'width' => '470'],
            ['field' => 'hd_sort', 'label' => '排序', 'type' => 'home_sort_dropdown', 'width' => '74'],
            ['field' => 'is_in_home', 'label' => '首頁顯示', 'type' => 'home_display_toggle', 'width' => '100'],
        ],
        'itemsPerPage' => 9999999,
        'orderBy' => 'is_in_home DESC, COALESCE(hd.hd_sort, 9999) ASC, ds.d_date DESC',
        'customQuery' => "
            SELECT
                ds.*,
                hd.hd_id,
                hd.hd_sort,
                hd.hd_active as hd_active,
                CASE WHEN hd.hd_id IS NOT NULL THEN 1 ELSE 0 END as is_in_home
            FROM data_set ds
            LEFT JOIN home_display hd
                ON ds.d_id = hd.hd_data_id
                AND hd.hd_module = :targetModule
                AND hd.lang = ds.lang
        ",
    ],

    'hiddenFields' => [],
];

return $settingPage;
?>

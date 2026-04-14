<?php
/**
 * CMS Menu Management Configuration (Hierarchical)
 * CMS 選單管理配置（階層式）
 */

$menu_is = "cmsMenu";
$settingPage = [
    'module' => $menu_is,
    'moduleName' => 'CMS選單管理',
    'tableName' => 'cms_menus',
    'primaryKey' => 'menu_id',

    'cols' => [
        'title' => 'menu_title',
        'sort' => 'menu_sort',
        'active' => 'menu_active',
        'delete_time' => null,
        'top' => null,
        'file_fk' => null,
        'parent_id' => 'menu_parent_id'  // 階層導航用
    ],
    
    'listPage' => [
        'title' => '選單列表',
        // ----------------------------------------------------------
        'hasCategory' => false,
        'hasHierarchy' => true,  // 開啟階層導航功能
        'hasLanguage' => false,
        // ----------------------------------------------------------
        'columns' => [
            ['field' => 'menu_sort', 'label' => '排序', 'type' => 'sort', 'width' => '74'],
            ['field' => 'menu_title', 'label' => '標題', 'type' => 'text', 'width' => '250'],
            ['field' => 'ttp_name', 'label' => '標籤屬性', 'type' => 'text', 'width' => '150'],
            ['field' => 'menu_type', 'label' => '類型', 'type' => 'text', 'width' => '150'],
            ['field' => 'menu_link', 'label' => '連結', 'type' => 'text', 'width' => '200'],
            ['field' => 'menu_active', 'label' => '狀態', 'type' => 'active', 'width' => '60'],
            // ['field' => 't_name', 'label' => '分類名稱', 'type' => 'text', 'width' => '400'],
            ['field' => 'next_level', 'label' => '下一層', 'type' => 'button', 'width' => '60'],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => '30'],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => '30']
        ],
        'itemsPerPage' => 9999999,
        'orderBy' => 'menu_sort ASC',
        'customQuery' => 'SELECT cms_menus.*, taxonomy_types.ttp_name 
                          FROM cms_menus 
                          LEFT JOIN taxonomy_types ON cms_menus.taxonomy_type_id = taxonomy_types.ttp_id',
    ],
    
    'detailPage' => [
        [
            'sheetTitle' => '基本設定',
            'boxTitle' => '選單資訊',
            'items' => [
                [
                    'type' => 'select',
                    'field' => 'menu_parent_id',
                    'label' => '父層分類',
                    'required' => true,
                    'category' => 'cmsMenu',
                    'note' => '選擇「頂層選單」表示這是主選單，選擇其他項目表示這是子選單'
                ],
                [
                    'type' => 'text',
                    'field' => 'menu_title',
                    'label' => '選單標題',
                    'required' => true,
                    'note' => '例如：首頁、燈箱、Banner'
                ],
                [
                    'type' => 'text',
                    'field' => 'menu_type',
                    'label' => '類型/模組名稱',
                    'required' => false,
                    'note' => '子選單必填，例如：popInfo、indexBannerInfo（主選單可留空）'
                ],
                [
                    'type' => 'text',
                    'field' => 'menu_link',
                    'label' => '選單連結',
                    'required' => false,
                    'note' => '主選單必填，例如：tpl=popInfo/info（子選單可留空）'
                ],
                // [
                //     'type' => 'select',
                //     'field' => 'menu_br',
                //     'label' => '是否換行',
                //     'options' => [
                //         ['value' => 0, 'label' => '不換行'],
                //         ['value' => 1, 'label' => '換行']
                //     ],
                //     'note' => '子選單專用，控制是否在此項目後換行'
                // ],
                // [
                //     'type' => 'number',
                //     'field' => 'menu_sort',
                //     'label' => '排序',
                //     'size' => 10,
                //     'note' => '數字越小越前面'
                // ],
                [
                    'type' => 'select',
                    'field' => 'menu_icon',
                    'label' => 'icon',
                    'class' => 'select2-icon-render',
                    'showPlaceholder' => true,
                    'options' => [
                        ['value' => 'bx bx-file', 'label' => '頁面'],
                        ['value' => 'bx bx-detail', 'label' => '表單'],
                        ['value' => 'bx bx-user-circle', 'label' => '人'],
                        ['value' => 'bx bx-cog', 'label' => '設定'],
                        ['value' => 'fa-solid fa-images', 'label' => '圖片'],
                    ]
                ],
                [
                    'type' => 'select',
                    'field' => 'menu_active',
                    'label' => '在網頁顯示',
                    'options' => [
                        ['value' => 1, 'label' => '顯示'],
                        ['value' => 0, 'label' => '不顯示']
                    ]
                ],
                [
                    'type' => 'select',
                    'field' => 'taxonomy_type_id',
                    'label' => '標籤類型',
                    'required' => false,
                    'category' => 'taxonomyType',
                    'useChosen' => true,
                    'note' => '分類的模組使用'
                ]
            ]
        ]
    ],
    
    'hiddenFields' => [],
];

return $settingPage;
?>

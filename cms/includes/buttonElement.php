<?php
/**
 * Button Element Functions
 * 可重用的按鈕元件
 */

/**
 * 渲染新增按鈕
 * @param string $module 模組名稱 (例如: 'blog', 'news')
 * @return string HTML 字串
 */
function renderAddButton($module)
{
    return "<a href=\"".PORTAL_AUTH_URL."tpl={$module}/detail\" class=\"btnType btn-add\"><i class=\"fas fa-plus-circle\"></i> 新增</a>";
}

/**
 * 渲染編輯按鈕
 * @param int $id 資料 ID
 * @param string $module 模組名稱
 * @param string $primaryKey 主鍵名稱（預設為 d_id）
 * @param bool $isTrash 是否為垃圾桶模式
 * @param array $extraParams 額外參數 (例如: ['selected1' => 1])
 * @return string HTML 字串
 */
function renderEditButton($id, $module, $primaryKey = 'd_id', $isTrash = false, $extraParams = [])
{
    $params = ["{$primaryKey}={$id}"];
    if ($isTrash) $params[] = 'trash_view=1';
    
    foreach ($extraParams as $key => $value) {
        if ($value !== '' && $value !== null) {
            $params[] = $key . "=" . urlencode($value);
        }
    }
    
    $queryString = implode('&', $params);
    return "<a href=\"".PORTAL_AUTH_URL."tpl={$module}/detail?{$queryString}\" class=\"btn btn-info\" title=\"編輯\"><i class=\"fas fa-edit\"></i></a>";
}

/**
 * 預覽子網站按鈕 (連結至實體子網站後台)
 */
function renderPreviewButton($id, $titleEn = '')
{
    if (!empty($titleEn)) {
        $slug = SubsiteHelper::sanitizeSlug($titleEn);
        // 子網站路徑通常在 WAMP www 的子目錄下
        $siteUrl = "/" . $slug . "/portal-auth/dashboard";
        return "<a href=\"{$siteUrl}\" target=\"_blank\" class=\"btn btn-success\" title=\"進入子網站後台\"><i class=\"fas fa-external-link-alt\"></i> 進入後台</a>";
    }
    
    // 如果沒有英文標題，回退到原本的預覽模式 (開發用)
    return "<a href=\"".PORTAL_AUTH_URL."dashboard?preview_id={$id}\" class=\"btn btn-success\" title=\"預覽環境\"><i class=\"fas fa-external-link-alt\"></i> 預覽</a>";
}

function renderViewButton($id, $module, $primaryKey = 'd_id', $isTrash = false, $extraParams = [])
{
    $params = ["{$primaryKey}={$id}"];
    if ($isTrash) $params[] = 'trash_view=1';
    
    foreach ($extraParams as $key => $value) {
        if ($value !== '' && $value !== null) {
            $params[] = $key . "=" . urlencode($value);
        }
    }
    
    $queryString = implode('&', $params);
    return "<a href=\"".PORTAL_AUTH_URL."tpl={$module}/detail?{$queryString}\" class=\"btn btn-info\" title=\"查看\"><i class=\"fas fa-eye\"></i></a>";
}

function renderDeleteButton($id, $module, $hasTrash = true, $hasHierarchy = false, $tableName = '')
{
    $hasTrashStr = $hasTrash ? '1' : '0';
    $hasHierarchyStr = $hasHierarchy ? '1' : '0';
    return "<a href=\"javascript:void(0);\" class=\"btn btn-danger\" data-id=\"{$id}\" data-module=\"{$module}\" data-has-trash=\"{$hasTrashStr}\" data-has-hierarchy=\"{$hasHierarchyStr}\" data-table-name=\"{$tableName}\" title=\"刪除\" onclick=\"deleteItem(this)\"><i class=\"fas fa-trash-alt\"></i></a>";
}

/**
 * 渲染返回按鈕
 * @param string $module 模組名稱
 * @param array $extraParams 額外參數
 * @return string HTML 字串
 */
function renderBackButton($module, $extraParams = [])
{
    $queryString = !empty($extraParams) ? "?" . http_build_query($extraParams) : "";
    return "<a href=\"".PORTAL_AUTH_URL."tpl={$module}/list{$queryString}\" class=\"btnType btn-back\"><i class=\"fas fa-arrow-left\"></i> 返回</a>";
}

/**
 * 渲染置頂按鈕
 */
function renderPinButton($id, $module, $isPinned = false)
{
    $icon = $isPinned ? 'fas fa-thumbtack' : 'fas fa-thumbtack';
    $title = $isPinned ? '取消置頂' : '置頂';
    $class = $isPinned ? 'btn btn-warning' : 'btn btn-default';
    
    return "<a href=\"javascript:void(0);\" class=\"{$class}\" data-id=\"{$id}\" data-module=\"{$module}\" data-pinned=\"{$isPinned}\" title=\"{$title}\" onclick=\"togglePin(this)\"><i class=\"{$icon}\"></i></a>";
}

/**
 * 渲染回收桶按鈕（列表頂部入口）
 */
function renderTrashButton($module)
{
    return "<a href=\"".PORTAL_AUTH_URL."tpl={$module}/list?trash=1\" class=\"btn btn-primary btn-md font-weight-semibold btn-py-2 px-4\"><i class=\"fas fa-trash-alt\"></i> 回收桶</a>";
}

/**
 * 渲染還原按鈕
 */
function renderRestoreButton($id, $module)
{
    return "<a href=\"javascript:void(0);\" class=\"btn btn-success\" data-id=\"{$id}\" data-module=\"{$module}\" title=\"還原\" onclick=\"restoreItem(this)\"><i class=\"fas fa-undo\"></i></a>";
}

/**
 * 渲染永久刪除按鈕
 */
function renderPermanentDeleteButton($id, $module)
{
    return "<a href=\"javascript:void(0);\" class=\"btn btn-danger\" data-id=\"{$id}\" data-module=\"{$module}\" title=\"永久刪除\" onclick=\"permanentDelete(this)\"><i class=\"fas fa-times\"></i></a>";
}

/**
 * 渲染 Active/Inactive 切換按鈕
 * @param int $active 當前狀態 (1=顯示, 0=不顯示, 2=草稿)
 * @param int $id 資料 ID
 * @param string $field 欄位名稱
 * @return string HTML 字串
 */
function renderActiveToggle($active, $id, $field = 'd_active')
{
    // 檢查模組配置是否支援草稿狀態
    global $moduleConfig;
    $hasDraftStatus = false;
    
    if (isset($moduleConfig['detailPage'])) {
        foreach ($moduleConfig['detailPage'] as $sheet) {
            foreach ($sheet['items'] ?? [] as $item) {
                if (isset($item['field']) && $item['field'] === $field && $item['type'] === 'select') {
                    // 檢查是否有 value=2 的選項（草稿）
                    foreach ($item['options'] ?? [] as $option) {
                        if ($option['value'] == 2) {
                            $hasDraftStatus = true;
                            break 3;
                        }
                    }
                }
            }
        }
    }
    
    if ($hasDraftStatus) {
        // 支援草稿：0=不顯示, 1=顯示, 2=草稿
        // 循環: 1 (顯示) -> 0 (不顯示) -> 2 (草稿) -> 1
        $states = [
            1 => ['label' => '顯示', 'color' => '#28a745', 'next' => 0],
            0 => ['label' => '不顯示', 'color' => '#dc3545', 'next' => 2],
            2 => ['label' => '草稿', 'color' => '#ffc107', 'next' => 1],
        ];
        $current = $states[$active] ?? $states[1];
        
        return sprintf(
            '<span class="btn" style="background-color: %s; cursor: pointer; padding: 5px 10px; border-radius: 4px; color: white;" 
                   onclick="toggleActive(this, %d, %d, \'%s\')">%s</span>',
            $current['color'],
            $id,
            $current['next'],
            $field,
            $current['label']
        );
    } else {
        // 標準模式：1=顯示, 0=不顯示
        $states = [
            1 => ['label' => '顯示', 'color' => '#28a745', 'next' => 0],
            0 => ['label' => '不顯示', 'color' => '#dc3545', 'next' => 1],
        ];
        $current = $states[$active] ?? $states[1];
        
        return sprintf(
            '<span class="btn" style="background-color: %s; cursor: pointer; padding: 5px 10px; border-radius: 4px; color: white;" 
                   onclick="toggleActive(this, %d, %d, \'%s\')">%s</span>',
            $current['color'],
            $id,
            $current['next'],
            $field,
            $current['label']
        );
    }
}

/**
 * 渲染首頁顯示切換按鈕
 */
function renderHomeActiveToggle($active, $id)
{
    $states = [
        1 => ['label' => '顯示', 'color' => '#28a745', 'next' => 0],
        0 => ['label' => '不顯示', 'color' => '#dc3545', 'next' => 1],
    ];
    $current = $states[$active] ?? $states[0];
    
    return sprintf(
        '<span class="btn" style="background-color: %s; cursor: pointer; padding: 5px 10px; border-radius: 4px; color: white;" 
               onclick="toggleActive(this, %d, %d, \'d_home_active\')">%s</span>',
        $current['color'],
        $id,
        $current['next'],
        $current['label']
    );
}

/**
 * 渲染 Read/Unread 切換按鈕
 * @param int $status 當前狀態 (1=已讀, 0=未讀)
 * @param int $id 資料 ID
 * @return string HTML 字串
 */
function renderReadToggle($status, $id)
{
    $states = [
        1 => ['label' => '已讀', 'color' => '#28a745', 'next' => 0],
        0 => ['label' => '未讀', 'color' => '#dc3545', 'next' => 1],
    ];
    $current = $states[$status] ?? $states[0];
    
    return sprintf(
        '<span class="btn badge" style="background-color: %s; cursor: pointer; padding: 10px 10px; border-radius: 4px; color: white;" 
               onclick="toggleRead(this, %d, %d)">%s</span>',
        $current['color'],
        $id,
        $current['next'],
        $current['label']
    );
}

/**
 * 渲染回覆狀態標籤
 * @param int $status (1=已回覆, 0=未回覆)
 * @return string HTML
 */
function renderReplyStatus($status)
{
    if ($status == 1) {
        return '<span class="btn badge badge-success" style="background-color: #28a745; padding: 10px 10px;color:#fff">已回覆</span>';
    } else {
        return '<span class="btn badge badge-default" style="background-color: #dc3545; padding: 10px 10px;color:#fff">未回覆</span>';
    }
}

/**
 * 渲染處理狀態徽章
 * @param string $status 處理狀態 (pending, processing, completed, cancelled)
 * @return string HTML
 */
function renderStatusBadge($status)
{
    $statusConfig = [
        'pending' => ['label' => '待處理', 'color' => '#6c757d'],      // 灰色
        'processing' => ['label' => '處理中', 'color' => '#007bff'],   // 藍色
        'completed' => ['label' => '已完成', 'color' => '#28a745'],    // 綠色
        'cancelled' => ['label' => '已取消', 'color' => '#dc3545'],    // 紅色
    ];

    $config = $statusConfig[$status] ?? $statusConfig['pending'];

    return sprintf(
        '<span class="btn badge" style="background-color: %s; padding: 10px 10px; color: #fff;">%s</span>',
        $config['color'],
        $config['label']
    );
}

/**
 * 渲染排序下拉選單
 * @param int $currentSort 當前排序值
 * @param int $totalRows 總筆數
 * @param int $id 資料 ID
 * @param int $pageNum 當前頁碼
 * @param int $selected1 選中的分類
 * @return string HTML 字串
 */
function renderSortDropdown($currentSort, $totalRows, $id, $pageNum, $selected1, $sortFieldName = 'd_sort')
{
    $categoryId = ($selected1 === null || $selected1 === '') ? 0 : $selected1;
    $html = "<select name=\"{$sortFieldName}\" id=\"{$sortFieldName}_{$id}\" class=\"form-control-sm\" onchange=\"changeSort('{$pageNum}','{$totalRows}','{$id}',this.options[this.selectedIndex].value, {$categoryId})\">";
    
    // 從 1 開始，不包含 0（置頂選項已移除，改用置頂按鈕）
    for ($j = 1; $j <= $totalRows; $j++) {
        $selected = ($j == $currentSort) ? " selected" : "";
        $html .= "<option value=\"{$j}\"{$selected}>{$j}</option>";
    }
    
    $html .= "</select>";
    return $html;
}

/**
 * 渲染送出按鈕
 */
function renderSubmitButton($label = '送出')
{
    // 務必確認 type="button"
    return "<button name=\"submitBtn\" type=\"button\" title=\"快捷鍵 (Alt + S)\" class=\"btn btn-primary btn-md font-weight-semibold btn-py-2 px-4 btn-save\" id=\"submitBtn\"><i class=\"fas fa-floppy-disk\"></i> {$label}</button>";
}
?>

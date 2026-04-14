<?php
/**
 * 權限檢查函數
 * 在需要權限控制的頁面開頭引入此檔案
 */

/**
 * 檢查使用者是否有指定選單的權限
 * @param int $menuId 選單 ID
 * @param string $action 動作類型：view, add, edit, delete
 * @return bool 是否有權限
 */
function hasPermission($menuId, $action = 'view') {
    // 如果沒有登入，沒有權限
    if (!isset($_SESSION['MM_LoginAccountUserId'])) {
        return false;
    }
    
    // 如果沒有設定權限（舊系統或超級管理員），全部允許
    if (!isset($_SESSION['MM_UserPermissions'])) {
        return true;
    }
    
    $permissions = $_SESSION['MM_UserPermissions'];
    
    // 如果該選單沒有設定權限，預設拒絕
    if (!isset($permissions[$menuId])) {
        return false;
    }
    
    // 檢查對應的權限
    $permKey = 'can_' . $action;
    if ($action === 'view') $permKey = 'view';
    if ($action === 'add') $permKey = 'add';
    if ($action === 'edit') $permKey = 'edit';
    if ($action === 'delete') $permKey = 'delete';
    
    return !empty($permissions[$menuId][$permKey]);
}

/**
 * 檢查當前頁面權限，如果沒有權限則跳轉
 * @param int $menuId 選單 ID
 * @param string $action 動作類型
 */
function requirePermission($menuId, $action = 'view') {
    if (!hasPermission($menuId, $action)) {
        header("Location: " . PORTAL_AUTH_URL . "dashboard?error=no_permission");
        exit;
    }
}

/**
 * 根據權限過濾選單
 * @param array $menus 選單陣列
 * @return array 過濾後的選單
 */
function filterMenusByPermission($menus) {
    if (!isset($_SESSION['MM_UserPermissions'])) {
        // 沒有權限設定，顯示全部
        return $menus;
    }
    
    $permissions = $_SESSION['MM_UserPermissions'];
    $filtered = [];
    
    foreach ($menus as $menu) {
        $menuId = $menu['menu_id'];
        
        // 檢查是否有檢視權限
        if (isset($permissions[$menuId]) && !empty($permissions[$menuId]['view'])) {
            $filtered[] = $menu;
        }
    }
    
    return $filtered;
}
?>

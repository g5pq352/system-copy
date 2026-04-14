<?php
/**
 * Permission Element
 * 權限檢查元件
 */

class PermissionElement
{
    /**
     * 檢查模組權限
     * @param PDO $conn 資料庫連線
     * @param string $module 模組名稱
     * @return array [canView, canAdd, canEdit, canDelete]
     */
    public static function checkModulePermission($conn, $module)
    {
        $userPermissions = $_SESSION['MM_UserPermissions'] ?? null;
        
        // 預設權限
        $canView = true;
        $canAdd = true;
        $canEdit = true;
        $canDelete = true;
        
        // 【修正】如果是超級管理員（$userPermissions === null），跳過所有權限檢查
        if ($userPermissions !== null) {
            // 一般使用者：檢查權限
            // 從資料庫查詢此模組對應的 menu_id
            $menuQuery = "SELECT menu_id FROM cms_menus WHERE menu_type = :module_name AND menu_active = 1 LIMIT 1";
            $menuStmt = $conn->prepare($menuQuery);
            $menuStmt->execute([':module_name' => $module]);
            $menuData = $menuStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($menuData) {
                $menuId = $menuData['menu_id'];
                
                // 如果該選單有權限設定，使用權限；否則拒絕訪問
                if (isset($userPermissions[$menuId])) {
                    $canView = !empty($userPermissions[$menuId]['view']);
                    $canAdd = !empty($userPermissions[$menuId]['add']);
                    $canEdit = !empty($userPermissions[$menuId]['edit']);
                    $canDelete = !empty($userPermissions[$menuId]['delete']);
                } else {
                    // 沒有權限設定 = 拒絕訪問
                    $canView = false;
                    $canAdd = false;
                    $canEdit = false;
                    $canDelete = false;
                }
            }
        }
        
        return [$canView, $canAdd, $canEdit, $canDelete];
    }
    
    /**
     * 要求檢視權限，否則跳轉
     * @param bool $canView 是否有檢視權限
     * @param string $redirectUrl 跳轉 URL
     */
    public static function requireViewPermission($canView, $redirectUrl = PORTAL_AUTH_URL.'dashboard?error=no_permission')
    {
        if (!$canView) {
            header("Location: {$redirectUrl}");
            exit;
        }
    }
    
    /**
     * 檢查詳細頁面權限（新增或編輯）
     * @param PDO $conn 資料庫連線
     * @param string $module 模組名稱
     * @param bool $isNewRecord 是否為新增模式
     * @return bool 是否有權限
     */
    public static function checkDetailPermission($conn, $module, $isNewRecord)
    {
        $userPermissions = $_SESSION['MM_UserPermissions'] ?? null;
        
        // 超級管理員：跳過所有權限檢查
        if ($userPermissions === null) {
            return true;
        }
        
        // 一般使用者：檢查權限
        $menuQuery = "SELECT menu_id FROM cms_menus WHERE menu_type = :module_name AND menu_active = 1 LIMIT 1";
        $menuStmt = $conn->prepare($menuQuery);
        $menuStmt->execute([':module_name' => $module]);
        $menuData = $menuStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($menuData) {
            $menuId = $menuData['menu_id'];
            
            if (isset($userPermissions[$menuId])) {
                // 新增時檢查 add 權限，編輯時檢查 edit 權限
                $requiredPermission = $isNewRecord ? 'add' : 'edit';
                return !empty($userPermissions[$menuId][$requiredPermission]);
            }
        }
        
        return false;
    }
    
    /**
     * 要求詳細頁面權限，否則跳轉
     * @param PDO $conn 資料庫連線
     * @param string $module 模組名稱
     * @param bool $isNewRecord 是否為新增模式
     * @param string $redirectUrl 跳轉 URL
     */
    public static function requireDetailPermission($conn, $module, $isNewRecord, $redirectUrl = PORTAL_AUTH_URL.'dashboard?error=no_permission')
    {
        if (!self::checkDetailPermission($conn, $module, $isNewRecord)) {
            header("Location: {$redirectUrl}");
            exit;
        }
    }
    
    /**
     * 檢查資訊頁面權限
     * @param PDO $conn 資料庫連線
     * @param string $module 模組名稱
     * @return array [hasViewPermission, hasAddPermission, hasEditPermission, hasDeletePermission]
     */
    public static function checkInfoPermission($conn, $module)
    {
        $userPermissions = $_SESSION['MM_UserPermissions'] ?? null;
        
        // 超級管理員：全部允許
        if ($userPermissions === null) {
            return [true, true, true, true];
        }
        
        // 一般使用者：檢查權限
        $menuQuery = "SELECT menu_id FROM cms_menus WHERE menu_type = :module_name AND menu_active = 1 LIMIT 1";
        $menuStmt = $conn->prepare($menuQuery);
        $menuStmt->execute([':module_name' => $module]);
        $menuData = $menuStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($menuData) {
            $menuId = $menuData['menu_id'];
            
            if (isset($userPermissions[$menuId])) {
                $canView = !empty($userPermissions[$menuId]['view']);
                $canAdd = !empty($userPermissions[$menuId]['add']);
                $canEdit = !empty($userPermissions[$menuId]['edit']);
                $canDelete = !empty($userPermissions[$menuId]['delete']);
                return [$canView, $canAdd, $canEdit, $canDelete];
            }
        }
        
        return [false, false, false, false];
    }
}

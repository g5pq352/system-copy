<?php
/**
 * Authority Management Helper Functions
 * 權限管理輔助函數
 */

/**
 * 獲取選單結構（階層式）
 * @param PDO $conn 資料庫連線
 * @param int $parentId 父選單 ID（預設 0 表示頂層）
 * @param int $level 層級（用於縮排顯示）
 * @return array 選單結構陣列
 */
function getMenuStructure($conn, $parentId = 0, $level = 0) {
    $menus = [];
    
    // 【修正】使用 menu_id 作為唯一識別,並取得 menu_type
    $query = "SELECT menu_id, menu_parent_id, menu_title, menu_type
              FROM cms_menus 
              WHERE menu_parent_id = :parent_id 
              AND menu_active = 1
              ORDER BY menu_sort ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':parent_id' => $parentId]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['level'] = $level;
        
        // 【新增】檢查是否為 info 頁面
        $row['is_info_page'] = false;
        if (!empty($row['menu_type'])) {
            $configFile = __DIR__ . "/../set/{$row['menu_type']}Set.php";
            if (file_exists($configFile)) {
                $config = include $configFile;
                if (is_array($config) && isset($config['pageType']) && $config['pageType'] === 'info') {
                    $row['is_info_page'] = true;
                }
            }
        }
        
        $menus[] = $row;
        
        // 遞迴取得子選單
        $children = getMenuStructure($conn, $row['menu_id'], $level + 1);
        $menus = array_merge($menus, $children);
    }
    
    return $menus;
}

/**
 * 獲取群組的權限設定
 * @param PDO $conn 資料庫連線
 * @param int $groupId 群組 ID
 * @return array 權限設定（key 為 menu_id）
 */
function getGroupPermissions($conn, $groupId) {
    $permissions = [];
    
    if (!$groupId) {
        return $permissions;
    }
    
    $query = "SELECT menu_id, can_view, can_add, can_edit, can_delete 
              FROM group_permissions 
              WHERE group_id = :group_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':group_id' => $groupId]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions[$row['menu_id']] = [
            'view' => $row['can_view'],
            'add' => $row['can_add'],
            'edit' => $row['can_edit'],
            'delete' => $row['can_delete']
        ];
    }
    
    return $permissions;
}

/**
 * 渲染權限矩陣表格
 * @param PDO $conn 資料庫連線
 * @param int $groupId 群組 ID
 * @return string HTML 內容
 */
function renderAuthorityMatrix($conn, $groupId = 0) {
    $menus = getMenuStructure($conn);
    $permissions = getGroupPermissions($conn, $groupId);
    
    $html = '<div class="authority-matrix" style="overflow-x: auto;">';
    
    // 全選/全反選按鈕
    $html .= '<div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
    $html .= '<strong>快速操作：</strong> ';
    $html .= '<button type="button" class="btn-select-all" data-type="view" style="margin: 0 5px; padding: 5px 10px;">全選檢視</button>';
    $html .= '<button type="button" class="btn-select-all" data-type="add" style="margin: 0 5px; padding: 5px 10px;">全選新增</button>';
    $html .= '<button type="button" class="btn-select-all" data-type="edit" style="margin: 0 5px; padding: 5px 10px;">全選修改</button>';
    $html .= '<button type="button" class="btn-select-all" data-type="delete" style="margin: 0 5px; padding: 5px 10px;">全選刪除</button>';
    $html .= ' | ';
    $html .= '<button type="button" class="btn-deselect-all" data-type="view" style="margin: 0 5px; padding: 5px 10px;">全不選檢視</button>';
    $html .= '<button type="button" class="btn-deselect-all" data-type="add" style="margin: 0 5px; padding: 5px 10px;">全不選新增</button>';
    $html .= '<button type="button" class="btn-deselect-all" data-type="edit" style="margin: 0 5px; padding: 5px 10px;">全不選修改</button>';
    $html .= '<button type="button" class="btn-deselect-all" data-type="delete" style="margin: 0 5px; padding: 5px 10px;">全不選刪除</button>';
    $html .= ' | ';
    $html .= '<button type="button" id="btn-select-all-permissions" style="margin: 0 5px; padding: 5px 15px; background: #4CAF50; color: white; border: none; border-radius: 3px;">全選所有</button>';
    $html .= '<button type="button" id="btn-deselect-all-permissions" style="margin: 0 5px; padding: 5px 15px; background: #f44336; color: white; border: none; border-radius: 3px;">全不選所有</button>';
    $html .= '</div>';
    
    $html .= '<table class="table" style="width: 100%; border-collapse: collapse;">';
    
    // 表頭
    $html .= '<thead>';
    $html .= '<tr style="background: #f5f5f5;">';
    $html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: left; min-width: 300px;">選單項目</th>';
    $html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center; width: 80px;">檢視</th>';
    $html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center; width: 80px;">新增</th>';
    $html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center; width: 80px;">修改</th>';
    $html .= '<th style="padding: 10px; border: 1px solid #ddd; text-align: center; width: 80px;">刪除</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    
    // 表身
    $html .= '<tbody>';
    
    foreach ($menus as $menu) {
        $menuId = $menu['menu_id'];
        $isParent = ($menu['level'] == 0); // 判斷是否為主選單
        $isInfoPage = $menu['is_info_page'] ?? false; // 判斷是否為 info 頁面
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $menu['level']);
        $prefix = $menu['level'] > 0 ? '└ ' : '';
        
        // 獲取當前選單的權限設定
        $perm = $permissions[$menuId] ?? ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0];
        
        $html .= '<tr>';
        $html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . $indent . $prefix . htmlspecialchars($menu['menu_title']) . ' <small style="color: #999;">(ID: ' . $menuId . ')</small></td>';
        
        // 【修改】判斷邏輯：info 頁面或子選單顯示完整權限，一般主選單只顯示檢視
        if ($isInfoPage || !$isParent) {
            // 【Info 頁面或子選單】顯示完整 4 個 checkbox
            // 檢視權限
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;">';
            $html .= '<input type="checkbox" class="perm-checkbox perm-view" name="perm_' . $menuId . '_view" value="1" ' . ($perm['view'] ? 'checked' : '') . '>';
            $html .= '</td>';
            
            // 新增權限
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;">';
            $html .= '<input type="checkbox" class="perm-checkbox perm-add" name="perm_' . $menuId . '_add" value="1" ' . ($perm['add'] ? 'checked' : '') . '>';
            $html .= '</td>';
            
            // 修改權限
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;">';
            $html .= '<input type="checkbox" class="perm-checkbox perm-edit" name="perm_' . $menuId . '_edit" value="1" ' . ($perm['edit'] ? 'checked' : '') . '>';
            $html .= '</td>';
            
            // 刪除權限
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;">';
            $html .= '<input type="checkbox" class="perm-checkbox perm-delete" name="perm_' . $menuId . '_delete" value="1" ' . ($perm['delete'] ? 'checked' : '') . '>';
            $html .= '</td>';
        } else {
            // 【一般主選單】只顯示「檢視」checkbox
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;">';
            $html .= '<input type="checkbox" class="perm-checkbox perm-view" name="perm_' . $menuId . '_view" value="1" ' . ($perm['view'] ? 'checked' : '') . '>';
            $html .= '</td>';
            
            // 其他欄位顯示 "-"
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: #ccc;">-</td>';
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: #ccc;">-</td>';
            $html .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: #ccc;">-</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    
    // 添加全選功能的 JavaScript
    $html .= '
    <script>
    $(document).ready(function() {
        // 單一欄位全選
        $(".btn-select-all").on("click", function() {
            var type = $(this).data("type");
            $(".perm-" + type).prop("checked", true);
        });
        
        // 單一欄位全不選
        $(".btn-deselect-all").on("click", function() {
            var type = $(this).data("type");
            $(".perm-" + type).prop("checked", false);
        });
        
        // 全選所有權限
        $("#btn-select-all-permissions").on("click", function() {
            $(".perm-checkbox").prop("checked", true);
        });
        
        // 全不選所有權限
        $("#btn-deselect-all-permissions").on("click", function() {
            $(".perm-checkbox").prop("checked", false);
        });
    });
    </script>';
    
    return $html;
}

/**
 * 儲存群組權限
 * @param PDO $conn 資料庫連線
 * @param int $groupId 群組 ID
 * @param array $postData POST 資料
 * @return bool 是否成功
 */
function saveGroupPermissions($conn, $groupId, $postData) {
    if (!$groupId) {
        return false;
    }
    
    try {
        // 【修正】不需要 beginTransaction,因為外層已經開始了事務
        
        // 先刪除該群組的所有權限
        $deleteStmt = $conn->prepare("DELETE FROM group_permissions WHERE group_id = :group_id");
        $deleteStmt->execute([':group_id' => $groupId]);
        
        // 插入新的權限設定
        $insertStmt = $conn->prepare("
            INSERT INTO group_permissions (group_id, menu_id, can_view, can_add, can_edit, can_delete)
            VALUES (:group_id, :menu_id, :can_view, :can_add, :can_edit, :can_delete)
        ");
        
        // 【新增】先取得所有選單資訊(判斷是否為主選單及是否為 info 頁面)
        $menuQuery = "SELECT menu_id, menu_parent_id, menu_type FROM cms_menus WHERE menu_active = 1";
        $menuStmt = $conn->prepare($menuQuery);
        $menuStmt->execute();
        $allMenus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $menuInfoMap = [];
        foreach ($allMenus as $menu) {
            $isInfoPage = false;
            if (!empty($menu['menu_type'])) {
                $configFile = __DIR__ . "/../set/{$menu['menu_type']}Set.php";
                if (file_exists($configFile)) {
                    $config = include $configFile;
                    if (is_array($config) && isset($config['pageType']) && $config['pageType'] === 'info') {
                        $isInfoPage = true;
                    }
                }
            }
            
            $menuInfoMap[$menu['menu_id']] = [
                'parent_id' => $menu['menu_parent_id'],
                'is_info_page' => $isInfoPage
            ];
        }
        
        // 收集所有權限欄位
        $menuPermissions = [];
        foreach ($postData as $key => $value) {
            if (strpos($key, 'perm_') === 0) {
                // 解析欄位名稱:perm_{menu_id}_{permission_type}
                $parts = explode('_', $key);
                if (count($parts) === 3) {
                    $menuId = $parts[1];
                    $permType = $parts[2]; // view, add, edit, delete
                    
                    // 【修正】跳過空的 menu_id(例如 perm__view)
                    if (empty($menuId)) {
                        continue;
                    }
                    
                    if (!isset($menuPermissions[$menuId])) {
                        $menuPermissions[$menuId] = [
                            'view' => 0,
                            'add' => 0,
                            'edit' => 0,
                            'delete' => 0
                        ];
                    }
                    
                    $menuPermissions[$menuId][$permType] = 1;
                }
            }
        }
        
        // 批次插入
        foreach ($menuPermissions as $menuId => $perms) {
            $menuInfo = $menuInfoMap[$menuId] ?? null;
            if (!$menuInfo) continue;
            
            $isParent = ($menuInfo['parent_id'] === 0);
            $isInfoPage = $menuInfo['is_info_page'];
            
            // 【修改】判斷邏輯:info 頁面或子選單儲存完整權限,一般主選單只儲存檢視
            if ($isInfoPage || !$isParent) {
                // 【Info 頁面或子選單】儲存完整權限
                // 只有至少有一個權限被勾選時才插入
                if ($perms['view'] || $perms['add'] || $perms['edit'] || $perms['delete']) {
                    $insertStmt->execute([
                        ':group_id' => $groupId,
                        ':menu_id' => $menuId,
                        ':can_view' => $perms['view'],
                        ':can_add' => $perms['add'],
                        ':can_edit' => $perms['edit'],
                        ':can_delete' => $perms['delete']
                    ]);
                }
            } else {
                // 【一般主選單】只儲存 can_view,其他設為 0
                if ($perms['view']) {
                    $insertStmt->execute([
                        ':group_id' => $groupId,
                        ':menu_id' => $menuId,
                        ':can_view' => 1,
                        ':can_add' => 0,
                        ':can_edit' => 0,
                        ':can_delete' => 0
                    ]);
                }
            }
        }
        
        // 【修正】不需要 commit,因為外層會統一 commit
        return true;
        
    } catch (Exception $e) {
        // 【修正】不需要 rollback,讓外層處理
        error_log("Error saving group permissions: " . $e->getMessage());
        throw $e; // 重新拋出異常讓外層捕獲
    }
}
?>

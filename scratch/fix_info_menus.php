<?php
/**
 * Fix hierarchical structure for info modules in subsite 'zxc'
 */
$dsn = "mysql:host=localhost;dbname=zxc;charset=utf8";
$user = 'root';
$pass = '';

try {
    $conn = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // 找出所有沒有子項的 info 類型父選單
    $sql = "SELECT menu_id, menu_title, menu_link, menu_type FROM cms_menus 
            WHERE menu_parent_id = 0 
            AND menu_link LIKE '%/info'
            AND menu_id NOT IN (SELECT DISTINCT menu_parent_id FROM cms_menus WHERE menu_parent_id > 0)";
    
    $parents = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($parents as $p) {
        $pId = $p['menu_id'];
        $title = $p['menu_title'];
        $link = $p['menu_link'];
        $slug = $p['menu_type'];
        
        echo "Fixing {$title} ({$slug})...\n";
        
        $childTitle = $title . '設定';
        
        // 插入子選單
        $stmtChild = $conn->prepare("INSERT INTO cms_menus (menu_parent_id, menu_title, menu_type, menu_link, menu_icon, menu_sort, menu_active, menu_base_type, menu_table, menu_pk) 
                                    VALUES (?, ?, ?, ?, '', 1, 1, 'info', 'data_set', 'd_id')");
        $stmtChild->execute([$pId, $childTitle, $slug, $link]);
    }
    
    echo "Done.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

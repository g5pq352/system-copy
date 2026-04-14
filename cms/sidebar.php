<?php
// 1. Load menu helper (ensure buildMenuHtml is defined here or in this file)
require_once(__DIR__ . '/includes/menuHelper.php');
?>

<aside id="sidebar-left" class="sidebar-left">
    <div class="sidebar-header">
        <div class="sidebar-title">Navigation</div>
        <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html"
            data-fire-event="sidebar-left-toggle">
            <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
        </div>
    </div>
    <div class="nano">
        <div class="nano-content">
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main mb-5">
                    <li class="<?php echo (isset($isDashboard) && $isDashboard) ? 'nav-expanded nav-active' : ''; ?>">
                        <a class="nav-link" href="<?=PORTAL_AUTH_URL?>dashboard">
                            <i class="bx bx-home-alt" aria-hidden="true"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <?php
                    // 1. 取得資料
                    $AllMenusConfig = loadMenusFromDatabase($conn);
                    $userPermissions = $_SESSION['MM_UserPermissions'] ?? null;

                    // 2. 準備參數 (現在程式會自動去抓 $_GET['module']，所以這裡就算傳空字串也沒關係)
                    $current_menu_is = $menu_is ?? '';
                    $ryder_now = $_SERVER['REQUEST_URI'];

                    // 3. 呼叫
                    list($menuHtml, $isActive) = buildMenuHtml($AllMenusConfig, $ryder_now, $userPermissions, $current_menu_is);

                    // 4. 輸出
                    echo $menuHtml;
                    ?>

                    <?php if(isset($_SESSION['MM_UserGroupId']) && $_SESSION['MM_UserGroupId'] == 999){ ?>
                        <li class="nav-parent <?php if ($module == "cmsMenu" || $module == "menus" || $module == "taxonomyType") {
                                echo 'nav-expanded nav-active';
                            } ?>">
                            <a class="nav-link" href="<?=PORTAL_AUTH_URL?>tpl=menus/list">
                                <i class="fa-solid fa-bars" aria-hidden="true"></i><span>選單管理</span> </a>
                            <ul class="nav nav-children">
                                <li class=""><a class="nav-link" href="<?=PORTAL_AUTH_URL?>tpl=menus/list">前端選單列表</a></li>
                                <li class=""><a class="nav-link" href="<?=PORTAL_AUTH_URL?>tpl=cmsMenu/list">後端選單列表</a></li>
                                <li class=""><a class="nav-link" href="<?=PORTAL_AUTH_URL?>tpl=taxonomyType/list">標籤類型列表</a></li>
                            </ul>
                        </li>
                    <?php } ?>
                </ul>
            </nav>
        </div>
    </div>
</aside>
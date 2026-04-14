<?php
require_once(__DIR__ . '/includes/categoryHelper.php');
?>
<!-- start: header -->
<?php if (isset($_SESSION['current_preview_id'])): ?>
<div style="background: #ffc107; color: #000; text-align: center; padding: 8px 10px; font-weight: bold; font-size: 14px; position: fixed; top: 0; left: 0; right: 0; z-index: 9999;">
    <i class="fas fa-exclamation-triangle"></i> 目前正在預覽子網站內容 (ID: <?= $_SESSION['current_preview_id'] ?>) - 
    <a href="?preview_id=0" style="color: #0056b3; text-decoration: underline; margin-left: 10px;">
        <i class="fas fa-sign-out-alt"></i> 結束預覽並返回主後台
    </a>
</div>
<style>
    /* 預覽模式時，將主體頁面往下推，避免遮擋 */
    body { padding-top: 35px !important; }
    .header { top: 35px !important; }
    .sidebar-left { top: 95px !important; } /* 60px header + 35px banner */
</style>
<?php endif; ?>
<header class="header">
    <div class="logo-container">
        <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html"
            data-fire-event="sidebar-left-opened">
            <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
        </div>
    </div>

    <!-- start: search & user box -->
    <div class="header-right">

        <div class="userbox">
            <?php
            if(CMS_LOGOUT_TIME > 60){
            ?>
            <div class="">
                <span>登出時間 : </span>
                <span id="time-countdown"></span>
            </div>
            <?php }?>
        </div>

        <span class="separator"></span>

        <div class="userbox">
            <div class="">
                <a href="../" target="_blank">
                    觀看首頁 
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
        </div>

        <span class="separator"></span>

        <div id="userbox" class="userbox">
            <a href="#" data-bs-toggle="dropdown">
                <figure class="profile-picture">
                    <img src="template-style/img/!logged-user.jpg" alt="Joseph Doe" class="rounded-circle"
                        data-lock-picture="img/!logged-user.jpg" />
                </figure>
                <?php if (isset($_SESSION['MM_LoginAccountUsername'])): ?>
                    <div class="profile-info" data-lock-name="John Doe" data-lock-email="johndoe@okler.com">
                        <span class="name"><strong><?php echo htmlspecialchars($_SESSION['MM_LoginAccountUsername']); ?></strong></span>
                        <?php 
                        if (isset($_SESSION['MM_UserGroupId'])): 
                        $authorityGroups = getCategoryOptions('authorityCate');
                        $groupName = $_SESSION['MM_UserGroupId'];

                        if (!empty($authorityGroups)) {
                            foreach ($authorityGroups as $group) {
                                if ($_SESSION['MM_UserGroupId'] == 999) {
                                    $groupName = '超級管理員';
                                    break;
                                }
                                if ($group['id'] == $_SESSION['MM_UserGroupId']) {
                                    $groupName = $group['name'];
                                    break;
                                }
                            }
                        }
                        ?>
                            <span class="role mt-1"><?php echo htmlspecialchars($groupName); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <i class="fa custom-caret"></i>
            </a>

            <div class="dropdown-menu">
                <ul class="list-unstyled mb-2">
                    <li class="divider"></li>
                    <?php if(isset($_SESSION['MM_UserGroupId']) && $_SESSION['MM_UserGroupId'] == 999){ ?>
                        <li>
                            <a role="menuitem" tabindex="-1" href="<?=PORTAL_AUTH_URL?>tpl=languageType/list"><i class="bx bx-globe"></i>語系列表</a>
                        </li>
                        <li>
                            <a role="menuitem" tabindex="-1" href="<?=PORTAL_AUTH_URL?>tpl=languagePack/list"><i class="bx bx-text"></i>語言包列表</a>
                        </li>
                    <?php } ?>
                    <li>
                        <a role="menuitem" tabindex="-1" href="<?php echo $logoutAction ?>"><i class="bx bx-power-off"></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <!-- end: search & user box -->
</header>
<!-- end: header -->
<?php
require_once 'auth.php';
// 判斷當前是否在 dashboard 頁面
$isDashboard = (basename($_SERVER['PHP_SELF']) === 'first.php' ||
                (isset($_GET['tpl']) && $_GET['tpl'] === 'dashboard') ||
                (!isset($_GET['tpl']) && !isset($_GET['module'])));
?>
<!DOCTYPE html>
<html class="sidebar-left-big-icons">
<head>
    <title><?php require_once('cmsTitle.php'); ?></title>
    <?php require_once('head.php'); ?>
    <?php require_once('script.php'); ?>
</head>
<body>
    <section class="body">
        <!-- start: header -->
        <?php require_once('header.php'); ?>
        <!-- end: header -->

        <div class="inner-wrapper">
            <!-- start: sidebar -->
            <?php require_once('sidebar.php'); ?>
            <!-- end: sidebar -->

            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>儀錶板</h2>

                    <div class="right-wrapper text-end">
                        <ol class="breadcrumbs">
                            <li>
                                <a href="<?=PORTAL_AUTH_URL?>dashboard">
                                    <i class="bx bx-home-alt"></i>
                                </a>
                            </li>
                        </ol>

                        <a class="sidebar-right-toggle" data-open="sidebar-right" style="pointer-events: none;"></a>
                    </div>
                </header>

                <div class="row">
                    <?php
                    // 根據 config 決定是否顯示 (總開關)
                    if (defined('SHOW_CONTACT_WIDGET') && SHOW_CONTACT_WIDGET):

                        // 1. 掃描所有設定檔，找出 viewOnly => true 的模組
                        $moduleWidgets = [];
                        $setDir = __DIR__ . '/set/';
                        $setFiles = glob($setDir . '*Set.php');

                        foreach ($setFiles as $file) {
                            $config = require $file; // 載入配置

                            // 檢查是否為「表單型」模組 (replyActive = true 且 tableName = message_set)
                            if (isset($config['tableName']) && $config['tableName'] === 'message_set' && $config['dashboardActive'] == true) {
                                $moduleName = $config['moduleName'] ?? '未命名模組';
                                $moduleKey = $config['module'] ?? '';
                                $tableName = $config['tableName'] ?? '';

                                // 判斷已讀/未讀欄位 (預設 m_read)
                                $readCol = $config['cols']['read'] ?? 'm_read';

                                // 判斷是否有過濾條件 (menuKey/menuValue)
                                $menuKey = $config['menuKey'] ?? null;
                                $menuValue = $config['menuValue'] ?? null;

                                // 查詢未讀數量
                                if ($tableName && $readCol) {
                                    try {
                                        $cntSql = "SELECT COUNT(*) FROM {$tableName} WHERE {$readCol} = 0";
                                        $params = [];

                                        // 【新增】加入過濾條件
                                        if ($menuKey && $menuValue !== null) {
                                            $cntSql .= " AND {$menuKey} = :val";
                                            $params[':val'] = $menuValue;
                                        }

                                        // 使用 prepare/execute 避免 SQL Injection (雖然 config 可信，但習慣較好)
                                        $cntStmt = $conn->prepare($cntSql);
                                        $cntStmt->execute($params);
                                        $unreadCount = $cntStmt->fetchColumn();

                                        // 只有在有未讀訊息時顯示？或是一直顯示已讀0？
                                        // 這裡選擇全部顯示，讓使用者方便知道有這些模組
                                        $moduleWidgets[] = [
                                            'title' => $moduleName,
                                            'count' => $unreadCount,
                                            'link' => PORTAL_AUTH_URL . "tpl={$moduleKey}/list",
                                            'icon' => 'fas fa-envelope'
                                        ];

                                    } catch (Exception $e) {
                                        // 忽略查詢錯誤
                                    }
                                }
                            }
                        }

                        // 2. 輸出 Widget
                        foreach ($moduleWidgets as $widget):
                        ?>
                        <div class="col-md-4 col-lg-12 col-xl-4">
                            <section class="card card-featured-left card-featured-primary mb-3">
                                <div class="card-body">
                                    <div class="widget-summary">
                                        <div class="widget-summary-col widget-summary-col-icon">
                                            <div class="summary-icon bg-primary">
                                                <i class="<?php echo $widget['icon']; ?>"></i>
                                            </div>
                                        </div>
                                        <div class="widget-summary-col">
                                            <div class="summary">
                                                <h4 class="title"><?php echo htmlspecialchars($widget['title']); ?> (未讀)</h4>
                                                <div class="info">
                                                    <strong class="amount <?php echo ($widget['count'] > 0) ? 'text-danger' : ''; ?>"><?php echo $widget['count']; ?></strong>
                                                </div>
                                            </div>
                                            <div class="summary-footer">
                                                <a class="text-muted text-uppercase" href="<?php echo $widget['link']; ?>">查看列表 (View All)</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>
                <div class="row">
                    <?php
                    // 瀏覽記錄統計 Widget
                    if (defined('SHOW_VIEW_LOG_WIDGET') && SHOW_VIEW_LOG_WIDGET):
                        require_once __DIR__ . '/../app/Repositories/ViewLogRepository.php';

                        try {
                            // 使用 $GLOBALS['db'] 而不是 $conn (因為 ViewLogRepository 需要 Db class 而不是 PDO)
                            $viewLogRepo = new \App\Repositories\ViewLogRepository($GLOBALS['db']);
                            $statsDays = defined('VIEW_LOG_STATS_DAYS') ? VIEW_LOG_STATS_DAYS : 7;
                            $viewStats = $viewLogRepo->getDashboardStats($statsDays);
                        ?>

                        <!-- 總瀏覽次數 -->
                        <div class="col-md-4 col-lg-12 col-xl-4">
                            <section class="card card-featured-left card-featured-success mb-3">
                                <div class="card-body">
                                    <div class="widget-summary">
                                        <div class="widget-summary-col widget-summary-col-icon">
                                            <div class="summary-icon bg-success">
                                                <i class="fas fa-eye"></i>
                                            </div>
                                        </div>
                                        <div class="widget-summary-col">
                                            <div class="summary">
                                                <h4 class="title">近 <?php echo $statsDays; ?> 天瀏覽次數</h4>
                                                <div class="info">
                                                    <strong class="amount"><?php echo number_format($viewStats['total_views']); ?></strong>
                                                    <span class="text-muted ms-2">(今日: <?php echo number_format($viewStats['today_views']); ?>)</span>
                                                </div>
                                            </div>
                                            <div class="summary-footer">
                                                <span class="text-muted">不重複訪客: <?php echo number_format($viewStats['unique_visitors']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <!-- 裝置類型分布 -->
                        <?php if (!empty($viewStats['device_stats'])): ?>
                        <div class="col-md-4 col-lg-12 col-xl-4">
                            <section class="card card-featured-left card-featured-info mb-3">
                                <div class="card-body">
                                    <div class="widget-summary">
                                        <div class="widget-summary-col widget-summary-col-icon">
                                            <div class="summary-icon bg-info">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                        </div>
                                        <div class="widget-summary-col">
                                            <div class="summary">
                                                <h4 class="title">裝置類型分布</h4>
                                                <div class="info">
                                                    <?php foreach ($viewStats['device_stats'] as $device): ?>
                                                    <div class="mb-1">
                                                        <span class="badge badge-sm badge-info"><?php echo ucfirst($device['device_type']); ?></span>
                                                        <strong><?php echo $device['count']; ?></strong>
                                                        <span class="text-muted">(<?php echo $device['percentage']; ?>%)</span>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <?php endif; ?>

                        <!-- 熱門文章 -->
                        <?php if (!empty($viewStats['popular_articles'])): ?>
                        <div class="col-md-4 col-lg-12 col-xl-4">
                            <section class="card card-featured-left card-featured-warning mb-3">
                                <div class="card-body">
                                    <div class="widget-summary">
                                        <div class="widget-summary-col widget-summary-col-icon">
                                            <div class="summary-icon bg-warning">
                                                <i class="fas fa-fire"></i>
                                            </div>
                                        </div>
                                        <div class="widget-summary-col">
                                            <div class="summary">
                                                <h4 class="title">熱門文章 Top 5</h4>
                                                <div class="info">
                                                    <?php foreach ($viewStats['popular_articles'] as $idx => $article): ?>
                                                    <div class="mb-1" style="font-size: 0.85rem;">
                                                        <span class="badge badge-sm badge-warning">#<?php echo $idx + 1; ?></span>
                                                        <?php if (!empty($article['module_name'])): ?>
                                                        <span class="badge badge-sm badge-secondary"><?php echo htmlspecialchars($article['module_name']); ?></span>
                                                        <?php endif; ?>
                                                        <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo htmlspecialchars($article['d_title'] ?? '未命名'); ?>">
                                                            <?php echo htmlspecialchars($article['d_title'] ?? '未命名'); ?>
                                                        </span>
                                                        <strong class="text-muted">(<?php echo $article['view_count']; ?>)</strong>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                        <?php endif; ?>

                        <?php
                        } catch (Exception $e) {
                            // 忽略錯誤，不顯示 Widget
                        }
                    endif;
                    ?>
                </div>
                <div class="row">
                    
                </div>
                <!-- end: page -->
            </section>
        </div>
    </section>
</body>
</html>
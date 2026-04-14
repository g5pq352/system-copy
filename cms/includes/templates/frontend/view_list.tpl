<!-- Main List Fragment -->
<section class="page-header dynamic-module-header py-5 bg-light mb-0">
    <div class="container text-center">
        <h1 class="page-title fw-bold h2"><?php echo htmlspecialchars($categoryInfo['t_name'] ?? '{Name}'); ?></h1>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar / Categories -->
        <aside class="col-lg-3">
            <div class="category-widget p-4 bg-white border rounded shadow-sm">
                <h5 class="widget-title mb-3 fw-bold border-bottom pb-2">分類項目</h5>
                <ul class="list-unstyled custom-menu">
                    <li><a href="<?php echo $baseurl; ?>/{Slug}" class="d-block py-2 text-decoration-none <?php echo empty($categoryInfo) ? 'text-primary fw-bold' : 'text-dark'; ?>">全部顯示</a></li>
                    <?php if(!empty($categories)): foreach($categories as $cat): ?>
                        <li>
                            <a href="<?php echo $baseurl; ?>/{Slug}/category/<?php echo $cat['t_slug']; ?>" 
                               class="d-block py-2 text-decoration-none <?php echo ($categoryInfo['t_id'] ?? 0) == $cat['t_id'] ? 'text-primary fw-bold' : 'text-dark'; ?>">
                                <?php echo htmlspecialchars($cat['t_name']); ?>
                            </a>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </aside>

        <!-- Main List -->
        <div class="col-lg-9">
            <div class="row g-4">
                <?php if(!empty($list)): foreach($list as $item): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="card h-100 border-0 shadow-sm overflow-hidden hover-shadow-lg transition">
                            <a href="<?php echo $baseurl; ?>/{Slug}/detail/<?php echo $item['d_slug']; ?>" class="d-block overflow-hidden">
                                <img src="<?php echo $baseurl . ($item['images'][0]['file_link1'] ?? '/images/default.jpg'); ?>" 
                                     class="card-img-top object-fit-cover transition-scale" style="height: 200px;" 
                                     alt="<?php echo htmlspecialchars($item['d_title']); ?>">
                            </a>
                            <div class="card-body p-4">
                                <div class="text-muted small mb-2"><?php echo date('Y-m-d', strtotime($item['d_date'])); ?></div>
                                <h5 class="card-title h6 mb-3">
                                    <a href="<?php echo $baseurl; ?>/{Slug}/detail/<?php echo $item['d_slug']; ?>" class="text-dark text-decoration-none stretched-link">
                                        <?php echo htmlspecialchars($item['d_title']); ?>
                                    </a>
                                </h5>
                                <p class="card-text text-muted small text-truncate-3">
                                    <?php echo mb_strimwidth(strip_tags($item['d_content']), 0, 150, "..."); ?>
                                </p>
                            </div>
                        </article>
                    </div>
                <?php endforeach; else: ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted lead">暫無相關資料內容</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if(!empty($pages)): ?>
            <div class="pagination-wrapper mt-5 d-flex justify-content-center">
                <?php echo $pages->render(); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

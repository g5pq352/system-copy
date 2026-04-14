<!-- Detail View Fragment -->
<section class="page-header py-5 bg-light mb-0">
    <div class="container text-center">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center mb-2">
                <li class="breadcrumb-item"><a href="<?php echo $baseurl; ?>/{Slug}" class="text-decoration-none text-secondary">{Name}</a></li>
                <?php if(!empty($categoryInfo)): ?>
                    <li class="breadcrumb-item active"><a href="<?php echo $baseurl; ?>/{Slug}/category/<?php echo $categoryInfo['t_slug']; ?>" class="text-decoration-none text-secondary"><?php echo htmlspecialchars($categoryInfo['t_name']); ?></a></li>
                <?php endif; ?>
            </ol>
        </nav>
        <h1 class="page-title h2 fw-bold text-dark"><?php echo htmlspecialchars($work['d_title']); ?></h1>
        <div class="text-muted small mt-3"><?php echo date('Y-m-d', strtotime($work['d_date'])); ?></div>
    </div>
</section>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <!-- Main Content -->
            <article class="content-wrapper bg-white shadow-sm p-4 p-md-5 rounded border">
                <?php if(!empty($coverImages)): ?>
                    <div class="detail-banner mb-5 rounded overflow-hidden shadow-sm">
                        <img src="<?php echo $baseurl . $coverImages[0]['file_link1']; ?>" 
                             class="w-100 object-fit-cover" 
                             alt="<?php echo htmlspecialchars($work['d_title']); ?>">
                    </div>
                <?php endif; ?>

                <div class="content post-content text-dark mb-5" style="line-height: 1.8; font-size: 1.05rem;">
                    <?php echo $work['d_content']; ?>
                </div>

                <!-- Multiple Images Gallery -->
                <?php if(!empty($images)): ?>
                <div class="row g-3 mb-5">
                    <?php foreach($images as $img): ?>
                    <div class="col-6 col-md-4">
                        <a href="<?php echo $baseurl . $img['file_link1']; ?>" data-lightbox="gallery" class="d-block rounded overflow-hidden border">
                            <img src="<?php echo $baseurl . $img['file_link1']; ?>" class="w-100 h-100 object-fit-cover" style="height: 150px;" alt="">
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Footer Info -->
                <div class="pt-4 border-top d-flex justify-content-between align-items-center">
                    <a href="javascript:history.back()" class="btn btn-outline-dark px-4 btn-sm">
                        <i class="fas fa-arrow-left me-2"></i> 返回列表
                    </a>
                </div>
            </article>
        </div>
    </div>
</div>

<!-- Info Page Fragment -->
<?php 
// 確保 $info 是陣列，避免沒資料時報錯
$info = $info ?: []; 
?>

<section class="page-header py-5 bg-light mb-0">
    <div class="container text-center">
        <h1 class="page-title h2 fw-bold text-dark"><?php echo htmlspecialchars($info['d_title'] ?? '尚未建立標題'); ?></h1>
    </div>
</section>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <!-- Main Content -->
            <article class="content-wrapper bg-white shadow-sm p-4 p-md-5 rounded border">
                <?php if(!empty($info['images'])): ?>
                    <div class="info-banner mb-5 rounded overflow-hidden shadow-sm">
                        <img src="<?php echo $baseurl . $info['images'][0]['file_link1']; ?>" 
                             class="w-100 object-fit-cover shadow-sm" 
                             alt="<?php echo htmlspecialchars($info['d_title'] ?? ''); ?>">
                    </div>
                <?php endif; ?>

                <div class="content post-content text-dark mb-5" style="line-height: 1.8; font-size: 1.05rem;">
                    <?php echo $info['d_content'] ?? '<p class="text-muted">目前尚未準備好相關內容資料。</p>'; ?>
                </div>

                <?php if(!empty($info['d_content2'])): ?>
                    <div class="content post-content text-muted small mt-4 pt-4 border-top">
                        <?php echo $info['d_content2']; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </div>
</div>

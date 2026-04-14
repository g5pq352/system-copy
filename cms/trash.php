<?php require_once('../Connections/connect2data.php'); ?>
<?php
// trash.php - 垃圾桶頁面 (完整修正版)
require_once realpath(__DIR__ . '/..') . '/config/config.php';
require_once 'auth.php';

$menu_is = "galleryManager";
$currentPage = $_SERVER["PHP_SELF"];

// 引入縮圖 helper
require_once __DIR__ . '/gallery/thumbs_helper.php';

// --- 設定與路徑 ---
$baseDir   = $UPLOAD_BASE;
$trashRoot = $baseDir . '/_trash';

// === 【新增】計算當前頁面 URL (用於查詢參數連結) ===
$currentPageUrl = strtok($_SERVER['REQUEST_URI'] ?? PORTAL_AUTH_URL . 'trash/', '?');

// 確保結構存在
$imgDir          = $trashRoot . '/images';
$thumbDir        = $trashRoot . '/thumbs';
$metaDir         = $trashRoot . '/meta';
$folderTrashRoot = $trashRoot . '/folders';

if (!is_dir($imgDir))  mkdir($imgDir, 0777, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);
if (!is_dir($metaDir))  mkdir($metaDir, 0777, true);
if (!is_dir($folderTrashRoot)) mkdir($folderTrashRoot, 0777, true);

// --- 邏輯判斷 ---
$viewMode = 'list'; 
$viewFolderId = $_GET['view'] ?? null;
// 安全過濾 path 參數
$currentSubPath = isset($_GET['path']) ? trim(str_replace(['..', '\\'], '', $_GET['path']), '/') : '';

$folderMeta = null;
$folderContent = [];
$breadcrumbs = [];

// =================================================================
// 模式 A: 資料夾詳細內容 (Folder Detail Mode)
// =================================================================
if ($viewFolderId) {
    // 1. 讀取該資料夾的 Meta
    $metaFile = $folderTrashRoot . '/' . $viewFolderId . '/meta.json';
    if (file_exists($metaFile)) {
        $folderMeta = json_decode(file_get_contents($metaFile), true);
    }

    if ($folderMeta) {
        $viewMode = 'folder_detail';

        // 2. 計算路徑
        $rootPhysicalPath = realpath($folderTrashRoot . '/' . $viewFolderId . '/original');
        $rootThumbPath    = $folderTrashRoot . '/' . $viewFolderId . '/thumbs';

        if ($rootPhysicalPath === false || !is_dir($rootPhysicalPath)) {
            echo "<script>alert('原始資料夾結構錯誤或已遺失'); location.href='" . PORTAL_AUTH_URL . "trash/';</script>";
            exit;
        }

        // 組合目標路徑
        $targetPath = $rootPhysicalPath;
        if ($currentSubPath) {
            $targetPath = realpath($rootPhysicalPath . '/' . $currentSubPath);
        }

        // 安全性檢查：確保沒有跳出 original 目錄
        if ($targetPath && strpos($targetPath, $rootPhysicalPath) === 0 && is_dir($targetPath)) {

            // 3. 掃描目錄
            $items = scandir($targetPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $targetPath . '/' . $item;
                $isDir = is_dir($itemPath);
                $nextSubPath = ($currentSubPath ? $currentSubPath . '/' : '') . $item;

                $fullUrl = '';
                $thumbUrl = '';

                if (!$isDir) {
                    // 計算網頁路徑
                    $rawRelativePath = "uploads/_trash/folders/$viewFolderId/original/$nextSubPath";
                    $fullUrl = implode('/', array_map('rawurlencode', explode('/', $rawRelativePath)));

                    // 檢查縮圖
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (is_image_ext($ext)) {
                        $physicalThumbFile = $rootThumbPath . '/' . $nextSubPath;
                        if (file_exists($physicalThumbFile)) {
                            $rawThumbPath = "uploads/_trash/folders/$viewFolderId/thumbs/$nextSubPath";
                            $thumbUrl = implode('/', array_map('rawurlencode', explode('/', $rawThumbPath)));
                        } else {
                            $thumbUrl = $fullUrl; 
                        }
                    }
                }

                $folderContent[] = [
                    'name' => $item,
                    'is_dir' => $isDir,
                    'sub_path' => $nextSubPath,
                    'full_url' => $fullUrl,
                    'thumb_url' => $thumbUrl,
                ];
            }

            // 排序
            usort($folderContent, function ($a, $b) {
                if ($a['is_dir'] && !$b['is_dir']) return -1;
                if (!$a['is_dir'] && $b['is_dir']) return 1;
                return strnatcasecmp($a['name'], $b['name']);
            });

            // 4. 麵包屑
            $breadcrumbs[] = ['name' => '垃圾桶首頁', 'link' => $currentPageUrl];
            $breadcrumbs[] = ['name' => basename($folderMeta['original_path'] ?? '未命名資料夾'), 'link' => $currentPageUrl . '?view=' . $viewFolderId];

            if ($currentSubPath) {
                $parts = explode('/', $currentSubPath);
                $tmpPath = '';
                foreach ($parts as $part) {
                    if ($part === '') continue;
                    $tmpPath .= ($tmpPath ? '/' : '') . $part;
                    $breadcrumbs[] = ['name' => $part, 'link' => $currentPageUrl . '?view=' . $viewFolderId . '&path=' . rawurlencode($tmpPath)];
                }
            }
        } else {
            echo "<script>alert('路徑不存在'); location.href='" . $currentPageUrl . "?view=$viewFolderId';</script>";
            exit;
        }
    } else {
        // 資料夾已被刪除或還原，靜默重定向回垃圾桶首頁
        header("Location: " . PORTAL_AUTH_URL . "trash/");
        exit;
    }
}

// =================================================================
// 模式 B: 列表模式 (List Mode)
// =================================================================
if ($viewMode === 'list') {
    // -----------------------------------------------------------
    // 1. 單張圖片 (★已修正：改為掃描實體檔案)
    // -----------------------------------------------------------
    $imgMetas = [];
    
    // 掃描 _trash/images 下的所有圖片
    $imageFiles = glob($imgDir . "/*.{jpg,jpeg,png,gif,webp,svg}", GLOB_BRACE) ?: [];
    
    foreach ($imageFiles as $file) {
        $fileName = basename($file);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        // 假設檔名就是 ID (例如 37.jpg 的 ID 為 37)
        $id = pathinfo($fileName, PATHINFO_FILENAME);
        
        // 嘗試讀取 Meta
        $jsonPath = $metaDir . '/' . $id . '.json';
        $m = [];
        
        if (file_exists($jsonPath)) {
            $content = file_get_contents($jsonPath);
            $m = json_decode($content, true);
        }
        
        // 如果 Meta 損毀或不存在，建立假資料以確保圖片顯示
        if (!$m || !is_array($m)) {
            $m = [
                'id' => $id,
                'ext' => $ext,
                'original_name' => $fileName,      // 無法得知原名，用檔名代替
                'original_path' => '位置資訊遺失',   // 提示使用者
                'is_orphan' => true                // 標記為孤兒檔案
            ];
        }
        
        // 強制校正 ID 與 Ext
        $m['id'] = $id;
        $m['ext'] = $ext;
        
        $imgMetas[] = $m;
    }

    // -----------------------------------------------------------
    // 2. 資料夾
    // -----------------------------------------------------------
    $folderMetas = [];
    if (is_dir($folderTrashRoot)) {
        $dirs = glob($folderTrashRoot . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $mf = $dir . '/meta.json';
            $origDir = $dir . '/original';

            if (!file_exists($mf) || !is_dir($origDir)) continue;

            $m = json_decode(file_get_contents($mf), true);
            if ($m) {
                // 確保 ID 存在
                if (!isset($m['id'])) {
                    $m['id'] = basename($dir);
                }

                // 計算預覽圖
                $previewImgs = [];
                $totalImgCount = 0;
                try {
                    $it = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($origDir, RecursiveDirectoryIterator::SKIP_DOTS), 
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($it as $file) {
                        if ($file->isFile() && is_image_ext($file->getExtension())) {
                            $totalImgCount++;
                            if (count($previewImgs) < 4) {
                                $relPath = $it->getSubPathName();
                                $relPath = str_replace('\\', '/', $relPath);
                                $previewImgs[] = "uploads/_trash/folders/" . basename($dir) . "/original/" . $relPath;
                            }
                        }
                    }
                } catch (Exception $e) {}

                $m['totalImgCount'] = $totalImgCount;
                $m['previewImgs'] = $previewImgs;
                $folderMetas[] = $m;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="sidebar-left-big-icons">
<head>
    <title><?php require_once('cmsTitle.php'); ?></title>
    <?php require_once('head.php'); ?>
    <?php require_once('script.php'); ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css" />
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
    <link rel="stylesheet" href="<?= APP_BACKEND_PATH ?>/gallery/style/style.css">
</head>
<body class="page-trash">
    <section class="body">
        <?php require_once('header.php'); ?>
        <div class="inner-wrapper">
            <?php require_once('sidebar.php'); ?>
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>圖片庫垃圾桶</h2>
                </header>

                <div class="app-wrapper">
                    <aside class="sidebar">
                        <h3>垃圾桶</h3>
                        <button class="nav-btn primary" onclick="location.href='<?= PORTAL_AUTH_URL ?>picture_library/'">← 回圖片庫</button>
                        <br>
                        <?php if ($viewMode === 'folder_detail') : ?>
                            <button class="nav-btn" onclick="location.href='<?= PORTAL_AUTH_URL ?>trash/'">← 回垃圾桶首頁</button>
                        <?php endif; ?>
                    </aside>

                    <main class="main">

                        <?php if ($viewMode === 'list') : ?>
                            <div class="trash-section">
                                <h2 class="main-title">🖼 已刪除圖片</h2>
                                <div class="trash-toolbar">
                                    <label><input type="checkbox" id="trash-check-all"> 全選</label>
                                    <button id="btn-restore-selected" class="btn bulk" disabled>批次還原</button>
                                    <button id="btn-delete-selected" class="btn danger" disabled>批次永久刪除</button>
                                </div>

                                <div class="card-grid">
                                    <?php if(empty($imgMetas)): ?>
                                        <p style="color:#999; padding:10px;">沒有已刪除的圖片</p>
                                    <?php else: ?>
                                        <?php foreach ($imgMetas as $meta) :
                                            $id = $meta['id'] ?? ''; 
                                            if(!$id) continue;
                                            $ext = $meta['ext'] ?? '';
                                            $origName = $meta['original_name'] ?? '未命名';
                                            $origPath = $meta['original_path'] ?? '';
                                            
                                            // 解析路徑資訊
                                            $info = pathinfo($origPath);
                                            $origDir  = ($origPath === '位置資訊遺失') ? '❓' : ($info['dirname'] ?? '');
                                            $origFile = ($origPath === '位置資訊遺失') ? $origName : ($info['basename'] ?? '');

                                            $fullUrl = $base_gallery_url . APP_FRONTEND_PATH . "/uploads/_trash/images/$id.$ext";
                                            $thumbPathCheck = __DIR__ . "/uploads/_trash/thumbs/$id.$ext"; 
                                            $showSrc = file_exists($thumbPathCheck) 
                                                ? "uploads/_trash/thumbs/$id.$ext" 
                                                : "uploads/_trash/images/$id.$ext";
                                        ?>
                                            <div class="img-card">
                                                <input type="checkbox" class="trash-check" value="<?= $id ?>">
                                                <a href="<?= htmlspecialchars($fullUrl) ?>" data-fancybox="trash-single-gallery" data-caption="<?= htmlspecialchars($origName) ?>">
                                                    <img src="<?= htmlspecialchars($base_gallery_url . APP_FRONTEND_PATH . '/' . $showSrc) ?>" loading="lazy">
                                                </a>
                                                <div class="img-name">
                                                    原始位置：<?= htmlspecialchars($origDir) ?>/<b><?= htmlspecialchars($origFile) ?></b>
                                                </div>
                                                <div class="card-actions">
                                                    <button type="button" class="btn-restore btn-trash-img-restore" data-id="<?= $id ?>">還原</button>
                                                    <button type="button" class="btn-delete-perm btn-trash-img-delete" data-id="<?= $id ?>">刪除</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="trash-section">
                                <h2 class="main-title">📁 已刪除資料夾</h2>
                                <div class="card-grid">
                                    <?php if(empty($folderMetas)): ?>
                                        <p style="color:#999; padding:10px;">沒有已刪除的資料夾</p>
                                    <?php else: ?>
                                        <?php foreach ($folderMetas as $meta) :
                                            $id = $meta['id'] ?? '';
                                            if(!$id) continue;
                                            
                                            $origPath = $meta['original_path'] ?? '';
                                            $totalImgCount = $meta['totalImgCount'] ?? 0;
                                            $previewImgs = $meta['previewImgs'] ?? [];
                                        ?>
                                            <div class="folder-card" onclick="location.href='<?= $currentPageUrl ?>?view=<?= $id ?>'">
                                                <div class="folder-header">
                                                    <div class="folder-icon">📁</div>
                                                    <div class="folder-name-group">
                                                        <div class="folder-name-title"><?= htmlspecialchars(basename($origPath)) ?></div>
                                                        <div class="folder-name-path"><?= htmlspecialchars($origPath) ?></div>
                                                    </div>
                                                </div>
                                                <div class="folder-preview">
                                                    <?php if ($totalImgCount > 0) : ?>
                                                        <?php foreach ($previewImgs as $src) : ?>
                                                            <img src="<?= htmlspecialchars($base_gallery_url . APP_FRONTEND_PATH . '/' . $src) ?>">
                                                        <?php endforeach; ?>
                                                    <?php else : ?>
                                                        <span class="no-img">無圖片</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="folder-count">共 <?= $totalImgCount ?> 張圖片</div>
                                                <div class="card-actions" onclick="event.stopPropagation()">
                                                    <button type="button" class="btn-restore btn-trash-folder-restore" data-id="<?= $id ?>">還原整包</button>
                                                    <button type="button" class="btn-delete-perm btn-trash-folder-delete" data-id="<?= $id ?>">永久刪除</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php else : ?>
                            <div class="browser-header">
                                <div class="breadcrumb">
                                    <?php foreach ($breadcrumbs as $index => $crumb) : ?>
                                        <?php if ($index > 0) echo '<span class="sep">/</span>'; ?>
                                        <?php if ($index === count($breadcrumbs) - 1) : ?>
                                            <strong><?= htmlspecialchars($crumb['name']) ?></strong>
                                        <?php else : ?>
                                            <a href="<?= $crumb['link'] ?>"><?= htmlspecialchars($crumb['name']) ?></a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <div class="browser-actions">
                                    <button class="btn-restore btn-trash-folder-restore" data-id="<?= $viewFolderId ?>">還原此資料夾</button>
                                    <button class="btn-delete-perm btn-trash-folder-delete" data-id="<?= $viewFolderId ?>">永久刪除</button>
                                </div>
                            </div>

                            <div class="detail-grid">
                                <?php if ($currentSubPath) :
                                    $parentPath = dirname($currentSubPath);
                                    $link = $currentPageUrl . '?view=' . $viewFolderId . ($parentPath === '.' ? '' : '&path=' . rawurlencode($parentPath));
                                ?>
                                    <div class="detail-item back-item">
                                        <a href="<?= $link ?>" class="card-link">
                                            <span class="detail-icon">↩️</span>
                                            <div class="detail-name">.. (上一層)</div>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($folderContent)) : ?>
                                    <p class="empty-note">此資料夾為空</p>
                                <?php endif; ?>

                                <?php foreach ($folderContent as $item) : ?>
                                    <div class="detail-item">
                                        <?php if ($item['is_dir']) : ?>
                                            <a href="<?= $currentPageUrl ?>?view=<?= $viewFolderId ?>&path=<?= rawurlencode($item['sub_path']) ?>" class="card-link">
                                                <span class="detail-icon">📁</span>
                                                <div class="detail-name"><?= htmlspecialchars($item['name']) ?></div>
                                            </a>
                                        <?php else : 
                                            $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                            $isImage = is_image_ext($ext);
                                        ?>
                                            <a href="<?= htmlspecialchars($base_gallery_url . APP_FRONTEND_PATH . '/' . $item['full_url']) ?>" 
                                               <?php if ($isImage) : ?> 
                                                   data-fancybox="trash-folder-gallery" 
                                                   data-caption="<?= htmlspecialchars($item['name']) ?>" 
                                               <?php else : ?> 
                                                   target="_blank" 
                                               <?php endif; ?> 
                                               class="card-link <?= $isImage ? 'is-image' : 'is-file' ?>">

                                                <?php if ($isImage) : ?>
                                                    <img src="<?= htmlspecialchars($base_gallery_url . APP_FRONTEND_PATH . '/' . $item['thumb_url']) ?>" class="detail-thumb" loading="lazy">
                                                <?php else : ?>
                                                    <span class="detail-icon">📄</span>
                                                <?php endif; ?>
                                            </a>
                                            <div class="detail-name"><?= htmlspecialchars($item['name']) ?></div>
                                            
                                            <div class="card-actions">
                                                <button type="button" class="btn-restore btn-trash-file-restore" data-folder-id="<?= $viewFolderId ?>" data-sub-path="<?= htmlspecialchars($item['sub_path']) ?>">還原</button>
                                                <button type="button" class="btn-delete-perm btn-trash-file-delete" data-folder-id="<?= $viewFolderId ?>" data-sub-path="<?= htmlspecialchars($item['sub_path']) ?>">刪除</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </main>
                </div>
            </section>
        </div>
    </section>
</body>
</html>

<script src="<?= APP_BACKEND_PATH ?>/gallery/js/app.js"></script>
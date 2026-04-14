<?php require_once('../Connections/connect2data.php'); ?>
<?php
require_once realpath(__DIR__ . '/..') . '/config/config.php';
require_once 'auth.php';

// trash_picker.php
// --------------------------------------------------
// 垃圾桶頁面：列表模式 (圖片/資料夾) 與 資料夾詳細瀏覽模式
// --------------------------------------------------

require_once __DIR__ . '/gallery/thumbs_helper.php'; // 需要 helper 檔案中的 $UPLOAD_BASE, $THUMBS_BASE, is_image_ext 等

// --- 設定與路徑 ---
$baseDir    = $UPLOAD_BASE; // 來自 thumbs_helper
$trashRoot = $baseDir . '/_trash';

// === 【新增】計算當前頁面 URL (用於查詢參數連結) ===
$currentPageUrl = strtok($_SERVER['REQUEST_URI'] ?? APP_BACKEND_PATH . '/trash_picker.php', '?');

// 確保結構存在
$imgDir           = $trashRoot . '/images';
$thumbDir         = $trashRoot . '/thumbs';
$metaDir          = $trashRoot . '/meta';
$folderTrashRoot = $trashRoot . '/folders';

if (!is_dir($imgDir))   mkdir($imgDir, 0777, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);
if (!is_dir($metaDir))  mkdir($metaDir, 0777, true);
if (!is_dir($folderTrashRoot)) mkdir($folderTrashRoot, 0777, true);


// --- 邏輯判斷：決定顯示「總列表」還是「資料夾內容」 ---
$viewMode = 'list'; // 預設列表模式
$viewFolderId = $_GET['view'] ?? null;
$currentSubPath = $_GET['path'] ?? ''; // 資料夾內部 (從 original/ 開始算) 的相對路徑

$folderMeta = null;
$folderContent = [];
$breadcrumbs = [];

if ($viewFolderId) {
    // 1. 讀取該資料夾的 Meta
    $metaFile = $folderTrashRoot . '/' . $viewFolderId . '/meta.json';
    if (file_exists($metaFile)) {
        $folderMeta = json_decode(file_get_contents($metaFile), true);
    }

    if ($folderMeta) {
        $viewMode = 'folder_detail';

        // 2. 計算物理路徑與安全性檢查
        // [修正點]：將根物理路徑設為 /folders/ID/original
        $rootPhysicalPath = realpath($folderTrashRoot . '/' . $viewFolderId . '/original');
        $rootThumbPath    = $folderTrashRoot . '/' . $viewFolderId . '/thumbs'; // 假設縮圖目錄結構

        if ($rootPhysicalPath === false) {
            // 如果 original 資料夾不存在，代表結構錯誤
            echo "<script>alert('原始資料夾結構錯誤'); location.href='" . $currentPageUrl . "';</script>";
            exit;
        }

        // 組合目標路徑 (根目錄 + 使用者點擊的子路徑)
        $targetPath = $rootPhysicalPath;
        if ($currentSubPath) {
            // [安全性修正]: 移除 .. 和 \ 
            $currentSubPath = str_replace(['..', '\\'], '', $currentSubPath);
            $targetPath = realpath($rootPhysicalPath . '/' . $currentSubPath);
        }

        // 安全性檢查：確保目標路徑一定是在該垃圾桶資料夾底下
        if ($targetPath && strpos($targetPath, $rootPhysicalPath) === 0 && is_dir($targetPath)) {

            // 3. 掃描目錄內容
            $items = scandir($targetPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $targetPath . '/' . $item;
                $isDir = is_dir($itemPath);

                // 計算下一層的相對路徑 (從 original/ 後開始算)
                $nextSubPath = ($currentSubPath ? $currentSubPath . '/' : '') . $item;

                $fullUrl = '';
                $thumbUrl = '';

                if (!$isDir) {
                    // 路徑結構：uploads/_trash/folders/ID/original/子路徑/檔名.ext
                    $rawRelativePath = 'uploads/_trash/folders/' . $viewFolderId . '/original/' . $nextSubPath;
                    $fullUrl = implode('/', array_map('rawurlencode', explode('/', $rawRelativePath)));

                    // 【新增縮圖路徑計算】
                    // 檢查是否為圖片
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (is_image_ext($ext)) {
                        // 假設縮圖路徑結構為 uploads/_trash/folders/ID/thumbs/子路徑/檔名.ext
                        $rawThumbPath = 'uploads/_trash/folders/' . $viewFolderId . '/thumbs/' . $nextSubPath;
                        $potentialThumbUrl = implode('/', array_map('rawurlencode', explode('/', $rawThumbPath)));

                        // 檢查縮圖的物理路徑是否存在
                        $physicalThumbPath = $rootThumbPath . '/' . $nextSubPath;

                        // 如果縮圖存在，則使用縮圖 URL；否則使用原圖 URL
                        $thumbUrl = file_exists($physicalThumbPath) ? $potentialThumbUrl : $fullUrl;
                    } else {
                        // 非圖片檔案，不顯示縮圖，使用空字串
                        $thumbUrl = '';
                    }
                }

                $folderContent[] = [
                    'name' => $item,
                    'is_dir' => $isDir,
                    'sub_path' => $nextSubPath, // 給連結/後端用
                    'full_url' => $fullUrl,     // 圖片的完整 URL
                    'thumb_url' => $thumbUrl,   // 圖片的縮圖 URL
                ];
            }

            // 排序：資料夾在前，然後是檔案
            usort($folderContent, function ($a, $b) {
                if ($a['is_dir'] && !$b['is_dir']) return -1;
                if (!$a['is_dir'] && $b['is_dir']) return 1;
                return strnatcasecmp($a['name'], $b['name']);
            });


            // 4. 建立麵包屑導航
            $breadcrumbs[] = ['name' => '垃圾桶首頁', 'link' => $currentPageUrl];
            // [修正點]：第一層麵包屑指向被刪除資料夾的名稱 (original_path 的 basename)
            $breadcrumbs[] = ['name' => basename($folderMeta['original_path']), 'link' => $currentPageUrl . '?view=' . $viewFolderId];

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
            // 路徑非法或不存在
            echo "<script>alert('路徑錯誤'); location.href='" . $currentPageUrl . "';</script>";
            exit;
        }
    }
}

// --- 列表模式下的資料讀取 ---
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
            $origDir = $dir . '/original'; // [修正點]：只掃描 original 資料夾

            if (!file_exists($mf) || !is_dir($origDir)) continue;

            $m = json_decode(file_get_contents($mf), true);
            if ($m) {
                // [修正點]：確保 ID 存在 - 從目錄名稱取得 (目錄名稱就是 ID)
                $folderId = basename($dir);
                $m['id'] = $folderId;

                // [修正點]：計算圖片數量和預覽圖，只掃描 /original
                $previewImgs = [];
                $totalImgCount = 0;

                try {
                    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($it as $file) {
                        if ($file->isFile() && is_image_ext($file->getExtension())) {
                            $totalImgCount++;
                            if (count($previewImgs) < 4) {
                                // 預覽圖路徑：uploads/_trash/folders/ID/original/子路徑
                                $previewImgs[] = str_replace('\\', '/', 'uploads/_trash/folders/' . $folderId . '/original/' . $it->getSubPathName());
                            }
                        }
                    }
                } catch (Exception $e) {
                }

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
    <div class="app-wrapper">
        <aside class="sidebar">
            <h3>垃圾桶</h3>
            <button class="nav-btn primary" onclick="location.href='<?= APP_BACKEND_PATH ?>/image_picker.php?mode=picker'">← 回圖片庫</button>
            <br>
            <?php if ($viewMode === 'folder_detail') : ?>
                <button class="nav-btn" onclick="location.href='<?= APP_BACKEND_PATH ?>/trash_picker.php'">← 回垃圾桶首頁</button>
            <?php endif; ?>
        </aside>

        <main class="main">

            <?php if ($viewMode === 'list') : ?>
                <div class="trash-section">
                    <h2 class="main-title">🖼 已刪除圖片</h2>
                    <div class="trash-toolbar">
                        <label><input type="checkbox" id="trash-check-all"> 全選</label>
                        <button id="btn-restore-selected" class="btn bulk">批次還原</button>
                        <button id="btn-delete-selected" class="btn danger">批次永久刪除</button>
                    </div>

                    <div class="card-grid">
                        <?php foreach ($imgMetas as $meta) :
                            $id = $meta['id'];
                            $ext = $meta['ext'];
                            $origName = $meta['original_name'] ?? '';
                            $origPath = $meta['original_path'] ?? '';

                            $info = pathinfo($origPath);
                            $origDir  = $info['dirname'] ?? '';
                            $origFile = $info['basename'] ?? '';

                            $fullUrl = $base_gallery_url . APP_FRONTEND_PATH . "/uploads/_trash/images/$id.$ext"; // 原圖路徑
                            // 這裡維持原圖/縮圖判斷邏輯
                            $showSrc = file_exists(__DIR__ . "/uploads/_trash/thumbs/$id.$ext")
                                ? "uploads/_trash/thumbs/$id.$ext"
                                : $fullUrl;
                        ?>
                            <div class="img-card">
                                <input type="checkbox" class="trash-check" value="<?= $id ?>">

                                <a href="<?= htmlspecialchars($fullUrl, ENT_QUOTES) ?>" data-fancybox="trash-single-gallery" data-caption="<?= htmlspecialchars($origName, ENT_QUOTES) ?>">
                                    <img src="<?= htmlspecialchars($showSrc, ENT_QUOTES) ?>" loading="lazy">
                                </a>

                                <!-- <div class="img-name"><?= htmlspecialchars($origName) ?></div> -->

                                <!-- 新增：顯示圖片原始來源 -->
                                <div class="img-name">原始位置：<?= htmlspecialchars($origDir) ?>/<b><?= htmlspecialchars($origFile) ?></b></div>

                                <div class="card-actions">
                                    <button type="button" class="btn-restore btn-trash-img-restore" data-id="<?= $id ?>">還原</button>
                                    <button type="button" class="btn-delete-perm btn-trash-img-delete" data-id="<?= $id ?>">刪除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="trash-section">
                    <h2 class="main-title">📁 已刪除資料夾</h2>
                    <p class="trash-note">點擊卡片可瀏覽內容。</p>

                    <div class="card-grid">
                        <?php foreach ($folderMetas as $meta) :
                            $id = $meta['id'];
                            $origPath = $meta['original_path'] ?? '';
                            $totalImgCount = $meta['totalImgCount'] ?? 0;
                            $previewImgs = $meta['previewImgs'] ?? [];
                        ?>
                            <div class="folder-card" onclick="location.href='<?= $currentPageUrl ?>?view=<?= $id ?>'">
                                <div>
                                    <div class="folder-header">
                                        <div class="folder-icon">📁</div>
                                        <div class="folder-name-group">
                                            <div class="folder-name-title"><?= htmlspecialchars(basename($origPath)) ?></div>
                                            <div class="folder-name-path"><?= htmlspecialchars($origPath) ?></div>
                                        </div>
                                    </div>
                                    <div class="folder-preview">
                                        <?php if ($totalImgCount > 0) : foreach ($previewImgs as $src) : ?>
                                                <img src="<?= htmlspecialchars($base_gallery_url . APP_FRONTEND_PATH . '/' . $src, ENT_QUOTES) ?>">
                                            <?php endforeach;
                                        else : ?>
                                            <span class="no-img">無圖片</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="folder-count">共 <?= $totalImgCount ?> 張圖片</div>
                                </div>
                                <div class="card-actions" onclick="event.stopPropagation()">
                                    <button type="button" class="btn-restore btn-trash-folder-restore" data-id="<?= $id ?>">還原整包</button>
                                    <button type="button" class="btn-delete-perm btn-trash-folder-delete" data-id="<?= $id ?>">永久刪除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                        <div class="detail-item">
                            <a href="<?= $link ?>" class="card-link">
                                <span class="detail-icon">↩️</span>
                                <div class="detail-name">.. (上一層)</div>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($folderContent)) : ?>
                        <p style="grid-column: 1/-1; text-align:center; color:#999; padding: 20px;">此資料夾為空</p>
                    <?php endif; ?>

                    <?php foreach ($folderContent as $item) : ?>
                        <div class="detail-item">
                            <?php if ($item['is_dir']) : ?>
                                <a href="<?= $currentPageUrl ?>?view=<?= $viewFolderId ?>&path=<?= rawurlencode($item['sub_path']) ?>" class="card-link">
                                    <span class="detail-icon">📁</span>
                                    <div class="detail-name"><?= htmlspecialchars($item['name']) ?></div>
                                </a>
                            <?php else :
                                // 判斷是否為圖片，如果不是圖片，就不給燈箱
                                $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                $isImage = is_image_ext($ext);
                            ?>
                                <a href="<?= htmlspecialchars($base_gallery_url . APP_FRONTEND_PATH . '/' . $item['full_url'], ENT_QUOTES) ?>" <?php if ($isImage) : ?> data-fancybox="trash-folder-gallery" data-caption="<?= htmlspecialchars($item['name']) ?>" <?php else : ?> target="_blank" <?php endif; ?> class="card-link <?= $isImage ? 'is-image' : 'is-file' ?>">

                                    <?php if ($isImage) : ?>
                                        <img src="<?= htmlspecialchars($base_gallery_url . APP_FRONTEND_PATH . '/' . $item['thumb_url'], ENT_QUOTES) ?>" class="detail-thumb" loading="lazy">
                                    <?php else : ?>
                                        <span class="detail-icon">📄</span>
                                    <?php endif; ?>

                                </a>
                                <div class="detail-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="card-actions">
                                    <button type="button" class="btn-restore btn-trash-file-restore" data-folder-id="<?= $viewFolderId ?>" data-sub-path="<?= htmlspecialchars($item['sub_path']) ?>">
                                        還原
                                    </button>
                                    <button type="button" class="btn-delete-perm btn-trash-file-delete" data-folder-id="<?= $viewFolderId ?>" data-sub-path="<?= htmlspecialchars($item['sub_path']) ?>" style="background-color: #dc3545;">
                                        刪除
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>
</body>
</html>

<script src="<?= APP_BACKEND_PATH ?>/gallery/js/app.js"></script>
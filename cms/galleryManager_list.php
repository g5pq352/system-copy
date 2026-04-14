<?php require_once('../Connections/connect2data.php'); ?>

<?php
require_once realpath(__DIR__ . '/..') . '/config/config.php';
require_once 'auth.php';

$menu_is = "galleryManager";
$currentPage = $_SERVER["PHP_SELF"];

// 引入 helper
require_once __DIR__ . '/gallery/thumbs_helper.php';

// --- 動態計算網站根目錄 URL ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host     = $_SERVER['HTTP_HOST'];
$dirUrl   = dirname($_SERVER['SCRIPT_NAME']);
$dirUrl   = str_replace('\\', '/', $dirUrl);
$dirUrl   = rtrim($dirUrl, '/');

$baseUrl = ($dirUrl === '/' || $dirUrl === '') ? $protocol . "://" . $host : $protocol . "://" . $host . $dirUrl;
$baseDir = $UPLOAD_BASE; 

if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);

// 建樹函數
function buildTree(string $baseDir, string $relative = ''): array
{
    $tree = [];
    $dirPath = rtrim($baseDir . ($relative ? '/' . $relative : ''), '/') . '/';
    if (!is_dir($dirPath)) return $tree;
    $entries = glob($dirPath . '*', GLOB_NOSORT) ?: [];
    foreach ($entries as $full) {
        if (!is_dir($full)) continue;
        $name = basename($full);
        if ($name === '_trash' || $name === 'thumbs') continue;
        $relPath = $relative ? $relative . '/' . $name : $name;
        $tree[] = [
            'name'     => $name,
            'path'     => $relPath,
            'children' => buildTree($baseDir, $relPath),
        ];
    }
    usort($tree, fn ($a, $b) => strcmp($a['name'], $b['name']));
    return $tree;
}

function pathExistsBase($baseDir, $rel): bool
{
    if ($rel === '') return true;
    return is_dir(rtrim($baseDir . '/' . $rel, '/') . '/');
}

// ==========================================================================
// 1. 計算當前路徑 (Current Path)
// ==========================================================================
$rawPath     = $_GET['path'] ?? '';
// 【關鍵修正 1】這裡必須做 urldecode，否則中文路徑在資料庫會查不到
$currentPath = normalize_rel_path(urldecode($rawPath));

if ($currentPath !== '' && !pathExistsBase($baseDir, $currentPath)) {
    // 雖然實體不存在，但為了讓使用者能操作刪除或修正，這裡暫不強制跳回根目錄
    // $currentPath = ''; 
}

$currentFolderName = ($currentPath === '') ? '根目錄' : basename($currentPath);

// ==========================================================================
// 2. 查詢資料庫取得 ID (遞迴查找)
// ==========================================================================
$currentFolderId = null; // 預設根目錄
$debugLog = []; // 用來追蹤為什麼查不到 ID

if ($currentPath !== '') {
    $parts = explode('/', $currentPath);
    $parentId = null;
    $validChain = true;
    
    foreach ($parts as $index => $part) {
        if ($part === '') continue;
        
        // 查詢：找名字符合 且 父 ID 符合 的資料夾
        $sql = "SELECT id FROM media_folders WHERE name = :name";
        $sql .= ($parentId === null) ? " AND (parent_id IS NULL OR parent_id = 0)" : " AND parent_id = :pid";
        
        $stmt = $conn->prepare($sql);
        $params = [':name' => $part];
        if ($parentId !== null) $params[':pid'] = $parentId;
        
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $parentId = $row['id'];
            $debugLog[] = "層級 $index ($part): 找到 ID = " . $parentId;
        } else {
            $validChain = false;
            $debugLog[] = "層級 $index ($part): ❌ 資料庫找不到紀錄 (Parent ID: " . ($parentId ?? 'NULL') . ")";
            break;
        }
    }
    
    if ($validChain) {
        $currentFolderId = $parentId;
    }
}

// ==========================================================================
// 3. 處理 AJAX
// ==========================================================================
if (isset($_GET['ajax_tree']) && $_GET['ajax_tree'] === 'true') {
    $tree = buildTree($baseDir, '');
    $rootActive = ($currentPath === '');
    $rootClasses = 'tree-node' . ($rootActive ? ' active' : '');
    $hasChildren = !empty($tree);
    
    echo '<div class="' . $rootClasses . '" data-path="">';
    echo '<span class="tree-toggle">' . ($hasChildren ? '▾' : '') . '</span>'; 
    echo '<span class="tree-label">🏠 根目錄</span>';
    echo '</div>';
    if ($hasChildren) {
        echo '<div class="tree-children" style="display:block">';
        renderTree($tree, $currentPath);
        echo '</div>';
    }
    exit;
}

if (isset($_GET['ajax_content']) && $_GET['ajax_content'] === 'true') {
    $currentAbsDir = original_abs($currentPath);
    $folders = [];
    $images  = [];

    // 取得排序參數
    $sortBy = $_GET['sort'] ?? 'date_desc';

    if (is_dir($currentAbsDir)) {
        $entries = scandir($currentAbsDir);
        foreach ($entries as $item) {
            if ($item === '.' || $item === '..' || $item === 'thumbs' || $item === '_trash') continue;
            $fullAbs = $currentAbsDir . '/' . $item;
            if (is_dir($fullAbs)) {
                $folders[] = [
                    'name' => $item,
                    'mtime' => filemtime($fullAbs)
                ];
            } else if (is_image_ext(strtolower(pathinfo($item, PATHINFO_EXTENSION)))) {
                $images[] = [
                    'name' => $item,
                    'mtime' => filemtime($fullAbs)
                ];
            }
        }
    }

    // 排序函數
    $sortFunction = function($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'name_asc':
                return strcasecmp($a['name'], $b['name']);
            case 'name_desc':
                return strcasecmp($b['name'], $a['name']);
            case 'date_asc':
                return $a['mtime'] - $b['mtime'];
            case 'date_desc':
            default:
                return $b['mtime'] - $a['mtime'];
        }
    };

    usort($folders, $sortFunction);
    usort($images, $sortFunction);

    // 保留完整資訊（包含 mtime）供 gallery_content.php 使用
    // 不再轉換回簡單陣列，而是保留關聯陣列

    require __DIR__ . '/gallery_content.php';
    exit;
}

function renderTree(array $nodes, string $currentPath, int $level = 0): void
{
    foreach ($nodes as $node) {
        // 比對時也要注意解碼問題，確保一致
        $isActive    = ($node['path'] === $currentPath);
        $hasChildren = !empty($node['children']);
        $classes     = 'tree-node' . ($isActive ? ' active' : '');
        $pathEsc     = htmlspecialchars($node['path'], ENT_QUOTES);
        $nameEsc     = htmlspecialchars($node['name'], ENT_QUOTES);

        echo '<div class="' . $classes . '" data-path="' . $pathEsc . '">';
        echo '<span class="tree-toggle">' . ($hasChildren ? '▸' : '') . '</span>';
        echo '<span class="tree-label">📁 ' . $nameEsc . '</span>';
        echo '<i class="fas fa-pen rename-icon" title="重新命名" onclick="startRename(\'' . addslashes($node['path']) . '\', \'folder\', event)"></i>'; 
        echo '<i class="fas fa-trash-alt delete-icon" title="刪除" onclick="confirmDeleteFolder(\'' . addslashes($node['path']) . '\', event)"></i>'; 
        echo '</div>';

        if ($hasChildren) {
            echo '<div class="tree-children" style="display:none">';
            renderTree($node['children'], $currentPath, $level + 1);
            echo '</div>';
        }
    }
}
$tree = buildTree($baseDir, '');
?>
<!DOCTYPE html>
<html class="sidebar-left-big-icons">
<head>
    <title><?php require_once('cmsTitle.php'); ?></title>
    <?php require_once('head.php'); ?>
    <?php require_once('script.php'); ?>

    <link rel="stylesheet" href="<?= APP_BACKEND_PATH ?>/gallery/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
</head>
<body class="page-gallery">
    <section class="body">
        <?php require_once('header.php'); ?>
        <div class="inner-wrapper">
            <?php require_once('sidebar.php'); ?>
            <section role="main" class="content-body">
                <header class="page-header">
                    <h2>圖片庫</h2>
                </header>

                <div class="app-wrapper wrapper">
                    <aside class="sidebar">
                        <div id="folder-tree">
                            <?php
                            $rootActive = ($currentPath === '');
                            $rootClasses = 'tree-node' . ($rootActive ? ' active' : '');
                            $hasChildren = !empty($tree);
                            echo '<div class="' . $rootClasses . '" data-path="">';
                            echo '<span class="tree-toggle">' . ($hasChildren ? '▾' : '') . '</span>'; 
                            echo '<span class="tree-label">🏠 根目錄</span>';
                            echo '</div>';
                            if ($hasChildren) {
                                echo '<div class="tree-children" style="display:block">';
                                renderTree($tree, $currentPath);
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <hr>
                        <button class="nav-btn secondary" onclick="location.href='<?=PORTAL_AUTH_URL?>trash'">🗑 垃圾桶</button>
                    </aside>

                    <main class="main">
                        <h2 style="padding: 20px;">資料載入中...</h2>
                    </main>
                </div>
            </section>
        </div>
    </section>
</body>
</html>

<script>
    // 關鍵修正：使用實際訪問的 URL 路徑，而不是實體檔案路徑
    window.AJAX_SCRIPT = '<?= $_SERVER['REQUEST_URI'] ? strtok($_SERVER['REQUEST_URI'], '?') : APP_BACKEND_PATH . '/galleryManager_list.php' ?>';
    window.currentPath = '<?= htmlspecialchars($currentPath, ENT_QUOTES) ?>';
    // 輸出 ID
    window.currentFolderId = <?= json_encode($currentFolderId) ?>;

    // 除錯模式：如果在 Console 看到 ID 是 null，請檢查這裡輸出的 log
    console.log("PHP ID Calculation Log:", <?= json_encode($debugLog) ?>);
    console.log("Calculated currentFolderId:", window.currentFolderId);
    console.log("AJAX Script Path:", window.AJAX_SCRIPT);

    window.baseUrl = '<?= $baseUrl ?>';
    window.PHP_UPLOAD_LIMIT = "<?= ini_get('upload_max_filesize') ?>";
    window.PHP_POST_LIMIT = "<?= ini_get('post_max_size') ?>";
</script>
<script src="<?= APP_BACKEND_PATH ?>/gallery/js/app.js"></script>
<script src="<?= APP_BACKEND_PATH ?>/gallery/js/upload.js"></script>
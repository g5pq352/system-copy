<?php require_once('../Connections/connect2data.php'); ?>
<?php
require_once realpath(__DIR__ . '/..') . '/config/config.php';
require_once 'auth.php';

// image_picker.php - 具備完整管理功能的圖片選擇器

// ----------------------------------------------------
// 1. 核心設定與依賴
// ----------------------------------------------------
require_once __DIR__ . '/gallery/thumbs_helper.php';
require_once __DIR__ . '/cms_media_helper.php';

// 雖是 Picker，但允許完整操作權限
// 若有權限驗證 (Session Check) 請放在這裡

// --- 動態計算網站根目錄 URL ---
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host     = $_SERVER['HTTP_HOST'];
$dirUrl   = dirname($_SERVER['SCRIPT_NAME']);
if ($dirUrl === '/' || $dirUrl === '\\') {
    $dirUrl = '';
} else {
    $dirUrl = str_replace('\\', '/', $dirUrl);
    $dirUrl = rtrim($dirUrl, '/');
}
$baseUrl  = $protocol . "://" . $host . $dirUrl;

$baseDir = $UPLOAD_BASE; 

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

// ----------------------------------------------------
// 2. 樹狀圖函數 (完全同步 gallery.php，保留管理功能)
// ----------------------------------------------------
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
// 【關鍵修正】這裡必須做 urldecode，否則中文路徑在資料庫會查不到
$currentPath = normalize_rel_path(urldecode($rawPath));

if ($currentPath !== '' && !pathExistsBase($baseDir, $currentPath)) {
    $currentPath = '';
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

// ----------------------------------------------------
// 3. 處理 AJAX 樹狀圖請求 (同步 gallery.php)
// ----------------------------------------------------
// 用於 app.js 刷新側邊欄時使用
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
        // 注意：這裡我們使用遞迴渲染，但需要定義 renderTree (見下方)
        // 為了避免重複定義，這裡簡單處理，或者將 renderTree 移到全域
        // 在 AJAX 請求中，我們需要在這裡定義或呼叫
        renderTree($tree, $currentPath); 
        echo '</div>';
    }
    exit;
}

// ----------------------------------------------------
// 4. 處理 AJAX 內容請求 (引入 image_picker_content.php)
// ----------------------------------------------------
if (isset($_GET['ajax_content']) && $_GET['ajax_content'] === 'true') {
    // 重新計算 folder ID (因為是 AJAX 請求)
    $currentFolderId = null;
    if ($currentPath !== '') {
        $parts = explode('/', $currentPath);
        $parentId = null;
        foreach ($parts as $part) {
            if ($part === '') continue;
            $sql = "SELECT id FROM media_folders WHERE name = :name";
            $sql .= ($parentId === null) ? " AND (parent_id IS NULL OR parent_id = 0)" : " AND parent_id = :pid";
            $stmt = $conn->prepare($sql);
            $params = [':name' => $part];
            if ($parentId !== null) $params[':pid'] = $parentId;
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $parentId = $row['id'];
            else break;
        }
        $currentFolderId = $parentId;
    }

    $currentFolderName = basename($currentPath) ?: '根目錄';
    $currentAbsDir     = rtrim($baseDir . ($currentPath ? '/' . $currentPath : ''), '/') . '/';

    // 取得排序參數
    $sortBy = $_GET['sort'] ?? 'date_desc';

    $folders = [];
    $images  = [];
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
            } else {
                // 寬鬆檢查圖片
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'])) {
                    $images[] = [
                        'name' => $item,
                        'mtime' => filemtime($fullAbs)
                    ];
                }
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

    // 保留完整資訊（包含 mtime）供 image_picker_content.php 使用

    // 【關鍵修改】引入 Picker 專用的內容模板
    require __DIR__ . '/image_picker_content.php';
    exit;
}

// ----------------------------------------------------
// 5. 輸出主 HTML
// ----------------------------------------------------
$tree = buildTree($baseDir, '');

function renderTree(array $nodes, string $currentPath, int $level = 0): void
{
    foreach ($nodes as $node) {
        $isActive    = ($node['path'] === $currentPath);
        $hasChildren = !empty($node['children']);
        $classes     = 'tree-node' . ($isActive ? ' active' : '');
        $pathEsc     = htmlspecialchars($node['path'], ENT_QUOTES);
        $nameEsc     = htmlspecialchars($node['name'], ENT_QUOTES);

        echo '<div class="' . $classes . '" data-path="' . $pathEsc . '">';
        echo '<span class="tree-toggle">' . ($hasChildren ? '▸' : '') . '</span>';
        echo '<span class="tree-label">📁 ' . $nameEsc . '</span>';
        
        // 【保留管理功能】
        echo '<i class="fas fa-pen rename-icon" title="重新命名此資料夾" onclick="startRename(\'' . addslashes($node['path']) . '\', \'folder\', event)"></i>'; 
        echo '<i class="fas fa-trash-alt delete-icon" title="刪除此資料夾" onclick="confirmDeleteFolder(\'' . addslashes($node['path']) . '\', event)"></i>'; 

        echo '</div>';

        if ($hasChildren) {
            echo '<div class="tree-children" style="display:none">';
            renderTree($node['children'], $currentPath, $level + 1);
            echo '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>選擇圖片 (可管理)</title>
    
    <?php require_once('head.php');?>

    <link rel="stylesheet" href="<?= APP_BACKEND_PATH ?>/gallery/style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* 讓圖片卡片有「可點選」的感覺 */
        .img-card { cursor: pointer; }
        .img-card:hover { border-color: #007bff; box-shadow: 0 0 8px rgba(0,123,255,0.3); }
    </style>
</head>

<body class="page-gallery">
    <div class="app-wrapper wrapper">
        <aside class="sidebar">
            <div id="folder-tree">
                <?php
                // 1. 根目錄
                $rootActive = ($currentPath === '');
                $rootClasses = 'tree-node' . ($rootActive ? ' active' : '');
                $hasChildren = !empty($tree);
                
                echo '<div class="' . $rootClasses . '" data-path="">';
                echo '<span class="tree-toggle">' . ($hasChildren ? '▾' : '') . '</span>'; 
                echo '<span class="tree-label">🏠 根目錄</span>';
                // 根目錄無刪除按鈕
                echo '</div>';

                // 2. 子資料夾
                if ($hasChildren) {
                    echo '<div class="tree-children" style="display:block">';
                    renderTree($tree, $currentPath);
                    echo '</div>';
                }
                ?>
            </div>
            
            <hr>
            <!-- <button class="nav-btn secondary" onclick="window.open('trash.php', '_blank')">🗑 垃圾桶 (新分頁)</button> -->
            <button class="nav-btn secondary" onclick="location.href='trash_picker.php'">🗑 垃圾桶</button>
        </aside>

        <main class="main">
            <h2 style="padding: 20px;">資料載入中...</h2>
        </main>
    </div>

    <script>
        // 設定全域變數供 app.js 和 upload.js 使用
        window.AJAX_SCRIPT = '<?= APP_BACKEND_PATH ?>/image_picker.php';
        window.currentPath = '<?= htmlspecialchars($currentPath, ENT_QUOTES) ?>';
        // 輸出 ID
        window.currentFolderId = <?= json_encode($currentFolderId) ?>;

        // 除錯模式：如果在 Console 看到 ID 是 null，請檢查這裡輸出的 log
        console.log("PHP ID Calculation Log:", <?= json_encode($debugLog) ?>);
        console.log("Calculated currentFolderId:", window.currentFolderId);

        window.baseUrl = '<?= $baseUrl ?>';
        window.PHP_UPLOAD_LIMIT = "<?= ini_get('upload_max_filesize') ?>";
        window.PHP_POST_LIMIT = "<?= ini_get('post_max_size') ?>";
        
        // 確保 loadFolderContent 存在，app.js 會定義它，但我們這裡可以預定義一些 Picker 邏輯
        
        // 【新增】選擇圖片並回傳 Token 格式給 CKEditor
        function selectImageWithId(mediaId, fallbackUrl) {
            if (!mediaId) {
                // 如果沒有 media ID (例如舊圖片),使用舊的 URL 方式
                console.warn('No media ID found, using fallback URL:', fallbackUrl);
                selectImage(fallbackUrl);
                return;
            }
            
            // 建立 token 格式: [media:' + mediaId + ']
            const token = '[media:' + mediaId + ']';
            
            if (window.opener) {
                // 嘗試多種 CKEditor 回調方式
                if (typeof window.opener.receiveImageFromGallery === 'function') {
                    // 自定義接收函數
                    window.opener.receiveImageFromGallery(token, mediaId, fallbackUrl);
                } else if (window.opener.CKEDITOR) {
                    // 舊版 CKEditor 4 方式
                    let funcNum = new URLSearchParams(window.location.search).get('CKEditorFuncNum');
                    window.opener.CKEDITOR.tools.callFunction(funcNum, token);
                } else {
                    // 通用：直接透過 postMessage (如果您的編輯器支援)
                    console.log('Selected media ID:', mediaId, 'Token:', token);
                }
                window.close();
            } else {
                alert('已選擇圖片 (非彈跳視窗模式):\nMedia ID: ' + mediaId + '\nToken: ' + token);
            }
        }
        
        // 【核心】選擇圖片並回傳給 CKEditor (舊版,向後相容)
        function selectImage(fullUrl) {
            if (window.opener) {
                // 嘗試多種 CKEditor 回調方式
                if (typeof window.opener.receiveImageFromGallery === 'function') {
                    // 自定義接收函數
                    window.opener.receiveImageFromGallery(fullUrl);
                } else if (window.opener.CKEDITOR) {
                    // 舊版 CKEditor 4 方式
                    let funcNum = new URLSearchParams(window.location.search).get('CKEditorFuncNum');
                    window.opener.CKEDITOR.tools.callFunction(funcNum, fullUrl);
                } else {
                    // 通用：直接透過 postMessage (如果您的編輯器支援) 或 alert
                    // 這裡假設是您目前的 setup
                    console.log('Selected:', fullUrl);
                }
                window.close();
            } else {
                alert('已選擇圖片 (非彈跳視窗模式):\n' + fullUrl);
            }
        }
    </script>

    <script src="<?= APP_BACKEND_PATH ?>/gallery/js/app.js"></script>
    <script src="<?= APP_BACKEND_PATH ?>/gallery/js/upload.js"></script>
</body>
</html>
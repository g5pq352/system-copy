<?php
require_once realpath(__DIR__ . '/../..') . '/config/config.php';

$baseDir = $rootPath . '/uploads';

// 取得頂層分類資料夾
$topFolders = [];
$entries = glob($baseDir . '/*', GLOB_NOSORT) ?: [];
foreach ($entries as $full) {
    if (!is_dir($full)) continue;
    $name = basename($full);
    if ($name === '_trash' || $name === 'thumbs') continue;
    $topFolders[] = $name;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>上傳圖片</title>
    
    <link rel="stylesheet" href="style.css">
</head>

<body class="page-upload">
    <div class="upload-wrapper">
        <div class="upload-box">
            <h2>上傳圖片</h2>

            <label>選擇分類資料夾：</label>
            <select id="folderSelect">
                <option value="">請選擇分類</option>
                <?php foreach ($topFolders as $name) : ?>
                    <option value="<?= htmlspecialchars($name, ENT_QUOTES) ?>">
                        <?= htmlspecialchars($name, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- 拖曳 + 點擊上傳區 -->
            <div id="dropArea" class="drop-area">
                點擊或拖曳圖片到這裡上傳
            </div>

            <!-- 隱藏 input -->
            <input type="file" id="fileInput" multiple accept="image/*" hidden>

            <button id="uploadBtn">開始上傳</button>

            <!-- 預覽區 -->
            <div id="preview"></div>

            <div class="link-row">
                <a href="gallery.php">← 回圖片庫</a>
            </div>
        </div>
    </div>

    <script src="upload.js"></script>
</body>

</html>
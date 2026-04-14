<?php
// gallery_upload.php - 整合到 gallery.php 的上傳介面模板
// 變數 $currentPath 和 $currentFolderName 應由 gallery.php 提供。

// 修正：在整合進去後，您不需要選擇頂層資料夾，只需要將檔案上傳到 $currentPath
// 這裡我們只顯示介面，實際的 currentPath 會透過 JS 傳遞給 upload_handler.php

?>
<div class="main-header">
    <h2 class="main-title">⬆️ 上傳至：<?= htmlspecialchars($currentFolderName, ENT_QUOTES) ?></h2>
    <div class="main-path">
        路徑：<?= $currentPath === '' ? '/' : 'uploads/' . htmlspecialchars($currentPath, ENT_QUOTES) . '/' ?>
    </div>
    <div class="mode-switch">
        <button id="switch-to-browse" class="nav-btn primary" data-path="<?= htmlspecialchars($currentPath, ENT_QUOTES) ?>">
            ← 回到瀏覽模式
        </button>
    </div>
</div>

<div class="upload-box">
    <div id="dropArea" class="drop-area">
        點擊或拖曳圖片到這裡上傳
    </div>

    <input type="file" id="fileInput" multiple accept="image/*" hidden>

    <input type="hidden" id="targetPath" value="<?= htmlspecialchars($currentPath, ENT_QUOTES) ?>">

    <button id="uploadBtn">開始上傳</button>

    <div id="preview"></div>
</div>

<script src="upload.js"></script>
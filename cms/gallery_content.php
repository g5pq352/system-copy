<?php
// gallery_content.php - 專門供 AJAX 請求的 main 區塊內容
// 這個檔案需要由 galleryManager_list.php 透過 include/require 引入。

// 1. 防呆：確保關鍵變數存在，避免 Undefined Variable 錯誤
$currentPath       = $currentPath ?? '';
$currentFolderName = $currentFolderName ?? '根目錄';
$folders           = $folders ?? [];
$images            = $images ?? [];

// 2. 防呆：確保 $currentFolderId 變數存在 (由主程式計算傳入)
if (!isset($currentFolderId)) {
    $currentFolderId = null;
}

// 3. 確保 APP_BACKEND_PATH 存在 (從 config.php 引入)
if (!defined('APP_BACKEND_PATH')) {
    define('APP_BACKEND_PATH', "/cms");
}
?>

<input type="hidden" id="current_folder_id_storage" value="<?= htmlspecialchars((string)$currentFolderId, ENT_QUOTES) ?>">

<script>
    // 提供 currentPath 給 upload.js 裡的 window.initUploadControls 使用
    window.currentPath = '<?= htmlspecialchars($currentPath, ENT_QUOTES) ?>'; 
    
    // 雙重保險：嘗試直接更新全域變數 (視瀏覽器執行策略而定)
    window.currentFolderId = <?= json_encode($currentFolderId) ?>;
    
    // 除錯訊息 (可以在 Console 看到切換後的 ID)
    console.log("AJAX Loaded: Path =", window.currentPath, ", ID =", window.currentFolderId);
    console.log("Hidden field #current_folder_id_storage value =", document.getElementById('current_folder_id_storage')?.value);
</script>

<div class="main-header">
    <div class="header-left">
        <h2 class="main-title">📁 <?= htmlspecialchars($currentFolderName, ENT_QUOTES) ?></h2>
        <div class="main-path">
            路徑：<?= $currentPath === '' ? '/' : 'uploads/' . htmlspecialchars($currentPath, ENT_QUOTES) . '/' ?>
        </div>
    </div>

    <div class="sort-controls">
        <label for="sortBy">排序：</label>
        <select id="sortBy" class="sort-select">
            <option value="name_asc">名稱 (A-Z)</option>
            <option value="name_desc">名稱 (Z-A)</option>
            <option value="date_asc">建立日期 (舊→新)</option>
            <option value="date_desc" selected>建立日期 (新→舊)</option>
        </select>
    </div>
</div>

<button class="nav-btn upload" id="openUploadModalBtn">
    <i class="fas fa-cloud-upload-alt"></i> 上傳圖片
</button>

<div id="uploadModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close-btn" id="closeUploadModalBtn">&times;</span>
        <h3>上傳圖片到此資料夾 (<?= htmlspecialchars($currentFolderName, ENT_QUOTES) ?>)</h3>

        <div class="upload-container">
            <div id="dropArea" class="drop-area">
                <i class="fas fa-cloud-upload-alt"></i>
                點擊或拖曳圖片到這裡上傳 (僅限圖片)
            </div>

            <input type="file" id="fileInput" multiple accept="image/*" hidden>

            <div id="fileListContainer" class="file-list-container"></div>

            <div class="upload-actions">
                <button id="uploadBtn" class="primary"><i class="fas fa-upload"></i> 開始上傳</button>
                <button id="cancelAllBtn" class="secondary"><i class="fas fa-times-circle"></i> 取消所有</button>
            </div>
        </div>
    </div>
</div>

<form class="new-sub-folder-form" action="<?= APP_BACKEND_PATH ?>/gallery/add_folder.php" method="post">
    <input type="text" name="folder_name" placeholder="在此新增子資料夾" required>

    <input type="hidden" name="parent_path" value="<?= htmlspecialchars($currentPath, ENT_QUOTES) ?>">

    <input type="hidden" name="parent_id" value="<?= htmlspecialchars((string)$currentFolderId, ENT_QUOTES) ?>">

    <button type="submit">新增</button>
</form>

<div class="bulk-actions">
    <label><input type="checkbox" id="check-all"> 全選</label>
    <button class="bulk-delete-btn" id="bulk-delete-btn" disabled>刪除已選取項目</button>
</div>

<div class="card-grid">

    <?php if (!empty($folders)): ?>
        <?php foreach ($folders as $folder) : ?>
            <?php
            // 支援兩種格式：關聯陣列或純字串
            $folderName = is_array($folder) ? $folder['name'] : $folder;
            $folderMtime = is_array($folder) ? $folder['mtime'] : 0;

            $fullRel    = $currentPath === '' ? $folderName : $currentPath . '/' . $folderName;
            $fullRelEsc = htmlspecialchars($fullRel, ENT_QUOTES);
            ?>
            <div class="folder-card" data-path="<?= $fullRelEsc ?>" data-mtime="<?= $folderMtime ?>">
                <i class="fas fa-folder" style="font-size: 3em; color: #ffc107;"></i>
                <div class="folder-name"><?= htmlspecialchars($folderName, ENT_QUOTES) ?></div>
                <div class="card-actions">
                    <button type="button" class="btn-rename btn-folder-rename" data-path="<?= $fullRelEsc ?>" title="重新命名">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-delete btn-folder-delete" data-path="<?= $fullRelEsc ?>" title="刪除">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($images)): ?>
        <?php foreach ($images as $image) : ?>
            <?php
            // 支援兩種格式：關聯陣列或純字串
            $img = is_array($image) ? $image['name'] : $image;
            $imgMtime = is_array($image) ? $image['mtime'] : 0;

            $fileRel    = $currentPath === '' ? $img : $currentPath . '/' . $img;
            $fileRelEsc = htmlspecialchars($fileRel, ENT_QUOTES);

            // 處理 URL 編碼 (支援中文檔名)
            $relParts     = explode('/', $fileRel);
            $urlRelPath = implode('/', array_map('rawurlencode', $relParts));

            // 縮圖與原圖路徑
            $bgUrl = $base_gallery_url ?? '';
            $feUrl = APP_FRONTEND_PATH;

            $thumbUrl = $bgUrl . $feUrl . '/uploads/thumbs/' . $urlRelPath;
            $fullUrl  = $bgUrl . $feUrl . '/uploads/' . $urlRelPath;
            ?>
            <div class="img-card selectable" data-path="<?= $fileRelEsc ?>" data-mtime="<?= $imgMtime ?>">
                <input type="checkbox" class="img-check" data-path="<?= $fileRelEsc ?>">

                <a href="<?= htmlspecialchars($fullUrl, ENT_QUOTES) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($img, ENT_QUOTES) ?>">
                    <img src="<?= htmlspecialchars($thumbUrl, ENT_QUOTES) ?>" alt="" loading="lazy">
                </a>

                <div class="img-name"><?= htmlspecialchars($img, ENT_QUOTES) ?></div>

                <div class="card-actions">
                    <button type="button" class="btn-rename btn-img-rename" data-path="<?= $fileRelEsc ?>" title="重新命名">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-delete btn-img-delete" data-path="<?= $fileRelEsc ?>" title="刪除">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <button type="button" class="btn-copy btn-copy-link" data-fullurl="<?= htmlspecialchars($fullUrl, ENT_QUOTES) ?>" title="複製連結">
                        <i class="fas fa-link"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (empty($folders) && empty($images)): ?>
        <p style="color: #999; grid-column: 1 / -1; text-align: center; padding: 40px;">
            此資料夾是空的，請上傳圖片或新增子資料夾。
        </p>
    <?php endif; ?>

</div>
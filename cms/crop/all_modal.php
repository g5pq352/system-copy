<?php ob_start(); ?>
<link rel="stylesheet" href="<?=APP_BASE_URL?>/cms/jquery/cropper/cropper.min.css" />
<script src="<?=APP_BASE_URL?>/cms/jquery/cropper/cropper.min.js"></script>
<script src="<?=APP_BASE_URL?>/cms/js/sweetalert2@11.js"></script>
<script src="<?=APP_BASE_URL?>/cms/js/pako.min.js"></script>
<script src="<?=APP_BASE_URL?>/cms/js/UPNG.min.js"></script>
<link rel="stylesheet" href="<?=APP_BASE_URL?>/cms/crop/crop.css" />

<template id="universalRowTemplate">
    <tr>
        <td>
            <div style="display: flex; align-items: flex-start; margin-bottom: 10px; position:relative;">
                <!-- 拖曳把手 (預設隱藏，由 JS 決定顯示) -->
                <div class="drag-handle" style="margin-right:10px; cursor:move; color:#ccc; display:none;" title="排序"><i class="fas fa-grip-vertical"></i></div>
                
                <div style="width:100px; height:100px; margin-right: 15px; border: 1px solid #ddd; overflow: hidden;">
                    <img class="preview-img" src="crop/demo.jpg" style="width:100%; height:100%; object-fit: cover;">
                </div>
                
                <div>
                    <div style="display: flex; flex-direction: column; gap: 5px;">
                        <input type="file" class="hidden-file-input" accept="image/*" style="display:none;" />
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" class="trigger-crop-btn btn btn-default">選擇檔案</button>
                            <a href="javascript:void(0)" class="trash-btn" style="color:#666; font-size:16px;" title="刪除整列"><i class="fas fa-trash-alt"></i></a>
                        </div>
                        <a href="javascript:void(0)" class="delete-row-btn" style="color:red; text-decoration:none; font-size:14px; margin-top:5px; display:none;"><i class="fas fa-times-circle"></i> 移除</a>
                    </div>
                    
                    <div style="margin-top: 5px;">
                        <p class="file-name-display" style="display:none; font-size:0.9rem; color:#555;margin: 0;">未選擇</p>
                        <p class="status-msg" style="font-size:0.9rem; color:blue; margin:5px 0 0 0;"></p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; margin-top: 5px;">
                <span class="table_data" style="flex-shrink:0;">圖片說明：</span>
                <input type="text" class="title-input table_data" style="width: 300px; padding: 4px; border: 1px solid #ccc;">
            </div>
            <input type="hidden" class="url-input">
            <!-- <hr class="row-separator" style="border:0; border-top:1px solid #eee; margin:15px 0; display:none;"> -->
        </td>
    </tr>
</template>

<div id="cropModal" class="modal-crop">
    <div class="modal-content">
        <h2>圖片裁切</h2>
        <p>最低尺寸: <span id="minDimensions"></span> | 目前尺寸: <span id="currentDimensions" style="font-weight:bold;">0 x 0</span></p>
        <div class="img-container"><img id="imageToCrop" src=""></div>
        <div id="cropProgressMsg" style="display:none; text-align:center; color:#007bff; font-weight:bold; margin: 10px 0;">⏳ 圖片裁切中，請稍候...</div>
        <div style="text-align: center; margin-top: 15px;">
            <button id="confirmCropBtn" class="crop-action-btn btn-green">✅ 確認</button>
            <button id="forceCropBtn" class="crop-action-btn btn-red">⚠️ 強制</button>
            <button id="cancelCropBtn" class="crop-action-btn btn-gray">取消</button>
        </div>
    </div>
</div>

<script>
    // --- (A) 全域變數設定 ---
    if (typeof window.GLOBAL_IMG_CONFIG === 'undefined') {
        window.GLOBAL_IMG_CONFIG = <?php echo json_encode($imagesSize); ?> || {};
    }
    window.uploaders = window.uploaders || {}; // 儲存所有的 ImageUploader 實例
    window.globalCounter = window.globalCounter || 0;

    // --- (B) ImageUploader 類別定義 ---
    class ImageUploader {
        constructor(idSuffix, minWidth, minHeight, aspectRatio, outputWidth, outputHeight, prefix = 'image', maxSize = 2) {
            this.id = idSuffix;
            this.minWidth = minWidth;
            this.minHeight = minHeight;
            this.prefix = prefix; // 保存前綴，上傳時使用
            this.maxSize = maxSize; // 單位 MB
            
            // 比例設定
            if (outputWidth > 0 && outputHeight > 0) {
                this.aspectRatio = outputWidth / outputHeight;
            } else {
                this.aspectRatio = null; 
            }
            
            this.outputWidth = outputWidth;
            this.outputHeight = outputHeight;
            
            this.croppedBlob = null; // 儲存裁切後的 Blob
            this.currentPreviewUrl = null;

            // 綁定 DOM 元素
            const inputId = `${prefix}_${this.id}`; 
            this.fileInput = document.getElementById(inputId);
            
            if (!this.fileInput) {
                console.error(`Uploader Error: 找不到 ID 為 ${inputId} 的 input`);
                return;
            }

            this.triggerBtn = document.querySelector(`.trigger-crop-btn[data-target="${inputId}"]`);
            this.fileNameDisplay = document.getElementById('fileNameDisplay' + this.id);
            this.croppedImagePreview = document.getElementById('croppedImagePreview' + this.id);
            this.uploadStatus = document.getElementById('uploadStatus' + this.id);
            this.imageUrlInput = document.getElementById('imageUrl' + this.id);
            // 嘗試多種方式找到移除按鈕 (支援現有圖片和新增圖片)
            this.removeBtn = document.getElementById('remove_btn_' + this.id) || document.getElementById('removeBtn' + this.id);
            // 嘗試找到圖片說明輸入框（支援現有圖片和新增圖片）
            this.titleInput = document.getElementById('title_' + this.id) || document.getElementById('title_ex_' + this.id);

            // ⭐ 判斷是否為已存在的圖片（ex_ 開頭的 ID 表示已存在）
            this.isExistingImage = inputId.includes('_ex_');

            // 綁定 Modal 元素
            this.modal = document.getElementById('cropModal');
            this.imageToCrop = document.getElementById('imageToCrop');
            this.confirmBtn = document.getElementById('confirmCropBtn');
            this.forceBtn = document.getElementById('forceCropBtn');
            this.cancelBtn = document.getElementById('cancelCropBtn');
            this.minDimensionsSpan = document.getElementById('minDimensions');
            this.currentDimensionsSpan = document.getElementById('currentDimensions');
            
            this.cropper = null;
            this.bindEvents();
        }

        bindEvents() {
            if (this.triggerBtn) this.triggerBtn.addEventListener('click', () => this.fileInput.click());
            this.fileInput.addEventListener('change', this.handleFileChange.bind(this));
            
            // Modal 事件 (綁定全域按鈕到當前實例)
            // 注意：這裡使用 onclick 覆寫，確保同一時間只有當前的 uploader 控制按鈕
            // 或者在 handleFileChange 時設定 data-current-instance-id
            
            if (this.removeBtn) this.removeBtn.addEventListener('click', this.reset.bind(this));
        }

        reset() {
            this.croppedBlob = null;

            // ⭐ 彻底清空 file input（使用 DataTransfer 确保真正清空）
            const emptyTransfer = new DataTransfer();
            this.fileInput.files = emptyTransfer.files;
            this.fileInput.value = '';

            this.imageUrlInput.value = '';
            this.fileNameDisplay.style.display = 'none';
            this.fileNameDisplay.textContent = '未選擇檔案';
            this.uploadStatus.textContent = '';

            // ⭐ 清空圖片說明輸入框
            if (this.titleInput) {
                this.titleInput.value = '';
            }

            if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
            this.croppedImagePreview.src = 'crop/demo.jpg';

            if (this.removeBtn) this.removeBtn.style.display = 'none';

            // ⭐ 如果是已存在的圖片，標記為刪除
            if (this.isExistingImage) {
                // 從 id 中提取真正的 file_id (例如 'ex_123' -> '123')
                const fileId = this.id.toString().replace('ex_', '');
                // 檢查是否已經標記過
                if (fileId && $('#del_input_' + fileId).length === 0) {
                    // 直接添加到刪除列表，不調用 markImageForDeletion（避免循環）
                    $('#delete_file_container').append(
                        '<input type="hidden" name="delete_file[]" value="' + fileId + '" id="del_input_' + fileId + '">'
                    );
                }
            }
        }

        handleFileChange(e) {
            const files = e.target.files;
            if (!files || files.length === 0) return;

            const file = files[0];
            
            // 檢查檔案大小 (防呆機制)
            const fileSizeMB = file.size / (1024 * 1024);
            if (fileSizeMB > this.maxSize) {
                Swal.fire({
                    icon: 'error',
                    title: '檔案太大了',
                    text: `該圖片大小為 ${fileSizeMB.toFixed(2)}MB，超過了限制的 ${this.maxSize}MB。`
                });
                this.reset();
                return;
            }

            // --- ⭐ 新增這行：記錄原始檔案的類型 (image/png, image/jpeg 等) ⭐ ---
            this.currentFileType = file.type; 

            this.fileNameDisplay.style.display = 'inline';
            this.fileNameDisplay.textContent = file.name;
            this.uploadStatus.textContent = '圖片載入中...';
            this.croppedBlob = null; // 重置舊的裁切

            const reader = new FileReader();
            reader.onload = (event) => {
                this.initCropper(event.target.result);
            };
            reader.readAsDataURL(file); 
            
            // 標記目前正在操作的實例 ID 到按鈕上
            this.confirmBtn.dataset.currentInstanceId = this.id;
            this.forceBtn.dataset.currentInstanceId = this.id; 
        }

        initCropper(imageSrc) {
            if (this.cropper) {
                this.cropper.destroy();
            }
            this.imageToCrop.src = imageSrc;
            
            // 顯示文字
            let ratioText = this.aspectRatio ? ` (比例 ${this.outputWidth}:${this.outputHeight})` : ` (比例自由)`;
            this.minDimensionsSpan.textContent = `${this.minWidth} x ${this.minHeight}` + ratioText;

            // 綁定 Modal 按鈕事件
            this.confirmBtn.onclick = () => this.handleStrictCrop();
            this.forceBtn.onclick = () => this.handleForceCrop();
            this.cancelBtn.onclick = () => this.closeModal();

            this.modal.style.display = 'flex'; 

            const cropperOptions = {
                aspectRatio: this.aspectRatio || NaN, 
                viewMode: 1, 
                autoCropArea: 1, // 設定為 1 表示預設盡量填滿
                ready: () => {
                    // --- ⭐ 修改開始：自動最大化裁切框邏輯 ⭐ ---
                    const imgData = this.cropper.getImageData();
                    const imgW = imgData.naturalWidth;
                    const imgH = imgData.naturalHeight;

                    // 如果有設定比例限制
                    if (this.aspectRatio) {
                        // 1. 先嘗試以「圖片高度」為基準，算出對應寬度
                        let finalW = imgH * this.aspectRatio;
                        let finalH = imgH;

                        // 2. 如果算出來的寬度比圖片還寬，那就改以「圖片寬度」為基準
                        if (finalW > imgW) {
                            finalW = imgW;
                            finalH = imgW / this.aspectRatio;
                        }

                        // 3. 計算置中座標
                        const startX = (imgW - finalW) / 2;
                        const startY = (imgH - finalH) / 2;

                        // 4. 設定裁切框
                        this.cropper.setData({
                            x: startX,
                            y: startY,
                            width: finalW,
                            height: finalH
                        });
                    } else {
                        // 如果沒有比例限制，直接全選整張圖
                        this.cropper.setData({
                            x: 0,
                            y: 0,
                            width: imgW,
                            height: imgH
                        });
                    }
                    // --- ⭐ 修改結束 ⭐ ---
                },
                crop: (event) => {
                    const w = Math.round(event.detail.width);
                    const h = Math.round(event.detail.height);
                    this.currentDimensionsSpan.textContent = `${w} x ${h}`;
                    this.currentDimensionsSpan.style.color = (w < this.minWidth || h < this.minHeight) ? 'red' : 'green';
                }
            };
            this.cropper = new Cropper(this.imageToCrop, cropperOptions);
        }

        handleStrictCrop() {
            if (!this.cropper) return;
            const data = this.cropper.getData();
            if (Math.round(data.width) < this.minWidth || Math.round(data.height) < this.minHeight) {
                Swal.fire({ icon: 'error', title: '尺寸不足', text: '請調整範圍或使用強制裁切' });
                return; 
            }
            this._processCropAndClose('嚴格裁切');
        }

        handleForceCrop() {
            if (!this.cropper) return;
            this._processCropAndClose('強制裁切');
        }

        _processCropAndClose(modeName) {
            this.uploadStatus.textContent = `處理中...`;
            this.confirmBtn.disabled = true;

            const cropData = this.cropper.getData();
            let finalWidth = Math.round(cropData.width);
            let finalHeight = Math.round(cropData.height);

            const MAX_WIDTH = 2560; 
            const MIN_WIDTH = this.outputWidth; 

            // 3. 計算最終輸出尺寸
            if (finalWidth > MAX_WIDTH) {
                finalWidth = MAX_WIDTH;
                if (this.aspectRatio) {
                    finalHeight = Math.round(finalWidth / this.aspectRatio);
                } else {
                    finalHeight = Math.round(cropData.height * (MAX_WIDTH / cropData.width));
                }
            } 
            // 註解掉最小寬度強制放大的邏輯，避免 1.74MB PNG 裁切後膨脹成 2.2MB
            else if (finalWidth < MIN_WIDTH) {
                finalWidth = MIN_WIDTH;
                if (this.aspectRatio) {
                    finalHeight = Math.round(finalWidth / this.aspectRatio);
                } else {
                    finalHeight = Math.round(cropData.height * (MIN_WIDTH / cropData.width));
                }
            } 
            
            // 設定 Canvas 背景處理
            const canvasOptions = {
                width: finalWidth,
                height: finalHeight,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            };

            // 如果原始格式就不支援透明度，直接給白底
            // 如果原始格式就不支援透明度 (JPEG/BMP等)，才給白底；PNG/GIF/SVG 則保留透明能力
            if (this.currentFileType !== 'image/png' && this.currentFileType !== 'image/gif' && this.currentFileType !== 'image/svg+xml') {
                canvasOptions.fillColor = '#fff';
            }

            let croppedCanvas = this.cropper.getCroppedCanvas(canvasOptions);

            // --- ⭐ 智慧型格式選擇：SVG 轉 PNG，其餘維持原樣 ⭐ ---
            let outputMimeType = this.currentFileType; 
            if (outputMimeType === 'image/svg+xml') {
                outputMimeType = 'image/png'; // SVG 裁切後轉為 PNG
            } else if (outputMimeType !== 'image/png' && outputMimeType !== 'image/jpeg' && outputMimeType !== 'image/gif') {
                outputMimeType = 'image/jpeg'; // 只有非標準格式才預設轉 JPEG
            }
            
            // 如果是 JPEG 或非透明格式，我們在畫布繪製前已經補過白底了

            // 5. 轉成 Blob (JPEG 設為 0.92 品質)
            if (outputMimeType === 'image/jpeg') {
                const quality = 0.92;
                croppedCanvas.toBlob((blob) => {
                    this._handleCroppedBlob(blob, finalWidth, finalHeight, outputMimeType);
                }, outputMimeType, quality);
            } else {
                // PNG / GIF (或 SVG 轉來的 PNG) 使用 UPNG.js 進行高效壓縮
                const progressMsg = document.getElementById('cropProgressMsg');
                if (progressMsg) progressMsg.style.display = 'block';
                this.confirmBtn.disabled = true;

                // 使用 setTimeout 確保讓 UI 先渲染「處理中」的文字，再進行耗時壓縮
                setTimeout(() => {
                    try {
                        const ctx = croppedCanvas.getContext('2d');
                        const imgData = ctx.getImageData(0, 0, croppedCanvas.width, croppedCanvas.height);
                        // UPNG.encode(rgba_buffers, width, height, cnum)
                        // ⭐ 調整壓縮比例：cnum = 0 為無損壓縮；cnum = 256 為 256 色有損壓縮 (檔案會變小非常多)
                        const buffer = UPNG.encode([imgData.data.buffer], croppedCanvas.width, croppedCanvas.height, 0);
                        const blob = new Blob([buffer], { type: 'image/png' });
                        this._handleCroppedBlob(blob, finalWidth, finalHeight, 'image/png');
                    } catch (e) {
                        console.warn('UPNG 壓縮失敗，改用原封不動儲存:', e);
                        croppedCanvas.toBlob((blob) => {
                            this._handleCroppedBlob(blob, finalWidth, finalHeight, outputMimeType);
                        }, outputMimeType);
                    } finally {
                        if (progressMsg) progressMsg.style.display = 'none';
                    }
                }, 100);
            }
        }

        // 抽取共同的 Blob 處理邏輯
        _handleCroppedBlob(blob, finalWidth, finalHeight, outputMimeType) {
            this.confirmBtn.disabled = false;
            if (!blob) { this.uploadStatus.textContent = '❌ 錯誤'; return; }

            this.croppedBlob = blob;
            
            // 將 Blob 轉換成 File 並替換 input 的值
            const timestamp = Date.now();
            const extension = outputMimeType === 'image/png' ? 'png' : (outputMimeType === 'image/gif' ? 'gif' : 'jpg');
            const fileName = `cropped_${timestamp}.${extension}`;
            
            const croppedFile = new File([blob], fileName, { 
                type: outputMimeType,
                lastModified: timestamp
            });
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(croppedFile);
            this.fileInput.files = dataTransfer.files;
            
            this.imageUrlInput.value = 'BLOB_READY'; 
            
            const sizeKB = (blob.size / 1024).toFixed(0);
            this.uploadStatus.textContent = `✅ 已準備 (${finalWidth}x${finalHeight}, ${sizeKB} KB)`;
            
            if (this.removeBtn) this.removeBtn.style.display = 'inline-block';

            if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
            this.currentPreviewUrl = URL.createObjectURL(blob);
            this.croppedImagePreview.src = this.currentPreviewUrl;
            this.croppedImagePreview.style.display = 'block';
            this.fileNameDisplay.style.display = 'none';
            this.uploadStatus.style.display = 'none';

            this.closeModal();

            // ⭐ 觸發自定義事件，讓外部知道圖片已更新
            $(this.fileInput).trigger('imageUpdated', [this.id]);
        }
        
        
        closeModal() {
            this.modal.style.display = 'none';
            if (!this.croppedBlob) this.reset();
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
        }
    }

    // --- (C) 初始化與動態新增邏輯 ---
    function addDynamicField(tbodyId, prefix, configKey, isRemovable = true) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;

        // 防呆:檢查當前 group 的上一張未完成不給新增 (可選)
        if (isRemovable) {
            // 找出當前 tbody 中所有的 file input
            const currentGroupInputs = tbody.querySelectorAll('.hidden-file-input');
            if (currentGroupInputs.length > 0) {
                // 取得最後一個 input 的 ID
                const lastInput = currentGroupInputs[currentGroupInputs.length - 1];
                const lastInputId = lastInput.id;
                
                // 從 ID 中提取數字部分 (例如 'imageCover_1' -> '1')
                const match = lastInputId.match(/_(\d+)$/);
                if (match) {
                    const lastUploaderId = parseInt(match[1]);
                    const lastUploader = window.uploaders[lastUploaderId];
                    
                    // 檢查最後一個 uploader 是否已完成
                    if (lastUploader && !lastUploader.croppedBlob && !lastUploader.imageUrlInput.value) {
                        Swal.fire({ icon: 'warning', title: '無法新增', text: '請先完成上一張圖片' });
                        return;
                    }
                }
            }
        }

        const cfg = GLOBAL_IMG_CONFIG[configKey]; 
        if (!cfg) return;

        window.globalCounter++;
        const id = window.globalCounter;

        // 複製模板
        const template = document.getElementById('universalRowTemplate');
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('tr');
        row.id = `row_container_${id}`;
        
        const uniqueKey = `${prefix}_${id}`; 

        // 設定 ID 與 Name
        const fileIn = clone.querySelector('.hidden-file-input');
        fileIn.id = uniqueKey;
        // name 設為 array 格式，讓 PHP 可以接收
        // 但其實用 JS FormData 送出時，我們會手動處理，這裡設著是為了保險或 debug
        fileIn.name = `${prefix}[]`; 
        fileIn.classList.add('new-image-input');

        const titleIn = clone.querySelector('.title-input');
        titleIn.id = `title_${id}`;
        titleIn.name = `${prefix}_title[]`; // 確保名稱與後端對齊 (例如 d_pic_title[])

        const urlIn = clone.querySelector('.url-input');
        urlIn.id = `imageUrl${id}`;
        
        const cropBtn = clone.querySelector('.trigger-crop-btn');
        cropBtn.dataset.target = uniqueKey;
        
        const dragHandle = clone.querySelector('.drag-handle');
        // const separator = clone.querySelector('.row-separator');

        // 如果是多圖模式，才顯現把手/分隔線
        const isMultiple = (tbody.dataset.multiple === '1');
        if (isMultiple) {
            if (dragHandle) dragHandle.style.display = 'block';
            // if (separator) separator.style.display = 'block';
        }

        clone.querySelector('.file-name-display').id = `fileNameDisplay${id}`;
        clone.querySelector('.preview-img').id = `croppedImagePreview${id}`;
        clone.querySelector('.status-msg').id = `uploadStatus${id}`;

        // 設置移除按鈕的 ID
        const templateRemoveBtn = clone.querySelector('.delete-row-btn');
        if (templateRemoveBtn) {
            templateRemoveBtn.id = `removeBtn${id}`;
        }

        // 垃圾桶按鈕：刪除整列
        const trashBtn = clone.querySelector('.trash-btn');
        if (!isRemovable && trashBtn) {
            trashBtn.style.display = 'none';
        } else if (trashBtn) {
            trashBtn.onclick = () => deleteRow(id);
        }

        // ⭐ 關鍵修正：先將元素添加到 DOM，再實例化 ImageUploader
        tbody.appendChild(clone);

        // 實例化 ImageUploader (此時元素已在 DOM 中)
        const maxSize = parseInt(tbody.dataset.maxSize) || 2;
        const uploader = new ImageUploader(
            id, cfg.IW, cfg.IH, null, cfg.OW || cfg.IW, cfg.OH || cfg.IH, prefix, maxSize
        );
        window.uploaders[id] = uploader;

        // 移除連結 (紅叉)：清空內容
        if (templateRemoveBtn) {
            templateRemoveBtn.onclick = () => uploader.reset();
        }
    }

    function deleteRow(id) {
        Swal.fire({
            title: '確定刪除?', icon: 'warning', showCancelButton: true, confirmButtonText: '刪除'
        }).then((result) => {
            if (result.isConfirmed) {
                const row = document.getElementById(`row_container_${id}`);
                if (row) row.remove();
                if (window.uploaders[id]) delete window.uploaders[id];
            }
        })
    }

    // --- (D) 🚀 送出表單的核心邏輯 (替換原本的檔案，使用傳統 Submit) ---
    function handleSubmit(e) {
        // 1. 先暫停送出，讓我們有時間處理圖片
        e.preventDefault();

        const submitBtn = e.target;
        // 【修正】先嘗試從 button 的 form 屬性找，再用 closest
        let myForm = submitBtn.form || submitBtn.closest('form');
        
        // 【修正】如果還是找不到，嘗試用 ID 找（通常是 form1）
        if (!myForm) {
            myForm = document.getElementById('form1') || document.querySelector('form');
        }

        if (!myForm) {
            Swal.fire('錯誤', '找不到 <form> 標籤', 'error');
            return;
        }

        // // 2. 顯示處理中的訊息 (因為轉檔需要一點點時間)
        // Swal.fire({
        //     title: '處理中...',
        //     text: '正在送出',
        //     allowOutsideClick: false,
        //     didOpen: () => { Swal.showLoading(); }
        // });

        // 使用 setTimeout 讓 UI 有機會渲染 Loading，避免畫面卡頓
        setTimeout(() => {
            try {
                // 3. 遍歷所有的圖片上傳器
                const uploaders = Object.values(window.uploaders);
                if (uploaders.length === 0) {
                    myForm.submit();
                    return;
                }

                uploaders.forEach(uploader => {
                    // 只要該 input 還在 DOM 中 (沒被整列刪除)
                    if (uploader.fileInput && document.body.contains(uploader.fileInput)) {

                        // 🔥 關鍵邏輯：如果有裁切後的圖片 (Blob)
                        if (uploader.croppedBlob) {

                            // (A) 建立一個新檔案物件
                            const fileName = `${uploader.prefix}_${uploader.id}.jpg`;
                            const file = new File([uploader.croppedBlob], fileName, { type: "image/jpeg", lastModified: new Date().getTime() });

                            // (B) 使用 DataTransfer 模擬檔案拖放行為
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);

                            // (C) ⭐ 強制覆蓋 ⭐
                            // 把原本 input 裡面的「原圖」換成「裁切後的圖」
                            uploader.fileInput.files = dataTransfer.files;

                            console.log(`ID: ${uploader.id} 已替換為裁切圖片`);
                        } else {
                            // ⭐ 如果沒有 croppedBlob，確保清空 file input
                            // 這是保險機制，防止 reset() 沒有完全清空
                            const emptyTransfer = new DataTransfer();
                            uploader.fileInput.files = emptyTransfer.files;
                            console.log(`ID: ${uploader.id} 已清空（無圖片）`);
                        }
                    }
                });

                // 4. 全部替換完成後，執行傳統表單送出 (會跳頁)
                console.log('全部圖片處理完成，準備送出...');
                myForm.submit();

            } catch (error) {
                console.error(error);
                Swal.fire('錯誤', '圖片處理失敗，請重新嘗試', 'error');
            }
        }, 100);
    }

    // 全域綁定 送出按鈕
    $(document).ready(function() {
        $(document).on('click', '#submitBtn', function(e) {
            // 如果是回收桶模式則不處理 (已有 onsubmit="return false;")
            if ($(this).closest('form').attr('onsubmit') === 'return false;') return;
            handleSubmit(e);
        });
    });
</script>
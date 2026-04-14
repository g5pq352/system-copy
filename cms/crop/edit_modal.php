<script>
    // 1. 設定 (PHP 傳值)
    const GLOBAL_IMG_CONFIG = <?php echo json_encode($imagesSize); ?>;
    
    // 優先使用 PHP 變數（從 image_edit.php 傳入）
    const phpWidth = <?php echo isset($IWidth) ? intval($IWidth) : 0; ?>;
    const phpHeight = <?php echo isset($IHeight) ? intval($IHeight) : 0; ?>;
    
    console.log('PHP Width:', phpWidth, 'PHP Height:', phpHeight);
    
    // 修正：確保 $type 變數存在且有效
    const configType = '<?php 
        if (isset($type) && !empty($type) && $type != '-1') {
            echo $type;
        } elseif (isset($_SESSION['nowMenu']) && !empty($_SESSION['nowMenu'])) {
            echo $_SESSION['nowMenu'];
        } else {
            echo 'blog'; // 預設值
        }
    ?>';

    window.uploaders = {}; 

    // 2. ImageUploader Class (UI 與 Cropper 邏輯)
    class ImageUploader {
        constructor(idSuffix, minWidth, minHeight, aspectRatio, outputWidth, outputHeight, prefix = 'image', maxSize = 2) {
            this.id = idSuffix;
            this.minWidth = minWidth;
            this.minHeight = minHeight;
            this.prefix = prefix;
            this.maxSize = maxSize;
            this.aspectRatio = (outputWidth > 0 && outputHeight > 0) ? outputWidth / outputHeight : null;
            this.outputWidth = outputWidth;
            this.outputHeight = outputHeight;
            this.croppedBlob = null;
            this.currentPreviewUrl = null;
            this.currentFileType = null;

            this.inputIdStr = `${prefix}_${this.id}`;

            this.modal = document.getElementById('cropModal');
            this.imageToCrop = document.getElementById('imageToCrop');
            this.confirmBtn = document.getElementById('confirmCropBtn');
            this.forceBtn = document.getElementById('forceCropBtn');
            this.cancelBtn = document.getElementById('cancelCropBtn');
            this.minDimensionsSpan = document.getElementById('minDimensions');
            this.currentDimensionsSpan = document.getElementById('currentDimensions');
            this.cropper = null;
        }

        handleFileChange(file) {
            if (!file) return;

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

            this.currentFileType = file.type;
            $('#fileNameDisplay' + this.id).show().text(file.name);
            $('#uploadStatus' + this.id).text('圖片載入中...');
            this.croppedBlob = null;
            const reader = new FileReader();
            reader.onload = (event) => {
                this.initCropper(event.target.result);
            };
            reader.readAsDataURL(file);
        }

        reset() {
            this.croppedBlob = null;
            const input = document.getElementById(this.inputIdStr);
            if (input) input.value = '';
            $('#fileNameDisplay' + this.id).hide().text('未選擇檔案');
            $('#uploadStatus' + this.id).text('');
            if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
            const previewImg = document.getElementById('croppedImagePreview' + this.id);
            if (previewImg) previewImg.src = 'crop/demo.jpg';
            $('#removeBtn' + this.id).hide();
        }

        initCropper(imageSrc) {
            if (this.cropper) {
                this.cropper.destroy();
            }
            this.imageToCrop.src = imageSrc;

            let ratioText = this.aspectRatio ? ` (比例 ${this.outputWidth}:${this.outputHeight})` : ` (比例自由)`;
            this.minDimensionsSpan.textContent = `${this.minWidth} x ${this.minHeight}` + ratioText;

            this.confirmBtn.onclick = () => this.handleStrictCrop();
            this.forceBtn.onclick = () => this.handleForceCrop();
            this.cancelBtn.onclick = () => this.closeModal();

            this.modal.style.display = 'flex';

            const cropperOptions = {
                aspectRatio: this.aspectRatio || NaN,
                viewMode: 1,
                autoCropArea: 1,
                ready: () => {
                    const imgData = this.cropper.getImageData();
                    const imgW = imgData.naturalWidth;
                    const imgH = imgData.naturalHeight;

                    if (this.aspectRatio) {
                        let finalW = imgH * this.aspectRatio;
                        let finalH = imgH;
                        if (finalW > imgW) {
                            finalW = imgW;
                            finalH = imgW / this.aspectRatio;
                        }
                        const startX = (imgW - finalW) / 2;
                        const startY = (imgH - finalH) / 2;
                        this.cropper.setData({ x: startX, y: startY, width: finalW, height: finalH });
                    } else {
                        this.cropper.setData({ x: 0, y: 0, width: imgW, height: imgH });
                    }
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
            this._processCropAndClose();
        }

        handleForceCrop() {
            if (!this.cropper) return;
            this._processCropAndClose();
        }

        _processCropAndClose() {
            $('#uploadStatus' + this.id).text('處理中...');
            this.confirmBtn.disabled = true;

            const cropData = this.cropper.getData();
            let finalWidth = Math.round(cropData.width);
            let finalHeight = Math.round(cropData.height);

            const MAX_WIDTH = 2560;
            const MIN_WIDTH = this.outputWidth || this.minWidth;

            if (finalWidth > MAX_WIDTH) {
                finalWidth = MAX_WIDTH;
                finalHeight = this.aspectRatio ? Math.round(finalWidth / this.aspectRatio) : Math.round(cropData.height * (MAX_WIDTH / cropData.width));
            } else if (finalWidth < MIN_WIDTH) {
                finalWidth = MIN_WIDTH;
                finalHeight = this.aspectRatio ? Math.round(finalWidth / this.aspectRatio) : Math.round(cropData.height * (MIN_WIDTH / cropData.width));
            }

            const croppedCanvas = this.cropper.getCroppedCanvas({
                width: finalWidth,
                height: finalHeight,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            let outputMimeType = 'image/jpeg';
            if (this.currentFileType === 'image/png' || this.currentFileType === 'image/gif') {
                outputMimeType = 'image/png';
            }

            croppedCanvas.toBlob((blob) => {
                this.confirmBtn.disabled = false;
                if (!blob) {
                    $('#uploadStatus' + this.id).text('❌ 錯誤');
                    return;
                }

                this.croppedBlob = blob;
                const sizeKB = (blob.size / 1024).toFixed(0);
                $('#uploadStatus' + this.id).text(`✅ 已準備 (${finalWidth}x${finalHeight}, ${sizeKB} KB)`);

                if (this.currentPreviewUrl) URL.revokeObjectURL(this.currentPreviewUrl);
                this.currentPreviewUrl = URL.createObjectURL(blob);

                const previewImg = document.getElementById('croppedImagePreview' + this.id);
                if (previewImg) {
                    previewImg.src = this.currentPreviewUrl;
                    previewImg.style.display = 'block';
                }

                $('#fileNameDisplay' + this.id).hide();
                $('#removeBtn' + this.id).show();
                $('#uploadStatus' + this.id).hide(); // 隱藏狀態避免雜亂
                this.closeModal();

            }, outputMimeType, 0.95);
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

    // 3. 初始化 - 優先使用 PHP 變數
    console.log('Config Type:', configType);
    console.log('PHP Width:', phpWidth, 'PHP Height:', phpHeight);
    console.log('Available Configs:', Object.keys(GLOBAL_IMG_CONFIG));
    
    let minWidth, minHeight, outputWidth, outputHeight;
    
    // 優先使用 PHP 變數（從 image_edit.php 傳入）
    if (phpWidth > 0 && phpHeight > 0) {
        minWidth = phpWidth;
        minHeight = phpHeight;
        outputWidth = phpWidth;
        outputHeight = phpHeight;
        console.log('Using PHP variables:', minWidth, 'x', minHeight);
    } else {
        // 其次使用 GLOBAL_IMG_CONFIG
        const cfg = GLOBAL_IMG_CONFIG[configType];
        if (cfg) {
            console.log('Using config:', cfg);
            minWidth = cfg.IW;
            minHeight = cfg.IH;
            outputWidth = cfg.OW || cfg.IW;
            outputHeight = cfg.OH || cfg.IH;
        } else {
            console.warn('Config not found for type:', configType, '- Using default values');
            minWidth = 800;
            minHeight = 600;
            outputWidth = 800;
            outputHeight = 600;
        }
    }
    
    const maxSize = <?php echo isset($maxSize) ? intval($maxSize) : (defined('DEFAULT_MAX_IMG_SIZE') ? DEFAULT_MAX_IMG_SIZE : 2); ?>;
    window.uploaders['Main'] = new ImageUploader(
        'Main', minWidth, minHeight, null, outputWidth, outputHeight, 'image', maxSize
    );

    // 事件綁定
    $(document).off('click', '.trigger-crop-btn').on('click', '.trigger-crop-btn', function(e) {
        e.preventDefault();
        const targetId = $(this).data('target'); 
        $('#' + targetId).click(); 
    });

    $(document).off('change', '#image_Main').on('change', '#image_Main', function(e) {
        if (this.files && this.files.length > 0) {
            window.uploaders['Main'].handleFileChange(this.files[0]);
        }
    });

    $(document).off('click', '#removeBtnMain').on('click', '#removeBtnMain', function(e) {
        e.preventDefault();
        window.uploaders['Main'].reset();
    });

    // --- ⭐⭐ 關鍵通用邏輯開始 ⭐⭐ ---
    $(document).off('click', '#submitBtn').on('click', '#submitBtn', async function(e) {
        e.preventDefault();

        const $myForm = $(this).closest('form');
        const previewImg = document.getElementById('croppedImagePreviewMain');
        const isBlobUrl = previewImg && previewImg.src.startsWith('blob:');

        // A. 若無裁切，直接送出
        if (!isBlobUrl) {
            $myForm[0].submit(); 
            return;
        }

        // B. 有裁切，處理 Blob 並替換 input
        Swal.fire({
            title: '處理圖片中...', text: '請稍候', 
            allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }
        });

        try {
            const uploader = window.uploaders['Main'];
            
            const response = await fetch(previewImg.src);
            const blob = await response.blob();
            
            const originalName = $('#fileNameDisplayMain').text() || 'image.jpg';
            const fileName = originalName.replace(/\.[^/.]+$/, "") + "_cropped.jpg";
            const newFile = new File([blob], fileName, { type: "image/jpeg", lastModified: Date.now() });

            // 尋找舊的 input
            const oldInput = $myForm.find('#image_Main')[0];
            
            if (oldInput) {
                const parent = oldInput.parentNode;
                const newInput = document.createElement('input');
                newInput.type = 'file';
                newInput.style.display = 'none';
                newInput.id = 'image_Main';
                
                // image_edit.php 使用 image[] 格式
                newInput.name = 'image[]';
                console.log("Edit modal: Using image[] format for image_edit.php");
                
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(newFile);
                newInput.files = dataTransfer.files;

                oldInput.disabled = true; 
                parent.appendChild(newInput);
            }

            $myForm[0].submit();

        } catch (error) {
            console.error(error);
            Swal.fire('錯誤', '處理失敗: ' + error.message, 'error');
        }
    });
</script>
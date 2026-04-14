<template id="universalRowTemplateInfo">
    <tr class="upload-row">
        <td>
            <div style="display: flex; align-items: flex-start; margin-bottom: 10px;">
                <div style="width:100px; height:100px; margin-right: 10px;" id="ImgCover">
                     <img class="preview-img" src="crop/demo.jpg">
                </div>
                
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <input type="file" class="hidden-file-input" accept="image/*" style="display:none;" />
                        <button type="button" class="trigger-crop-btn"  style="margin-bottom: 5px;">選擇檔案</button>
                    </div>
                    
                    <div>
                        <button type="button" class="remove-btn" style="display:none; color:red; border:none; background:none; cursor:pointer;">❌ 移除</button>
                        <p class="file-name-display" style="display:none; font-size:0.9rem; color:#555; margin: 0;">未選擇</p>
                        <p class="status-msg" style="font-size:0.9rem; color:blue; margin:5px 0 0 0;"></p>
                    </div>
                </div>
            </div>
            
            <div style="margin-top:5px;">
                <span class="table_data">圖片說明：</span>
                <input type="text" class="title-input table_data" style="width: 300px;">
            </div>
            
            <input type="hidden" class="url-input">
            <div class="hidden-inputs-container"></div>
        </td>
    </tr>
</template>

<script>
// Info 頁面專用的 addDynamicField 函數（支援舊資料載入）
function addDynamicFieldInfo(tbodyId, prefix, configKey, isRemovable = true, checkExisting = false) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (isRemovable && globalCounter > 0) {
        const lastUploader = window.uploaders[globalCounter];
        if (lastUploader && !lastUploader.croppedBlob && !lastUploader.imageUrlInput.value) {
            Swal.fire({ icon: 'warning', title: '無法新增', text: '請先完成上一張圖片' });
            return;
        }
    }

    const cfg = GLOBAL_IMG_CONFIG[configKey];
    if (!cfg) return;

    let existingImg = '';
    let existingId = '';
    let existingTitle = '';

    if (checkExisting) {
        existingImg = tbody.dataset.value;
        existingId = tbody.dataset.fileId;
        existingTitle = tbody.dataset.fileTitle || '';
    }

    globalCounter++;
    const id = globalCounter;

    const template = document.getElementById('universalRowTemplateInfo');
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('tr');
    row.id = `row_container_${id}`;
    
    const uniqueKey = `${prefix}_${id}`;

    const fileIn = clone.querySelector('.hidden-file-input');
    fileIn.id = uniqueKey;
    fileIn.name = `${prefix}[]`;

    const titleIn = clone.querySelector('.title-input');
    
    let newUploadTitleName = '';
    if (prefix.endsWith(']')) {
        newUploadTitleName = prefix.replace(/\]$/, '_title][]');
    } else {
        newUploadTitleName = prefix.replace('image', 'image_title') + '[]';
    }

    if(titleIn) {
        titleIn.id = `title_${id}`;
        titleIn.name = newUploadTitleName;
    }

    const urlIn = clone.querySelector('.url-input');
    if(urlIn) urlIn.id = `imageUrl${id}`;
    
    const cropBtn = clone.querySelector('.trigger-crop-btn');
    if(cropBtn) {
        cropBtn.dataset.target = uniqueKey;
        cropBtn.id = `btn_${id}`;
    }
    
    const removeBtn = clone.querySelector('.remove-btn');
    if(removeBtn) removeBtn.id = `removeBtn${id}`;

    const fileNameDisplay = clone.querySelector('.file-name-display');
    if(fileNameDisplay) fileNameDisplay.id = `fileNameDisplay${id}`;
    
    const previewImg = clone.querySelector('.preview-img');
    if(previewImg) previewImg.id = `croppedImagePreview${id}`;
    
    const statusMsg = clone.querySelector('.status-msg');
    if(statusMsg) statusMsg.id = `uploadStatus${id}`;

    if (isRemovable) {
        const btnContainer = clone.querySelector('div[style*="display: flex"]');
        if(btnContainer){
            const deleteRowBtn = document.createElement('button');
            deleteRowBtn.type = 'button';
            deleteRowBtn.innerHTML = '🗑️';
            deleteRowBtn.style.cssText = 'margin-left:5px; cursor:pointer; padding:5px;';
            deleteRowBtn.onclick = function() { deleteRow(id); };
            btnContainer.appendChild(deleteRowBtn);
        }
    }

    tbody.appendChild(clone);

    // 使用通用的 ImageUploader 類別（來自 all_modal.php）
    const uploader = new ImageUploader(
        id, cfg.IW, cfg.IH, null, cfg.OW || cfg.IW, cfg.OH || cfg.IH, prefix
    );
    window.uploaders[id] = uploader;

    // 處理舊資料
    if (checkExisting && existingImg && existingImg !== '') {
        const pImg = document.getElementById(`croppedImagePreview${id}`);
        if(pImg) {
            pImg.src = existingImg;
            pImg.style.display = 'block';
        }

        if(cropBtn) cropBtn.style.display = 'none';
        if(removeBtn) removeBtn.style.display = 'inline-block';

        if (titleIn) {
            titleIn.name = 'file_title';
            if (existingTitle) titleIn.value = existingTitle;
        }

        const hiddenContainer = row.querySelector('.hidden-inputs-container');
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = `delete_image[${existingId}]`;
        deleteInput.value = '0';
        deleteInput.className = 'delete-flag';
        
        if(hiddenContainer) hiddenContainer.appendChild(deleteInput);

        if(removeBtn) {
            removeBtn.onclick = function(e) {
                e.preventDefault();
                deleteInput.value = '1';
                
                pImg.src = 'crop/demo.jpg';
                
                removeBtn.style.display = 'none';
                cropBtn.style.display = 'inline-block';
                
                statusMsg.innerText = "";
                titleIn.value = "";

                titleIn.name = newUploadTitleName;

                uploader.reset();
            };
        }
    }
}

// 為了向後兼容，提供 addDynamicField 別名
function addDynamicField(tbodyId, prefix, configKey, isRemovable = true, checkExisting = false) {
    return addDynamicFieldInfo(tbodyId, prefix, configKey, isRemovable, checkExisting);
}
</script>

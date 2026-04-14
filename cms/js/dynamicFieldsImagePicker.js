/**
 * 動態欄位圖片選擇器整合腳本
 * 用於連接 image_picker.php 和動態欄位編輯器
 */

(function() {
    'use strict';

    // 儲存當前選擇的欄位 ID
    window.currentDynamicFieldId = null;

    /**
     * 從動態欄位開啟圖片選擇器
     * @param {string} fieldId - 欄位 ID
     * @param {string} fileType - 檔案類型
     */
    window.openDynamicFieldImagePicker = function(fieldId, fileType) {
        window.currentDynamicFieldId = fieldId;

        const pickerUrl = 'image_picker.php?mode=picker';

        // 使用 fancybox 開啟
        if (typeof $.fancybox !== 'undefined') {
            $.fancybox.open({
                src: pickerUrl,
                type: 'iframe',
                opts: {
                    iframe: {
                        css: {
                            width: '90%',
                            height: '90%'
                        }
                    },
                    afterClose: function() {
                        window.currentDynamicFieldId = null;
                    }
                }
            });
        } else {
            // 備用：使用 window.open
            const width = Math.min(1200, window.screen.width * 0.9);
            const height = Math.min(800, window.screen.height * 0.9);
            const left = (window.screen.width - width) / 2;
            const top = (window.screen.height - height) / 2;

            window.open(
                pickerUrl,
                'ImagePicker',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
            );
        }
    };

    /**
     * 從 image_picker 接收選擇的圖片
     * 這個函數會被 image_picker.php 調用
     * @param {number} fileId - 檔案 ID
     * @param {string} fileUrl - 檔案 URL
     * @param {object} fileInfo - 檔案資訊
     */
    window.receiveDynamicFieldImage = function(fileId, fileUrl, fileInfo) {
        if (!window.currentDynamicFieldId) {
            console.error('No field ID specified');
            return;
        }

        const fieldId = window.currentDynamicFieldId;

        // 更新隱藏的 file_id 輸入框
        const fileIdInput = document.getElementById(`${fieldId}_file_id`);
        if (fileIdInput) {
            fileIdInput.value = fileId;
        }

        // 更新預覽圖
        const wrapper = document.getElementById(`${fieldId}_wrapper`);
        if (!wrapper) {
            console.error('Wrapper not found:', `${fieldId}_wrapper`);
            return;
        }

        // 移除舊的預覽
        const existingPreview = wrapper.querySelector('.image-preview');
        if (existingPreview) {
            existingPreview.remove();
        }

        // 確保圖片路徑正確
        const imageSrc = fileUrl.startsWith('http') ? fileUrl : '../' + fileUrl;

        // 建立新的預覽
        const preview = document.createElement('div');
        preview.className = 'image-preview';
        preview.id = `${fieldId}_preview`;
        preview.innerHTML = `
            <img src="${imageSrc}" alt="預覽圖">
            <button type="button" class="btn-remove-image" data-field="${fieldId}">
                <i class="fas fa-times"></i>
            </button>
        `;

        // 插入到按鈕前面
        const selectBtn = wrapper.querySelector('.btn-select-image');
        if (selectBtn) {
            wrapper.insertBefore(preview, selectBtn);
        } else {
            wrapper.appendChild(preview);
        }

        // 關閉 fancybox
        if (typeof $.fancybox !== 'undefined') {
            $.fancybox.close();
        }

        // 清除當前欄位 ID
        window.currentDynamicFieldId = null;

        console.log('Image selected:', fileId, fileUrl);
    };

})();

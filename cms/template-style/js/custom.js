/* Add here all your JS customizations */
/*
* Form Image - Dropzone Initialization
*/
var initializeDropzone = function() {       
    // 選取所有具有 .dropzone-modern 類別但尚未初始化的元素
    $('.dropzone-modern:not(.initialized)').each(function() {
        var $el = $(this);
        
        // 1. 從 HTML data 屬性取得參數
        var dId = $el.data('d-id');
        var fileType = $el.data('file-type');
        var maxSize = $el.data('max-size') || 2;
        
        // 根據 fileType 動態決定接受的檔案類型
        var acceptedFiles = (fileType === 'file') ? null : 'image/*';

        console.log("初始化 Dropzone - ID:", $el.attr('id'), "Type:", fileType, "MaxSize:", maxSize);

        // 2. 初始化 Dropzone
        $el.dropzone({
            url: 'upload_dropzone.php',
            paramName: "file",
            maxFilesize: maxSize,
            acceptedFiles: acceptedFiles,
            addRemoveLinks: true,
            dictDefaultMessage: "",

            sending: function(file, xhr, formData) {
                formData.append("d_id", dId);
                formData.append("file_type", fileType);
                formData.append("max_size", maxSize);
            },

            success: function(file, response) {
                if (typeof response === "string") {
                    try { response = JSON.parse(response); } catch (e) { console.error("JSON 解析失敗:", e); }
                }

                if (response.status === 'success') {
                    console.log("上傳成功:", response);
                    if (file.previewElement) file.previewElement.classList.add("dz-success");
                } else {
                    console.error("上傳失敗:", response.message);
                    file.status = Dropzone.ERROR;
                    if (file.previewElement) {
                        file.previewElement.classList.add("dz-error");
                        var errorMsg = file.previewElement.querySelector(".dz-error-message");
                        if (errorMsg) errorMsg.textContent = response.message || "上傳失敗";
                    }
                }
            },

            error: function(file, errorMessage) {
                console.error("Dropzone Error:", errorMessage);
                var msg = (typeof errorMessage === 'object' && errorMessage.message) ? errorMessage.message : errorMessage;

                if (file.previewElement) {
                    var errorMsg = file.previewElement.querySelector(".dz-error-message");
                    if (errorMsg) {
                        if (typeof errorMessage === 'string' && errorMessage.includes('File is too big')) {
                            var fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
                            errorMsg.textContent = "檔案過大 (" + fileSizeMB + "MB > " + maxSize + "MB)";
                        } else {
                            errorMsg.textContent = msg;
                        }
                    }
                }
            },

            init: function() {
                var dropzoneInstance = this;
                this.on("addedfile", function(file) {
                    var fileSizeMB = file.size / (1024 * 1024);
                    console.log("檢查檔案:", file.name, "大小:", fileSizeMB.toFixed(2) + "MB", "限制:", maxSize + "MB");
                    
                    if (fileSizeMB > maxSize) {
                        file.status = Dropzone.ERROR;
                        if (file.previewElement) {
                            file.previewElement.classList.add("dz-error");
                            var errorMsg = file.previewElement.querySelector(".dz-error-message");
                            if (errorMsg) errorMsg.textContent = "檔案過大 (" + fileSizeMB.toFixed(2) + "MB > " + maxSize + "MB)";
                        }
                        return false;
                    }
                });

                this.on("queuecomplete", function() {
                    console.log("所有檔案處理完畢");
                    // 勾選自定義事件，通知外部 (如果有需要同步 UI)
                    $el.trigger('dropzoneComplete');
                });
            }
        }).addClass('dropzone initialized');
    });
};

// 頁面載入完成時執行
$(document).ready(function(){
    initializeDropzone();
});

/**
 * 切換已讀/未讀狀態
 * @param {HTMLElement} obj 按鈕元素
 * @param {int} id 資料 ID
 * @param {int} nextState 下一個狀態 (1=已讀, 0=未讀)
 */
function toggleRead(obj, id, nextState) {
    $.ajax({
        url: 'ajax_read.php',
        type: 'POST',
        dataType: 'json',
        data: {
            id: id,
            m_read: nextState
        },
        success: function(response) {
            if (response.status === 'success') {
                // 更新按鈕狀態
                var $btn = $(obj);
                if (nextState == 1) {
                    $btn.css('background-color', '#28a745').text('已讀');
                    $btn.attr('onclick', 'toggleRead(this, ' + id + ', 0)');
                } else {
                    $btn.css('background-color', '#dc3545').text('未讀');
                    $btn.attr('onclick', 'toggleRead(this, ' + id + ', 1)');
                }
            } else {
                alert('更新失敗: ' + (response.message || '未知錯誤'));
            }
        },
        error: function() {
            alert('系統錯誤，請稍後再試');
        }
    });
}
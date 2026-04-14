// upload.js (新版本，適用於 gallery.php 內的燈箱)
let fileList = [];
let uploadControllers = {};

// 將初始化邏輯包裝成一個函數，以便在 AJAX 載入內容後可以重新呼叫
window.initUploadControls = function() {
    // 檢查 DOM 元素是否存在
    const dropArea = document.getElementById("dropArea");
    const fileInput = document.getElementById("fileInput");
    const uploadBtn = document.getElementById("uploadBtn");
    const cancelAllBtn = document.getElementById("cancelAllBtn");
    const fileListContainer = document.getElementById("fileListContainer");
    
    // 如果元素不存在 (例如在 gallery.php 以外的頁面)，則不執行初始化
    if (!dropArea || !fileInput || !uploadBtn || !cancelAllBtn || !fileListContainer) {
        return; 
    }

    // 清空現有列表和控制器，確保重新初始化時是乾淨的狀態
    fileList = [];
    uploadControllers = {};
    fileListContainer.innerHTML = '';


    /**
     * 檢查是否有待上傳/未完成的檔案，並設定上傳按鈕狀態
     */
    function setUploadingState(isUploading) {
        // 檢查是否有未完成的檔案（未上傳且未取消）
        const hasPendingFiles = fileList.some(f => !f.uploaded && !f.canceled && !f.failed);

        if (isUploading || !hasPendingFiles) {
            // 如果正在上傳中，或者列表內已經沒有任何待辦事項，則禁用
            uploadBtn.classList.add("disabled");
            uploadBtn.disabled = true;
        } else {
            // 列表內有待辦事項，且不在上傳中，則啟用
            uploadBtn.classList.remove("disabled");
            uploadBtn.disabled = false;
        }
    }
    
    // 1. 點擊區
    dropArea.onclick = () => fileInput.click();

    // 2. 檔案選擇
    fileInput.onchange = () => {
        addFiles([...fileInput.files]);
        fileInput.value = ""; 
        setUploadingState(false); 
    };
    
    // 3. 拖曳事件 (保持不變)
    dropArea.ondragover = (e) => {
        e.preventDefault();
        dropArea.classList.add("dragover");
    };

    dropArea.ondragleave = () => {
        dropArea.classList.remove("dragover");
    };

    dropArea.ondrop = (e) => {
        e.preventDefault();
        dropArea.classList.remove("dragover");
        // 過濾非圖片檔案
        const files = [...e.dataTransfer.files].filter(f => f.type.startsWith("image/"));
        addFiles(files);
        setUploadingState(false); 
    };

    // 4. 上傳按鈕
    uploadBtn.onclick = () => {
        let filesToUpload = fileList.filter(f => !f.uploaded && !f.canceled && !f.failed);

        if (filesToUpload.length === 0) return;

        // --- 【修正】從隱藏欄位讀取最新的 folder_id ---
        const folderPath = window.currentPath || ''; 
        
        // 從 DOM 讀取最新的 folder_id (AJAX 更新後的值)
        const folderIdInput = document.getElementById('current_folder_id_storage');
        let folderId = null;
        
        if (folderIdInput && folderIdInput.value) {
            const val = parseInt(folderIdInput.value, 10);
            // 只有當它是有效數字且大於 0 時才使用
            if (!isNaN(val) && val > 0) {
                folderId = val;
            }
        }
        
        // 除錯訊息：確認讀取到的 ID
        console.log('上傳時讀取的 folder_id:', folderId, '路徑:', folderPath);
        // --- 修改結束 ---
        
        setUploadingState(true); 
        
        let uploadsPending = filesToUpload.length;
        let successfulUploads = 0; // 追蹤成功數量

        filesToUpload.forEach(fileObj => {
            if (!fileObj.isUploading) {
                fileObj.isUploading = true;
                uploadFile(fileObj, folderPath, folderId, (isSuccess) => {
                    fileObj.isUploading = false;
                    uploadsPending--;
                    if (isSuccess) {
                        successfulUploads++;
                    }
                    
                    // 檢查是否所有「已開始」的上傳都完成
                    if (uploadsPending === 0) {
                        setUploadingState(false); 

                        // *** 關鍵修改：設置全域旗標，不立即重新整理 ***
                        if (successfulUploads > 0 && typeof window.needsReload !== 'undefined') {
                            window.needsReload = true;
                        }
                    }
                });
            }
        });
    };

    // 5. 取消所有按鈕
    cancelAllBtn.onclick = () => {
        // 確保取消所有正在進行的請求
        Object.values(uploadControllers).forEach(ctrl => ctrl.abort());
        
        // 清空列表和 UI
        fileList = [];
        uploadControllers = {};
        fileListContainer.innerHTML = "";
        
        setUploadingState(false); 
    };

    // 初始狀態檢查
    setUploadingState(false);
};

function phpSizeToBytes(val) {
    val = val.trim().toUpperCase();
    const unit = val.slice(-1);
    let num = parseFloat(val);

    switch (unit) {
        case 'G': return num * 1024 * 1024 * 1024;
        case 'M': return num * 1024 * 1024;
        case 'K': return num * 1024;
        default:  return num; // 沒單位就當 bytes
    }
}


// =========================================================================
// 核心函數： uploadFile 
// =========================================================================
function uploadFile(fileObj, folderPath, folderId, onComplete) {
    const file = fileObj.file;
    const progressFill = fileObj.progressFill;
    const status = fileObj.status;

    const xhr = new XMLHttpRequest();
    uploadControllers[fileObj.id] = xhr; 
    let successStatus = false;

    // 處理進度
    xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            progressFill.style.width = percent.toFixed(2) + "%";
            status.textContent = "上傳中... " + percent.toFixed(0) + "%";
        }
    };

    // 處理完成
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            delete uploadControllers[fileObj.id];

            let serverMsg = "";
            let resJson = null;

            // ⭐【新增 JSON 解析：不管成功或失敗都嘗試解析】
            try {
                resJson = JSON.parse(xhr.responseText);
                serverMsg = resJson.msg || "";
            } catch (e) {
                serverMsg = "";
            }

            if (xhr.status === 200) {

                const success = resJson && resJson.success === true;
                const msg = resJson ? resJson.msg : "";

                if (success) {
                    fileObj.uploaded = true;
                    progressFill.style.width = "100%";
                    status.textContent = "上傳成功";
                    status.classList.remove("fail");
                    status.classList.add("success");
                    fileObj.uiElement.querySelector('.cancel-btn')?.remove();
                    successStatus = true;
                } else {
                    fileObj.failed = true;
                    status.textContent = "上傳失敗：" + (msg || "伺服器錯誤");
                    status.classList.add("fail");
                }

            } 
            else if (xhr.status === 0) {
                if (!fileObj.canceled) { 
                    fileObj.failed = true;
                    status.textContent = "連線中斷或伺服器無回應";
                    status.classList.add("fail");
                }
            } 
            else {
                // ⭐【重寫錯誤顯示：把後端的 msg 秀出來】
                fileObj.failed = true;
                status.textContent = serverMsg
                    ? `上傳失敗：${serverMsg}`
                    : `上傳失敗 (HTTP ${xhr.status})`;
                status.classList.add("fail");
            }

            if (typeof onComplete === "function") onComplete(successStatus);
        }
    };

    const fd = new FormData();
    fd.append("file", file);
    fd.append("path", folderPath);
    
    if (folderId) {
        fd.append("folder_id", folderId);
    }
    
    xhr.open("POST", './gallery/upload_handler.php', true);
    xhr.send(fd);
}



// =========================================================================
// 輔助函數： 建立 UI 和管理列表 
// =========================================================================
function formatSize(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / 1024 / 1024).toFixed(1) + " MB";
}

function addFiles(files) {
    const fileListContainer = document.getElementById("fileListContainer");
    if (!fileListContainer) return;

    const uploadMax = phpSizeToBytes(window.PHP_UPLOAD_LIMIT || "8M");

    files.forEach(file => {
        if (!file.type.startsWith("image/")) {
            console.warn(`檔案 ${file.name} 非圖片格式，已略過。`);
            return;
        }

        // **重點：前端用 PHP 的 upload_max_filesize 來做大小檢查**
        if (file.size > uploadMax) {
            const box = document.createElement("div");
            box.className = "progress-item error-item";

            box.innerHTML = `
                <div class="preview"><img src="" alt=""></div>
                <div class="info-box">
                    <div class="meta">${file.name} (${formatSize(file.size)})</div>
                    <div class="status fail">
                        檔案過大，PHP 最大限制為：${window.PHP_UPLOAD_LIMIT}
                    </div>
                </div>
            `;

            const img = box.querySelector("img");
            const reader = new FileReader();
            reader.onload = e => img.src = e.target.result;
            reader.readAsDataURL(file);

            fileListContainer.appendChild(box);
            return;
        }

        // 渲染正常檔案
        const id = Date.now() + Math.random().toString(36).substring(2, 9);
        const fileObj = { file, id, uploaded: false, canceled: false, failed: false, isUploading: false };
        fileList.push(fileObj);

        createProgressUI(fileObj);
        fileListContainer.appendChild(fileObj.uiElement);
    });
}



function createProgressUI(fileObj) {
    const file = fileObj.file;
    const box = document.createElement("div");
    box.className = "progress-item";
    box.id = `upload-item-${fileObj.id}`;

    // 預覽圖
    const preview = document.createElement("div");
    preview.className = "preview";
    const img = document.createElement("img");
    img.alt = "預覽圖";
    const reader = new FileReader();
    reader.onload = (e) => {
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    preview.appendChild(img);
    box.appendChild(preview);
    
    // 中間的資訊和進度條
    const infoBox = document.createElement("div");
    infoBox.className = "info-box";
    
    // 檔名和大小
    const meta = document.createElement("div");
    meta.className = "meta";
    meta.textContent = `${file.name} (${formatSize(file.size)})`;
    infoBox.appendChild(meta);

    // 進度條容器
    const progressContainer = document.createElement("div");
    progressContainer.className = "progress-bar";
    
    // 進度填充
    const progressFill = document.createElement("div");
    progressFill.className = "progress-fill";
    progressFill.style.width = "0%";
    progressContainer.appendChild(progressFill);
    infoBox.appendChild(progressContainer);

    // 狀態
    const status = document.createElement("div");
    status.className = "status";
    status.textContent = "等待上傳...";
    infoBox.appendChild(status);
    
    box.appendChild(infoBox);


    // 取消按鈕 (單個)
    const cancelBtn = document.createElement("button");
    cancelBtn.className = "cancel-btn";
    cancelBtn.textContent = "✖";
    cancelBtn.title = "取消此檔案";
    
    cancelBtn.onclick = () => {
        // 1. 中止上傳 (如果正在上傳中)
        if (uploadControllers[fileObj.id]) {
            uploadControllers[fileObj.id].abort();
            delete uploadControllers[fileObj.id];
        }
        
        // 2. 標記為已取消，並從 fileList 中移除
        fileObj.canceled = true; 
        fileList = fileList.filter(f => f.id !== fileObj.id);
        
        // 3. 從 UI 移除
        box.remove();
        
        // 4. 檢查並更新上傳按鈕狀態 (調用 initUploadControls 內部定義的 setUploadingState)
        const uploadBtn = document.getElementById("uploadBtn");
        const hasPendingFiles = fileList.some(f => !f.uploaded && !f.canceled && !f.failed);
        
        if (!hasPendingFiles) {
            uploadBtn.classList.add("disabled");
            uploadBtn.disabled = true;
        }
    };
    
    box.appendChild(cancelBtn);

    // 將 UI 元素引用存儲在 fileObj 中
    fileObj.uiElement = box;
    fileObj.progressFill = progressFill;
    fileObj.status = status;
    fileObj.preview = preview; // 保存預覽元素引用
}

/**
 * 初始化 Modal 的顯示/隱藏邏輯
 * 確保在關閉時能觸發頁面重新載入，以顯示新上傳的檔案。
 */
window.initUploadModalControls = function() {
    const modal = document.getElementById('uploadModal');
    const openBtn = document.getElementById('openUploadModalBtn');
    const closeBtn = document.getElementById('closeUploadModalBtn');
    
    if (!modal || !openBtn || !closeBtn) {
        return;
    }

    // 獨立的關閉邏輯，用於重複呼叫
    const closeModal = function() {
        // 檢查是否有成功上傳的檔案 (在 upload.js 裡設置的旗標)
        if (window.needsReload && typeof window.loadFolderContent === 'function') {
            // 呼叫 app.js 的函數來重新載入當前資料夾內容
            window.loadFolderContent(window.currentPath || ''); 
            window.needsReload = false; // 重置旗標
        }
        modal.style.display = 'none';
    }

    // 1. 綁定開啟按鈕：點擊時顯示 Modal
    openBtn.onclick = function() {
        modal.style.display = 'flex'; // 使用 flex 來實現置中
    }

    // 2. 綁定關閉按鈕：點擊 X 關閉
    closeBtn.onclick = closeModal;

    // 3. 點擊 Modal 外部時關閉
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // 確保 Modal 在初始化時是隱藏的
    modal.style.display = 'none'; 
}
/**
 * 判斷是否為選擇器模式 (檢查網址參數 mode=picker)
 */
function isPickerMode() {
    const params = new URLSearchParams(window.location.search);
    return params.get('mode') === 'picker';
}

/**
 * 取得當前的 PHP 檔案名稱 (gallery.php 或 image_picker.php)
 * 讓 AJAX 請求能自動打回正確的入口檔案
 */
function getCurrentScript() {
    const path = window.location.pathname;
    const script = path.substring(path.lastIndexOf('/') + 1);
    // 如果網址是目錄結尾 (例如 /admin/)，預設回傳 gallery.php，否則回傳當前檔名
    return (script === '' || script === '/') ? 'gallery.php' : script;
}

/**
 * 替代原生 alert()
 * @param {string} text 顯示訊息
 * @param {string} icon 圖標 (success, error, warning, info, question)
 */
function swalAlert(text, icon = 'info') {
    return Swal.fire({
        title: '訊息通知',
        text: text,
        icon: icon,
        confirmButtonText: '確定',
        allowOutsideClick: false,
    });
}

/**
 * 替代原生 confirm()
 * @param {string} text 顯示訊息
 * @param {string} title 標題
 * @param {string} icon 圖標 (warning, question, error)
 * @returns {Promise<boolean>}
 */
function swalConfirm(text, title = '確認操作', icon = 'warning') {
    return Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonText: '確定',
        cancelButtonText: '取消',
        reverseButtons: true,
    }).then((result) => result.isConfirmed);
}

/**
 * 替代原生 prompt()
 * @param {string} text 顯示訊息
 * @param {string} defaultValue 預設值
 * @returns {Promise<string|null>}
 */
async function swalPrompt(text, defaultValue = '') {
    const result = await Swal.fire({
        title: '輸入新名稱',
        text: text,
        input: 'text',
        inputValue: defaultValue,
        showCancelButton: true,
        confirmButtonText: '確定',
        cancelButtonText: '取消',
        inputValidator: (value) => {
            if (!value) {
                return '名稱不能為空！';
            }
        }
    });

    if (result.isConfirmed) {
        return result.value;
    } else {
        return null;
    }
}

// 追蹤上傳是否成功，用於燈箱關閉時決定是否重新整理
window.needsReload = false;

/**
 * 處理燈箱顯示/隱藏和事件綁定
 */
function initUploadModalControls() {
    const modal = document.getElementById('uploadModal');
    const openBtn = document.getElementById('openUploadModalBtn');
    const closeBtn = document.getElementById('closeUploadModalBtn');

    if (!modal || !openBtn || !closeBtn) return;

    // 確保 upload.js 的初始化邏輯已經準備好
    if (typeof window.initUploadControls === 'function') {
        window.initUploadControls();
    }

    // 開啟燈箱
    openBtn.onclick = () => {
        modal.style.display = 'block';
    };

    // 關閉燈箱
    const closeModal = () => {
        modal.style.display = 'none';

        // 檢查是否需要重新載入畫廊
        if (window.needsReload) {
            console.log("上傳完成，關閉燈箱後重新載入內容...");
            loadFolderContent(window.currentPath || '', false);
            window.needsReload = false; // 重置旗標
        }
    };

    closeBtn.onclick = closeModal;

    // 點擊背景關閉
    window.onclick = (event) => {
        if (event.target === modal) {
            closeModal();
        }
    };
}


/* ========== 初始化邏輯 ========== */

let dragData = null;
let dragGhost = null;

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;

    // 檢查是否有全域路徑變數（由 PHP 設置），若無則從 URL 抓取
    window.currentPath = window.currentPath || new URLSearchParams(window.location.search).get('path') || '';

    // 若為 Gallery 或 Picker 頁面
    if (body.classList.contains('page-gallery')) {
        
        // 1. 初始化樹狀圖控制項
        initTreeControls();

        // 2. 綁定樹狀圖拖曳
        document.querySelectorAll('.tree-node').forEach(node => {
            makeDraggableFolder(node, node.dataset.path || '');
        });

        // 3. 載入內容 (這裡會自動判斷是哪種模式)
        loadFolderContent(window.currentPath);

        // 4. 只有在「非 Picker 模式」下才綁定 Fancybox
        // 這樣在選擇圖片模式下，點擊圖片就不會彈出燈箱，而是執行選擇邏輯
        if (!isPickerMode()) {
            Fancybox.bind("[data-fancybox='gallery']", {
                Toolbar: {
                    display: ["zoom", "slideShow", "fullscreen", "download", "thumbs", "close"],
                },
            });
        }
    }

    if (body.classList.contains('page-trash')) {
        initTrash();
        initTrashBulk();
    }
});


/* ========== AJAX 核心功能 (關鍵修改區) ========== */

/**
 * 載入指定資料夾內容並更新右側 main 區塊
 */
function loadFolderContent(newPath, updateUrl = true, sortBy = null) {
    const mainContent = document.querySelector('.main');
    if (!mainContent) return;

    mainContent.innerHTML = '<h2 style="padding: 20px; color: #666;">資料載入中...</h2>';

    // 更新左側樹狀圖 Active 狀態
    document.querySelectorAll('.tree-node').forEach(n => n.classList.remove('active'));
    // 處理空路徑與特殊字元
    const safePath = (newPath || '').replace(/"/g, '\\"');
    const selectorPath = safePath === '' ? `[data-path=""]` : `[data-path="${safePath}"]`;
    document.querySelector(`.tree-node${selectorPath}`)?.classList.add('active');

    // --- 【關鍵修改】動態建構 URL，保留 mode 參數 ---
    const scriptName = window.AJAX_SCRIPT || getCurrentScript(); // 自動抓取 image_picker.php 或 gallery.php
    const urlParams = new URLSearchParams(window.location.search);

    // 設定 AJAX 必要參數，並更新 path
    urlParams.set('ajax_content', 'true');
    urlParams.set('path', newPath || ''); // 確保不為 null

    // 添加排序參數
    if (sortBy) {
        urlParams.set('sort', sortBy);
        window.currentSort = sortBy; // 儲存當前排序方式
    } else if (window.currentSort) {
        urlParams.set('sort', window.currentSort);
    }

    // 組合最終 URL (會自動包含 mode=picker 如果存在)
    const ajaxUrl = `${scriptName}?${urlParams.toString()}`;

    fetch(ajaxUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('伺服器回應錯誤: ' + response.status);
            }
            return response.text();
        })
        .then(html => {
            mainContent.innerHTML = html;
            window.currentPath = newPath;

            // 重新初始化介面功能
            initGallery();
            autoExpandCurrentPath();

            // 重新初始化上傳與燈箱 (因為 HTML 被替換了)
            if (typeof initUploadModalControls === 'function') initUploadModalControls();
            if (typeof initUploadControls === 'function') initUploadControls();

            // 只有在非 Picker 模式才重新綁定 Fancybox
            if (!isPickerMode()) {
                Fancybox.bind("[data-fancybox='gallery']");
            }

            // 更新網址列 (Push State)
            if (updateUrl) {
                const stateParams = new URLSearchParams(window.location.search);
                stateParams.set('path', newPath || '');
                // 移除 ajax 參數，只留 path 和 mode
                stateParams.delete('ajax_content');
                stateParams.delete('ajax_tree');
                
                const pushUrl = window.location.pathname + '?' + stateParams.toString();
                history.pushState({ path: newPath }, '', pushUrl);
            }
        })
        .catch(error => {
            mainContent.innerHTML = `<h2 style="padding: 20px; color: red;">載入內容失敗！</h2><p style="padding: 0 20px;">錯誤訊息: ${error.message}</p>`;
            console.error('AJAX Load Content Error:', error);
        });
}

/**
 * 透過 AJAX 重新載入左側樹狀圖選單
 */
function reloadTree() {
    const folderTree = document.getElementById('folder-tree');
    if (!folderTree) return;

    const currentPathForTree = window.currentPath || '';
    const scriptName = window.AJAX_SCRIPT || getCurrentScript();
    const urlParams = new URLSearchParams(window.location.search);

    urlParams.set('ajax_tree', 'true');
    urlParams.set('path', currentPathForTree);

    const ajaxUrl = `${scriptName}?${urlParams.toString()}`;

    fetch(ajaxUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Tree Reload Network Error: ' + response.status);
            }
            return response.text();
        })
        .then(html => {
            folderTree.innerHTML = html;

            initTreeControls();
            initTreeNavigation();

            document.querySelectorAll('.tree-node').forEach(node => {
                makeDraggableFolder(node, node.dataset.path || '');
            });

            autoExpandCurrentPath();
        })
        .catch(error => {
            console.error('AJAX Tree Reload Error:', error);
        });
}


// 處理瀏覽器回退/前進按鈕
window.addEventListener('popstate', (e) => {
    const path = e.state ? e.state.path : new URLSearchParams(window.location.search).get('path') || '';
    loadFolderContent(path, false);
});

/* ========== Helper Functions ========== */

function post(url, data) {
    console.log(url)
    return fetch(url, {
        method: 'POST',
        body: new URLSearchParams(data)
    }).then(r => r.text());
}

/**
 * 處理 AJAX 成功後的邏輯
 */
function handleOperationSuccess(response) {
    // 1. 定義變數
    let cleanMsg = "";
    let isError = false;

    // 2. 判斷傳進來的是什麼資料？
    if (typeof response === 'object' && response !== null) {
        // --- 情況 A：傳進來是 JSON 物件 (最標準的情況) ---
        cleanMsg = response.msg || ""; // 取出 msg 欄位
        // 直接用 PHP 給的 success 布林值來判斷，比猜測文字準確多了
        isError = (response.success === false); 
    } 
    else if (typeof response === 'string') {
        // --- 情況 B：傳進來是字串 ---
        // 先嘗試看看能不能轉成 JSON (有時候 fetch 沒轉好會變 JSON 字串)
        try {
            const parsed = JSON.parse(response);
            cleanMsg = parsed.msg || "";
            isError = (parsed.success === false);
        } catch (e) {
            // --- 情況 C：真的就是純文字 (相容舊程式碼) ---
            cleanMsg = (response || "").trim();
            // 沿用原本的關鍵字判斷邏輯
            isError = cleanMsg.startsWith("錯誤") || cleanMsg.startsWith("失敗") || cleanMsg.startsWith("無法");
        }
    }

    // 如果沒有訊息，就不處理
    if (!cleanMsg) return;

    // 3. 顯示 SweetAlert2
    const icon = isError ? 'error' : 'success';
    swalAlert(cleanMsg, icon); 

    if (isError) {
        console.error("操作失敗:", cleanMsg);
        return; // 失敗就不執行下面的刷新
    }

    // 4. 操作成功後的刷新邏輯 (保持原本邏輯不變)
    const body = document.body;

    setTimeout(() => {
        if (body.classList.contains('page-gallery')) {
            // 使用 AJAX 局部刷新，保持 Picker 狀態
            if (typeof reloadTree === 'function') reloadTree();
            if (typeof loadFolderContent === 'function') {
                loadFolderContent(window.currentPath || '', false);
            }
        }
        else if (body.classList.contains('page-trash')) {
            // 垃圾桶頁面：檢查是否在資料夾詳細頁面
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('view')) {
                // 如果在資料夾詳細頁面，跳轉回垃圾桶首頁
                // 因為資料夾可能已被還原或刪除
                window.location.href = window.location.pathname;
            } else {
                // 在列表頁面，直接刷新
                window.location.reload();
            }
        }
    }, 50);
}


/* ========== 自動展開目前所在資料夾 ========== */
function autoExpandCurrentPath() {
    const currentPath = window.currentPath || '';

    const rootNode = document.querySelector('.tree-node[data-path=""]');
    if (rootNode) {
        const rootChildren = rootNode.nextElementSibling; // 修正結構尋找方式
        if (rootChildren && rootChildren.classList.contains('tree-children')) {
            rootChildren.style.display = 'block';
            const toggle = rootNode.querySelector('.tree-toggle');
            if(toggle) toggle.textContent = '▾';
        }
    }

    if (!currentPath) return;

    const parts = currentPath.split('/');
    let build = '';

    parts.forEach((p, idx) => {
        build += (idx === 0 ? p : '/' + p);

        const node = document.querySelector(`.tree-node[data-path="${build}"]`);
        if (node) {
            const children = node.nextElementSibling; // 修正為 nextElementSibling
            if (children && children.classList.contains('tree-children')) {
                children.style.display = 'block';
                const toggle = node.querySelector('.tree-toggle');
                if (toggle) toggle.textContent = '▾';
            }
            // 向上展開父層
            let parent = node.parentElement; // .tree-children
            while (parent && parent.id !== 'folder-tree') {
                if (parent.classList.contains('tree-children')) {
                    parent.style.display = 'block';
                    // 找到控制該 children 的 toggle 並轉向
                    const controller = parent.previousElementSibling;
                    if(controller && controller.classList.contains('tree-node')){
                        const toggle = controller.querySelector('.tree-toggle');
                        if(toggle) toggle.textContent = '▾';
                    }
                }
                parent = parent.parentElement;
            }
        }
    });
}


/* ========== Gallery Init (重新綁定事件) ========== */
function initGallery() {
    initTreeNavigation(); // 重新綁定樹狀圖點擊
    initFolderCards();    // 重新綁定資料夾卡片
    initImageCards();     // 重新綁定圖片卡片 (拖曳、管理)
    initGalleryBulk();    // 重新綁定批次操作
    initNewFolderControl(); //綁定新增資料夾表單的 AJAX 提交
    initSortControl();    // 綁定排序選擇器
}

/* --- Tree Controls (專門處理展開/收合) --- */
function initTreeControls() {
    // 這裡使用事件委派可能更好，但維持您原本邏輯
    const treeRoot = document.getElementById('folder-tree');
    if(!treeRoot) return;
    
    // 移除舊的監聽器 (如果是重複呼叫 init) - 實際上因為 innerHTML 換了所以不用移除
    // 但為了安全，直接綁定新的
    
    // 使用 Delegation 處理 Toggle
    treeRoot.onclick = function(e) {
        if (e.target.classList.contains('tree-toggle')) {
            e.stopPropagation();
            const node = e.target.closest('.tree-node');
            const children = node.nextElementSibling;
            if (children && children.classList.contains('tree-children')) {
                const hidden = children.style.display === 'none' || children.style.display === '';
                children.style.display = hidden ? 'block' : 'none';
                e.target.textContent = hidden ? '▾' : '▸';
            }
        }
    }
}

/* --- Tree Navigation (點擊切換資料夾) --- */
function initTreeNavigation() {
    document.querySelectorAll('.tree-node').forEach(node => {
        const path = node.dataset.path || '';
        const label = node.querySelector('.tree-label');
        if (label) {
            label.onclick = (e) => {
                e.stopPropagation();
                loadFolderContent(path);
            };
        }
        makeDropTarget(node, path);
    });
}

/* --- Folder Cards --- */
function initFolderCards() {
    const cards = document.querySelectorAll('.folder-card');

    cards.forEach(card => {
        const path = card.dataset.path || '';
        card.onclick = (e) => {
            // 防止點擊編輯按鈕時觸發進入資料夾
            if (e.target.closest('.card-actions') || e.target.closest('button') || e.target.tagName === 'INPUT') return;
            e.stopPropagation();
            loadFolderContent(path);
        };
        makeDraggableFolder(card, path);
        makeDropTarget(card, path);
    });

    // Rename Folder
    document.querySelectorAll('.btn-folder-rename').forEach(btn => {
        // 【SweetAlert2 替換】將匿名函式改為 async
        btn.onclick = async (e) => { 
            e.stopPropagation();
            const path = btn.dataset.path || '';
            await startRename(path, 'folder', e); // 【新增 await】
        };
    });

    // Delete Folder
    document.querySelectorAll('.btn-folder-delete').forEach(btn => {
        // 【SweetAlert2 替換】將匿名函式改為 async
        btn.onclick = async (e) => { 
            e.stopPropagation();
            await confirmDeleteFolder(btn.dataset.path || '', e); // 【新增 await】
        };
    });
}

/* --- Image Cards --- */
function initImageCards() {
    const cards = document.querySelectorAll('.img-card');

    cards.forEach(card => {
        const path = card.dataset.path || '';
        makeDraggableFile(card, path);
        makeSelectableCard(card);
    });

    // Rename Image
    document.querySelectorAll('.btn-img-rename').forEach(btn => {
        // 【SweetAlert2 替換】將匿名函式改為 async
        btn.onclick = async (e) => { 
            e.stopPropagation();
            await startRename(btn.dataset.path || '', 'file', e); // 【新增 await】
        };
    });

    // Delete Image
    document.querySelectorAll('.btn-img-delete').forEach(btn => {
        // 【SweetAlert2 替換】將匿名函式改為 async
        btn.onclick = async (e) => { 
            e.stopPropagation();
            const path = btn.dataset.path || '';
            // 【SweetAlert2 替換】將 confirm 替換為 swalConfirm
            if (!(await swalConfirm('確定刪除這張圖片？\n將移到垃圾桶。', '確認刪除'))) return; 
            post('./gallery/'+'delete_image.php', { path }).then(handleOperationSuccess);
        };
    });

    // Copy Link
    document.querySelectorAll('.btn-copy-link').forEach(btn => {
        btn.onclick = (e) => {
            e.stopPropagation();
            const url = btn.dataset.fullurl;
            if (!url) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    // 【SweetAlert2 替換】將 alert 替換為 swalAlert
                    .then(() => swalAlert('已複製連結: ' + url, 'success'))
                    .catch(() => swalAlert('複製失敗，請手動複製', 'error'));
            } else {
                // 【SweetAlert2 替換】將 alert 替換為 swalAlert
                swalAlert('瀏覽器不支援自動複製', 'error');
            }
        };
    });
}

/* --- Selectable (批次勾選) --- */
function makeSelectableCard(card) {
    const checkbox = card.querySelector('.img-check');
    if (!checkbox) return;

    // 點擊卡片邊緣時勾選 (注意：Picker 模式下圖片有點擊事件，這裡僅處理卡片背景)
    card.addEventListener('click', (e) => {
        // 排除輸入框、按鈕、連結、以及 Picker 模式下的圖片容器(img-thumb-wrapper)
        if (e.target.tagName === 'INPUT' || 
            e.target.closest('button') || 
            e.target.closest('a') ||
            e.target.closest('.img-thumb-wrapper')) { // 關鍵：Picker模式下，點縮圖是選擇，不是勾選
            return;
        }

        checkbox.checked = !checkbox.checked;
        updateBulkState();
    });

    checkbox.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

/* --- Drag & Drop (保持原樣) --- */
function makeDraggableFolder(el, path) {
    if (!path) return;
    el.setAttribute('draggable', 'true');

    el.addEventListener('dragstart', (e) => {
        dragData = { type: 'folder', path };
        el.classList.add('dragging');
        // ... Ghost creation ...
        dragGhost = el.cloneNode(true);
        dragGhost.style.opacity = 0.3;
        dragGhost.style.position = 'absolute';
        dragGhost.style.top = '-9999px';
        document.body.appendChild(dragGhost);
        e.dataTransfer.setDragImage(dragGhost, 50, 20);
    });

    el.addEventListener('dragend', () => {
        el.classList.remove('dragging');
        if (dragGhost) dragGhost.remove();
        dragGhost = null;
        dragData = null;
    });
}

function makeDraggableFile(el, path) {
    if (!path) return;
    el.setAttribute('draggable', 'true');

    el.addEventListener('dragstart', (e) => {
        // 【修正點：確保能抓到所有已勾選項目的路徑】
        const checked = [...document.querySelectorAll('.img-check:checked')].map(c => {
            // 修正：如果 checkbox 本身沒有 data-path，就往上層尋找最近的 .img-card 來獲取 data-path
            return c.dataset.path || c.closest('.img-card')?.dataset.path;
        }).filter(p => p); // 過濾掉所有無效的路徑 (undefined 或 null)

        if (checked.length > 1 && checked.includes(path)) {
            // 批次拖曳
            dragData = { type: 'files', paths: checked };
        } else {
            // 單張拖曳
            dragData = { type: 'file', path };
        }

        // 確保瀏覽器兼容性 (如 Firefox) 
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', JSON.stringify(dragData)); // 新增：傳遞數據

        el.classList.add('dragging');
        dragGhost = el.cloneNode(true);
        dragGhost.style.opacity = 0.3;
        dragGhost.style.position = 'absolute';
        dragGhost.style.top = '-9999px';
        document.body.appendChild(dragGhost);
        e.dataTransfer.setDragImage(dragGhost, 50, 20);
    });

    el.addEventListener('dragend', () => {
        el.classList.remove('dragging');
        if (dragGhost) dragGhost.remove();
        dragGhost = null;
        // 修正：拖曳結束後延遲清除數據，確保 drop 事件能成功讀取
        setTimeout(() => { dragData = null; }, 100);
    });
}

function makeDropTarget(el, targetPath) {
    el.addEventListener('dragover', (e) => {
        e.preventDefault(); // 必要：允許 Drop
        e.dataTransfer.dropEffect = 'move';
        el.classList.add('drop-target');
    });

    el.addEventListener('dragleave', () => {
        el.classList.remove('drop-target');
    });

    // 【SweetAlert2 替換】將匿名函式改為 async
    el.addEventListener('drop', async (e) => { 
        e.preventDefault();
        e.stopPropagation();
        el.classList.remove('drop-target');

        if (!dragData) return;
        
        // 避免拖曳到自己所在的資料夾
        if (dragData.type === 'folder' && dragData.path === targetPath) return;

        // 處理資料夾移動
        if (dragData.type === 'folder') {
            if (targetPath.startsWith(dragData.path + '/')) {
                // 【SweetAlert2 替換】將 alert 替換為 swalAlert
                await swalAlert('不能將資料夾搬到自己的子層。', 'warning'); 
                return;
            }
            post('./gallery/'+'move_item.php', { type: 'folder', path: dragData.path, to_path: targetPath })
                .then(handleOperationSuccess);
        } 
        // 處理單一檔案移動
        else if (dragData.type === 'file') {
            post('./gallery/'+'move_item.php', { type: 'file', path: dragData.path, to_path: targetPath })
                .then(handleOperationSuccess);
        } 
        // 處理批次檔案移動
        else if (dragData.type === 'files') {
            // 確保您的後端有 move_multi.php (或者統一用 move_bulk_images.php)
            fetch('./gallery/'+'move_multi.php', {
                method: 'POST',
                // 這裡傳送的是 paths 字串 (JSON 格式)，PHP 端需用 json_decode($_POST['paths']) 解析
                body: new URLSearchParams({ 
                    type: 'files', 
                    to_path: targetPath, 
                    paths: JSON.stringify(dragData.paths) 
                })
            })
            .then(r => r.text())
            .then(handleOperationSuccess);
        }
        
        // 處理完畢重置
        dragData = null;
    });
}

/* ========== Gallery bulk delete ========== */
function initGalleryBulk() {
    const checkAll = document.getElementById('check-all');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    function updateBulk() {
        const checks = document.querySelectorAll('.img-check:checked');
        if (!bulkDeleteBtn) return;
        if (checks.length > 0) {
            bulkDeleteBtn.disabled = false;
            bulkDeleteBtn.classList.add('enabled');
            bulkDeleteBtn.textContent = `刪除已選取項目 (${checks.length})`;
        } else {
            bulkDeleteBtn.disabled = true;
            bulkDeleteBtn.classList.remove('enabled');
            bulkDeleteBtn.textContent = `刪除已選取項目`;
        }
    }
    window.updateBulkState = updateBulk;

    if (checkAll) {
        checkAll.onchange = () => {
            const checks = document.querySelectorAll('.img-check');
            checks.forEach(c => (c.checked = checkAll.checked));
            updateBulk();
        };
    }

    document.querySelectorAll('.img-check').forEach(c => {
        c.onchange = updateBulk;
    });

    if (bulkDeleteBtn) {
        // 【SweetAlert2 替換】將匿名函式改為 async
        bulkDeleteBtn.onclick = async () => { 
            const checks = document.querySelectorAll('.img-check:checked');
            if (checks.length === 0) return;
            
            const confirmMsg = `確定刪除這 ${checks.length} 張圖片？將移至垃圾桶。`;
            // 【SweetAlert2 替換】將 confirm 替換為 swalConfirm
            if (!(await swalConfirm(confirmMsg, '確認批次刪除'))) return; 

            // 確保路徑收集正確
            const list = [...checks]
                .map(c => c.dataset.path || c.closest('.img-card')?.dataset.path)
                .filter(p => p); 

            const fd = new FormData();
            fd.append('list', JSON.stringify(list));

            fetch('./gallery/'+'bulk_delete_images.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.text())
            .then(handleOperationSuccess);
        };
    }
}


/* ========== Trash 功能 (垃圾桶) ========== */
function initTrash() {
    // 綁定各類還原/刪除按鈕 (簡化寫法，適用於主垃圾桶列表的 data-id 項目)
    const bindAction = (selector, url, confirmMsg) => {
        // 由於這些是主列表元素，我們使用 querySelectorAll 進行靜態綁定
        document.querySelectorAll(selector).forEach(btn => {
            
            // 【SweetAlert2 替換】將匿名函式改為 async
            btn.addEventListener('click', async (e) => { 
                e.stopPropagation(); // 防止事件冒泡
                const id = btn.dataset.id;
                if (!id) return;
                
                // 1. 確認對話框 (如果有的話)
                if (confirmMsg) {
                    // 【SweetAlert2 替換】將 confirm 替換為 swalConfirm
                    // 建議：刪除類操作通常用 'warning' (黃色驚嘆號) 或 'error' (紅色)
                    const icon = url.includes('_delete') ? 'warning' : 'question';
                    
                    // 等待使用者按下「確定」，如果按取消則直接 return
                    const isConfirmed = await swalConfirm(confirmMsg, '確認操作', icon);
                    if (!isConfirmed) return; 
                }

                // 2. 【優化】顯示 Loading 畫面
                Swal.fire({
                    title: '處理中...',
                    text: '請稍候',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // 3. 執行 POST 請求
                try {
                    // 假設您的 post 封裝函式回傳的是 Promise
                    // 我們使用 await 等待結果，這樣才能 catch 到錯誤
                    const result = await post(url, { id });
                    
                    Swal.close(); // 關閉 Loading
                    
                    // 把結果丟給我們剛剛改寫過的 "聰明版" handleOperationSuccess
                    // 它會自動判斷 result 是 JSON 物件還是字串，並顯示對應訊息
                    handleOperationSuccess(result);

                } catch (error) {
                    Swal.close(); // 確保發生錯誤時關閉 Loading
                    console.error("Action Error:", error);
                    
                    // 顯示錯誤訊息
                    const errMsg = error.message || error;
                    swalAlert('操作失敗: ' + errMsg, 'error');
                }
            });
        });
    };

    // 1. 綁定主垃圾桶列表的項目 (使用 data-id)
    bindAction('.btn-trash-img-restore', './gallery/'+'restore_image.php');
    bindAction('.btn-trash-img-delete', './gallery/'+'trash_delete_image.php', '確定永久刪除此圖片？此動作無法復原！');
    bindAction('.btn-trash-folder-restore', './gallery/'+'restore_folder.php');
    bindAction('.btn-trash-folder-delete', './gallery/'+'trash_delete_folder.php', '確定永久刪除整個資料夾？此動作無法復原！');
}

// ----------------------------------------------------------------------------------
// **單張圖片還原/永久刪除 (資料夾詳細視圖內) - 已合併並優化**
// 透過事件委派，統一處理 btn-trash-file-restore/delete 的點擊事件
// ----------------------------------------------------------------------------------
// 【SweetAlert2 替換】將匿名函式改為 async
document.addEventListener('click', async function (e) {
    // 尋找目標按鈕，只處理具有 data-folder-id 和 data-sub-path 的檔案操作按鈕
    const btn = e.target.closest('.btn-trash-file-restore, .btn-trash-file-delete');
    if (!btn || !btn.dataset.folderId || !btn.dataset.subPath) return;
    
    e.stopPropagation(); // 阻止事件冒泡

    const folderId = btn.dataset.folderId;
    const subPath = btn.dataset.subPath;
    const isRestore = btn.classList.contains('btn-trash-file-restore');

    let url = '';
    let confirmMsg = '';
    let icon = 'question';

    if (isRestore) {
        url = './gallery/'+'trash_restore_file.php';
        confirmMsg = `確定要還原檔案 ${subPath} 嗎？`;
    } else {
        url = './gallery/'+'trash_delete_file_perm.php';
        confirmMsg = `警告：確定要永久刪除檔案 ${subPath} 嗎？此操作不可逆！`;
        icon = 'error';
    }

    // 【SweetAlert2 替換】將 confirm 替換為 swalConfirm
    if (!(await swalConfirm(confirmMsg, isRestore ? '確認還原' : '永久刪除警告', icon))) return;

    // 執行 AJAX
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            folder_id: folderId,
            sub_path: subPath
        })
    })
    .then(response => response.text())
    .then(handleOperationSuccess) // 統一使用 handleOperationSuccess 處理結果和刷新
    .catch(error => {
        // 【SweetAlert2 替換】將 alert 替換為 swalAlert
        swalAlert((isRestore ? '還原' : '刪除') + '失敗: ' + error, 'error');
    });
});

// ----------------------------------------------------------------------------------

function initTrashBulk() {
    const checkAll = document.getElementById('trash-check-all');
    const btnRestore = document.getElementById('btn-restore-selected');
    const btnDelete = document.getElementById('btn-delete-selected');

    function updateState() {
        const checks = document.querySelectorAll('.trash-check:checked');
        const enabled = checks.length > 0;
        if(btnRestore) {
            btnRestore.disabled = !enabled;
            if(enabled) btnRestore.classList.add('enabled'); else btnRestore.classList.remove('enabled');
        }
        if(btnDelete) {
            btnDelete.disabled = !enabled;
            if(enabled) btnDelete.classList.add('enabled'); else btnDelete.classList.remove('enabled');
        }
    }
    
    // 確保所有 checkbox 變動都能觸發狀態更新
    // 使用 document 上的委派來監聽，確保動態內容也能觸發
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('trash-check')) updateState();
    });

    if (checkAll) {
        checkAll.addEventListener('change', () => {
            document.querySelectorAll('.trash-check').forEach(c => c.checked = checkAll.checked);
            updateState();
        });
    }

    const handleBulk = (btn, url, confirmMsg) => {
        if (!btn) return;

        // 【SweetAlert2 替換 & 優化】將匿名函式改為 async，解決卡頓問題
        btn.addEventListener('click', async () => {
            const checks = document.querySelectorAll('.trash-check:checked');
            if (checks.length === 0) {
                // 優化：如果沒選任何東西，給個提示
                swalAlert('請至少選擇一個項目', 'warning');
                return;
            }

            if (confirmMsg) {
                // 【SweetAlert2 替換】將 confirm 替換為 swalConfirm
                // 如果是刪除類操作用 error (紅色)，還原類操作用 question (藍色)
                const icon = url.includes('delete') ? 'warning' : 'question'; 
                // 這裡將 confirmMsg 傳入 title 或 text 視你的 swalConfirm 實作而定
                if (!(await swalConfirm(confirmMsg, '確認批次操作', icon))) return;
            }

            // 【優化點】：顯示 Loading
            Swal.fire({
                title: '正在處理中...',
                text: '請稍候，系統正在執行批次操作。',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const list = [...checks].map(c => c.value);
            const fd = new FormData();
            // 注意：這裡傳送的 key 是 'list'，後端 PHP 接收時要用 $_POST['list']
            fd.append('list', JSON.stringify(list));

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: fd
                });

                // 1. 嘗試讀取 JSON (因為後端現在都改回傳 JSON 了)
                // 如果後端有可能回傳純文字，這裡會報錯，跳到 catch
                const result = await response.json().catch(() => null); 

                Swal.close(); // 關閉 loading 提示

                if (!response.ok) {
                    // 如果 HTTP 狀態碼不是 200-299 (例如 500 error)
                    throw new Error(result?.msg || `伺服器錯誤 (${response.status})`);
                }
                
                // 2. 如果成功解析 JSON (result 不是 null)，傳 JSON 物件
                // 如果解析失敗 (result 是 null)，則嘗試讀取純文字 text() 傳入 (相容舊版)
                if (result) {
                    handleOperationSuccess(result);
                } else {
                    // 如果 fetch 成功但不是 JSON (極少見)，讀取文字
                    // 注意：response body stream 只能讀一次，上面 json() 讀過就不能讀 text()
                    // 所以通常 json() catch 回傳 null 代表這裡無法再讀 text()
                    // 實務上我們認定這次升級後都是 JSON，若失敗就報錯
                    throw new Error("伺服器回傳格式錯誤 (非 JSON)");
                }

            } catch (error) {
                Swal.close(); // 確保發生錯誤時關閉 loading
                console.error("Bulk Operation Error:", error);
                // 顯示錯誤訊息
                swalAlert('操作失敗: ' + (error.message || error), 'error');
            }
        });
    }

    handleBulk(btnRestore, './gallery/'+'restore_bulk_images.php', '確定還原選取的項目？');
    handleBulk(btnDelete, './gallery/'+'delete_bulk_images_perm.php', '確定永久刪除選取的項目？無法復原！');
    
    // 首次調用時更新狀態
    updateState();
}


/* --- New Folder Control (新增) --- */
function initNewFolderControl() {
    const form = document.querySelector('.new-sub-folder-form');
    if (!form) return;

    // 【SweetAlert2 替換】將匿名函式改為 async
    form.addEventListener('submit', async (e) => {
        e.preventDefault(); // 【關鍵】阻止傳統表單提交和頁面跳轉

        const folderNameInput = form.querySelector('input[name="folder_name"]');
        const parentPathInput = form.querySelector('input[name="parent_path"]');
        const parentIdInput = form.querySelector('input[name="parent_id"]');
        
        const folderName = folderNameInput ? folderNameInput.value.trim() : '';
        const parentPath = parentPathInput ? parentPathInput.value : '';
        const parentId = parentIdInput ? parentIdInput.value : '';

        if (folderName === '') {
            await swalAlert('資料夾名稱不能為空！', 'warning'); // 使用 SweetAlert2 彈出
            return;
        }

        // 顯示 Loading 提示
        Swal.fire({
            title: '正在新增資料夾...',
            text: '請稍候',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // 執行 AJAX POST 請求到 add_folder.php
        post(form.action, { 
            folder_name: folderName, 
            parent_path: parentPath,
            parent_id: parentId
        })
        .then(result => {
            Swal.close(); // 關閉 Loading
            // handleOperationSuccess 會執行 swalAlert 彈出結果，並執行刷新
            handleOperationSuccess(result); 
            folderNameInput.value = ''; // 清空輸入框
        })
        .catch(error => {
            Swal.close(); // 關閉 Loading
            swalAlert('新增資料夾失敗: ' + error.message, 'error'); // 使用 SweetAlert2 彈出
            console.error('Add Folder AJAX Error:', error);
        });
    });
}


/* ========== 統一的重新命名與刪除邏輯 ========== */
async function confirmDeleteFolder(folderPath, event) {
    if (event) event.stopPropagation();

    const confirmMsg = `【移動到垃圾桶】\n您確定要刪除資料夾：${folderPath}\n及其所有內容嗎？`;
    
    // 1. 跳出確認視窗
    if (await swalConfirm(confirmMsg, '確認刪除資料夾', 'warning')) { // 刪除建議用 warning icon
        
        // 2. 發送請求
        post('./gallery/'+'delete_folder.php', { path: folderPath }).then(result => {
            
            // --- [關鍵修改] 解析後端回傳資料 (支援 JSON 與 純文字) ---
            let res = {};
            try {
                // 嘗試將回傳字串轉為 JSON 物件
                res = JSON.parse(result);
            } catch (e) {
                // 如果解析失敗，代表後端可能回傳純文字錯誤訊息 (相容舊版)
                // 用文字內容來猜測是否成功
                res = { 
                    success: !result.includes("錯誤") && !result.includes("無法"), 
                    msg: result 
                };
            }

            // --- 3. 判斷失敗 ---
            // 直接讀取 success 布林值，準確度 100%
            if (!res.success) {
                swalAlert(res.msg, 'error');
                return;
            }

            // --- 4. 成功：顯示訊息 ---
            swalAlert(res.msg, 'success');

            // --- 5. 執行跳轉邏輯 ---
            const currentPath = window.currentPath || '';

            // 邏輯 A: 如果使用者目前正在「被刪除的資料夾」裡面 (或其子資料夾)
            // 必須往上跳一層，不然會停留在不存在的路徑
            if (folderPath === currentPath || currentPath.startsWith(folderPath + '/')) {
                const parentPath = currentPath.split('/').slice(0, -1).join('/');
                reloadTree(); // 刷新左側樹狀圖
                loadFolderContent(parentPath, true); // 載入上一層並更新網址
            } 
            // 邏輯 B: 如果刪除的是別的資料夾 (使用者不在裡面)
            else {
                reloadTree(); // 刷新左側樹狀圖 (把刪掉的資料夾移除)
                // 重新載入當前頁面內容 (以免畫面上有殘留的資料夾圖示)
                loadFolderContent(currentPath, false); 
            }
        });
    }
}

// 【SweetAlert2 替換】將 function 改為 async function
async function startRename(oldPath, type, event) {
    if (event) event.stopPropagation();
    if (!oldPath) return;

    const currentName = oldPath.split('/').pop();
    const promptText = `請為${type === 'folder' ? '資料夾' : '檔案'}輸入新的名稱：`;

    // 【SweetAlert2 替換】將 prompt 替換為 await swalPrompt
    const newName = await swalPrompt(promptText, currentName);

    if (newName && newName.trim() !== "" && newName !== currentName) {
        // 根據類型決定後端
        const script = type === 'folder' ? './gallery/'+'rename_folder.php' : './gallery/'+'rename_image.php';
        post(script, { path: oldPath, new_name: newName.trim() }).then(handleOperationSuccess);
    }
}

/* ========== 排序控制 ========== */
function initSortControl() {
    const sortSelect = document.getElementById('sortBy');
    if (!sortSelect) return;

    // 恢復上次選擇的排序方式
    if (window.currentSort) {
        sortSelect.value = window.currentSort;
    }

    // 監聽排序變更
    sortSelect.addEventListener('change', (e) => {
        const sortValue = e.target.value;
        window.currentSort = sortValue; // 儲存排序方式
        sortGalleryItems(sortValue); // 直接在前端排序
    });
}

/* ========== 前端排序功能 ========== */
function sortGalleryItems(sortBy) {
    const cardGrid = document.querySelector('.card-grid');
    if (!cardGrid) return;

    // 取得所有資料夾和圖片卡片
    const folderCards = Array.from(cardGrid.querySelectorAll('.folder-card'));
    const imageCards = Array.from(cardGrid.querySelectorAll('.img-card'));

    // 排序函數
    const sortFunction = (a, b, type) => {
        let aName, bName, aTime, bTime;

        if (type === 'folder') {
            aName = a.querySelector('.folder-name')?.textContent || '';
            bName = b.querySelector('.folder-name')?.textContent || '';
        } else {
            aName = a.querySelector('.img-name')?.textContent || '';
            bName = b.querySelector('.img-name')?.textContent || '';
        }

        // 從 DOM 取得時間戳記（如果有的話）
        aTime = parseInt(a.dataset.mtime || '0');
        bTime = parseInt(b.dataset.mtime || '0');

        switch (sortBy) {
            case 'name_asc':
                return aName.localeCompare(bName, 'zh-TW');
            case 'name_desc':
                return bName.localeCompare(aName, 'zh-TW');
            case 'date_asc':
                return aTime - bTime;
            case 'date_desc':
            default:
                return bTime - aTime;
        }
    };

    // 排序資料夾和圖片
    folderCards.sort((a, b) => sortFunction(a, b, 'folder'));
    imageCards.sort((a, b) => sortFunction(a, b, 'image'));

    // 清空並重新插入排序後的元素
    cardGrid.innerHTML = '';

    // 先插入資料夾
    folderCards.forEach(card => cardGrid.appendChild(card));

    // 再插入圖片
    imageCards.forEach(card => cardGrid.appendChild(card));

    // 如果沒有任何項目，顯示空資料夾訊息
    if (folderCards.length === 0 && imageCards.length === 0) {
        const emptyMsg = document.createElement('p');
        emptyMsg.style.cssText = 'color: #999; grid-column: 1 / -1; text-align: center; padding: 40px;';
        emptyMsg.textContent = '此資料夾是空的，請上傳圖片或新增子資料夾。';
        cardGrid.appendChild(emptyMsg);
    }
}
/**
 * 動態欄位編輯器 - 完整版
 * 整合 Cropper 系統 + 拖曳排序 + 動態新增
 */

class DynamicFieldsEditor {
    constructor(containerId, config) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Container not found:', containerId);
            return;
        }

        this.config = config;
        this.groups = [];
        this.groupCounter = 0;
        this.sortable = null;

        this.init();
    }

    // ★ 新增：產生穩定 UID（拖曳排序不變）
    generateUID() {
        if (window.crypto && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'uid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    init() {
        this.render();
        this.attachEvents();
        this.initSortable();
    }

    render() {
        const html = `
            <div class="dynamic-fields-wrapper">
                <div id="${this.config.field}_full_container">
                    ${this.renderFullContent()}
                </div>
                ${this.config.note ? `<div class="dynamic-fields-note">${this.config.note}</div>` : ''}
            </div>
        `;

        this.container.innerHTML = html;
    }

    renderFullContent() {
        return `
            <div class="dynamic-fields-container" id="${this.config.field}_items">
                ${this.renderGroups()}
            </div>
            <div class="dynamic-fields-footer" id="${this.config.field}_footer">
                <a href="javascript:void(0)" class="btn-add-group-link" data-action="add-group">
                    <img src="image/add.png" width="16" height="16" border="0" style="vertical-align:middle;"> 新增項目
                </a>
            </div>
        `;
    }

    renderGroups() {
        return this.groups.map((group, index) => this.renderGroup(group, index)).join('');
    }

    renderGroup(group, index) {
        const fields = this.config.fields.map(field => {
            return this.renderField(field, group[field.name] || '', index, group);
        }).join('');

        return `
            <div class="dynamic-field-group" data-group-index="${index}">
                <input type="hidden" name="${this.config.field}[${index}][_uid]" value="${group._uid}">
                <div style="display: flex; align-items: flex-start; position:relative;">
                    <div class="drag-handle" style="margin-right:10px; cursor:move; color:#ccc; padding-top: 10px;" title="拖曳排序">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div style="flex: 1;">
                        <div class="dynamic-field-group-header">
                            <div class="header-left">
                                <span class="group-number">項目 ${index + 1}</span>
                                <button type="button" class="btn-toggle-group" data-action="toggle-group" data-index="${index}" title="收合/展開">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                            <div class="header-actions">
                                <button type="button" class="btn-remove-group" data-action="remove-group" data-index="${index}">
                                    <i class="fas fa-trash"></i> 刪除
                                </button>
                            </div>
                        </div>
                        <div class="dynamic-field-group-body">
                            ${fields}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderField(fieldConfig, value, groupIndex, group) {
        const fieldName = `${this.config.field}[${groupIndex}][${fieldConfig.name}]`;
        const fieldId = `${this.config.field}_${groupIndex}_${fieldConfig.name}`;
        const required = fieldConfig.required ? 'required' : '';

        switch (fieldConfig.type) {
            case 'select':
                let optionsHtml = (fieldConfig.options || []).map(opt => {
                    const selected = String(opt.value) === String(value) ? 'selected' : '';
                    return `<option value="${this.escapeHtml(opt.value)}" ${selected}>${this.escapeHtml(opt.label)}</option>`;
                }).join('');

                // 插入空值的預設選項，以便觸發必填驗證
                const placeholder = fieldConfig.placeholder || '請選擇';
                const isSelected = (value === '' || value === undefined || value === null);
                const defaultOption = `<option value="" ${isSelected ? 'selected' : ''} ${fieldConfig.required ? 'disabled' : ''}>${this.escapeHtml(placeholder)}</option>`;
                optionsHtml = defaultOption + optionsHtml;

                return `
                    <div class="form-group">
                        <label for="${fieldId}">
                            ${fieldConfig.label}
                            ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                        </label>
                        <select id="${fieldId}"
                                name="${fieldName}"
                                class="form-control"
                                ${required}>
                            ${optionsHtml}
                        </select>
                    </div>
                `;

            case 'text':
                return `
                    <div class="form-group">
                        <label for="${fieldId}">
                            ${fieldConfig.label}
                            ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                        </label>
                        <input type="text"
                               id="${fieldId}"
                               name="${fieldName}"
                               value="${this.escapeHtml(value)}"
                               class="form-control"
                               ${required}>
                    </div>
                `;

            case 'textarea':
                return `
                    <div class="form-group">
                        <label for="${fieldId}">
                            ${fieldConfig.label}
                            ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                        </label>
                        <textarea id="${fieldId}"
                                  name="${fieldName}"
                                  class="form-control"
                                  rows="${fieldConfig.rows || 3}"
                                  ${required}>${this.escapeHtml(value)}</textarea>
                    </div>
                `;

            case 'image':
                return this.renderImageField(fieldConfig, value, groupIndex, group);

            case 'file':
                if (fieldConfig.multiple) {
                    return this.renderMultipleFileField(fieldConfig, value, groupIndex, group, group._uid || this.generateUID());
                }
                return this.renderFileField(fieldConfig, value, groupIndex, group);

            default:
                return '';
        }
    }

    renderImageField(fieldConfig, value, groupIndex, group) {
        const uid = group && group._uid ? group._uid : (this.groups[groupIndex] && this.groups[groupIndex]._uid ? this.groups[groupIndex]._uid : this.generateUID());
        // 若 group 沒有 uid，補上（避免新資料或舊資料缺 uid）
        if (group && !group._uid) { group._uid = uid; }

        const isMultiple = fieldConfig.multiple || false;

        // 如果是多圖模式，value 應該是陣列
        if (isMultiple) {
            return this.renderMultipleImageField(fieldConfig, value, groupIndex, group, uid);
        }

        // 單圖模式
        // ⭐ 修正：統一使用 [groupIndex][fieldName][subField] 結構
        // 這樣後端收到的 $_POST[config.field][groupIndex][fieldName] 就會是一個 array
        const baseName = `${this.config.field}[${groupIndex}][${fieldConfig.name}]`;
        
        // upload 使用 uid 做為 key 的一部分（維持原有 $_FILES 邏輯，但結構要清清楚楚）
        const uploadFieldName = `${this.config.field}[${uid}][${fieldConfig.name}_upload]`; 
        
        // ⭐ 改用 UID 作為 ID 的一部分，確保排序時 ID 不變
        const fieldId = `${this.config.field}_${uid}_${fieldConfig.name}`; 
        
        const prefix = 'df';
        const idSuffix = fieldId; 
        const uploaderId = `${prefix}_${idSuffix}`;

        const fileId = value?.file_id || '';
        const fileInfo = value?.file_info || null;
        const required = fieldConfig.required ? 'required' : '';

        // 判斷是否有現有圖片
        const hasExistingImage = fileInfo && fileInfo.file_link2;
        let imageSrc = 'crop/demo.jpg'; // 預設圖片
        let imageLink = '#';

        if (hasExistingImage) {
            imageSrc = fileInfo.file_link2.startsWith('http') ? fileInfo.file_link2 : '../' + fileInfo.file_link2;
            imageLink = fileInfo.file_link1.startsWith('http') ? fileInfo.file_link1 : '../' + fileInfo.file_link1;
        }

        const targetW = fieldConfig.size?.[0]?.w || 800;
        const targetH = fieldConfig.size?.[0]?.h || 600;

        // --- ⭐ 自動生成提示文字 (建議尺寸 + 大小限制) ⭐ ---
        // 優先使用 fieldConfig 的設定，其次使用 this.config 的全域設定
        // 注意：size 可能是陣列，maxSize 可能在陣列的屬性中
        let effMaxSize = fieldConfig.maxSize;
        if (!effMaxSize && fieldConfig.size) {
            effMaxSize = fieldConfig.size.maxSize;
        }
        if (!effMaxSize && this.config.maxSize) {
            effMaxSize = this.config.maxSize;
        }
        if (!effMaxSize && this.config.size) {
            effMaxSize = this.config.size.maxSize;
        }
        if (!effMaxSize && typeof DEFAULT_MAX_IMG_SIZE !== 'undefined') {
            effMaxSize = DEFAULT_MAX_IMG_SIZE;
        }
        if (!effMaxSize) {
            effMaxSize = 2;
        }
        const sizeLimitNote = `(大小限制 ${effMaxSize}MB)`;

        let dimensionNote = "";
        // 優先使用 fieldConfig 的 size，其次使用 this.config 的 size
        const sizeConfig = fieldConfig.size || this.config.size;
        if (sizeConfig) {
            // 處理陣列或物件格式
            if (Array.isArray(sizeConfig)) {
                // 標準陣列格式
                for (const s of sizeConfig) {
                    if (s && s.w && s.h) {
                        dimensionNote = `* 建議尺寸：${s.w}x${s.h}px`;
                        break;
                    }
                }
            } else if (typeof sizeConfig === 'object') {
                // 物件格式（PHP 混合陣列轉換後）
                // 遍歷物件的數字鍵
                for (const key in sizeConfig) {
                    const s = sizeConfig[key];
                    if (s && typeof s === 'object' && s.w && s.h) {
                        dimensionNote = `* 建議尺寸：${s.w}x${s.h}px`;
                        break;
                    }
                }
            }
        }

        const autoNote = (dimensionNote + " " + sizeLimitNote).trim();
        const userNote = (fieldConfig.note === '*' || !fieldConfig.note) ? "" : fieldConfig.note;
        const finalNote = [autoNote, userNote].filter(n => n).join('<br>');

        return `
            <div class="form-group">
                <label for="${fieldId}">
                    ${fieldConfig.label}
                    ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                </label>
                <input type="hidden" name="${baseName}[file_id]" value="${fileId}" id="${fieldId}_file_id" ${required}>

                <div style="display: flex; align-items: flex-start; margin-bottom: 10px;">
                    <div style="width:100px; height:100px; margin-right: 15px; border: 1px solid #ddd; overflow: hidden;">
                        ${hasExistingImage ? `
                            <a href="${imageLink}" class="fancyboxImg" rel="group_${fieldId}" title="${fileInfo.file_title || ''}">
                                <img src="${imageSrc}" id="croppedImagePreview${fieldId}" style="width:100%; height:100%; object-fit: cover; cursor: pointer;">
                            </a>
                        ` : `
                            <img src="${imageSrc}" id="croppedImagePreview${fieldId}" style="width:100%; height:100%; object-fit: cover;">
                        `}
                    </div>
                    <div>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <input type="file"
                                   id="${uploaderId}"
                                   name="${uploadFieldName}"
                                   class="hidden-file-input"
                                   style="display:none;"
                                   accept="image/*"
                                   ${required}>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button"
                                        class="trigger-crop-btn btn btn-default"
                                        data-target="${uploaderId}">
                                    選擇檔案
                                </button>
                            </div>
                            <a href="javascript:void(0)"
                               class="btn-remove-single-image"
                               data-action="remove-single-image"
                               data-uploader-id="${uploaderId}"
                               data-field-id="${fieldId}"
                               data-uid="${uid}"
                               data-field-name="${fieldConfig.name}"
                               id="remove_btn_${fieldId}"
                               style="color:red; text-decoration:none; font-size:14px; margin-top:5px; display:${hasExistingImage ? 'inline-block' : 'none'};">
                                <i class="fas fa-times-circle"></i> 移除
                            </a>

                        </div>
                        <div style="margin-top: 5px;">
                            <p id="fileNameDisplay${fieldId}" class="file-name-display" style="display:none; font-size:0.9rem; color:#555;margin: 0;">未選擇</p>
                            <p id="uploadStatus${fieldId}" class="status-msg" style="font-size:0.9rem; color:blue; margin:5px 0 0 0;"></p>
                            <input type="hidden" id="imageUrl${fieldId}" class="url-input">
                        </div>
                    </div>
                </div>
                <div style="margin-top:5px; display: flex; align-items: center;">
                    <span class="table_data" style="flex-shrink:0;">圖片說明：</span>
                    <input type="text"
                           id="title_${fieldId}"
                           name="${baseName}[title]"
                           value="${this.escapeHtml(fileInfo?.file_title || '')}"
                           class="table_data"
                           style="width: 300px; padding: 4px; border: 1px solid #ccc;">
                </div>
                ${finalNote ? `<label class="error mt-2">${finalNote}</label>` : ''}
            </div>
        `;
    }

    renderMultipleImageField(fieldConfig, value, groupIndex, group, uid) {
        const fieldName = `${this.config.field}[${groupIndex}][${fieldConfig.name}]`;
        // ⭐ 改用 UID 作為 ID 的一部分
        const fieldId = `${this.config.field}_${uid}_${fieldConfig.name}`;
        const required = fieldConfig.required ? 'required' : '';

        // --- ⭐ 自動生成提示文字 (建議尺寸 + 大小限制) ⭐ ---
        // 優先使用 fieldConfig 的設定，其次使用 this.config 的全域設定
        // 注意：size 可能是陣列，maxSize 可能在陣列的屬性中
        let effMaxSize = fieldConfig.maxSize;
        if (!effMaxSize && fieldConfig.size) {
            effMaxSize = fieldConfig.size.maxSize;
        }
        if (!effMaxSize && this.config.maxSize) {
            effMaxSize = this.config.maxSize;
        }
        if (!effMaxSize && this.config.size) {
            effMaxSize = this.config.size.maxSize;
        }
        if (!effMaxSize && typeof DEFAULT_MAX_IMG_SIZE !== 'undefined') {
            effMaxSize = DEFAULT_MAX_IMG_SIZE;
        }
        if (!effMaxSize) {
            effMaxSize = 2;
        }
        const sizeLimitNote = `(大小限制 ${effMaxSize}MB)`;

        let dimensionNote = "";
        // 優先使用 fieldConfig 的 size，其次使用 this.config 的 size
        const sizeConfig = fieldConfig.size || this.config.size;
        if (sizeConfig) {
            // 處理陣列或物件格式
            if (Array.isArray(sizeConfig)) {
                // 標準陣列格式
                for (const s of sizeConfig) {
                    if (s && s.w && s.h) {
                        dimensionNote = `* 建議尺寸：${s.w}x${s.h}px`;
                        break;
                    }
                }
            } else if (typeof sizeConfig === 'object') {
                // 物件格式（PHP 混合陣列轉換後）
                // 遍歷物件的數字鍵
                for (const key in sizeConfig) {
                    const s = sizeConfig[key];
                    if (s && typeof s === 'object' && s.w && s.h) {
                        dimensionNote = `* 建議尺寸：${s.w}x${s.h}px`;
                        break;
                    }
                }
            }
        }

        const autoNote = (dimensionNote + " " + sizeLimitNote).trim();
        const userNote = (fieldConfig.note === '*' || !fieldConfig.note) ? "" : fieldConfig.note;
        const finalNote = [autoNote, userNote].filter(n => n).join('<br>');

        // value 應該是陣列，每個元素包含 file_id 和 file_info
        const images = Array.isArray(value) ? value : (value ? [value] : []);

        let html = `
            <div class="form-group">
                <label for="${fieldId}">
                    ${fieldConfig.label}
                    ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                </label>
                <div id="draggable_${fieldId}" class="dynamic-image-container" data-group-index="${groupIndex}" data-field-name="${fieldConfig.name}" data-group-uid="${uid}">
        `;

        // 渲染現有圖片
        images.forEach((img, imgIndex) => {
            html += this.renderSingleImageInMultiple(fieldConfig, img, groupIndex, uid, imgIndex);
        });

        html += `
                </div>
                <div style="margin-top:10px;">
                    <a href="javascript:void(0)"
                       class="btn-add-image-link"
                       data-group-uid="${uid}"
                       data-field-name="${fieldConfig.name}"
                       data-action="add-image">
                        <img src="image/add.png" width="16" height="16" border="0" style="vertical-align:middle;"> 新增圖片
                    </a>
                </div>
                ${finalNote ? `<label class="error mt-2">${finalNote}</label>` : ''}
            </div>
        `;

        return html;
    }

    renderSingleImageInMultiple(fieldConfig, imageData, groupIndex, uid, imgIndex) {
        // ⭐ 改用 UID 作為 ID 的一部分
        const fieldId = `${this.config.field}_${uid}_${fieldConfig.name}_${imgIndex}`;
        const uploaderId = `df_${fieldId}`;
        const uploadFieldName = `${this.config.field}[${uid}][${fieldConfig.name}_upload][${imgIndex}]`;

        const fileId = imageData?.file_id || '';
        const fileInfo = imageData?.file_info || null;
        const hasExistingImage = fileInfo && fileInfo.file_link2;

        let imageSrc = 'crop/demo.jpg';
        let imageLink = '#';

        if (hasExistingImage) {
            imageSrc = fileInfo.file_link2.startsWith('http') ? fileInfo.file_link2 : '../' + fileInfo.file_link2;
            imageLink = fileInfo.file_link1.startsWith('http') ? fileInfo.file_link1 : '../' + fileInfo.file_link1;
        }

        // ${imgIndex > 0 ? `
        //     <a href="javascript:void(0)"
        //         class="btn-delete-image"
        //         data-group-index="${groupIndex}"
        //         data-field-name="${fieldConfig.name}"
        //         data-img-index="${imgIndex}"
        //         data-action="delete-image"
        //         style="color:#666; font-size:16px;"
        //         title="刪除圖片">
        //         <i class="fas fa-trash-alt"></i>
        //     </a>
        // ` : ''}
        return `
            <div class="dynamic-image-item" data-img-index="${imgIndex}" style="display: flex; align-items: flex-start; margin-bottom: 15px; position: relative;">
                <div class="image-drag-handle" style="margin-right:10px; cursor:move; color:#ccc; padding-top: 10px;" title="拖曳排序">
                    <i class="fas fa-grip-vertical"></i>
                </div>
                <input type="hidden" name="${this.config.field}[${groupIndex}][${fieldConfig.name}][${imgIndex}][file_id]" value="${fileId}" id="${fieldId}_file_id">
                <div style="width:100px; height:100px; margin-right: 15px; border: 1px solid #ddd; overflow: hidden;">
                    ${hasExistingImage ? `
                        <a href="${imageLink}" class="fancyboxImg" rel="group_${fieldId}" title="${fileInfo.file_title || ''}">
                            <img src="${imageSrc}" id="croppedImagePreview${fieldId}" style="width:100%; height:100%; object-fit: cover; cursor: pointer;">
                        </a>
                    ` : `
                        <img src="${imageSrc}" id="croppedImagePreview${fieldId}" style="width:100%; height:100%; object-fit: cover;">
                    `}
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; flex-direction: column; gap: 5px;">
                        <input type="file"
                               id="${uploaderId}"
                               name="${uploadFieldName}"
                               class="hidden-file-input"
                               style="display:none;"
                               accept="image/*">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button"
                                    class="trigger-crop-btn btn btn-default"
                                    data-target="${uploaderId}">
                                選擇檔案
                            </button>
                        </div>
                        <a href="javascript:void(0)"
                           onclick="
                               if (window.dynamicFieldUploaders && window.dynamicFieldUploaders['${uploaderId}']) {
                                   window.dynamicFieldUploaders['${uploaderId}'].reset();
                               }
                               var hidden = document.getElementById('${fieldId}_file_id');
                               if (hidden) {
                                   hidden.value = '__DELETE__';
                               }
                               var img = document.getElementById('croppedImagePreview${fieldId}');
                               if (img) {
                                   img.src = 'crop/demo.jpg';
                               }
                               this.style.display = 'none';
                           "
                           id="remove_btn_${fieldId}"
                           style="color:red; text-decoration:none; font-size:14px; display:${hasExistingImage ? 'inline-block' : 'none'};">
                            <i class="fas fa-times-circle"></i> 移除
                        </a>
                    </div>
                    <div style="margin-top: 5px;">
                        <p id="fileNameDisplay${fieldId}" class="file-name-display" style="display:none; font-size:0.9rem; color:#555;margin: 0;">未選擇</p>
                        <p id="uploadStatus${fieldId}" class="status-msg" style="font-size:0.9rem; color:blue; margin:5px 0 0 0;"></p>
                        <input type="hidden" id="imageUrl${fieldId}" class="url-input">
                    </div>
                    <div style="margin-top:5px; display: flex; align-items: center;">
                        <span class="table_data" style="flex-shrink:0; margin-right: 5px;">圖片說明：</span>
                        <input type="text"
                               id="title_${fieldId}"
                               name="${this.config.field}[${groupIndex}][${fieldConfig.name}][${imgIndex}][title]"
                               value="${this.escapeHtml(fileInfo?.file_title || '')}"
                               class="table_data"
                               style="width: 300px; padding: 4px; border: 1px solid #ccc;">
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        // 使用事件委派，綁定在最外層容器
        this.container.addEventListener('click', (e) => {

            // 新增群組
            const addBtn = e.target.closest('[data-action="add-group"]');
            if (addBtn) {
                e.preventDefault();
                this.addGroup();
                return;
            }

            // 收合/展開群組
            const toggleBtn = e.target.closest('[data-action="toggle-group"]');
            if (toggleBtn) {
                e.preventDefault();
                const index = parseInt(toggleBtn.dataset.index);
                this.toggleGroup(index);
                return;
            }

            // 刪除群組
            const removeBtn = e.target.closest('[data-action="remove-group"]');
            if (removeBtn) {
                e.preventDefault();
                const index = parseInt(removeBtn.dataset.index);
                this.removeGroup(index);
                return;
            }

            // 新增圖片（多圖模式）
            const addImageBtn = e.target.closest('[data-action="add-image"]');
            if (addImageBtn) {
                e.preventDefault();
                const groupUid = addImageBtn.dataset.groupUid;
                const fieldName = addImageBtn.dataset.fieldName;
                this.addImageToGroup(groupUid, fieldName);
                return;
            }

            // 刪除圖片（多圖模式）
            const deleteImageBtn = e.target.closest('[data-action="delete-image"]');
            if (deleteImageBtn) {
                e.preventDefault();
                const groupIndex = parseInt(deleteImageBtn.dataset.groupIndex);
                const fieldName = deleteImageBtn.dataset.fieldName;
                const imgIndex = parseInt(deleteImageBtn.dataset.imgIndex);
                this.deleteImageFromGroup(groupIndex, fieldName, imgIndex);
                return;
            }

            // 新增檔案（多檔模式）
            const addFileBtn = e.target.closest('[data-action="add-file"]');
            if (addFileBtn) {
                e.preventDefault();
                const groupUid = addFileBtn.dataset.groupUid;
                const fieldName = addFileBtn.dataset.fieldName;
                this.addFileToGroup(groupUid, fieldName);
                return;
            }

            // 刪除檔案（多檔模式）
            const removeFileBtn = e.target.closest('[data-action="remove-file"]');
            if (removeFileBtn) {
                e.preventDefault();
                const groupIndex = parseInt(removeFileBtn.dataset.groupIndex);
                const fieldName = removeFileBtn.dataset.fieldName;
                const fileIndex = parseInt(removeFileBtn.dataset.fileIndex);
                this.removeFileFromGroup(groupIndex, fieldName, fileIndex);
                return;
            }

            // 移除單張圖片
            const removeSingleBtn = e.target.closest('[data-action="remove-single-image"]');
            if (removeSingleBtn) {
                e.preventDefault();
                const uploaderId = removeSingleBtn.dataset.uploaderId;
                const fieldId = removeSingleBtn.dataset.fieldId;
                const uid = removeSingleBtn.dataset.uid;
                const fieldName = removeSingleBtn.dataset.fieldName;
                this.removeSingleImage(uploaderId, fieldId, uid, fieldName, removeSingleBtn);
                return;
            }

            // ⭐ 不需要處理 trigger-crop-btn，讓 ImageUploader 自己處理
            // ImageUploader 的 bindEvents() 已經綁定了 triggerBtn 的點擊事件
        });

    }

    initSortable() {
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) return;

        // 先銷毀舊的 sortable
        if (typeof $ !== 'undefined' && $.fn.sortable) {
            try {
                $(container).sortable('destroy');
            } catch (e) {
                // 忽略錯誤
            }

            // 重新初始化
            $(container).sortable({
                handle: '.drag-handle',
                items: '.dynamic-field-group',
                placeholder: 'sortable-placeholder',
                tolerance: 'pointer',
                cursor: 'move',
                appendTo: 'body',  // ⭐ 關鍵：將 helper 附加到 body，避免容器滾動影響
                helper: function(event, element) {
                    // 創建一個複製品，保持容器的完整寬度
                    const containerWidth = $(container).width();
                    const clone = element.clone();

                    // ⭐ 關鍵：保持所有內部元素的樣式
                    clone.css({
                        'width': containerWidth + 'px',
                        'height': 'auto',
                        'box-sizing': 'border-box'
                    });

                    return clone;
                },
                opacity: 0.6,
                revert: 150,
                scroll: true,
                scrollSensitivity: 40,
                scrollSpeed: 40,
                axis: 'y',
                forcePlaceholderSize: true,
                // 拖曳開始時
                start: (event, ui) => {
                    // 記錄原始高度和寬度
                    const itemHeight = ui.item.outerHeight();
                    const containerWidth = $(container).width();

                    // ⭐ 記錄容器的 left offset（相對於頁面）
                    const containerOffset = $(container).offset();
                    ui.item.data('containerLeft', containerOffset.left);

                    // 設定佔位符
                    ui.placeholder.height(itemHeight);
                    ui.placeholder.css({
                        'visibility': 'visible',
                        'margin-bottom': '25px',
                        'width': '100%',
                        'box-sizing': 'border-box'
                    });

                    // 設定 helper 的樣式
                    ui.helper.css({
                        'opacity': '0.6',
                        'box-shadow': '0 5px 15px rgba(0,0,0,0.3)',
                        'z-index': '1000',
                        'width': containerWidth + 'px',
                        'box-sizing': 'border-box',
                        'left': containerOffset.left + 'px'  // ⭐ 設定正確的 left 位置
                    });
                },
                // ⭐ 拖曳過程中保持 X 軸位置
                sort: (event, ui) => {
                    const containerLeft = ui.item.data('containerLeft');
                    if (containerLeft !== undefined) {
                        ui.helper.css('left', containerLeft + 'px');
                    }
                },
                update: (event, ui) => {
                    this.updateGroupOrder();
                }
            });
        }
    }

    // 在 dynamicFieldsEditor.js 中修正此函式
    syncDataFromDom() {
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) return;

        const items = container.querySelectorAll('.dynamic-field-group');

        items.forEach((item, loopIndex) => {
            const originalIndex = parseInt(item.dataset.groupIndex);
            const groupData = this.groups[originalIndex];
            if (!groupData) return;

            this.config.fields.forEach(field => {
                if (field.type === 'image' || field.type === 'file') {
                    const isMultiple = field.multiple || false;
                    
                    if (isMultiple) {
                        const mediaArray = [];
                        const selector = field.type === 'image' ? '.dynamic-image-item' : '.dynamic-file-item';
                        const mediaItems = item.querySelectorAll(selector);
                        
                        mediaItems.forEach(mediaItem => {
                            const fileIdInput = mediaItem.querySelector('input[type="hidden"]');
                            const titleInput = mediaItem.querySelector('input[type="text"]');
                            if (fileIdInput) {
                                mediaArray.push({
                                    file_id: fileIdInput.value,
                                    file_info: {
                                        file_title: titleInput ? titleInput.value : ''
                                    }
                                });
                            }
                        });
                        groupData[field.name] = mediaArray;
                    } else {
                        // 單圖/單檔模式
                        // 包含處理單檔的 file_id_hidden 和 file_id (checkbox)
                        const fileIdInput = item.querySelector(`input[name*="[${field.name}][file_id_hidden]"]`) || item.querySelector(`input[name*="[${field.name}][file_id]"]`);
                        const titleInput = item.querySelector(`input[name*="[${field.name}][title]"]`);
                        const deleteCheck = item.querySelector(`input[type="checkbox"][name*="[${field.name}][file_id]"]`);
                        
                        let currentVal = fileIdInput ? fileIdInput.value : '';
                        if (deleteCheck && deleteCheck.checked) {
                            currentVal = '__DELETE__';
                        }

                        if (typeof groupData[field.name] === 'object' && groupData[field.name] !== null) {
                            groupData[field.name].file_id = currentVal;
                            if (currentVal === '__DELETE__') {
                                groupData[field.name].file_info = null;
                            } else if (titleInput) {
                                if (!groupData[field.name].file_info) groupData[field.name].file_info = {};
                                groupData[field.name].file_info.file_title = titleInput.value;
                            }
                        } else {
                            groupData[field.name] = {
                                file_id: currentVal,
                                file_info: titleInput ? { file_title: titleInput.value } : null
                            };
                        }
                    }
                } else {
                    // 文字欄位
                    const input = item.querySelector(`[name*="[${field.name}]"]`);
                    if (input) {
                        groupData[field.name] = input.value;
                    }
                }
            });
        });
    }

    // ... (updateGroupOrder kept same) ...

    /**
     * 更新圖片相關的其他元素（預覽圖、狀態文字等）
     */
    updateImageRelatedElements(container, newFieldId) {
        // 更新預覽圖片
        const previewImg = container.querySelector('img[id^="croppedImagePreview"]');
        if (previewImg) {
            previewImg.id = `croppedImagePreview${newFieldId}`;
        }

        // 更新檔名顯示
        const fileNameDisplay = container.querySelector('.file-name-display');
        if (fileNameDisplay) {
            fileNameDisplay.id = `fileNameDisplay${newFieldId}`;
        }

        // 更新狀態訊息
        const uploadStatus = container.querySelector('.status-msg');
        if (uploadStatus) {
            uploadStatus.id = `uploadStatus${newFieldId}`;
        }

        // 更新 URL input
        const imageUrlInput = container.querySelector('.url-input');
        if (imageUrlInput) {
            imageUrlInput.id = `imageUrl${newFieldId}`;
        }

        // 更新移除按鈕
        const removeBtn = container.querySelector('[id^="remove_btn_"]');
        if (removeBtn) {
            removeBtn.id = `remove_btn_${newFieldId}`;
            // ⭐ 關鍵修正：更新 dataset 以便移除功能正常運作
            removeBtn.dataset.fieldId = newFieldId;
            removeBtn.dataset.uploaderId = `df_${newFieldId}`;
        }
    }


    updateGroupOrder() {
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) return;

        // ⭐ 關鍵：排序前先同步資料
        this.syncDataFromDom();

        const items = container.querySelectorAll('.dynamic-field-group');
        const newGroups = [];

        items.forEach((item) => {
            const oldIndex = parseInt(item.dataset.groupIndex);
            if (this.groups[oldIndex]) {
                newGroups.push(this.groups[oldIndex]);
            }
        });

        this.groups = newGroups;
        this.updateGroupIndices();
        
        // 這裡不需要再呼叫原本錯誤的 syncImageFileIdsFromGroups 了
    }

    updateGroupIndices() {
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) return;

        const items = container.querySelectorAll('.dynamic-field-group');

        items.forEach((item, newIndex) => {
            const group = this.groups[newIndex];
            if (!group) return;

            // 更新 data-group-index
            item.dataset.groupIndex = newIndex;

            // 更新項目編號顯示
            const groupNumber = item.querySelector('.group-number');
            if (groupNumber) {
                groupNumber.textContent = `項目 ${newIndex + 1}`;
            }

            // 更新按鈕的 data-index
            const toggleBtn = item.querySelector('[data-action="toggle-group"]');
            if (toggleBtn) {
                toggleBtn.dataset.index = newIndex;
            }

            const removeBtn = item.querySelector('[data-action="remove-group"]');
            if (removeBtn) {
                removeBtn.dataset.index = newIndex;
            }

            // ★ 更新 _uid hidden 欄位的 name
            const uidInput = item.querySelector('input[type="hidden"][name$="[_uid]"]');
            if (uidInput) {
                uidInput.name = `${this.config.field}[${newIndex}][_uid]`;
            }

            // ⭐ 完整更新所有欄位的 name 和 id
            this.config.fields.forEach(field => {
                if (field.type === 'image') {
                    this.updateImageFieldIndices(item, newIndex, field, group);
                } else if (field.type === 'file') {
                    this.updateFileFieldIndices(item, newIndex, field, group);
                } else {
                    this.updateTextFieldIndices(item, newIndex, field);
                }
            });
        });

        // ⭐ 關鍵修正：更新後重新初始化所有 ImageUploader
        
        // ⭐ 使用 UID 後，不需要重新初始化，因為 ID 沒變
        // this.reinitAllImageUploaders();
    }

    /**
     * 更新文字欄位的索引
     */
    updateTextFieldIndices(item, newIndex, field) {
        const oldPattern = new RegExp(`${this.config.field}\\[\\d+\\]\\[${field.name}\\]`, 'g');
        const newFieldName = `${this.config.field}[${newIndex}][${field.name}]`;
        const newFieldId = `${this.config.field}_${newIndex}_${field.name}`;

        // 更新 input/textarea
        const input = item.querySelector(`[name*="[${field.name}]"]`);
        if (input) {
            input.name = newFieldName;
            input.id = newFieldId;
        }

        // 更新 label
        const label = item.querySelector(`label[for*="${field.name}"]`);
        if (label) {
            label.htmlFor = newFieldId;
        }
    }

    /**
     * 更新圖片欄位的索引（包含單圖和多圖）
     */
    updateImageFieldIndices(item, newIndex, field, group) {
        const isMultiple = field.multiple || false;

        if (isMultiple) {
            // 多圖模式
            this.updateMultipleImageFieldIndices(item, newIndex, field, group);
        } else {
            // 單圖模式
            this.updateSingleImageFieldIndices(item, newIndex, field, group);
        }
    }

    /**
     * 更新單圖欄位的索引
     */
    updateSingleImageFieldIndices(item, newIndex, field, group) {
        // ⭐ 使用 UID，不再需要更新 ID，只需要更新 name
        
        // 更新 file_id hidden input
        const fileIdInput = item.querySelector(`input[name*="[${field.name}][file_id]"]`);
        if (fileIdInput) {
            fileIdInput.name = `${this.config.field}[${newIndex}][${field.name}][file_id]`;
        }

        // 更新 file input (上傳欄位)
        const fileInput = item.querySelector(`input[type="file"][name*="[${field.name}_upload]"]`);
        if (fileInput) {
            fileInput.name = `${this.config.field}[${group._uid}][${field.name}_upload]`;
        }

        // 更新圖片說明欄位
        const titleInput = item.querySelector(`input[name*="[${field.name}][title]"]`);
        if (titleInput) {
            titleInput.name = `${this.config.field}[${newIndex}][${field.name}][title]`;
        }
    }

    /**
     * 更新多圖欄位的索引
     */
    updateMultipleImageFieldIndices(item, newIndex, field, group) {
        const container = item.querySelector('[id^="draggable_"]');
        if (!container) return;

        // 更新容器屬性
        container.dataset.groupIndex = newIndex;

        // 更新每張圖片
        const imageItems = container.querySelectorAll('.dynamic-image-item');
        imageItems.forEach((imageItem, imgIndex) => {
            // 更新 file_id hidden input
            const fileIdInput = imageItem.querySelector('input[type="hidden"]');
            if (fileIdInput) {
                fileIdInput.name = `${this.config.field}[${newIndex}][${field.name}][${imgIndex}][file_id]`;
            }

            // 更新 file input
            const fileInput = imageItem.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.name = `${this.config.field}[${group._uid}][${field.name}_upload][${imgIndex}]`;
            }

            // 更新圖片說明
            const titleInput = imageItem.querySelector('input[type="text"]');
            if (titleInput) {
                titleInput.name = `${this.config.field}[${newIndex}][${field.name}][${imgIndex}][title]`;
            }

            // 更新刪除按鈕
            const deleteBtn = imageItem.querySelector('.btn-delete-image');
            if (deleteBtn) {
                deleteBtn.dataset.groupIndex = newIndex;
                deleteBtn.dataset.imgIndex = imgIndex;
            }
        });
    }

    /**
     * 更新圖片相關的其他元素（預覽圖、狀態文字等）
     */
    updateImageRelatedElements(container, newFieldId) {
        // 更新預覽圖片
        const previewImg = container.querySelector('img[id^="croppedImagePreview"]');
        if (previewImg) {
            previewImg.id = `croppedImagePreview${newFieldId}`;
        }

        // 更新檔名顯示
        const fileNameDisplay = container.querySelector('.file-name-display');
        if (fileNameDisplay) {
            fileNameDisplay.id = `fileNameDisplay${newFieldId}`;
        }

        // 更新狀態訊息
        const uploadStatus = container.querySelector('.status-msg');
        if (uploadStatus) {
            uploadStatus.id = `uploadStatus${newFieldId}`;
        }

        // 更新 URL input
        const imageUrlInput = container.querySelector('.url-input');
        if (imageUrlInput) {
            imageUrlInput.id = `imageUrl${newFieldId}`;
        }

        // 更新移除按鈕
        const removeBtn = container.querySelector('[id^="remove_btn_"]');
        if (removeBtn) {
            removeBtn.id = `remove_btn_${newFieldId}`;
        }
    }

    /**
     * 渲染檔案欄位
     */
    renderFileField(fieldConfig, value, groupIndex, group) {
        const uid = group && group._uid ? group._uid : (this.groups[groupIndex] && this.groups[groupIndex]._uid ? this.groups[groupIndex]._uid : this.generateUID());
        if (group && !group._uid) { group._uid = uid; }

        const isMultiple = fieldConfig.multiple || false;

        if (isMultiple) {
            return this.renderMultipleFileField(fieldConfig, value, groupIndex, group, uid);
        }

        // 單檔模式
        const baseName = `${this.config.field}[${groupIndex}][${fieldConfig.name}]`;
        const uploadFieldName = `${this.config.field}[${uid}][${fieldConfig.name}_upload]`;
        const fieldId = `${this.config.field}_${uid}_${fieldConfig.name}`;
        
        const fileId = value?.file_id || '';
        const fileInfo = value?.file_info || null;
        const required = fieldConfig.required ? 'required' : '';
        const accept = fieldConfig.format || '*';
        const hasExistingFile = fileInfo && fileInfo.file_link1;

        // 生成提示文字
        let effMaxSize = fieldConfig.maxSize;
        if (!effMaxSize && fieldConfig.size) {
            effMaxSize = fieldConfig.size.maxSize;
        }
        if (!effMaxSize && this.config.maxSize) {
            effMaxSize = this.config.maxSize;
        }
        if (!effMaxSize && this.config.size) {
            effMaxSize = this.config.size.maxSize;
        }
        if (!effMaxSize && typeof DEFAULT_MAX_IMG_SIZE !== 'undefined') {
            effMaxSize = DEFAULT_MAX_IMG_SIZE;
        }
        if (!effMaxSize) {
            effMaxSize = 2;
        }
        const sizeLimitNote = `(大小限制 ${effMaxSize}MB)`;
        const formatNote = (accept && accept !== '*') ? `* 支援格式：${accept.replace(/\./g, '')}` : "";
        const autoNote = (formatNote + " " + sizeLimitNote).trim();
        const userNote = (fieldConfig.note === '*' || !fieldConfig.note) ? "" : fieldConfig.note;
        const finalNote = [autoNote, userNote].filter(n => n).join('<br>');

        return `
            <div class="form-group file-upload-wrapper" data-accept="${accept}" data-max-size="${effMaxSize}">
                <label for="${fieldId}">
                    ${fieldConfig.label}
                    ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                </label>
                
                <div class="file-field-container">
                    ${hasExistingFile ? `
                        <div class="mb-2 d-flex align-items-center" id="file_display_${fieldId}">
                            <a href="../${fileInfo.file_link1}" target="_blank" class="me-3 text-decoration-none">
                                <i class="fas fa-file-alt me-1"></i> ${fileInfo.file_name}
                            </a>
                            <input type="text" 
                                   name="${baseName}[title]" 
                                   value="${this.escapeHtml(fileInfo.file_title || '')}" 
                                   class="form-control form-control-sm me-3" 
                                   style="width: 200px;" 
                                   placeholder="檔案說明">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="${baseName}[file_id]" 
                                       value="__DELETE__" 
                                       id="del_${fieldId}"
                                       onchange="document.getElementById('file_input_container_${fieldId}').style.display = this.checked ? 'flex' : 'none'">
                                <label class="form-check-label text-danger cursor-pointer" for="del_${fieldId}">刪除</label>
                                <input type="hidden" name="${baseName}[file_id_hidden]" value="${fileId}" id="hidden_${fieldId}">
                            </div>
                        </div>
                    ` : `
                        <input type="hidden" name="${baseName}[file_id_hidden]" value="" id="hidden_${fieldId}">
                    `}

                    <div id="file_input_container_${fieldId}" class="align-items-center" style="${hasExistingFile ? 'display:none;' : 'display:flex;'}">
                        <input type="file" 
                               name="${uploadFieldName}" 
                               id="file_input_${fieldId}"
                               class="form-control me-2 file-input" 
                               accept="${accept}" 
                               ${!hasExistingFile && fieldConfig.required ? 'required' : ''}
                               style="width: auto; flex: 1;">
                        <input type="text" 
                               name="${baseName}[title]" 
                               class="form-control" 
                               style="width: 200px;" 
                               placeholder="檔案說明">
                    </div>
                </div>

                ${finalNote ? `<label class="error mt-2">${finalNote}</label>` : ''}
            </div>
        `;
    }

    /**
     * 渲染多檔欄位
     */
    renderMultipleFileField(fieldConfig, value, groupIndex, group, uid) {
        const fieldId = `${this.config.field}_${uid}_${fieldConfig.name}`;
        const accept = fieldConfig.format || '*';
        let effMaxSize = fieldConfig.maxSize;
        if (!effMaxSize && fieldConfig.size) {
            effMaxSize = fieldConfig.size.maxSize;
        }
        if (!effMaxSize && this.config.maxSize) {
            effMaxSize = this.config.maxSize;
        }
        if (!effMaxSize && this.config.size) {
            effMaxSize = this.config.size.maxSize;
        }
        if (!effMaxSize && typeof DEFAULT_MAX_IMG_SIZE !== 'undefined') {
            effMaxSize = DEFAULT_MAX_IMG_SIZE;
        }
        if (!effMaxSize) {
            effMaxSize = 2;
        }
        
        const files = Array.isArray(value) ? value : (value ? [value] : []);

        let html = `
            <div class="form-group file-upload-wrapper" data-accept="${accept}" data-max-size="${effMaxSize}">
                <label>
                    ${fieldConfig.label}
                    ${fieldConfig.required ? '<span class="required">*</span>' : ''}
                </label>
                <div id="file_list_${fieldId}" class="dynamic-file-list" data-group-index="${groupIndex}" data-field-name="${fieldConfig.name}" data-group-uid="${uid}">
        `;

        files.forEach((file, fileIndex) => {
            html += this.renderSingleFileInMultiple(fieldConfig, file, groupIndex, uid, fileIndex);
        });

        const sizeLimitNote = `(大小限制 ${effMaxSize}MB)`;
        const formatNote = (accept && accept !== '*') ? `* 支援格式：${accept.replace(/\./g, '')}` : "";
        const autoNote = (formatNote + " " + sizeLimitNote).trim();
        const userNote = (fieldConfig.note === '*' || !fieldConfig.note) ? "" : fieldConfig.note;
        const finalNote = [autoNote, userNote].filter(n => n).join('<br>');

        html += `
                </div>
                <div style="margin-top:10px;">
                    <button type="button" class="btn btn-default btn-sm" 
                            data-action="add-file" 
                            data-group-uid="${uid}" 
                            data-field-name="${fieldConfig.name}">
                        <i class="fas fa-plus me-1"></i> 新增檔案
                    </button>
                </div>
                ${finalNote ? `<label class="error mt-2">${finalNote}</label>` : ''}
            </div>
        `;

        return html;
    }

    renderSingleFileInMultiple(fieldConfig, fileData, groupIndex, uid, fileIndex) {
        const baseName = `${this.config.field}[${groupIndex}][${fieldConfig.name}][${fileIndex}]`;
        const uploadFieldName = `${this.config.field}[${uid}][${fieldConfig.name}_upload][${fileIndex}]`;
        
        const fileId = fileData?.file_id || '';
        const fileInfo = fileData?.file_info || null;
        const accept = fieldConfig.format || '*';
        const hasExistingFile = fileInfo && fileInfo.file_link1;

        return `
            <div class="dynamic-file-item mb-2 d-flex align-items-center" data-file-index="${fileIndex}">
                <div class="file-drag-handle me-2" style="cursor:move; color:#ccc;"><i class="fas fa-grip-vertical"></i></div>
                <input type="hidden" name="${baseName}[file_id]" value="${fileId}">
                
                ${hasExistingFile ? `
                    <a href="../${fileInfo.file_link1}" target="_blank" class="me-2 text-decoration-none" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                        <i class="fas fa-file-alt me-1"></i> ${fileInfo.file_name}
                    </a>
                ` : `
                    <input type="file" name="${uploadFieldName}" class="form-control form-control-sm me-2 file-input" accept="${accept}" style="width: auto; flex: 1;">
                `}

                <input type="text" 
                       name="${baseName}[title]" 
                       value="${this.escapeHtml(fileInfo?.file_title || '')}" 
                       class="form-control form-control-sm me-2" 
                       style="width: 150px;" 
                       placeholder="檔案說明">
                
                <button type="button" class="btn btn-danger btn-sm" 
                        data-action="remove-file" 
                        data-group-index="${groupIndex}" 
                        data-field-name="${fieldConfig.name}" 
                        data-file-index="${fileIndex}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }

    /**
     * 更新檔案欄位的索引
     */
    updateFileFieldIndices(item, newIndex, field, group) {
        const isMultiple = field.multiple || false;

        if (isMultiple) {
            this.updateMultipleFileFieldIndices(item, newIndex, field, group);
        } else {
            this.updateSingleFileFieldIndices(item, newIndex, field, group);
        }
    }

    updateSingleFileFieldIndices(item, newIndex, field, group) {
        const baseName = `${this.config.field}[${newIndex}][${field.name}]`;
        
        const fileIdHidden = item.querySelector(`input[type="hidden"][name$="[${field.name}][file_id_hidden]"]`);
        if (fileIdHidden) fileIdHidden.name = `${baseName}[file_id_hidden]`;

        const titleInputs = item.querySelectorAll(`input[type="text"][name*="[${field.name}][title]"]`);
        titleInputs.forEach(input => {
            input.name = `${baseName}[title]`;
        });

        const deleteCheck = item.querySelector(`input[type="checkbox"][name*="[${field.name}][file_id]"]`);
        if (deleteCheck) deleteCheck.name = `${baseName}[file_id]`;
    }

    updateMultipleFileFieldIndices(item, newIndex, field, group) {
        const container = item.querySelector('.dynamic-file-list');
        if (!container) return;

        container.dataset.groupIndex = newIndex;

        const fileItems = container.querySelectorAll('.dynamic-file-item');
        fileItems.forEach((fileItem, fileIndex) => {
            const baseName = `${this.config.field}[${newIndex}][${field.name}][${fileIndex}]`;
            
            const fileIdHidden = fileItem.querySelector('input[type="hidden"]');
            if (fileIdHidden) fileIdHidden.name = `${baseName}[file_id]`;

            const fileInput = fileItem.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.name = `${this.config.field}[${group._uid}][${field.name}_upload][${fileIndex}]`;
            }

            const titleInput = fileItem.querySelector('input[type="text"]');
            if (titleInput) {
                titleInput.name = `${baseName}[title]`;
            }

            const removeBtn = fileItem.querySelector('[data-action="remove-file"]');
            if (removeBtn) {
                removeBtn.dataset.groupIndex = newIndex;
                removeBtn.dataset.fileIndex = fileIndex;
            }
        });
    }

    addFileToGroup(groupUid, fieldName) {
        const groupIndex = this.groups.findIndex(g => g._uid === groupUid);
        if (groupIndex === -1) return;

        const containerId = `file_list_${this.config.field}_${groupUid}_${fieldName}`;
        const container = document.getElementById(containerId);
        if (!container) return;

        const fieldConfig = this.config.fields.find(f => f.name === fieldName);
        if (!fieldConfig) return;

        const fileIndex = container.querySelectorAll('.dynamic-file-item').length;
        const fileHtml = this.renderSingleFileInMultiple(fieldConfig, null, groupIndex, groupUid, fileIndex);

        const temp = document.createElement('div');
        temp.innerHTML = fileHtml.trim();
        container.appendChild(temp.firstChild);
    }

    removeFileFromGroup(groupIndex, fieldName, fileIndex) {
        const group = this.groups[groupIndex];
        if (!group) return;

        const containerId = `file_list_${this.config.field}_${group._uid}_${fieldName}`;
        const container = document.getElementById(containerId);
        if (!container) return;

        const fileItems = container.querySelectorAll('.dynamic-file-item');
        if (fileItems[fileIndex]) {
            fileItems[fileIndex].remove();
            // 重新編號
            const fieldConfig = this.config.fields.find(f => f.name === fieldName);
            this.updateMultipleFileFieldIndices(container.closest('.dynamic-field-group'), groupIndex, fieldConfig, group);
        }
    }

    /**
     * 重新初始化所有圖片上傳器
     */
    reinitAllImageUploaders() {
        

        // ⭐ 先銷毀所有舊的上傳器和 cropper 實例
        if (window.dynamicFieldUploaders) {
            Object.entries(window.dynamicFieldUploaders).forEach(([uploaderId, uploader]) => {
                try {
                    

                    // 銷毀 cropper 實例
                    if (uploader.cropper) {
                        uploader.cropper.destroy();
                        uploader.cropper = null;
                    }

                    // 關閉任何打開的 modal
                    const modalId = `cropModal${uploader.id}`;
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        $(modal).modal('hide');
                        // 移除 modal backdrop
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                    }

                } catch (e) {
                    console.warn('Failed to destroy uploader:', uploaderId, e);
                }
            });
        }

        // 清空舊的上傳器
        window.dynamicFieldUploaders = {};

        

        // ⭐ 使用 requestAnimationFrame 確保 DOM 更新完成
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                

                // 為每個群組重新初始化
                this.groups.forEach((group, index) => {
                    this.initImageUploadersForGroup(index);
                    this.initImageSortableForGroup(index);
                });

                
                
                
            });
        });
    }

    addGroup(silent = false) {
        // 驗證當前所有項目的必填欄位
        if (!silent && !this.validateAllGroups()) {
            return; // 驗證失敗，不新增
        }

        this.syncDataFromDom();

        const newGroup = { _uid: this.generateUID() };
        this.config.fields.forEach(field => {
            newGroup[field.name] = '';
        });

        this.groups.push(newGroup);
        

        if (!silent) {
            
            const containerId = `${this.config.field}_items`;
            

            const container = document.getElementById(containerId);

            if (container) {
                
                

                const newIndex = this.groups.length - 1;
                const groupHtml = this.renderGroup(newGroup, newIndex);

                // 創建臨時容器來解析 HTML
                const temp = document.createElement('div');
                temp.innerHTML = groupHtml.trim();

                // 找到 dynamic-field-group 元素
                const newGroupElement = temp.querySelector('.dynamic-field-group');

                if (newGroupElement) {
                    
                    

                    // 添加到容器
                    container.appendChild(newGroupElement);

                    
                    

                    // ⭐ 初始化這個群組中的所有圖片上傳器
                    this.initImageUploadersForGroup(newIndex);
                } else {
                    console.error('Failed to find .dynamic-field-group element');
                    
                }
            } else {
                console.error('Container not found with ID:', containerId);
            }
        }
    }

    /**
     * 為指定群組初始化圖片上傳器
     */
    initImageUploadersForGroup(groupIndex) {
        
        
        

        if (!window.dynamicFieldUploaders) {
            window.dynamicFieldUploaders = {};
        }

        // 遍歷配置中的所有欄位
        this.config.fields.forEach(field => {
            

            if (field.type === 'image') {
                const isMultiple = field.multiple || false;

                if (isMultiple) {
                    // 多圖模式：為每張圖片初始化上傳器
                    const group = this.groups[groupIndex];
                    const images = Array.isArray(group[field.name]) ? group[field.name] : [];

                    images.forEach((img, imgIndex) => {
                        // ⭐ 改用 UID
                        const fieldId = `${this.config.field}_${group._uid}_${field.name}_${imgIndex}`;
                        const uploaderId = `df_${fieldId}`;

                        
                        
                        
                        

                        // ⭐ 先銷毀舊的實例（如果存在）
                        if (window.dynamicFieldUploaders[uploaderId]) {
                            try {
                                const oldUploader = window.dynamicFieldUploaders[uploaderId];

                                // 銷毀 cropper
                                if (oldUploader.cropper) {
                                    oldUploader.cropper.destroy();
                                    oldUploader.cropper = null;
                                }

                                // 關閉 modal
                                const modalId = `cropModal${oldUploader.id}`;
                                const modal = document.getElementById(modalId);
                                if (modal) {
                                    $(modal).modal('hide');
                                }

                                delete window.dynamicFieldUploaders[uploaderId];
                            } catch (e) {
                                console.warn('Failed to destroy old uploader:', e);
                            }
                        }

                        // 檢查 DOM 元素是否存在
                        const inputElement = document.getElementById(uploaderId);
                        

                        if (inputElement) {
                            // 取得圖片配置
                            const targetW = field.size?.[0]?.w || 800;
                            const targetH = field.size?.[0]?.h || 600;
                            const cfg = (window.GLOBAL_IMG_CONFIG && window.GLOBAL_IMG_CONFIG[field.fileType || 'image']) || {IW: targetW, IH: targetH};

                            

                            const prefix = 'df';
                            const idSuffix = fieldId;

                            
                            

                            try {
                                // ⭐ 取得 maxSize 設定
                                let maxSize = field.maxSize;
                                if (!maxSize && field.size) {
                                    maxSize = field.size.maxSize;
                                }
                                if (!maxSize && this.config.maxSize) {
                                    maxSize = this.config.maxSize;
                                }
                                if (!maxSize && this.config.size) {
                                    maxSize = this.config.size.maxSize;
                                }
                                if (!maxSize) {
                                    maxSize = 2; // 預設值
                                }

                                const uploader = new ImageUploader(
                                    idSuffix,
                                    cfg.IW,
                                    cfg.IH,
                                    null,
                                    cfg.OW || cfg.IW,
                                    cfg.OH || cfg.IH,
                                    prefix,
                                    maxSize
                                );

                                window.dynamicFieldUploaders[uploaderId] = uploader;
                                
                            } catch (error) {
                                console.error('  ❌ Failed to create ImageUploader:', error);
                            }
                        }
                    });
                } else {
                    // 單圖模式（原有邏輯）
                    const group = this.groups[groupIndex]; // ⭐ 定義 group 變數
                    // ⭐ 改用 UID
                    const fieldId = `${this.config.field}_${group._uid}_${field.name}`;
                    const uploaderId = `df_${fieldId}`;

                    
                    
                    

                    // ⭐ 先銷毀舊的實例（如果存在）
                    if (window.dynamicFieldUploaders[uploaderId]) {
                        try {
                            const oldUploader = window.dynamicFieldUploaders[uploaderId];

                            // 銷毀 cropper
                            if (oldUploader.cropper) {
                                oldUploader.cropper.destroy();
                                oldUploader.cropper = null;
                            }

                            // 關閉 modal
                            const modalId = `cropModal${oldUploader.id}`;
                            const modal = document.getElementById(modalId);
                            if (modal) {
                                $(modal).modal('hide');
                            }

                            delete window.dynamicFieldUploaders[uploaderId];
                        } catch (e) {
                            console.warn('Failed to destroy old uploader:', e);
                        }
                    }

                    // 檢查 DOM 元素是否存在
                    const inputElement = document.getElementById(uploaderId);
                    
                    if (inputElement) {
                        
                    }

                    // ⭐ 關鍵：移除舊的事件監聽器
                    // 找到 trigger button 並替換它（移除所有事件監聽器）
                    const triggerBtn = document.querySelector(`[data-target="${uploaderId}"]`);
                    if (triggerBtn) {
                        const newTriggerBtn = triggerBtn.cloneNode(true);
                        triggerBtn.parentNode.replaceChild(newTriggerBtn, triggerBtn);
                        
                    }

                    // 如果 input 元素存在，也替換它
                    if (inputElement) {
                        const newInputElement = inputElement.cloneNode(true);
                        inputElement.parentNode.replaceChild(newInputElement, inputElement);
                        
                    }

                    // 取得圖片配置
                    const targetW = field.size?.[0]?.w || 800;
                    const targetH = field.size?.[0]?.h || 600;
                    const cfg = (window.GLOBAL_IMG_CONFIG && window.GLOBAL_IMG_CONFIG[field.fileType || 'image']) || {IW: targetW, IH: targetH};

                    

                    // ⭐ 關鍵修改: 拆分 prefix 和 idSuffix
                    // uploaderId = 'df_dynamic_rooms_0_room_image'
                    // 需要拆成 prefix='df' 和 idSuffix='dynamic_rooms_0_room_image'
                    const prefix = 'df';
                    const idSuffix = fieldId; // dynamic_rooms_0_room_image

                    
                    

                    // 創建 ImageUploader 實例
                    try {
                        // ⭐ 取得 maxSize 設定
                        let maxSize = field.maxSize;
                        if (!maxSize && field.size) {
                            maxSize = field.size.maxSize;
                        }
                        if (!maxSize && this.config.maxSize) {
                            maxSize = this.config.maxSize;
                        }
                        if (!maxSize && this.config.size) {
                            maxSize = this.config.size.maxSize;
                        }
                        if (!maxSize) {
                            maxSize = 2; // 預設值
                        }

                        const uploader = new ImageUploader(
                            idSuffix,      // dynamic_rooms_0_room_image
                            cfg.IW,
                            cfg.IH,
                            null,
                            cfg.OW || cfg.IW,
                            cfg.OH || cfg.IH,
                            prefix,        // 'df'
                            maxSize
                        );

                        // ⭐ 關鍵：添加自定義回調，使用 UID 來更新圖片
                        // 保存 group 的引用，以便在回調中使用
                        uploader._groupUID = group._uid;
                        uploader._fieldName = field.name;
                        uploader._dynamicFieldsEditor = this;

                        window.dynamicFieldUploaders[uploaderId] = uploader;
                        
                        
                        
                    } catch (error) {
                        console.error('  ❌ Failed to create ImageUploader:', error);
                        console.error('  Error stack:', error.stack);
                    }
                }
            }
        });

        
        
        
    }

    validateAllGroups() {
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) return true;

        const groups = container.querySelectorAll('.dynamic-field-group');
        const emptyFields = [];

        groups.forEach((group, index) => {
            this.config.fields.forEach(field => {
                if (field.required) {
                    const fieldName = `${this.config.field}[${index}][${field.name}]`;

                    if (field.type === 'image') {
                        // 檢查圖片欄位
                        const fileIdInput = group.querySelector(`[name="${fieldName}_file_id"]`);
                        if (!fileIdInput || !fileIdInput.value) {
                            emptyFields.push({
                                groupIndex: index + 1,
                                fieldLabel: field.label
                            });
                        }
                    } else {
                        // 檢查文字欄位
                        const input = group.querySelector(`[name="${fieldName}"]`);
                        if (!input || !input.value.trim()) {
                            emptyFields.push({
                                groupIndex: index + 1,
                                fieldLabel: field.label
                            });
                        }
                    }
                }
            });
        });

        if (emptyFields.length > 0) {
            // 使用 SweetAlert2 顯示錯誤訊息
            const errorMessages = emptyFields.map(field =>
                `項目 ${field.groupIndex}：${field.fieldLabel}`
            ).join('<br>');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: '請填寫必填欄位',
                    html: `以下欄位為必填，請先完成填寫：<br><br>${errorMessages}`,
                    confirmButtonText: '確定',
                    confirmButtonColor: '#d33'
                });
            } else {
                alert(`請填寫必填欄位：\n\n${emptyFields.map(f => `項目 ${f.groupIndex}：${f.fieldLabel}`).join('\n')}`);
            }
            return false;
        }

        return true;
    }

    removeGroup(index) {
        this.syncDataFromDom();

        // 使用 SweetAlert2 確認刪除
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '確定要刪除嗎？',
                text: '此操作無法復原',
                showCancelButton: true,
                confirmButtonText: '確定刪除',
                cancelButtonText: '取消',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performRemoveGroup(index);
                }
            });
        } else {
            if (confirm('確定要刪除此項目嗎？')) {
                this.performRemoveGroup(index);
            }
        }
    }

    performRemoveGroup(index) {
        // 從資料中移除
        this.groups.splice(index, 1);
        

        // 從 DOM 中移除
        const container = document.getElementById(`${this.config.field}_items`);
        if (container) {
            const items = container.querySelectorAll('.dynamic-field-group');
            if (items[index]) {
                items[index].remove();
            }

            // 更新剩餘項目的索引
            this.updateGroupIndices();
        }
    }

    toggleGroup(index) {
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) return;

        const groups = container.querySelectorAll('.dynamic-field-group');
        if (!groups[index]) return;

        const group = groups[index];
        const body = group.querySelector('.dynamic-field-group-body');
        const toggleBtn = group.querySelector('[data-action="toggle-group"]');
        const icon = toggleBtn.querySelector('i');

        if (body.style.display === 'none') {
            // 展開
            body.style.display = 'grid';
            icon.className = 'fas fa-chevron-up';
            group.classList.remove('collapsed');
        } else {
            // 收合
            body.style.display = 'none';
            icon.className = 'fas fa-chevron-down';
            group.classList.add('collapsed');
        }
    }

    updateContainer() {
        
        const fullContainer = document.getElementById(`${this.config.field}_full_container`);
        if (fullContainer) {
            
            fullContainer.innerHTML = this.renderFullContent();
            
        } else {
            console.error('Full container not found:', `${this.config.field}_full_container`);
        }
    }

    loadData(data) {
        
        
        
        

        // 【修正】處理從資料庫載入的資料格式
        // 資料庫返回的格式是物件 {0: {...}, 1: {...}}，需要轉換為陣列
        let processedData = [];

        if (data && typeof data === 'object') {
            if (Array.isArray(data)) {
                // 已經是陣列，直接使用
                processedData = data;
            } else {
                // 是物件，轉換為陣列
                processedData = Object.values(data);
            }
        }

        
        

        if (!processedData || processedData.length === 0) {
            // 如果沒有資料，不自動新增空白項目
            
            this.groups = [];
        } else {
            
            this.groups = processedData.map(group => ({
                _uid: group._uid || this.generateUID(),
                ...group
            }));
            }

        this.updateContainer();
        this.initSortable();

        // ⭐ 初始化所有群組的圖片上傳器
        
        this.groups.forEach((group, index) => {
            
            this.initImageUploadersForGroup(index);
            // ⭐ 初始化多圖欄位的拖曳排序
            this.initImageSortableForGroup(index);
        });

        
    }

    escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * 新增圖片到指定群組的多圖欄位
     */
    addImageToGroup(groupUid, fieldName) {
        // ⭐ 使用 UID 找到對應的群組
        const group = this.groups.find(g => g._uid === groupUid);
        if (!group) {
            console.error('Group not found with UID:', groupUid);
            return;
        }

        // 找到群組在陣列中的索引
        const groupIndex = this.groups.indexOf(group);
        
        

        // 找到對應的欄位配置
        const fieldConfig = this.config.fields.find(f => f.name === fieldName);
        if (!fieldConfig || fieldConfig.type !== 'image' || !fieldConfig.multiple) {
            console.error('Invalid field config for multiple images:', fieldName);
            return;
        }

        // 確保該欄位的值是陣列
        if (!Array.isArray(group[fieldName])) {
            group[fieldName] = group[fieldName] ? [group[fieldName]] : [];
        }

        // 計算新圖片的索引
        const newImgIndex = group[fieldName].length;
        

        // 新增一個空的圖片物件到資料中
        group[fieldName].push({
            file_id: '',
            file_info: null
        });

        // ⭐ 改用 UID
        const fieldId = `${this.config.field}_${group._uid}_${fieldConfig.name}`;
        const imageContainer = document.getElementById(`draggable_${fieldId}`);

        if (imageContainer) {
            // 生成新圖片的 HTML
            const newImageHtml = this.renderSingleImageInMultiple(
                fieldConfig,
                { file_id: '', file_info: null },
                groupIndex,
                group._uid,
                newImgIndex
            );

            // 創建臨時容器
            const temp = document.createElement('div');
            temp.innerHTML = newImageHtml.trim();
            const newImageElement = temp.querySelector('.dynamic-image-item');
            
            // ⭐ 確保新元素的 data-img-index 正確
            if (newImageElement) {
                newImageElement.dataset.imgIndex = newImgIndex;
            }

            if (newImageElement) {
                // 添加到容器
                imageContainer.appendChild(newImageElement);
                

                // 初始化新圖片的上傳器
                // ⭐ 改用 UID
                const newFieldId = `${this.config.field}_${group._uid}_${fieldConfig.name}_${newImgIndex}`;
                const newUploaderId = `df_${newFieldId}`;
                console.log('[DEBUG] addImageToGroup:', { uid: group._uid, newFieldId, newUploaderId });

                const targetW = fieldConfig.size?.[0]?.w || 800;
                const targetH = fieldConfig.size?.[0]?.h || 600;
                const cfg = (window.GLOBAL_IMG_CONFIG && window.GLOBAL_IMG_CONFIG[fieldConfig.fileType || 'image']) || {IW: targetW, IH: targetH};

                // ⭐ 取得 maxSize 設定
                let maxSize = fieldConfig.maxSize;
                if (!maxSize && fieldConfig.size) {
                    maxSize = fieldConfig.size.maxSize;
                }
                if (!maxSize && this.config.maxSize) {
                    maxSize = this.config.maxSize;
                }
                if (!maxSize && this.config.size) {
                    maxSize = this.config.size.maxSize;
                }
                if (!maxSize) {
                    maxSize = 2; // 預設值
                }

                try {
                    const uploader = new ImageUploader(
                        newFieldId,
                        cfg.IW,
                        cfg.IH,
                        null,
                        cfg.OW || cfg.IW,
                        cfg.OH || cfg.IH,
                        'df',
                        maxSize
                    );
                    window.dynamicFieldUploaders[newUploaderId] = uploader;
                    
                } catch (error) {
                    console.error('❌ Failed to create uploader:', error);
                }
            } else {
                console.error('Failed to create image element from HTML');
            }
        } else {
            console.error('Image container not found');
        }

        
    }

    /**
     * 從指定群組的多圖欄位刪除圖片
     */
    deleteImageFromGroup(groupIndex, fieldName, imgIndex) {
        // ⭐ 關鍵修正：從 DOM 中找到實際的群組元素
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) {
            console.error('Container not found');
            return;
        }

        const groupElements = container.querySelectorAll('.dynamic-field-group');
        const targetGroupElement = groupElements[groupIndex];

        if (!targetGroupElement) {
            console.error('Group element not found at index:', groupIndex);
            return;
        }

        // ⭐ 從 DOM 元素中讀取實際的 data-group-index
        const actualGroupIndex = parseInt(targetGroupElement.dataset.groupIndex);
        

        const group = this.groups[actualGroupIndex];
        if (!group || !Array.isArray(group[fieldName])) {
            console.error('Invalid group or field:', actualGroupIndex, fieldName);
            return;
        }

        // 使用 SweetAlert2 確認刪除
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: '確定要刪除這張圖片嗎？',
                text: '此操作無法復原',
                showCancelButton: true,
                confirmButtonText: '確定刪除',
                cancelButtonText: '取消',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performDeleteImage(actualGroupIndex, fieldName, imgIndex);
                }
            });
        } else {
            if (confirm('確定要刪除這張圖片嗎？')) {
                this.performDeleteImage(actualGroupIndex, fieldName, imgIndex);
            }
        }
    }

    /**
     * 執行刪除圖片
     */
    performDeleteImage(groupIndex, fieldName, imgIndex) {
        const group = this.groups[groupIndex];
        const fieldId = `${this.config.field}_${groupIndex}_${fieldName}`;
        const container = document.getElementById(`draggable_${fieldId}`);

        if (!container) return;

        // ⭐ 直接從 DOM 中移除該圖片元素
        const items = container.querySelectorAll('.dynamic-image-item');
        const targetItem = items[imgIndex];

        if (targetItem) {
            // 移除對應的上傳器
            const targetFieldId = `${this.config.field}_${groupIndex}_${fieldName}_${imgIndex}`;
            const targetUploaderId = `df_${targetFieldId}`;

            if (window.dynamicFieldUploaders && window.dynamicFieldUploaders[targetUploaderId]) {
                delete window.dynamicFieldUploaders[targetUploaderId];
            }

            // 從 DOM 移除
            targetItem.remove();

            // 從資料中移除
            group[fieldName].splice(imgIndex, 1);

            // 如果陣列為空，至少保留一個空物件
            if (group[fieldName].length === 0) {
                group[fieldName] = [{
                    file_id: '',
                    file_info: null
                }];

                // 重新渲染以顯示空的上傳欄位
                this.rerenderGroup(groupIndex);
            } else {
                // ⭐ 更新剩餘圖片的索引和 name 屬性
                this.updateImageIndices(groupIndex, fieldName);
            }
        }
    }

    /**
     * 更新多圖欄位中所有圖片的索引 (優化版)
     */
    updateImageIndices(groupIndex, fieldName) {
        const fieldId = `${this.config.field}_${groupIndex}_${fieldName}`;
        const container = document.getElementById(`draggable_${fieldId}`);
        if (!container) return;

        const group = this.groups[groupIndex];
        const items = container.querySelectorAll('.dynamic-image-item');

        items.forEach((item, newIndex) => {
            // 更新 data-img-index
            item.dataset.imgIndex = newIndex;

            const newFieldId = `${this.config.field}_${groupIndex}_${fieldName}_${newIndex}`;
            const newUploaderId = `df_${newFieldId}`;

            // 更新 hidden input (file_id)
            const fileIdInput = item.querySelector('input[type="hidden"]');
            if (fileIdInput) {
                fileIdInput.id = `${newFieldId}_file_id`;
                fileIdInput.name = `${this.config.field}[${groupIndex}][${fieldName}][${newIndex}][file_id]`;
            }

            // 更新 file input
            const fileInput = item.querySelector('input[type="file"]');
            if (fileInput) {
                const oldUploaderId = fileInput.id;

                // ⭐ 關鍵修正：先保存上傳器的狀態
                let uploaderState = null;
                if (window.dynamicFieldUploaders && window.dynamicFieldUploaders[oldUploaderId]) {
                    const uploader = window.dynamicFieldUploaders[oldUploaderId];
                    uploaderState = {
                        croppedBlob: uploader.croppedBlob,
                        currentFileType: uploader.currentFileType,
                        currentPreviewUrl: uploader.currentPreviewUrl
                    };
                }

                // 更新 DOM 元素
                fileInput.id = newUploaderId;
                fileInput.name = `${this.config.field}[${group._uid}][${fieldName}_upload][${newIndex}]`;

                // 更新按鈕的 data-target
                const triggerBtn = item.querySelector('.trigger-crop-btn');
                if (triggerBtn) {
                    triggerBtn.dataset.target = newUploaderId;
                }

                // ⭐ 重新創建上傳器實例（確保綁定正確）
                if (uploaderState || window.dynamicFieldUploaders[oldUploaderId]) {
                    // 刪除舊的上傳器
                    if (window.dynamicFieldUploaders[oldUploaderId]) {
                        delete window.dynamicFieldUploaders[oldUploaderId];
                    }

                    // 獲取欄位配置
                    const fieldConfig = this.config.fields.find(f => f.name === fieldName);
                    if (fieldConfig) {
                        const targetW = fieldConfig.size?.[0]?.w || 800;
                        const targetH = fieldConfig.size?.[0]?.h || 600;
                        const cfg = (window.GLOBAL_IMG_CONFIG && window.GLOBAL_IMG_CONFIG[fieldConfig.fileType || 'image']) || {IW: targetW, IH: targetH};

                        // ⭐ 取得 maxSize 設定
                        let maxSize = fieldConfig.maxSize;
                        if (!maxSize && fieldConfig.size) {
                            maxSize = fieldConfig.size.maxSize;
                        }
                        if (!maxSize && this.config.maxSize) {
                            maxSize = this.config.maxSize;
                        }
                        if (!maxSize && this.config.size) {
                            maxSize = this.config.size.maxSize;
                        }
                        if (!maxSize) {
                            maxSize = 2; // 預設值
                        }

                        try {
                            // 創建新的上傳器
                            const uploader = new ImageUploader(
                                newFieldId,
                                cfg.IW,
                                cfg.IH,
                                null,
                                cfg.OW || cfg.IW,
                                cfg.OH || cfg.IH,
                                'df',
                                maxSize
                            );

                            // 恢復狀態
                            if (uploaderState && uploaderState.croppedBlob) {
                                uploader.croppedBlob = uploaderState.croppedBlob;
                                uploader.currentFileType = uploaderState.currentFileType;
                                uploader.currentPreviewUrl = uploaderState.currentPreviewUrl;

                                // 更新 UI
                                const previewImg = document.getElementById('croppedImagePreview' + newFieldId);
                                if (previewImg && uploaderState.currentPreviewUrl) {
                                    previewImg.src = uploaderState.currentPreviewUrl;
                                }

                                const uploadStatus = document.getElementById('uploadStatus' + newFieldId);
                                if (uploadStatus) {
                                    const sizeKB = (uploaderState.croppedBlob.size / 1024).toFixed(0);
                                    uploadStatus.textContent = `✅ 已準備 (${sizeKB} KB)`;
                                }

                                const imageUrlInput = document.getElementById('imageUrl' + newFieldId);
                                if (imageUrlInput) {
                                    imageUrlInput.value = 'BLOB_READY';
                                }
                            }

                            window.dynamicFieldUploaders[newUploaderId] = uploader;
                        } catch (error) {
                            console.error('Failed to recreate uploader:', error);
                        }
                    }
                }
            }

            // 更新圖片說明輸入框
            const titleInput = item.querySelector('input[type="text"]');
            if (titleInput) {
                titleInput.id = `title_${newFieldId}`;
                titleInput.name = `${this.config.field}[${groupIndex}][${fieldName}][${newIndex}][title]`;
            }

            // 更新刪除按鈕的 data 屬性
            const deleteBtn = item.querySelector('.btn-delete-image');
            if (deleteBtn) {
                deleteBtn.dataset.imgIndex = newIndex;
            }

            // 使用統一的函數更新其他元素
            this.updateImageRelatedElements(item, newFieldId);
        });
    }

    /**
     * 重新渲染指定的群組
     */
    rerenderGroup(groupIndex) {
        const container = document.getElementById(`${this.config.field}_items`);
        if (!container) return;

        const groups = container.querySelectorAll('.dynamic-field-group');
        const targetGroup = groups[groupIndex];
        if (!targetGroup) return;

        // ⭐ 關鍵修正：在重新渲染前，保存所有上傳器的狀態
        const uploaderStates = this.saveUploaderStates(groupIndex);

        // 同步當前資料
        this.syncDataFromDom();

        // 生成新的 HTML
        const newHtml = this.renderGroup(this.groups[groupIndex], groupIndex);

        // 創建臨時容器
        const temp = document.createElement('div');
        temp.innerHTML = newHtml.trim();
        const newGroupElement = temp.querySelector('.dynamic-field-group');

        if (newGroupElement) {
            // 替換舊的群組元素
            targetGroup.replaceWith(newGroupElement);

            // 重新初始化該群組的圖片上傳器
            this.initImageUploadersForGroup(groupIndex);

            // ⭐ 關鍵修正：恢復上傳器的狀態
            this.restoreUploaderStates(groupIndex, uploaderStates);

            // 重新初始化拖曳排序（針對多圖欄位）
            this.initImageSortableForGroup(groupIndex);
        }
    }

    /**
     * 保存指定群組的所有上傳器狀態
     */
    saveUploaderStates(groupIndex) {
        const states = {};

        if (!window.dynamicFieldUploaders) {
            return states;
        }

        // 遍歷所有上傳器，找出屬於這個群組的
        for (const [uploaderId, uploader] of Object.entries(window.dynamicFieldUploaders)) {
            // 檢查 uploaderId 是否屬於這個群組
            // uploaderId 格式: df_dynamic_rooms_0_room_image_0
            const pattern = new RegExp(`^df_${this.config.field}_${groupIndex}_`);
            if (pattern.test(uploaderId)) {
                // 保存上傳器的重要狀態
                states[uploaderId] = {
                    croppedBlob: uploader.croppedBlob,
                    currentFileType: uploader.currentFileType,
                    currentPreviewUrl: uploader.currentPreviewUrl,
                    hasData: uploader.croppedBlob !== null
                };
            }
        }

        return states;
    }

    /**
     * 恢復指定群組的所有上傳器狀態
     */
    restoreUploaderStates(groupIndex, states) {
        if (!states || Object.keys(states).length === 0) {
            return;
        }

        // 等待 DOM 更新完成
        setTimeout(() => {
            for (const [uploaderId, state] of Object.entries(states)) {
                const uploader = window.dynamicFieldUploaders[uploaderId];

                if (!uploader || !state.hasData) {
                    continue;
                }

                // 恢復上傳器的狀態
                uploader.croppedBlob = state.croppedBlob;
                uploader.currentFileType = state.currentFileType;
                uploader.currentPreviewUrl = state.currentPreviewUrl;

                // 更新 UI 顯示
                const fieldId = uploader.id;

                // 更新預覽圖片
                if (state.currentPreviewUrl) {
                    const previewImg = document.getElementById('croppedImagePreview' + fieldId);
                    if (previewImg) {
                        previewImg.src = state.currentPreviewUrl;
                    }
                }

                // 更新狀態文字
                const uploadStatus = document.getElementById('uploadStatus' + fieldId);
                if (uploadStatus && state.croppedBlob) {
                    const sizeKB = (state.croppedBlob.size / 1024).toFixed(0);
                    uploadStatus.textContent = `✅ 已準備 (${sizeKB} KB)`;
                }

                // 更新 imageUrl 標記
                const imageUrlInput = document.getElementById('imageUrl' + fieldId);
                if (imageUrlInput) {
                    imageUrlInput.value = 'BLOB_READY';
                }

                // 顯示移除按鈕
                const removeBtn = document.getElementById('remove_btn_' + fieldId);
                if (removeBtn) {
                    removeBtn.style.display = 'inline-block';
                }
            }
        }, 100);
    }

    /**
     * 為指定群組的多圖欄位初始化拖曳排序 (優化版 v2)
     */
    initImageSortableForGroup(groupIndex) {
        const group = this.groups[groupIndex];
        if (!group) return;

        this.config.fields.forEach(field => {
            if (field.type === 'image' && field.multiple) {
                // ⭐ 改用 UID
                const fieldId = `${this.config.field}_${group._uid}_${field.name}`;
                const container = document.getElementById(`draggable_${fieldId}`);

                if (container && typeof $ !== 'undefined' && $.fn.sortable) {
                    try {
                        $(container).sortable('destroy');
                    } catch (e) {
                        // 忽略錯誤
                    }

                    $(container).sortable({
                        handle: '.image-drag-handle',
                        items: '.dynamic-image-item',
                        placeholder: 'sortable-placeholder',
                        tolerance: 'pointer',
                        cursor: 'move',
                        appendTo: 'body', // ⭐ 改為 body 以避免滾動偏移
                        helper: function(event, element) {
                            const clone = element.clone();
                            // 保持寬度
                            clone.css({
                                'width': element.outerWidth() + 'px',
                                'height': element.outerHeight() + 'px',
                                'box-sizing': 'border-box',
                                'background-color': '#fff', // 確保背景不透明
                                'z-index': 1000
                            });
                            return clone;
                        },
                        opacity: 0.6,
                        scroll: true,
                        scrollSensitivity: 40,
                        scrollSpeed: 40,
                        axis: 'y',
                        // ⭐ 拖曳開始時
                        start: (event, ui) => {
                            // 記錄原始高度
                            ui.placeholder.height(ui.item.outerHeight());
                            ui.placeholder.css({
                                'visibility': 'visible',
                                'margin-bottom': '15px'
                            });

                            // ⭐ 鎖定 X 軸位置 (參考群組拖曳的邏輯)
                            const containerOffset = $(container).offset();
                            ui.item.data('containerLeft', containerOffset.left);
                            
                            // 設定 helper 初始位置
                            ui.helper.css({
                                'left': containerOffset.left + 'px',
                                'width': $(container).width() + 'px' // 確保寬度一致
                            });
                        },
                        // ⭐ 拖曳過程中保持 X 軸位置
                        sort: (event, ui) => {
                            const containerLeft = ui.item.data('containerLeft');
                            if (containerLeft !== undefined) {
                                ui.helper.css('left', containerLeft + 'px');
                            }
                        },
                        // ⭐ 拖曳結束時更新順序
                        update: (event, ui) => {
                            this.updateImageOrder(groupIndex, field.name);
                        }
                    });
                }
            }
        });
    }

    /**
     * 移除單張圖片
     */
    removeSingleImage(uploaderId, fieldId, uid, fieldName, btnElement) {
        if (window.dynamicFieldUploaders && window.dynamicFieldUploaders[uploaderId]) {
            window.dynamicFieldUploaders[uploaderId].reset();
        }

        // ⭐ 修正：使用按鈕元素的上下文來查找相關元素，避免 ID 衝突或過時問題
        let container = null;
        if (btnElement) {
            container = btnElement.closest('.form-group') || btnElement.closest('.dynamic-image-item');
        }

        // 如果找不到容器，回退到使用 ID (保險起見)
        const hidden = container 
            ? container.querySelector('input[type="hidden"][name$="[file_id]"]') || container.querySelector(`[id$="_file_id"]`)
            : document.getElementById(`${fieldId}_file_id`);

        if (hidden) {
            hidden.value = '__DELETE__';
        }

        const groupData = this.groups.find(g => g._uid === uid);
        if (groupData) {
            groupData[fieldName] = null;
        }

        const img = container
            ? container.querySelector('img[id^="croppedImagePreview"]')
            : document.getElementById(`croppedImagePreview${fieldId}`);
            
        if (img) {
            img.src = 'crop/demo.jpg';
        }

        if (btnElement) {
            btnElement.style.display = 'none';
        } else {
            const btn = document.getElementById(`remove_btn_${fieldId}`);
            if (btn) {
                btn.style.display = 'none';
            }
        }
    }

    /**
     * 更新多圖欄位的圖片順序 (優化版 - 不重新渲染)
     */
    updateImageOrder(groupIndex, fieldName) {
        const group = this.groups[groupIndex];
        if (!group || !Array.isArray(group[fieldName])) return;

        // ⭐ 改用 UID
        const fieldId = `${this.config.field}_${group._uid}_${fieldName}`;
        const container = document.getElementById(`draggable_${fieldId}`);
        if (!container) return;

        const items = container.querySelectorAll('.dynamic-image-item');
        const newOrder = [];

        // 收集新的順序
        items.forEach((item) => {
            const oldIndex = parseInt(item.dataset.imgIndex);
            if (group[fieldName][oldIndex]) {
                newOrder.push(group[fieldName][oldIndex]);
            }
        });

        // 更新群組資料
        group[fieldName] = newOrder;

        // ⭐ 關鍵修正：只更新索引,不重新渲染
        // 這樣可以保留上傳器狀態和避免閃爍
        this.updateImageIndices(groupIndex, fieldName);
    }
}

// 初始化函數
function initDynamicFields(containerId, config, initialData = []) {
    const editor = new DynamicFieldsEditor(containerId, config);
    if (initialData && initialData.length > 0) {
        editor.loadData(initialData);
    }
    return editor;
}

// 初始化全域 uploaders 物件
if (!window.dynamicFieldUploaders) {
    window.dynamicFieldUploaders = {};
}
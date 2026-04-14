<?php
/**
 * SwalConfirmElement - 統一的 SweetAlert2 確認提示 JavaScript 函數
 *
 * 提供統一的 SweetAlert2 確認對話框
 * 支援所有 CRUD 操作：新增、修改、刪除（軟/硬）、還原
 */

class SwalConfirmElement
{
    /**
     * 直接輸出 JavaScript 代碼
     */
    public static function render()
    {
        self::outputScript();
    }

    /**
     * 輸出 JavaScript 腳本
     */
    private static function outputScript()
    {
        ?>
        <script>
            /**
             * 統一的操作確認對話框
             */
            function showActionConfirm(options) {
                const {
                    action = 'soft_delete',
                    itemType = 'data',
                    items = '',
                    articleCount = 0,
                    onConfirm = null,
                    customTitle = '',
                    customMessage = '',
                    useRawMessage = false
                } = options;

                const isBatch = Array.isArray(items);
                const itemCount = isBatch ? items.length : 1;

                const itemTypeNames = {
                    'category': '分類',
                    'data': '資料',
                    'file': '檔案',
                    'image': '圖片',
                    'article': '文章',
                    'news': '新聞',
                    'product': '產品',
                    'user': '使用者',
                    'page': '頁面',
                    'banner': 'Banner'
                };
                const itemTypeName = itemTypeNames[itemType] || '項目';

                let title, message, confirmButtonColor, confirmButtonText, icon;

                switch (action) {
                    case 'create':
                        title = customTitle || '確認新增';
                        icon = 'question';
                        confirmButtonColor = '#28a745';
                        confirmButtonText = '確定新增';
                        message = customMessage || `確定要新增此${itemTypeName}嗎？`;
                        break;

                    case 'update':
                        title = customTitle || '確認修改';
                        icon = 'question';
                        confirmButtonColor = '#17a2b8';
                        confirmButtonText = '確定修改';
                        message = customMessage || `確定要修改此${itemTypeName}嗎？`;
                        break;

                    case 'soft_delete':
                        title = customTitle || '確認移到垃圾桶';
                        icon = 'warning';
                        confirmButtonColor = '#3085d6';
                        confirmButtonText = '確定刪除';

                        if (customMessage) {
                            message = customMessage;
                        } else if (articleCount > 0) {
                            message = `<strong>以下${itemTypeName}及其文章將被移到垃圾桶：</strong><br><br>`;
                            if (isBatch) {
                                message += items.join('<br>');
                            } else {
                                message += `此${itemTypeName}下還有 <strong style="color: #dc3545;">${articleCount}</strong> 篇文章使用此${itemTypeName}`;
                            }
                            message += '<br><br>確定要繼續嗎？';
                        } else {
                            if (isBatch) {
                                message = `<strong>以下 ${itemCount} 筆${itemTypeName}將被移到垃圾桶：</strong><br><br>`;
                                message += items.join('<br>');
                                message += '<br><br>確定要繼續嗎？';
                            } else {
                                message = `此${itemTypeName}將被移到垃圾桶，可以稍後還原。<br><br>確定要繼續嗎？`;
                            }
                        }
                        break;

                    case 'hard_delete':
                        title = customTitle || '確認永久刪除';
                        icon = 'error';
                        confirmButtonColor = '#dc3545';
                        confirmButtonText = '刪除';

                        if (customMessage) {
                            message = customMessage;
                        } else if (articleCount > 0) {
                            message = `<strong style="color: #dc3545;">以下${itemTypeName}及其文章將被永久刪除：</strong><br><br>`;
                            if (isBatch) {
                                message += items.join('<br>');
                            } else {
                                message += `此${itemTypeName}下還有 <strong style="color: #dc3545;">${articleCount}</strong> 篇文章使用此${itemTypeName}`;
                            }
                            message += '<br><br><strong style="color: #dc3545;">此操作無法復原，確定要繼續嗎？</strong>';
                        } else {
                            if (isBatch) {
                                message = `<strong style="color: #dc3545;">以下 ${itemCount} 筆${itemTypeName}將被永久刪除：</strong><br><br>`;
                                message += items.join('<br>');
                                message += '<br><br><strong style="color: #dc3545;">此操作無法復原，確定要繼續嗎？</strong>';
                            } else {
                                message = `<strong style="color: #dc3545;">此${itemTypeName}將被永久刪除！</strong><br><br>`;
                                message += '<strong style="color: #dc3545;">此操作無法復原，確定要繼續嗎？</strong>';
                            }
                        }
                        break;

                    case 'restore':
                        title = customTitle || '確定要還原嗎？';
                        icon = 'question';
                        confirmButtonColor = '#28a745';
                        confirmButtonText = '確定還原';

                        if (customMessage) {
                            message = customMessage;
                        } else {
                            if (isBatch) {
                                message = `所選的 ${itemCount} 筆${itemTypeName}將被還原`;
                            } else {
                                message = `還原後此${itemTypeName}將回到正常列表`;
                            }
                        }
                        break;

                    default:
                        title = customTitle || '確認操作';
                        icon = 'question';
                        confirmButtonColor = '#3085d6';
                        confirmButtonText = '確定';
                        message = customMessage || '確定要執行此操作嗎？';
                }

                const htmlContent = useRawMessage ? message : '<div style="line-height: 1.8; text-align: left;">' + message + '</div>';

                return new Promise((resolve) => {
                    Swal.fire({
                        title: title,
                        html: htmlContent,
                        icon: icon,
                        showCancelButton: true,
                        confirmButtonColor: confirmButtonColor,
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: confirmButtonText,
                        cancelButtonText: '取消',
                        width: '600px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if (onConfirm) onConfirm();
                            resolve(true);
                        } else {
                            resolve(false);
                        }
                    });
                });
            }

            /**
             * 顯示處理中的提示
             */
            function showProcessing(message = '處理中...') {
                Swal.fire({
                    title: message,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            }

            /**
             * 顯示成功訊息
             */
            function showSuccess(title = '操作成功！', message = '', callback = null) {
                const config = {
                    title: title,
                    icon: 'success',
                    confirmButtonColor: '#28a745'
                };
                if (message) config.text = message;
                Swal.fire(config).then(() => {
                    if (callback) callback();
                });
            }

            /**
             * 顯示錯誤訊息
             */
            function showError(title = '操作失敗', message = '') {
                const config = {
                    title: title,
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                };
                if (message) config.text = message;
                Swal.fire(config);
            }

            /**
             * 顯示警告訊息
             */
            function showWarning(title = '注意', message = '') {
                const config = {
                    title: title,
                    icon: 'warning',
                    confirmButtonColor: '#ffc107'
                };
                if (message) config.text = message;
                Swal.fire(config);
            }

            /**
             * 顯示資訊訊息
             */
            function showInfo(title = '提示', message = '') {
                const config = {
                    title: title,
                    icon: 'info',
                    confirmButtonColor: '#17a2b8'
                };
                if (message) config.text = message;
                Swal.fire(config);
            }
        </script>
        <?php
    }
}

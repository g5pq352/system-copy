let currentTargetEditorId = null; // 記錄當前正在操作哪個編輯器的 ID

$(function() {
    $('.gallery-opener').on('click', function() {
        const targetId = $(this).data('target');
        const editorInstance = CKEDITOR.instances[targetId];

        if (!editorInstance) {
            alert(`編輯器 ${targetId} 尚未載入完成，請稍候。`);
            return;
        }

        currentTargetEditorId = targetId;

        const galleryUrl = 'image_picker.php?mode=picker';

        const width = 1300;
        const height = 700;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;

        window.open(
            galleryUrl,
            'ImageGalleryPicker', // 視窗名稱
            `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`
        );
    });
});
window.receiveImageFromGallery = function(imageUrlOrToken, mediaId, fallbackUrl) {
    if (currentTargetEditorId && imageUrlOrToken) {
        const editor = CKEDITOR.instances[currentTargetEditorId]; // 獲取實例

        if (editor) {
            let htmlContent;
            
            // 檢查是否為 token 格式
            if (imageUrlOrToken.startsWith('[media:') && imageUrlOrToken.endsWith(']')) {
                // Token 格式: [media:123]
                // 使用 fallbackUrl 作為預覽圖,但 src 儲存 token
                
                if (fallbackUrl) {
                    // 方案 A: 使用 data-media-id 屬性 (推薦,向後相容)
                    htmlContent = `<img src="${fallbackUrl}" data-media-token="${imageUrlOrToken}" data-media-id="${mediaId}" alt="image" />`;
                    
                    // 方案 B: 直接使用 token (需要前端即時替換)
                    // htmlContent = `<img src="${imageUrlOrToken}" data-preview="${fallbackUrl}" alt="image" />`;
                } else {
                    // 沒有 fallback URL,直接使用 token
                    htmlContent = `<img src="${imageUrlOrToken}" alt="image" />`;
                }
                
                console.log('Inserting image with token:', imageUrlOrToken, 'Preview URL:', fallbackUrl);
            } else {
                // 舊格式: 直接是 URL (向後相容)
                htmlContent = `<img src="${imageUrlOrToken}" alt="image" />`;
                console.log('Inserting image with URL:', imageUrlOrToken);
            }

            editor.insertHtml(htmlContent);
        }

        currentTargetEditorId = null;
    } else {
        console.error('無法插入圖片：目標編輯器未定義或圖片 URL 缺失。');
        if (!currentTargetEditorId) alert("錯誤：無法鎖定目標編輯器。");
    }
}
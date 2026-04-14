<script type="text/javascript" src="ckeditor/ckeditor.js"></script>
<script type="text/javascript">
    $(function () {
    	$("textarea.tiny").each(function(i, el) {
			if (!el.id) {
				const generatedId = 'ckeditor_' + i;
				$(el).attr('id', generatedId);
			}
			CKEDITOR.replace(el.id, {
			    height: '350px',
				contentsCss: '<?=APP_BACKEND_PATH?>/css/layout.css',
			    ignoreReadOnlyWarning: true,
			    // 貼上時移除外部樣式
			    pasteFromWordRemoveFontStyles: true,
			    pasteFromWordRemoveStyles: true,
			    forcePasteAsPlainText: false,  // 設為 true 會移除所有格式，false 則保留基本格式
			    // 過濾規則：移除 style 屬性和 font 標籤
			    pasteFilter: 'p; h1; h2; h3; h4; h5; h6; ul; ol; li; strong; em; u; a[!href]; img[!src,alt,width,height]; br',
			    // 或者使用更嚴格的過濾
			    removeFormatAttributes: 'class,style,lang,width,height,align,hspace,valign',
			    removeFormatTags: 'font,span'
			});
		});
    });
</script>
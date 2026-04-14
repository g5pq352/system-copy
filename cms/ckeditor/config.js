/**
 * @license Copyright (c) 2003-2018, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {

	config.removeButtons = 'NewPage,Print,SelectAll,Scayt,Form,Checkbox,Textarea,TextField,Radio,Select,Button,HiddenField,ImageButton,Strike,Subscript,Superscript,NumberedList,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Flash,Table,HorizontalRule,PageBreak,Iframe,About';

	config.language = 'zh';

	config.removeDialogTabs = 'image:advanced;image:Upload;link:advanced';
	config.image_previewText = ' ';

	config.extraPlugins = 'dragresize,youtube,autogrow,autolink';

	config.height = 400;
	config.autoGrow_minHeight = 400;
	config.autoGrow_maxHeight = 700;

	// 字型設定
	config.font_names =
		'Noto Sans TC/Noto Sans TC, sans-serif;' +
		'Crimson Text, sans-serif;';

	// 限制可選顏色
	config.colorButton_colors = '000000,FFFFFF,666666,a47c4a,9b7336'; // 只允許這幾種顏色
	config.colorButton_enableAutomatic = false; // 不顯示自動顏色
	// 字體大小設定
	config.fontSize_sizes = '12/12px;14/14px;16/16px;18/18px;20/20px;22/22px;24/24px;26/26px;28/28px;36/36px;48/48px;72/72px';

	// 格式樣式設定
	config.format_tags = 'p;h1;h2;h3;h4;h5;h6;pre;div';

	// 指定使用自訂樣式集
	config.stylesSet = 'my_styles';

};

// 自訂樣式集 (需要在 editorConfig 函數外部定義)
CKEDITOR.stylesSet.add('my_styles', [
	// 文章副標題樣式
	{
		name: '｜文章副標題<br>(限生活誌使用)',
		element: 'p',
		attributes: { 'class': 'article-subtitle' },
		styles: {
			'font-family': 'Source Han Serif TC, serif',
			'font-weight': '700',
			'letter-spacing': '1px',
			'text-align': 'justify',
			'color': '#666666',
			'line-height': '27px',
			'margin-bottom': '25px',
			'padding-left': '15px'
		}
	}
]);


// remove image attributes (width +  height + style)
CKEDITOR.on('instanceReady', function(ev) {
	ev.editor.dataProcessor.htmlFilter.addRules({
		elements: {
			$: function(element) {
				// check for the tag name
				if (element.name == 'img') {

					delete element.attributes.width;
					delete element.attributes.height;
					delete element.attributes.style;

					// var style = element.attributes.style;

					// // Get the height from the style.
					// var match = /(?:^|\s)height\s*:\s*(\d+)px/i.exec(style);
					// var height = match && match[1];

					// // Replace the height
					// if (height) {
					// 	element.attributes.style = element.attributes.style.replace(/(?:^|\s)height\s*:\s*(\d+)px;?/i, '');
					// }
				}

				// return element
				return element;
			}
		}
	});
});
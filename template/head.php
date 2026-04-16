<meta charset="utf-8">
<base href="<?php echo $langurl ?? $baseurl; ?>/">

<title></title>

<?php //Behavioral Meta Data ?>
<meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<?php //關閉瀏覽器自動識別數字為電話號碼、信箱、地址 ?>
<meta name="format-detection" content="telephone=no,email=no,adress=no" />


<?php if(0):?>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    <script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            includedLanguages: 'zh-TW',
        }, 'google_translate_element');
    }
    </script>
<?php endif?>
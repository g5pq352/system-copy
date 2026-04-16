<?php
if(empty($tpl_setting['Template']))
{
	header('Location:/404/');
}
?>
<!DOCTYPE html>
<html lang="<?php echo ($htmlLang ? $htmlLang : DEFAULT_LANG_LOCALE); ?>">

<?php
//data-page css 標記
$page = 'faq';
?>

<head>
    <?php include($tpl_setting['Template']::__dir('head.php'));?>

    <?php //Open Graph Meta Data ?>

    <?php include($tpl_setting['Template']::__dir('css.php'));?>
</head>

<body data-page="<?php echo $page; ?>">

    <?php if($tpl_setting['header'] != '' && $tpl_setting['header'] != '00' ):?>
        <?php include($tpl_setting['Template']::__dir('module/header/header'.$tpl_setting['header'].'.php'));?>
    <?php endif?>

    <?php if($tpl_setting['mbPanel'] != '' && $tpl_setting['mbPanel'] !='00'):?>
        <?php include($tpl_setting['Template']::__dir('module/mbPanel/mbPanel'.$tpl_setting['mbPanel'].'.php'));?>
    <?php endif?>

    <div class="page_wrap">
        
        <!-- 這裡是放區塊的地方-->
        <?php include($tpl_setting['Template']::__dir('view/faq/faq.php'));?>
    </div>

    <?php if($tpl_setting['footer'] != '' && $tpl_setting['footer']!='00' ):?>
        <?php include($tpl_setting['Template']::__dir('module/footer/footer'.$tpl_setting['footer'].'.php'));?>
    <?php endif?>

    <?php if($tpl_setting['gdpr'] != '' && $tpl_setting['gdpr']!='00' ):?>
        <?php include($tpl_setting['Template']::__dir('module/gdpr/gdpr'.$tpl_setting['gdpr'].'.php'));?>
    <?php endif?>

    <?php include($tpl_setting['Template']::__dir('end.php'));?>
</body>

</html>
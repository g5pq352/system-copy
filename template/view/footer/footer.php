<?php if($tpl_setting['footer'] != '' && $tpl_setting['footer']!='00' ):?>
    <?php include($tpl_setting['Template']::__dir('module/footer/footer'.$tpl_setting['footer'].'.php'));?>
<?php endif?>
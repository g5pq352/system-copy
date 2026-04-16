<?php if($tpl_setting['faq'] != '' && $tpl_setting['faq']!='00'):?>
	<?php include($tpl_setting['Template']::__dir('module/faq/faq'.$tpl_setting['faq'].'.php'));?>
<?php endif?>
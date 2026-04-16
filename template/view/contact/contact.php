<?php if($tpl_setting['contact'] != '' && $tpl_setting['contact']!='00'):?>
	<?php include($tpl_setting['Template']::__dir('module/contact/contact'.$tpl_setting['contact'].'.php'));?>
<?php endif?>
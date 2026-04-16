<?php if($tpl_setting['news_detail'] != '' && $tpl_setting['news_detail']!='00'):?>
	<?php include($tpl_setting['Template']::__dir('module/news_sys/news_detail'.$tpl_setting['news_detail'].'.php'));?>
<?php endif?>
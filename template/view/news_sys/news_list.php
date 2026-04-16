<?php if($tpl_setting['news_list'] != '' && $tpl_setting['news_list']!='00'):?>
	<?php include($tpl_setting['Template']::__dir('module/news_sys/news_list'.$tpl_setting['news_list'].'.php'));?>
<?php endif?>
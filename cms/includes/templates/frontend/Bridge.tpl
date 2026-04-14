<?php if(!empty($tpl_setting['{ConfigKey}']) && $tpl_setting['{ConfigKey}'] !== '00'): ?>
    <?php include($tpl_setting['Template']::__dir('module/{Folder}/{Filename}' . $tpl_setting['{ConfigKey}'] . '.php')); ?>
<?php endif; ?>

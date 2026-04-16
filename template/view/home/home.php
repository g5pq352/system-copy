<?php
if(!empty($systemTemplateSet['home_box_number'])) :
    foreach ($systemTemplateSet['home_box_number'] as $box):
?>
    <?php 
        $folder = preg_replace('/[0-9]/', '', $box); 
        include($tpl_setting['Template']::__dir("module/{$folder}/{$box}.php")); 
    ?>
<?php 
    endforeach; 
endif;
?>
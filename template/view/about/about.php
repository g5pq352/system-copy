<?php
if(!empty($systemTemplateSet['about_box_number'])) :
    foreach ($systemTemplateSet['about_box_number'] as $box):
?>
    <?php 
        $folder = preg_replace('/[0-9]/', '', $box); 
        include($tpl_setting['Template']::__dir("module/{$folder}/{$box}.php")); 
    ?>
<?php 
    endforeach; 
endif;
?>

<?php include($tpl_setting['Template']::__dir("module/about/about05.php"));  ?>
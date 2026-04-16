<!-- Font -->
<script src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js"></script>
<script>
WebFont.load({
    google: {
        families: [
            'Noto Sans TC: 100,200,300,400,500,600,700,800,900',
            'Noto Serif TC: 200,300,400,500,600,700,800,900',
            'Roboto: 100,300,400,500,700,900',
            'Poppins: 100,200,300,400,500,600,700,800,900',
            'DM Sans: 400,500,700',
            'Open Sans: 300,400,500,600,700,800'
        ]
    }
});
</script>

<!-- Plugin -->
<script src="<?=$baseurl?>/template/js/jquery-3.7.1.min.js"></script>
<script src="<?=$baseurl?>/template/js/countup/jquery.countup.min.js"></script>
<script src="<?=$baseurl?>/template/js/gsap/gsap.min.js"></script>
<script src="<?=$baseurl?>/template/js/gsap/ScrollTrigger.min.js"></script>
<script src="<?=$baseurl?>/template/js/gsap/Flip.min.js"></script>
<script src="<?=$baseurl?>/template/js/lazyload/lazyload.min.js"></script>
<!-- <script src="<?=$baseurl?>/template/js/slick/slick.min.js"></script> -->
<!-- <script src="<?=$baseurl?>/template/js/slick-lightbox/slick-lightbox.min.js"></script> -->
<script src="<?=$baseurl?>/template/js/splide/splide.min.js"></script>
<script src="<?=$baseurl?>/template/js/splide/splide-extension-auto-scroll.min.js"></script>
<script src="<?=$baseurl?>/template/js/dist/common.min.js"></script>

<!-- js -->
<?php if(!empty($systemTemplateSet['home_box_number'])) : ?>
    <?php foreach ($systemTemplateSet['home_box_number'] as $box): ?>
        <script>
            <?php 
                $folder = preg_replace('/[0-9]/', '', $box); 
                include($tpl_setting['Template']::__dir("module/{$folder}/{$box}.js")); 
            ?>
        </script>
    <?php endforeach; ?>
<?php endif; ?>

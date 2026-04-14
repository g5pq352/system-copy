<?php
$imagesSize = $imagesSize ?? []; // 若未定義則給空陣列
?>

<!-- Vendor -->
<script src="template-style/vendor/jquery/jquery.js"></script>
<script src="template-style/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
<script src="template-style/vendor/popper/umd/popper.min.js"></script>
<script src="template-style/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="template-style/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
<script src="template-style/vendor/common/common.js"></script>
<script src="template-style/vendor/nanoscroller/nanoscroller.js"></script>
<script src="template-style/vendor/magnific-popup/jquery.magnific-popup.js"></script>
<script src="template-style/vendor/jquery-placeholder/jquery.placeholder.js"></script>

<!-- Specific Page Vendor -->
<script src="template-style/vendor/jquery-ui/jquery-ui.js"></script>
<script src="template-style/vendor/jqueryui-touch-punch/jquery.ui.touch-punch.js"></script>
<script src="template-style/vendor/jquery-appear/jquery.appear.js"></script>
<script src="template-style/vendor/bootstrapv5-multiselect/js/bootstrap-multiselect.js"></script>
<script src="template-style/vendor/jquery.easy-pie-chart/jquery.easypiechart.js"></script>
<script src="template-style/vendor/flot/jquery.flot.js"></script>
<script src="template-style/vendor/flot.tooltip/jquery.flot.tooltip.js"></script>
<script src="template-style/vendor/flot/jquery.flot.pie.js"></script>
<script src="template-style/vendor/flot/jquery.flot.categories.js"></script>
<script src="template-style/vendor/flot/jquery.flot.resize.js"></script>
<script src="template-style/vendor/jquery-sparkline/jquery.sparkline.js"></script>
<script src="template-style/vendor/raphael/raphael.js"></script>
<script src="template-style/vendor/morris/morris.js"></script>
<script src="template-style/vendor/gauge/gauge.js"></script>
<script src="template-style/vendor/snap.svg/snap.svg.js"></script>
<script src="template-style/vendor/liquid-meter/liquid.meter.js"></script>
<script src="template-style/vendor/jqvmap/jquery.vmap.js"></script>
<script src="template-style/vendor/jqvmap/data/jquery.vmap.sampledata.js"></script>
<script src="template-style/vendor/jqvmap/maps/jquery.vmap.world.js"></script>
<script src="template-style/vendor/jqvmap/maps/continents/jquery.vmap.africa.js"></script>
<script src="template-style/vendor/jqvmap/maps/continents/jquery.vmap.asia.js"></script>
<script src="template-style/vendor/jqvmap/maps/continents/jquery.vmap.australia.js"></script>
<script src="template-style/vendor/jqvmap/maps/continents/jquery.vmap.europe.js"></script>
<script src="template-style/vendor/jqvmap/maps/continents/jquery.vmap.north-america.js"></script>
<script src="template-style/vendor/jqvmap/maps/continents/jquery.vmap.south-america.js"></script>
<script src="template-style/vendor/jquery-validation/jquery.validate.js"></script>
<script src="template-style/vendor/select2/js/select2.js"></script>
<script src="template-style/vendor/dropzone/dropzone.js"></script>
<script src="template-style/vendor/pnotify/pnotify.custom.js"></script>


<!-- Specific Page Vendor -->
<script src="template-style/vendor/datatables/media/js/jquery.dataTables.min.js"></script>
<script src="template-style/vendor/datatables/media/js/dataTables.bootstrap5.min.js"></script>

<!-- Theme Base, Components and Settings -->
<script src="template-style/js/theme.js"></script>

<!-- Theme Custom -->
<script src="template-style/js/custom.js"></script>

<!-- Theme Initialization Files -->
<script src="template-style/js/theme.init.js"></script>

<!-- Examples -->
<script src="template-style/js/examples/examples.dashboard.js"></script>
<script src="template-style/js/examples/examples.header.menu.js"></script>
<!-- <script src="template-style/js/examples/examples.ecommerce.datatables.list.js"></script> -->
<script src="template-style/js/examples/examples.ecommerce.form.js"></script>

<link rel="stylesheet" href="jquery/chosen_v1.8.5/chosen.css">
<link rel="stylesheet" href="css/ImageSelect.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link rel="stylesheet" type="text/css" href="jquery/fancyapps-fancyBox/source/jquery.fancybox.css" media="screen" />
<!-- <script type="text/javascript" src="jquery/jquery-1.7.2.min.js"></script> -->
<script type='text/javascript' src='js/ckeditorInitialization.js'></script>
<script src="jquery/chosen_v1.8.5/chosen.jquery.js" type="text/javascript"></script>
<script src="js/ImageSelect.jquery.js"></script>
<script type="text/javascript" src="jquery/fancyapps-fancyBox/lib/jquery.mousewheel-3.0.6.pack.js"></script>
<script type="text/javascript" src="jquery/fancyapps-fancyBox/source/jquery.fancybox.pack.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.6.1/Sortable.min.js"></script>
<script src="js/time-countdown.js" type="text/javascript"></script>

<?php
require_once 'ckeditor.php';
require_once 'crop/all_modal.php';
?>

<?php
if(CMS_LOGOUT_TIME > 60){
    $_SESSION['CmsLogoutTime'] = time();
?>
<script type="text/javascript">
$(document).ready(function(){
    $("#time-countdown").countdown({
    "seconds": '<?php echo CMS_LOGOUT_TIME?>',
    "prefix-text": "",
    "logoutUrl": "<?php echo "login.php"?>",
    "keepAliveUrl": ""
    });
});
</script>
<?php
}
?>

<script>
$(document).ready(function() {

    /**
     * 核心送出處理函數
     * @param {boolean} needConfirm 是否需要彈出確認視窗 (true=要問, false=直接存)
     */
    function processSubmit(needConfirm) {
        var $btn = $('#submitBtn');
        var $form = $btn.closest('form');
        var formDom = $form[0];

        // 1. HTML5 必填欄位驗證
        if (formDom.checkValidity && !formDom.checkValidity()) {
            formDom.reportValidity();
            return;
        }

        // 定義實際的送出動作 (含 Loading 畫面)
        var performSubmit = function() {
            Swal.fire({
                title: '資料儲存中...',
                text: '請稍候',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); }
            });

            setTimeout(function() {
                formDom.submit();
            }, 100);
        };

        // 1.5 【重複檢查】呼叫 cmsBeforeSubmit hook (如果存在)
        if (typeof window.cmsBeforeSubmit === 'function') {
            var canContinue = window.cmsBeforeSubmit(performSubmit, needConfirm);
            if (canContinue === false) {
                // 如果返回 false，表示需要等待非同步檢查，不繼續往下執行
                return;
            }
            // 如果返回 true，表示可以繼續往下執行
        }

        // 2. 判斷路徑
        if (needConfirm) {
            // [路徑 A] 需要確認 (Alt+S)
            Swal.fire({
                title: '確定要儲存嗎？',
                text: "資料將被寫入資料庫",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '確定儲存',
                cancelButtonText: '取消',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    performSubmit(); // 使用者按確定後，執行送出
                }
            });
        } else {
            // [路徑 B] 直接送出 (按鈕點擊)
            // 這裡不跳詢問窗，但還是建議顯示 Loading，體驗比較好
            performSubmit();
        }
    }

    // -----------------------------------------------------------

    // 1. 綁定鍵盤快捷鍵 (Alt + S) -> 傳入 true (要確認)
    $(document).off('keydown.saveShortcut').on('keydown.saveShortcut', function(e) {
        if (e.altKey && (e.key === 's' || e.key === 'S')) {
            e.preventDefault(); 
            // 這裡不模擬點擊，直接呼叫函數並要求確認
            processSubmit(true);
        }
    });

    // 2. 處理按鈕點擊 -> 傳入 false (不要確認，直接存)
    $(document).off('click', '#submitBtn').on('click', '#submitBtn', function(e) {
        e.preventDefault(); 
        processSubmit(false);
    });

});
</script>
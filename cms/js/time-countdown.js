(function ($) {
    $.fn.countdown = function (options) {

        var settings = $.extend({
            "seconds": 0,
            "ongoing": true, // 預設開啟
            "selector-start": "",
            "selector-pause": "",
            "prefix-text": "",
            "stop-text": "00:00",
            "normal-class": "",
            "warning-class": "",
            "stop-class": "",
            "warning-time": 60,
            "logoutUrl": '',
            "keepAliveUrl": location.href
        }, options);

        var timer;
        var elem = this;
        var ongoing = settings['ongoing']; // 這裡抓到 true
        var logoutUrl = settings['logoutUrl'];
        var defaultSeconds = parseInt(settings['seconds']);
        var runSeconds = defaultSeconds;

        // --- 修正後的 Start Timer ---
        function startTimer() {
            // 1. 先清除舊的計時器，避免重複
            if (timer) clearInterval(timer);

            // 2. 啟動計時
            timer = setInterval(function () {
                runSeconds--;
                draw();
            }, 1000);
            
            // 3. 標記狀態為進行中
            ongoing = true;
        }

        function stopTimer() {
            clearInterval(timer);
            ongoing = false;
        }

        function doLogout() {
            stopTimer();
            $(elem).html(settings['prefix-text'] + settings['stop-text']);
            $(elem).removeClass(settings['normal-class'])
                   .removeClass(settings['warning-class'])
                   .addClass(settings['stop-class']);

            var ajaxData = new FormData();
            var processData = false;
            var contentType = false;
            var ajaxSettings = {
                actionType: 'logout',
                savePage: 1,
                redirectUrl: location.href
            };

            if ($('form[name="modDetailForm"]').length > 0) {
                var form = $('form[name="modDetailForm"]');
                var formData = new FormData(form[0]);
                for (var key in ajaxSettings) {
                    formData.append(key, ajaxSettings[key]);
                }
                ajaxData = formData;
            } else {
                ajaxData = ajaxSettings;
                processData = true;
                contentType = 'application/x-www-form-urlencoded; charset=UTF-8';
            }

            $.ajax({
                type: "POST",
                url: logoutUrl,
                dataType: "text",
                cache: false,
                processData: processData,
                contentType: contentType,
                data: ajaxData,
            }).done(function (msg) {
                location.href = logoutUrl;
            }).fail(function(){
                location.href = logoutUrl;
            });
        }

        function draw() {
            if (runSeconds <= 0) {
                doLogout();
                return;
            }

            if (runSeconds == 60) {
                stopTimer(); // 暫停計時等待使用者選擇

                Swal.fire({
                    title: '最後一分鐘即將登出您的裝置，是否重置使用時間?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '重置',
                    cancelButtonText: '登出',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    timer: 60000,
                    timerProgressBar: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // 按下重置，呼叫後端延長 Session
                        $.ajax({
                            url: settings['keepAliveUrl'], 
                            type: 'HEAD',
                            success: function(){
                                runSeconds = defaultSeconds; // 重置秒數
                                draw(); // 更新畫面
                                startTimer(); // 重新啟動倒數
                            },
                            error: function(){
                                doLogout();
                            }
                        });
                    } else {
                        runSeconds = 0;
                        doLogout();
                    }
                });
            }

            // 樣式切換
            if (runSeconds <= settings['warning-time'] && !$(elem).hasClass(settings['warning-class'])) {
                $(elem).removeClass(settings['normal-class']).addClass(settings['warning-class']);
            } else if (runSeconds > settings['warning-time']) {
                $(elem).addClass(settings['normal-class']).removeClass(settings['warning-class']);
            }

            // 顯示時間
            var minutes = Math.floor(runSeconds / 60);
            var seconds = runSeconds % 60;
            var res = (minutes < 10 ? "0" + minutes : minutes) + ':' + (seconds < 10 ? "0" + seconds : seconds);
            
            $(elem).text(settings['prefix-text'] + res);
        }

        // --- 按鈕事件綁定 ---
        $(settings['selector-start']).bind("click", function () {
            if(!ongoing) startTimer(); // 只有按鈕點擊時才檢查 ongoing 狀態
        });

        $(settings['selector-pause']).bind("click", function () {
            stopTimer();
        });

        // --- 初始化執行 ---
        $(elem).removeClass(settings['stop-class'])
               .removeClass(settings['warning-class'])
               .addClass(settings['normal-class']);

        draw();
        
        // 這裡直接判斷：如果設定是 ongoing，就直接跑 startTimer
        if (settings['ongoing']) {
            startTimer();
        }
    };
})(jQuery);
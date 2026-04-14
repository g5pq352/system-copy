<?php
require_once('../Connections/connect2data.php');

if ((isset($_GET['doLogout'])) && ($_GET['doLogout'] == "true")) {
    //to fully log out a visitor we need to clear the session varialbles
    $_SESSION['MM_LoginAccountUsername'] = NULL;
    $_SESSION['MM_LoginAccountUserGroup'] = NULL;
    $_SESSION['MM_UserGroupId'] = NULL;
    $_SESSION['MM_UserPermissions'] = [];
    $_SESSION['PrevUrl'] = NULL;

    unset($_SESSION['MM_LoginAccountUsername']);
    unset($_SESSION['MM_LoginAccountUserGroup']);
    unset($_SESSION['MM_UserGroupId']);
    unset($_SESSION['MM_UserPermissions']);
    unset($_SESSION['PrevUrl']);

    $logoutGoTo = PORTAL_AUTH_URL."dashboard";
    if ($logoutGoTo) {
        header("Location: $logoutGoTo");
        exit;
    }
}

$loginFormAction = PORTAL_AUTH_URL."signin";

// if (isset($_GET['accesscheck'])) {
//     $_SESSION['PrevUrl'] = $_GET['accesscheck'];
// }

if (!isset($_SESSION['errorTimes'])) {
    $_SESSION['errorTimes'] = 0;
}

if (isset($_POST['use_rname'])) {

    $loginUsername = $_POST['use_rname'];
    $password = $_POST['user_password'];

    $MM_fldUserAuthorization = "";
    $MM_redirectLoginSuccess = PORTAL_AUTH_URL."dashboard";
    $MM_redirectLoginFailed = PORTAL_AUTH_URL."signin";
    $MM_redirecttoReferrer = false;

    // 【修改】先查詢使用者資料（包含 salt）
    $LoginRS__query = "SELECT user_id, user_name, user_password, user_salt FROM admin WHERE user_name=:user_name AND user_active='1'";

    $LoginRS = $conn->prepare($LoginRS__query);
    $LoginRS->bindParam(':user_name', $loginUsername, PDO::PARAM_STR);
    $LoginRS->execute();
    $userData = $LoginRS->fetch(PDO::FETCH_ASSOC);

    // 【修改】驗證密碼（支援新版 password_hash 與舊版 sha256）
    $loginFoundUser = false;
    if ($userData) {
        $storedPassword = $userData['user_password'];
        $salt = $userData['user_salt'];
        
        // 1. 先嘗試新版驗證 (password_verify 會自動識別演算法)
        if (password_verify($password, $storedPassword)) {
            $loginFoundUser = true;
        } 
        // 2. 如果新版失敗且 salt 存在，嘗試舊版 sha256 驗證 (向下相容)
        else if (!empty($salt)) {
            $hashedPassword = hash('sha256', $password . $salt);
            if ($hashedPassword === $storedPassword) {
                $loginFoundUser = true;
                
                // 【建議】可以在此處自動將密碼升級為新版雜湊
                // 但為了單純化先不在此處實作自動升級
            }
        }
    }

    if ($loginFoundUser) {
        $loginStrGroup = "";

        if (PHP_VERSION >= 5.1) {session_regenerate_id(true);} else {session_regenerate_id();}
        //declare two session variables and assign them
        $_SESSION['MM_LoginAccountUsername'] = $loginUsername;
        $_SESSION['MM_LoginAccountUserGroup'] = $loginStrGroup;
        $_SESSION['MM_LoginAccountUserId'] = $userData['user_id'];  // 【新增】儲存 user_id 供權限檢查使用
        
        // 【新增】初始化 CSRF Token 供後台 AJAX 動作使用 (與 Slim 共享)
        if (!isset($_SESSION['csrf_name'])) {
            $_SESSION['csrf_name'] = bin2hex(random_bytes(16));
            $_SESSION['csrf_value'] = bin2hex(random_bytes(32));
        }
        
        // 【新增】載入使用者的權限群組和權限
        require_once(__DIR__ . '/includes/authorityHelper.php');
        
        // 查詢使用者的群組 ID
        $groupStmt = $conn->prepare("SELECT group_id FROM admin WHERE user_id = :user_id");
        $groupStmt->execute([':user_id' => $userData['user_id']]);
        $groupData = $groupStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($groupData && $groupData['group_id']) {
            $_SESSION['MM_UserGroupId'] = $groupData['group_id'];
            
            // 載入該群組的所有權限
            $permissions = getGroupPermissions($conn, $groupData['group_id']);
            $_SESSION['MM_UserPermissions'] = $permissions;
        } else {
            // 沒有群組，設定為空權限
            $_SESSION['MM_UserGroupId'] = null;
            $_SESSION['MM_UserPermissions'] = [];
        }

        // --- 【新增】記錄登入成功日誌 ---
        try {
            $logIp = $_SERVER['REMOTE_ADDR'];
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $logIp = $_SERVER['HTTP_CLIENT_IP']; }
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $logIp = $_SERVER['HTTP_X_FORWARDED_FOR']; }
            
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // 簡易判斷裝置
            $device = 'Desktop';
            if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($userAgent))) {
                $device = 'Tablet';
            } elseif (preg_match('/(mobile|android|iphone|ipad|phone)/i', strtolower($userAgent))) {
                $device = 'Mobile';
            }

            $logStmt = $conn->prepare("INSERT INTO admin_login_logs (user_id, username, login_ip, login_status, login_type, user_device, user_agent, login_time) VALUES (:uid, :uname, :ip, 'success', 'normal', :device, :ua, NOW())");
            $logStmt->execute([
                ':uid' => $userData['user_id'],
                ':uname' => $loginUsername,
                ':ip' => $logIp,
                ':device' => $device,
                ':ua' => $userAgent
            ]);
        } catch (Exception $e) {
            // 日誌寫入失敗不應阻擋登入，僅記錄錯誤或忽略
             error_log("Login log error: " . $e->getMessage());
        }
        // --------------------------------

        // if (isset($_SESSION['PrevUrl'])) {
        //     $MM_redirectLoginSuccess = $_SESSION['PrevUrl'];
        // }

        unset($_SESSION["errorTimes"]);
        header("Location: " . $MM_redirectLoginSuccess);

    } else {
        $_SESSION['errorTimes']++;

        // --- 【新增】記錄登入失敗日誌 ---
        try {
             $logIp = $_SERVER['REMOTE_ADDR'];
             if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $logIp = $_SERVER['HTTP_CLIENT_IP']; }
             elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $logIp = $_SERVER['HTTP_X_FORWARDED_FOR']; }
             
             $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
             
             // 簡易判斷裝置
             $device = 'Desktop';
             if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($userAgent))) {
                 $device = 'Tablet';
             } elseif (preg_match('/(mobile|android|iphone|ipad|phone)/i', strtolower($userAgent))) {
                 $device = 'Mobile';
             }
 
             // 失敗時可能沒有 user_id，查詢看看是否存在此帳號
             $failUid = null;
             if ($userData) { $failUid = $userData['user_id']; }
 
             $logStmt = $conn->prepare("INSERT INTO admin_login_logs (user_id, username, login_ip, login_status, login_type, user_device, user_agent, login_time) VALUES (:uid, :uname, :ip, 'fail', 'normal', :device, :ua, NOW())");
             $logStmt->execute([
                 ':uid' => $failUid,
                 ':uname' => $loginUsername,
                 ':ip' => $logIp,
                 ':device' => $device,
                 ':ua' => $userAgent
             ]);
        } catch (Exception $e) {
             error_log("Login fail log error: " . $e->getMessage());
        }
        // --------------------------------

        if ($_SESSION['errorTimes'] >= 5) {

            $updateSQL = "UPDATE admin SET user_active='0' WHERE user_name=:user_name";

            $sth = $conn->prepare($updateSQL);
            $sth->bindParam(':user_name', $loginUsername, PDO::PARAM_STR);
            $sth->execute();
        }

        header("Location: " . $MM_redirectLoginFailed);
    }
}
?>


<!doctype html>
<html class="fixed">
	<head>

		<!-- Basic -->
		<meta charset="UTF-8">

		<meta name="keywords" content="HTML5 Admin Template" />
		<meta name="description" content="Porto Admin - Responsive HTML5 Template">
		<meta name="author" content="okler.net">

		<!-- Mobile Metas -->
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

		<!-- Web Fonts  -->
		<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

		<!-- Vendor CSS -->
		<link rel="stylesheet" href="../cms/template-style/vendor/bootstrap/css/bootstrap.css" />
		<link rel="stylesheet" href="../cms/template-style/vendor/animate/animate.compat.css">
		<link rel="stylesheet" href="../cms/template-style/vendor/font-awesome/css/all.min.css" />
		<link rel="stylesheet" href="../cms/template-style/vendor/boxicons/css/boxicons.min.css" />
		<link rel="stylesheet" href="../cms/template-style/vendor/magnific-popup/magnific-popup.css" />
		<link rel="stylesheet" href="../cms/template-style/vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css" />

		<!-- Theme CSS -->
		<link rel="stylesheet" href="../cms/template-style/css/theme.css" />

		<!-- Skin CSS -->
		<link rel="stylesheet" href="../cms/template-style/css/skins/default.css" />

		<!-- Theme Custom CSS -->
		<link rel="stylesheet" href="../cms/template-style/css/custom.css">

		<!-- Head Libs -->
		<script src="../cms/template-style/vendor/modernizr/modernizr.js"></script>
        
        <!-- SweetAlert2 -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	</head>
	<body>
		<!-- start: page -->
		<section class="body-sign">
			<div class="center-sign">
				<div class="panel card-sign">
					<div class="card-title-sign mt-3 text-end">
						<h2 class="title text-uppercase font-weight-bold m-0"><i class="bx bx-user-circle me-1 text-6 position-relative top-5"></i> Sign In</h2>
					</div>
					<div class="card-body">
						<form action="<?php echo $loginFormAction; ?>" method="POST" name="form1" id="form1">

							<div class="form-group mb-3">
								<label>Username</label>
								<div class="input-group">
									<input name="use_rname" type="text" class="form-control form-control-lg" />
									<span class="input-group-text">
										<i class="bx bx-user text-4"></i>
									</span>
								</div>
							</div>

							<div class="form-group mb-3">
								<div class="clearfix">
									<label class="float-left">Password</label>
								</div>
								<div class="input-group">
									<input name="user_password" type="password" class="form-control form-control-lg" />
									<span class="input-group-text">
										<i class="bx bx-lock text-4"></i>
									</span>
								</div>
							</div>

							<div class="row" style="align-items: center;">
								<div class="col-sm-8">
                                    <?php
                                    // 【新增】超級管理員快速登入按鈕（僅在允許的 IP 顯示）
                                    $superAdminConfig = require(__DIR__ . '/config/superAdminConfig.php');
                                    function getClientIP() {
                                        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                                            return $_SERVER['HTTP_CLIENT_IP'];
                                        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                                            return $_SERVER['HTTP_X_FORWARDED_FOR'];
                                        } else {
                                            return $_SERVER['REMOTE_ADDR'];
                                        }
                                    }
                                    $clientIP = getClientIP();
                                    if (in_array($clientIP, $superAdminConfig['allowed_ips'])): ?>
                                        <button type="button" id="superAdminLoginBtn" class="btn btn-primary mt-2" style="background: #ff6b6b; color: white;">
                                            🔑 超級管理員登入
                                        </button>
                                    <?php endif; ?>
                                </div>
								<div class="col-sm-4 text-end">
									<button type="submit" class="btn btn-primary mt-2">Sign In</button>
								</div>
							</div>

                            <!-- 
                            <div class="row">
                                <?php if ($_SESSION['errorTimes'] >= 5){ ?>
                                    <li class="loginlock">此帳號已被鎖，請通知管理員解鎖並更新密碼。</li>
                                <?php }else if($_SESSION['errorTimes'] > 0){ ?>
                                    <li class="loginerror">密碼輸入錯誤 <?= $_SESSION['errorTimes'] ?> 次，請重新輸入。<br>超過5次請通知管理員解鎖。</li>
                                <?php } ?>
                            </div> 
                            -->
						</form>
					</div>
				</div>
			</div>
		</section>
		<!-- end: page -->

		<!-- Vendor -->
		<script src="../cms/template-style/vendor/jquery/jquery.js"></script>
		<script src="../cms/template-style/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
		<script src="../cms/template-style/vendor/popper/umd/popper.min.js"></script>
		<script src="../cms/template-style/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
		<script src="../cms/template-style/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
		<script src="../cms/template-style/vendor/common/common.js"></script>
		<script src="../cms/template-style/vendor/nanoscroller/nanoscroller.js"></script>
		<script src="../cms/template-style/vendor/magnific-popup/jquery.magnific-popup.js"></script>
		<script src="../cms/template-style/vendor/jquery-placeholder/jquery.placeholder.js"></script>

		<!-- Specific Page Vendor -->

		<!-- Theme Base, Components and Settings -->
		<script src="../cms/template-style/js/theme.js"></script>

		<!-- Theme Custom -->
		<script src="../cms/template-style/js/custom.js"></script>

		<!-- Theme Initialization Files -->
		<script src="../cms/template-style/js/theme.init.js"></script>

	</body>
</html>

<script type="text/javascript" src="../cms/jquery/jquery-1.7.2.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script type="text/javascript">
    $(document).ready(function() {
        window.onSubmit = () => {
            form1.submit();
        }

        window.onError = () => {
            alert('發生錯誤，請稍後再試')
        }
        
        // 【新增】超級管理員快速登入
        $('#superAdminLoginBtn').click(function() {
            // if (!confirm('確定要使用超級管理員身份登入嗎？')) {
            //     return;
            // }
            
            $(this).prop('disabled', true).text('驗證中...');
            
            $.ajax({
                url: '../cms/super_admin_login.php',
                type: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.redirect;
                    } else {
                        alert('登入失敗：' + response.message);
                        $('#superAdminLoginBtn').prop('disabled', false).text('🔑 超級管理員登入');
                    }
                },
                error: function() {
                    alert('登入失敗：無法連接到伺服器');
                    $('#superAdminLoginBtn').prop('disabled', false).text('🔑 超級管理員登入');
                }
            });
        });

        $(".btnType").hover(function() {
            $(this).addClass('btnTypeClass');
            $(this).css('cursor', 'pointer');
        }, function() {
            $(this).removeClass('btnTypeClass');
        });

        var mrg = ($(window).height() - $('#login-wrapper-form').height()) / 2 - 40;
        $('#login-wrapper-form').css('margin-top', mrg + 'px');

        // 【新增】Login Error SweetAlert2
        <?php if ($_SESSION['errorTimes'] > 0): ?>
            <?php if ($_SESSION['errorTimes'] >= 5): ?>
                Swal.fire({
                    icon: 'error',
                    title: '帳號鎖定',
                    text: '此帳號已被鎖定，請通知管理員解鎖並更新密碼。',
                    confirmButtonColor: '#d33'
                });
            <?php else: ?>
                Swal.fire({
                    icon: 'warning',
                    title: '登入失敗',
                    html: '密碼輸入錯誤 <b><?= $_SESSION['errorTimes'] ?></b> 次，請重新輸入。<br>超過 5 次將鎖定帳號。',
                    confirmButtonColor: '#3085d6'
                });
            <?php endif; ?>
        <?php endif; ?>
    });
</script>
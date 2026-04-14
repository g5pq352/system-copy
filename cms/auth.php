<?php
require_once '../Connections/connect2data.php';
require_once '../config/config.php';

// 建議確認 session 已啟動
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------- Logout ---------------- */

$logoutAction = $_SERVER['PHP_SELF'] . "?doLogout=true";

if (isset($_GET['doLogout']) && $_GET['doLogout'] == "true") {

    $_SESSION = [];

    session_destroy();

    header("Location: " . PORTAL_AUTH_URL . "dashboard");
    exit;
}

/* ---------------- Auth check ---------------- */

$MM_authorizedUsers = "";

function isAuthorized($strUsers, $strGroups, $UserName, $UserGroup) {
    $isValid = false;

    if (!empty($UserName)) {
        $arrUsers = explode(",", $strUsers);
        $arrGroups = explode(",", $strGroups);

        if (in_array($UserName, $arrUsers)) $isValid = true;
        if (in_array($UserGroup, $arrGroups)) $isValid = true;
        if ($strUsers == "") $isValid = true;
    }
    return $isValid;
}

$MM_restrictGoTo = PORTAL_AUTH_URL . "signin";

if (!(
    isset($_SESSION['MM_LoginAccountUsername'])
    &&
    isAuthorized(
        "",
        $MM_authorizedUsers,
        $_SESSION['MM_LoginAccountUsername'],
        $_SESSION['MM_LoginAccountUserGroup']
    )
)) {
    header("Location: " . $MM_restrictGoTo);
    exit;
}

/* ---------------- Get User Data ---------------- */

$colname_RecUser = $_SESSION['MM_LoginAccountUsername'] ?? '';

$query_RecUser = "SELECT user_id, user_name, user_level FROM admin WHERE user_name = :user_name";
$RecUser = $conn->prepare($query_RecUser);
$RecUser->execute([':user_name' => $colname_RecUser]);
$row_RecUser = $RecUser->fetch();

/* ---------------- Get Role ---------------- */
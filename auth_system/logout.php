<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_unset();
session_destroy();

// Keep feedback in a fresh anonymous session after clearing authentication.
session_id("");
session_start();
$_SESSION["login_flash"] = "You have logged out successfully and your session was securely cleared";

header("Location: login.php");
exit();
?>

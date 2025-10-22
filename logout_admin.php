<?php
// Destroy admin session (use the admin session name)
if (session_status() === PHP_SESSION_NONE) {
    session_name('FES_ADMIN');
    session_start();
}
$_SESSION = [];
session_unset();
session_destroy();
header("Location: admin/admin_login.php");
exit;

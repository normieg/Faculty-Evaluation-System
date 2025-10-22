<?php
// Destroy student session (use the student session name)
if (session_status() === PHP_SESSION_NONE) {
    session_name('FES_STUDENT');
    session_start();
}
$_SESSION = [];
session_unset();
session_destroy();
header("Location: student/login.php");
exit;

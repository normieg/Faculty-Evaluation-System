<?php
session_start();

$_SESSION = [];
session_unset();
session_destroy();
header("Location: admin/admin_login.php");
exit;

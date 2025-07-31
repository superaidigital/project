<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['name'] = 'Test Admin';
header('Location: pages/shelters.php');
exit();
?>

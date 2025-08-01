<?php
// File: logout.php
// DESCRIPTION: สคริปต์สำหรับออกจากระบบ

// Initialize the session
session_start();
 
// Unset all of the session variables
$_SESSION = array();
 
// Destroy the session.
session_destroy();
 
// Redirect to login page
header("location: login.php");
exit;
?>
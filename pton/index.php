<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
require_once "db_connect.php";

// Fetch settings
$settings_result = $conn->query("SELECT * FROM settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Check system status
if ($settings['system_status'] != '1' && $_SESSION['role'] !== 'Admin') {
    // Gracefully handle disabled system
    $page = 'disabled';
} else {
    $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
}

$allowed_pages = ['dashboard', 'shelters', 'users', 'settings'];
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

include 'partials/header.php';

if ($page === 'disabled') {
    echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert"><p class="font-bold">ระบบปิดปรับปรุง</p><p>ขออภัยในความไม่สะดวก ขณะนี้ระบบกำลังปิดปรับปรุงชั่วคราว</p></div>';
} else {
    include "pages/{$page}.php";
}

include 'partials/footer.php';
?>

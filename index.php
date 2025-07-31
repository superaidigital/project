<?php
// index.php
define('BASE_PATH', __DIR__);

ini_set('display_errors', 0); // ไม่แสดง error บนหน้าจอจริง
ini_set('log_errors', 1); // แต่ให้บันทึก error ลงไฟล์ log
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- API Routing ---
// ตรวจสอบว่าเป็นการเรียก API หรือไม่
if (isset($_GET['api_action'])) {
    $action = basename($_GET['api_action']);
    $api_file = __DIR__ . "/api/{$action}.php";

    if (file_exists($api_file)) {
        require_once $api_file; // ไฟล์ API จะจัดการทุกอย่างและ exit เอง
    } else {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'API endpoint not found.']);
    }
    exit;
}

// --- Page Rendering ---
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "db_connect.php";
require_once "includes/functions.php";

try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบไฟล์ db_connect.php");
    }

    $settings_result = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $permissions = getUserPermissions($conn, $_SESSION['user_id'], $_SESSION['role']);
    $_SESSION['permissions'] = $permissions;
    
    $is_unassigned = false;
    if (in_array($_SESSION['role'], ['Coordinator', 'HealthStaff'])) {
        $is_unassigned = isUserUnassigned($conn, $_SESSION['user_id'], $_SESSION['role']);
    }

    $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_SANITIZE_STRING) : 'dashboard';

    if (($settings['system_status'] ?? '1') != '1' && $_SESSION['role'] !== 'Admin') {
        $page = 'system_disabled';
    } elseif ($is_unassigned) {
        if ($page !== 'assign_shelter') {
            header("Location: index.php?page=assign_shelter");
            exit;
        }
    } elseif (!in_array($page, $permissions['allowed_pages'])) {
        $page = 'dashboard';
    }

    if (!file_exists("pages/{$page}.php")) {
        throw new Exception("ไม่พบหน้าที่ต้องการ ({$page}.php)");
    }

    include 'partials/header.php';
    require_once "pages/{$page}.php";
    include 'partials/footer.php';

} catch (Exception $e) {
    error_log("Error in index.php: " . $e->getMessage());
    $error_message_for_user = $e->getMessage();
    
    if (!headers_sent()) {
        include 'partials/header.php';
    }
    include 'pages/error.php'; // ส่งตัวแปร $e ไปให้หน้า error
    if (!headers_sent()) {
       include 'partials/footer.php';
    }
}
?>

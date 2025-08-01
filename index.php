<?php
// File: index.php (แก้ไข API Routing แล้ว)
// DESCRIPTION: ไฟล์หลักที่ควบคุมการทำงานทั้งหมดของเว็บแอปพลิเคชัน

define('BASE_PATH', __DIR__);

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/pages/system_error.log');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Page Rendering & Page-based API Handling ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once "db_connect.php";
require_once "includes/functions.php";

// *** REVISED: Check if the request is a page-based API call ***
$page = isset($_GET['page']) ? basename(filter_var($_GET['page'], FILTER_SANITIZE_STRING)) : 'dashboard';
$is_page_api_call = isset($_GET['api']);

// If it's a page-based API, just run the page script.
// The page script is responsible for outputting JSON and calling exit().
if ($is_page_api_call) {
    $page_path = __DIR__ . "/pages/{$page}.php";
    if (file_exists($page_path)) {
        require_once $page_path;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => "API page '{$page}.php' not found."]);
    }
    exit(); // Ensure script stops here for API calls.
}

// --- Normal Page Rendering Logic ---
try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้", 503);
    }

    $settings_result = $conn->query("SELECT setting_key, setting_value FROM settings");
    if (!$settings_result) throw new Exception("ไม่สามารถดึงข้อมูลการตั้งค่าระบบได้", 500);
    $settings = array_column($settings_result->fetch_all(MYSQLI_ASSOC), 'setting_value', 'setting_key');

    if (!isset($_SESSION['permissions'])) {
        $_SESSION['permissions'] = getUserPermissions($conn, $_SESSION['user_id'], $_SESSION['role']);
    }
    $permissions = $_SESSION['permissions'];
    
    // --- VERIFICATION & ASSIGNMENT LOGIC ---
    if ($_SESSION['role'] === 'Coordinator' || $_SESSION['role'] === 'HealthStaff') {
        $user_check_stmt = $conn->prepare("SELECT assigned_shelter_id, is_verified FROM users WHERE id = ?");
        $user_check_stmt->bind_param("i", $_SESSION['user_id']);
        $user_check_stmt->execute();
        $user_data = $user_check_stmt->get_result()->fetch_assoc();
        $user_check_stmt->close();

        $is_unassigned = isUserUnassigned($conn, $_SESSION['user_id'], $_SESSION['role']);

        if (!$is_unassigned && $user_data['is_verified'] == 0) {
            if ($page !== 'verify_account') {
                header("Location: index.php?page=verify_account");
                exit;
            }
        } 
        elseif ($is_unassigned) {
            if ($page !== 'assign_shelter') {
                header("Location: index.php?page=assign_shelter");
                exit;
            }
        }
    }

    if (($settings['system_status'] ?? '1') != '1' && $_SESSION['role'] !== 'Admin') {
        $page = 'system_disabled';
    } 
    elseif (!in_array($page, $permissions['allowed_pages'])) {
        $page = 'dashboard';
    }

    $page_path = __DIR__ . "/pages/{$page}.php";
    if (!file_exists($page_path)) {
        if ($page === 'verify_account') {
            file_put_contents($page_path, "<?php // Placeholder for verify_account.php ?>");
        } else {
            throw new Exception("ไม่พบหน้าที่ต้องการ ({$page}.php)", 404);
        }
    }

    include 'partials/header.php';
    require_once $page_path;
    include 'partials/footer.php';

} catch (Exception $e) {
    $error_code = $e->getCode();
    $error_message_for_user = $e->getMessage();
    
    if (!headers_sent()) include 'partials/header.php';
    include 'pages/error.php';
    if (!headers_sent()) include 'partials/footer.php';
}
?>

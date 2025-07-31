<?php
define('BASE_PATH', __DIR__);

// Set error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    $session_opts = [
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict'
    ];
    session_start($session_opts);
}

// --- API Request Routing ---
// If the request is for an API, handle it here before any HTML is sent.
if (isset($_GET['page']) && isset($_GET['api'])) {
    $page = filter_var($_GET['page'], FILTER_SANITIZE_STRING);
    $pageFile = __DIR__ . "/pages/{$page}.php";
    
    if (file_exists($pageFile)) {
        // The page file is responsible for its own logic, including
        // db connection, session checks, JSON output, and exiting.
        require_once $pageFile;
    } else {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'API endpoint not found.']);
    }
    // The included page file is expected to call exit(), but we add this as a safeguard.
    exit;
}

// --- Full Page Rendering ---
// Redirect to login if not authenticated for a regular page view.
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "db_connect.php";
require_once "includes/functions.php";

try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
    }

    // Fetch system settings
    $settings_sql = "SELECT setting_key, setting_value FROM settings";
    $settings_result = $conn->prepare($settings_sql);
    if (!$settings_result || !$settings_result->execute()) {
        throw new Exception("ไม่สามารถดึงการตั้งค่าระบบได้");
    }

    $settings = [];
    $result = $settings_result->get_result();
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Check system status and user permissions
    if (($settings['system_status'] ?? '1') != '1' && $_SESSION['role'] !== 'Admin') {
        $page = 'system_disabled';
    } else {
        $permissions = getUserPermissions($conn, $_SESSION['user_id'], $_SESSION['role']);
        if (!$permissions) {
            throw new Exception("ไม่สามารถดึงข้อมูลสิทธิ์การใช้งานได้");
        }
        $_SESSION['permissions'] = $permissions;

        $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_SANITIZE_STRING) : 'dashboard';
        if (!in_array($page, $permissions['allowed_pages'])) {
            $page = 'dashboard'; // Default to dashboard if not allowed
        }

        if (!file_exists("pages/{$page}.php")) {
            throw new Exception("ไม่พบหน้าที่ต้องการ ({$page}.php)");
        }
    }

    // Start rendering the page
    include 'partials/header.php';

    if ($page === 'system_disabled') {
        require_once 'pages/system_disabled.php';
    } else {
        require_once "pages/{$page}.php";
    }

    include 'partials/footer.php';

} catch (Exception $e) {
    error_log("Error in index.php: " . $e->getMessage());
    
    // To avoid "headers already sent" error, check if headers were sent before including header.
    if (!headers_sent()) {
        include 'partials/header.php';
    }
    require_once 'pages/error.php';
    include 'partials/footer.php';
}
?>

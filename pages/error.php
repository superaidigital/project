<?php
// ป้องกันการเข้าถึงไฟล์โดยตรง
if (!defined('BASE_PATH')) {
    http_response_code(403);
    die('Forbidden');
}

// ตรวจสอบและบันทึก error log
$error_message = 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';
$error_code = 500;
$show_details = (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin');

if (isset($e) && $e instanceof Exception) {
    $error_message = $e->getMessage();
    $error_code = $e->getCode() ?: 500;
    error_log("Application Error: " . $e->getMessage());
    if (function_exists('logSystemError')) {
        logSystemError($error_message, [
            'error_code' => $error_code,
            'user_id' => $_SESSION['id'] ?? null,
            'page' => $_GET['page'] ?? null
        ]);
    }
}

/**
 * Logs system errors to a file or other logging mechanism.
 *
 * @param string $message The error message.
 * @param array $context Additional context (e.g., error code, user ID, page).
 */
function logSystemError($message, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . " | $message | " . json_encode($context) . PHP_EOL;
    // You can change the log file path as needed
    file_put_contents(__DIR__ . '/system_error.log', $logEntry, FILE_APPEND);
}

// กำหนดประเภท error
$error_types = [
    404 => [
        'title' => 'ไม่พบหน้าที่ต้องการ',
        'class' => 'border-yellow-500 bg-yellow-100 text-yellow-700'
    ],
    403 => [
        'title' => 'ไม่มีสิทธิ์เข้าถึง',
        'class' => 'border-orange-500 bg-orange-100 text-orange-700'
    ],
    'default' => [
        'title' => 'เกิดข้อผิดพลาด',
        'class' => 'border-red-500 bg-red-100 text-red-700'
    ]
];
$error_type = $error_types[$error_code] ?? $error_types['default'];
?>

<div class="min-h-screen flex items-center justify-center">
    <div class="max-w-lg w-full mx-4">
        <div class="border-l-4 p-4 <?php echo htmlspecialchars($error_type['class']); ?>">
            <div class="flex items-center mb-2">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h1 class="font-bold text-lg"><?php echo htmlspecialchars($error_type['title']); ?></h1>
            </div>
            <p class="mb-4">กรุณาลองใหม่อีกครั้งหรือติดต่อผู้ดูแลระบบ</p>
            <?php if ($show_details): ?>
            <div class="mt-4 p-3 bg-white bg-opacity-50 rounded">
                <p class="font-bold mb-1">รายละเอียดข้อผิดพลาด:</p>
                <p class="font-mono text-sm"><?php echo htmlspecialchars($error_message); ?></p>
                <?php if (isset($e) && $e instanceof Exception && $e->getTrace()): ?>
                <pre class="mt-2 text-xs overflow-x-auto"><?php echo htmlspecialchars(print_r($e->getTrace(), true)); ?></pre>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="mt-6 flex justify-center">
                <a href="index.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    กลับหน้าหลัก
                </a>
            </div>
        </div>
    </div>
</div>
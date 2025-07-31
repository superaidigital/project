<?php
define('BASE_PATH', __DIR__);

// ตรวจสอบการเข้าถึงโดยตรง
if (!defined('BASE_PATH')) {
    http_response_code(403);
    die('Forbidden');
}

// ดึงข้อมูลการปิดระบบ
$maintenance_message = $settings['maintenance_message'] ?? 'ขณะนี้ระบบกำลังปิดปรับปรุงชั่วคราว';
$maintenance_start = $settings['maintenance_start'] ?? null;
$maintenance_end = $settings['maintenance_end'] ?? null;

// ตรวจสอบสถานะการเข้าถึง
$canAccessSystem = $_SESSION['role'] === 'Admin';
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
        <div class="flex items-center mb-2">
            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="font-bold text-lg">ระบบปิดปรับปรุง</p>
        </div>
        
        <p class="mb-2"><?php echo htmlspecialchars($maintenance_message); ?></p>
        
        <?php if ($maintenance_start && $maintenance_end): ?>
        <p class="text-sm">
            ระยะเวลาปิดปรับปรุง: 
            <?php 
            echo date('d/m/Y H:i', strtotime($maintenance_start));
            echo ' - ';
            echo date('d/m/Y H:i', strtotime($maintenance_end));
            ?>
        </p>
        <?php endif; ?>
    </div>

    <?php if ($canAccessSystem): ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4">
        <p class="font-bold">สำหรับผู้ดูแลระบบ</p>
        <p class="mt-2">คุณสามารถเข้าใช้งานระบบได้ตามปกติในระหว่างปิดปรับปรุง</p>
        <div class="mt-4">
            <a href="index.php?page=settings" 
               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                ไปยังการตั้งค่าระบบ
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// บันทึก log การเข้าชมหน้านี้
if (function_exists('logSystemAccess')) {
    logSystemAccess('system_disabled', [
        'user_id' => $_SESSION['id'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'access_granted' => $canAccessSystem
    ]);
}

/**
 * Logs system access events to a file or other logging mechanism.
 *
 * @param string $page The page or event name.
 * @param array $context Additional context (e.g., user ID, role, access status).
 */
function logSystemAccess($page, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . " | $page | " . json_encode($context) . PHP_EOL;
    // You can change the log file path as needed
    file_put_contents(__DIR__ . '/system_access.log', $logEntry, FILE_APPEND);
}
?>

<script>
// ตรวจสอบว่าตัวแปร amphoes ถูกกำหนดหรือยัง
if (typeof amphoes === 'undefined') {
    var amphoes = []; // หรือ fetch ข้อมูลก่อน
}

// ตรวจสอบ element ก่อนใช้งาน
function populateAmphoeDropdowns() {
    const amphoeDropdown = document.querySelector('#amphoeDropdown');
    if (!amphoeDropdown) return; // ป้องกัน error

    amphoeDropdown.innerHTML = amphoes.map(a => 
        `<option value="${a.id}">${a.name}</option>`
    ).join('');
}

// เรียกใช้หลังจาก amphoes ถูกกำหนดค่าแล้วเท่านั้น
populateAmphoeDropdowns();
</script>
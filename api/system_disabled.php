<?php
// File: pages/system_disabled.php
// DESCRIPTION: หน้าที่แสดงเมื่อระบบถูกปิดใช้งาน

if (!defined('BASE_PATH')) {
    http_response_code(403);
    die('Forbidden');
}

$maintenance_message = $settings['maintenance_message'] ?? 'ขณะนี้ระบบกำลังปิดปรับปรุงชั่วคราว ขออภัยในความไม่สะดวก';
?>

<div class="flex items-center justify-center" style="min-height: 60vh;">
    <div class="max-w-2xl w-full mx-4 text-center">
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-8 rounded-lg shadow-md">
            <i data-lucide="server-off" class="w-16 h-16 mx-auto text-yellow-500"></i>
            <h1 class="text-2xl font-bold mt-4">ระบบปิดปรับปรุง</h1>
            <p class="mt-2"><?php echo htmlspecialchars($maintenance_message); ?></p>
        </div>
    </div>
</div>
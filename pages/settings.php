<?php
// pages/settings.php

// เริ่ม session และตรวจสอบสิทธิ์ผู้ดูแลระบบ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p class="font-bold">ไม่มีสิทธิ์เข้าถึง</p><p>คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้</p></div>';
    exit();
}

require_once 'db_connect.php';

// --- ศูนย์กลางการตั้งค่าเมนู ---
// Array นี้ควรอยู่ในไฟล์ config กลาง (เช่น config/app.php)
// และถูก include ทั้งในหน้านี้และใน header.php เพื่อไม่ให้โค้ดซ้ำซ้อน
$navItems = [
    'dashboard' => [
        'label' => 'หน้าหลัก',
        'icon' => 'home',
        'roles' => ['Admin', 'Coordinator', 'HealthStaff', 'User'],
    ],
    'shelters' => [
        'label' => 'จัดการข้อมูลศูนย์',
        'icon' => 'building',
        'roles' => ['Admin', 'Coordinator', 'HealthStaff'],
    ],
    'users' => [
        'label' => 'จัดการผู้ใช้งาน',
        'icon' => 'users',
        'roles' => ['Admin'],
    ],
    'settings' => [
        'label' => 'ตั้งค่าระบบ',
        'icon' => 'settings',
        'roles' => ['Admin'],
    ],
];

// --- API Logic สำหรับการอัปเดตค่า ---
// ใช้การตรวจสอบ REQUEST_METHOD ซึ่งเป็นมาตรฐานกว่า
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูล JSON ไม่ถูกต้อง']);
        exit();
    }

    try {
        $conn->begin_transaction();
        // ใช้ INSERT ... ON DUPLICATE KEY UPDATE เพื่อสร้างค่าใหม่หากยังไม่มี
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        foreach ($data as $key => $value) {
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
        
        $conn->commit();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกการตั้งค่าสำเร็จ']);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        // ไม่ควรแสดง error ของ DB ตรงๆ ใน Production
        error_log('Settings update error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกลงฐานข้อมูล']);
    }
    
    $conn->close();
    exit();
}

// --- ดึงข้อมูลการตั้งค่าปัจจุบันมาแสดง ---
$settings = [];
try {
    $settings_result = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // จัดการ error กรณีตาราง settings ไม่มี
    $settings = []; // ตั้งค่าเป็น array ว่างเพื่อให้หน้าเว็บไม่พัง
}
?>

<!-- ส่วนแสดงผล HTML และฟอร์ม -->
<div class="space-y-8">

    <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
        <form id="settingsForm" class="space-y-8">
            
            <!-- การตั้งค่าสถานะระบบ -->
            <div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3 border-b pb-2">การตั้งค่าทั่วไป</h3>
                <div class="pl-2">
                    <h4 class="text-lg font-medium text-gray-800 mb-2">สถานะระบบ</h4>
                    <label for="system_status" class="flex items-center cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" id="system_status" name="system_status" class="sr-only" <?= ($settings['system_status'] ?? '0') == '1' ? 'checked' : '' ?>>
                            <div class="block bg-gray-600 w-14 h-8 rounded-full"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
                        </div>
                        <div class="ml-4 text-gray-700">
                            <span class="font-medium">เปิดใช้งานระบบ</span>
                            <p class="text-sm text-gray-500 mt-1">หากปิด, ผู้ใช้ที่ไม่ใช่ผู้ดูแลระบบจะเห็นข้อความแจ้งปิดปรับปรุง</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- การตั้งค่าเมนู (สร้างแบบไดนามิก) -->
            <!-- <div class="border-t pt-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4 border-b pb-2">การแสดงผลเมนู</h3>
                <div class="space-y-4 pl-2">
                    <?php foreach ($navItems as $key => $item): ?>
                        <?php 
                            $setting_key = 'menu_' . $key;
                            // กำหนดค่าเริ่มต้นเป็น 1 (เปิด) หากยังไม่มีใน DB
                            $is_checked = ($settings[$setting_key] ?? '1') == '1';
                        ?>
                        <label class="flex items-center">
                            <input type="checkbox" name="<?= htmlspecialchars($setting_key) ?>" class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= $is_checked ? 'checked' : '' ?>> 
                            <span class="ml-3 text-gray-700">
                                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="inline-block w-4 h-4 mr-1"></i>
                                <?= htmlspecialchars($item['label']) ?>
                                <span class="text-xs text-gray-500">(สิทธิ์: <?= htmlspecialchars(implode(', ', $item['roles'])) ?>)</span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div> -->

            <!-- ปุ่มบันทึก -->
            <div class="mt-8 pt-5 border-t flex justify-end">
                 <button type="submit" class="inline-flex items-center px-8 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                    <span>บันทึกการเปลี่ยนแปลง</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* CSS สำหรับ Toggle Switch */
input:checked ~ .dot { 
    transform: translateX(1.5rem); /* สำหรับ h-8 w-14 */
    background-color: #4f46e5; 
}
input:checked ~ .block { 
    background-color: #a5b4fc; /* indigo-300 */
}
</style>

<!-- JavaScript สำหรับจัดการฟอร์ม -->
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// เรียกใช้ Lucide Icons หลังจากที่สร้างไอคอนแบบไดนามิกเสร็จ
lucide.createIcons();

document.getElementById('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const data = {};
    
    // รวบรวมข้อมูลจากฟอร์มแบบไดนามิก
    data['system_status'] = form.querySelector('[name="system_status"]').checked ? '1' : '0';
    const menuCheckboxes = form.querySelectorAll('input[type="checkbox"][name^="menu_"]');
    menuCheckboxes.forEach(cb => {
        data[cb.name] = cb.checked ? '1' : '0';
    });

    const submitButton = form.querySelector('button[type="submit"]');
    const buttonSpan = submitButton.querySelector('span');
    const originalButtonText = buttonSpan.textContent;
    submitButton.disabled = true;
    buttonSpan.textContent = 'กำลังบันทึก...';
    
    try {
        // ส่งข้อมูลไปที่หน้าปัจจุบัน (index.php?page=settings)
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest' // เป็น convention ที่ดีสำหรับระบุว่าเป็น AJAX
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (response.ok) {
            await Swal.fire({ 
                icon: 'success', 
                title: 'สำเร็จ!', 
                text: result.message, 
                timer: 2000,
                showConfirmButton: false
            });
            location.reload();
        } else {
            Swal.fire({ 
                icon: 'error', 
                title: 'เกิดข้อผิดพลาด', 
                text: result.message || 'ไม่สามารถบันทึกข้อมูลได้',
                confirmButtonColor: '#4f46e5'
            });
        }
    } catch (error) {
        Swal.fire({ 
            icon: 'error', 
            title: 'การเชื่อมต่อล้มเหลว', 
            text: 'ไม่สามารถส่งข้อมูลไปยังเซิร์ฟเวอร์ได้',
            confirmButtonColor: '#4f46e5'
        });
    } finally {
        submitButton.disabled = false;
        buttonSpan.textContent = originalButtonText;
    }
});
</script>

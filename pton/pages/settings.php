<?php
// pages/settings.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert"><p class="font-bold">ไม่มีสิทธิ์เข้าถึง</p><p>คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้</p></div>';
    exit();
}

require_once 'db_connect.php';

// --- API Logic for settings ---
if (isset($_GET['api']) && $_GET['api'] == 'update_settings') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    foreach($data as $key => $value) {
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }
    $stmt->close();
    $conn->close();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกการตั้งค่าสำเร็จ']);
    exit();
}

// Fetch current settings
$settings_result = $conn->query("SELECT * FROM settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="space-y-6">
    <h1 class="text-3xl font-bold text-gray-800">ตั้งค่าระบบ</h1>

    <div class="bg-white p-8 rounded-xl shadow-md max-w-2xl">
        <form id="settingsForm" class="space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">สถานะระบบ</h3>
                <label for="system_status" class="flex items-center cursor-pointer">
                    <div class="relative">
                        <input type="checkbox" id="system_status" name="system_status" class="sr-only" <?= $settings['system_status'] == '1' ? 'checked' : '' ?>>
                        <div class="block bg-gray-600 w-14 h-8 rounded-full"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
                    </div>
                    <div class="ml-3 text-gray-700">
                        เปิดใช้งานระบบ
                    </div>
                </label>
                <p class="text-sm text-gray-500 mt-2">หากปิด, ผู้ใช้ที่ไม่ใช่ผู้ดูแลระบบจะไม่สามารถเข้าถึงหน้าใดๆ ได้</p>
            </div>

            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">การแสดงผลเมนู</h3>
                <div class="space-y-4">
                    <label class="flex items-center"><input type="checkbox" name="menu_dashboard" class="h-5 w-5 rounded border-gray-300 text-blue-600" <?= $settings['menu_dashboard'] == '1' ? 'checked' : '' ?>> <span class="ml-3 text-gray-700">ภาพรวมระบบ</span></label>
                    <label class="flex items-center"><input type="checkbox" name="menu_shelters" class="h-5 w-5 rounded border-gray-300 text-blue-600" <?= $settings['menu_shelters'] == '1' ? 'checked' : '' ?>> <span class="ml-3 text-gray-700">จัดการข้อมูลศูนย์</span></label>
                    <label class="flex items-center"><input type="checkbox" name="menu_users" class="h-5 w-5 rounded border-gray-300 text-blue-600" <?= $settings['menu_users'] == '1' ? 'checked' : '' ?>> <span class="ml-3 text-gray-700">จัดการผู้ใช้งาน (สำหรับ Admin)</span></label>
                    <label class="flex items-center"><input type="checkbox" name="menu_settings" class="h-5 w-5 rounded border-gray-300 text-blue-600" <?= $settings['menu_settings'] == '1' ? 'checked' : '' ?>> <span class="ml-3 text-gray-700">ตั้งค่าระบบ (สำหรับ Admin)</span></label>
                </div>
            </div>

            <div class="mt-8 flex justify-end">
                 <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">บันทึกการเปลี่ยนแปลง</button>
            </div>
        </form>
    </div>
</div>

<style>
input:checked ~ .dot { transform: translateX(100%); background-color: #4f46e5; }
input:checked ~ .block { background-color: #c7d2fe; }
</style>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        system_status: formData.get('system_status') ? '1' : '0',
        menu_dashboard: formData.get('menu_dashboard') ? '1' : '0',
        menu_shelters: formData.get('menu_shelters') ? '1' : '0',
        menu_users: formData.get('menu_users') ? '1' : '0',
        menu_settings: formData.get('menu_settings') ? '1' : '0',
    };

    try {
        const response = await fetch('pages/settings.php?api=update_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.status === 'success') {
            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: result.message, confirmButtonColor: '#2563EB' })
               .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message, confirmButtonColor: '#2563EB' });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'การเชื่อมต่อล้มเหลว', text: 'ไม่สามารถส่งข้อมูลได้', confirmButtonColor: '#2563EB' });
    }
});
</script>
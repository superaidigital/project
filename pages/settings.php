<?php
// File: pages/settings.php (ฉบับสมบูรณ์)
// DESCRIPTION: หน้าตั้งค่าระบบสำหรับ Admin (ปรับปรุงใหม่ทั้งหมด)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- API Logic ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
            throw new Exception('Permission Denied', 403);
        }

        require_once __DIR__ . '/../db_connect.php';
        if (!$conn || $conn->connect_error) {
            throw new Exception('Database connection failed', 500);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method.', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            throw new Exception('Invalid JSON data', 400);
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        foreach ($data as $key => $value) {
            // Sanitize key to prevent unexpected insertions
            if (in_array($key, ['system_status', 'maintenance_message'])) {
                $stmt->bind_param("ss", $key, $value);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'บันทึกการตั้งค่าสำเร็จ']);

    } catch (Exception $e) {
        if(isset($conn) && $conn->in_transaction) $conn->rollback();
        $http_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($http_code);
        error_log('Settings update error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()]);
    } finally {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }
    exit();
}

// --- Page Rendering Logic ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    die('Access Denied');
}
require_once __DIR__ . '/../db_connect.php';

$settings = [];
try {
    $settings_result = $conn->query("SELECT setting_key, setting_value FROM settings");
    if ($settings_result) {
        $settings = array_column($settings_result->fetch_all(MYSQLI_ASSOC), 'setting_value', 'setting_key');
    }
} catch (Exception $e) {
    $settings = []; // Fallback if table doesn't exist
}

// Default values
$system_status = $settings['system_status'] ?? '1';
$maintenance_message = $settings['maintenance_message'] ?? 'ขณะนี้ระบบกำลังปิดปรับปรุงชั่วคราว ขออภัยในความไม่สะดวก';
?>

<div class="space-y-8">
    <h1 class="text-3xl font-bold text-gray-800">ตั้งค่าระบบ</h1>
    <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg max-w-2xl mx-auto">
        <form id="settingsForm" class="space-y-8">
            <div>
                <h3 class="text-xl font-semibold text-gray-900 mb-3 border-b pb-2">การตั้งค่าทั่วไป</h3>
                <div class="pl-2 space-y-6">
                    <div>
                        <h4 class="text-lg font-medium text-gray-800 mb-2">สถานะระบบ</h4>
                        <label for="system_status" class="flex items-center cursor-pointer">
                            <div class="relative">
                                <input type="checkbox" id="system_status" name="system_status" class="sr-only" <?= $system_status == '1' ? 'checked' : '' ?>>
                                <div class="block bg-gray-600 w-14 h-8 rounded-full"></div>
                                <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
                            </div>
                            <div class="ml-4 text-gray-700">
                                <span class="font-medium">เปิดใช้งานระบบ</span>
                                <p class="text-sm text-gray-500 mt-1">หากปิด, ผู้ใช้ที่ไม่ใช่ผู้ดูแลระบบจะเห็นข้อความแจ้งปิดปรับปรุง</p>
                            </div>
                        </label>
                    </div>
                    <div>
                        <label for="maintenance_message" class="block text-lg font-medium text-gray-800 mb-2">ข้อความแจ้งปิดปรับปรุง</label>
                        <textarea id="maintenance_message" name="maintenance_message" rows="3" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"><?= htmlspecialchars($maintenance_message) ?></textarea>
                        <p class="text-sm text-gray-500 mt-1">ข้อความนี้จะแสดงเมื่อระบบถูกปิด</p>
                    </div>
                </div>
            </div>

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
input:checked ~ .dot { transform: translateX(1.5rem); background-color: #4f46e5; }
input:checked ~ .block { background-color: #a5b4fc; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitButton = settingsForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = `<svg class="animate-spin h-5 w-5 mr-3" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> กำลังบันทึก...`;

            const data = {
                'system_status': document.getElementById('system_status').checked ? '1' : '0',
                'maintenance_message': document.getElementById('maintenance_message').value
            };
            
            try {
                const response = await fetch('index.php?page=settings&api=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    await Swal.fire({ 
                        icon: 'success', 
                        title: 'สำเร็จ!', 
                        text: result.message, 
                        timer: 2000, 
                        showConfirmButton: false 
                    });
                } else {
                    throw new Error(result.message || 'ไม่สามารถบันทึกข้อมูลได้');
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: error.message });
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = `<i data-lucide="save" class="w-5 h-5 mr-2"></i><span>บันทึกการเปลี่ยนแปลง</span>`;
                lucide.createIcons();
            }
        });
    }
});
</script>

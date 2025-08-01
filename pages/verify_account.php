<?php
// File: pages/verify_account.php
// DESCRIPTION: หน้าสำหรับให้ Coordinator ยืนยันตัวตนด้วยรหัสผ่านครั้งแรก

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// API Logic for this page
if (isset($_GET['api']) && $_GET['api'] === 'verify_code') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../db_connect.php';

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    $submitted_code = $data['code'] ?? '';

    try {
        $stmt = $conn->prepare("SELECT verification_code FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result && $result['verification_code'] === $submitted_code) {
            // Code is correct, update user status
            $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Unset permissions to force a refresh on next page load
            unset($_SESSION['permissions']);

            echo json_encode(['status' => 'success', 'message' => 'ยืนยันตัวตนสำเร็จ!']);
        } else {
            throw new Exception('รหัสยืนยันไม่ถูกต้อง');
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    $conn->close();
    exit;
}
?>

<div class="max-w-md mx-auto mt-10">
    <div class="bg-white p-8 rounded-2xl shadow-lg text-center">
        <i data-lucide="shield-check" class="mx-auto h-16 w-16 text-green-500"></i>
        <h1 class="text-3xl font-bold text-gray-800 mt-4">ยืนยันบัญชีของคุณ</h1>
        <p class="text-gray-500 mt-2">เพื่อความปลอดภัย กรุณากรอกรหัสยืนยัน 6 หลักที่ได้รับจากผู้ดูแลระบบเพื่อเข้าใช้งานครั้งแรก</p>

        <form id="verifyForm" class="mt-8 space-y-6">
            <div>
                <label for="verification_code" class="sr-only">รหัสยืนยัน</label>
                <input type="text" name="code" id="verification_code" 
                       class="block w-full text-center text-2xl tracking-[1em] font-mono p-4 border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500" 
                       maxlength="6" required placeholder="------">
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-indigo-700 transition-colors">
                ยืนยันและเข้าสู่ระบบ
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('verifyForm');
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const codeInput = document.getElementById('verification_code');
        const code = codeInput.value;

        if (code.length !== 6 || !/^\d{6}$/.test(code)) {
            Swal.fire('ข้อมูลไม่ถูกต้อง', 'กรุณากรอกรหัสยืนยันเป็นตัวเลข 6 หลัก', 'warning');
            return;
        }

        try {
            const response = await fetch('index.php?page=verify_account&api=verify_code', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: code })
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
                // Redirect to dashboard after successful verification
                window.location.href = 'index.php?page=dashboard';
            } else {
                Swal.fire('เกิดข้อผิดพลาด', result.message, 'error');
                codeInput.value = '';
            }
        } catch (error) {
            Swal.fire('การเชื่อมต่อล้มเหลว', 'ไม่สามารถส่งข้อมูลได้', 'error');
        }
    });
});
</script>

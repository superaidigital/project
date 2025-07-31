<?php
// pages/update_shelter_details.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/permissions.php'; // For canManageShelter

// --- Data Validation and Pre-computation ---
if (!isset($_GET['shelter_id']) || !filter_var($_GET['shelter_id'], FILTER_VALIDATE_INT)) {
    die("Invalid Shelter ID.");
}
$shelter_id = intval($_GET['shelter_id']);

// Check permission
if (!canManageShelter($shelter_id)) {
     die("You do not have permission to manage this shelter.");
}

// Fetch shelter details
$stmt = $conn->prepare("SELECT name, type FROM shelters WHERE id = ?");
$stmt->bind_param("i", $shelter_id);
$stmt->execute();
$shelter = $stmt->get_result()->fetch_assoc();
if (!$shelter) {
    die("Shelter not found.");
}
$stmt->close();

$page_title = ($shelter['type'] === 'รพ.สต.') ? 'เพิ่ม/ลดจำนวนผู้ป่วย' : 'เพิ่ม/ลดจำนวนผู้เข้าพัก';
$success_message = '';
$error_message = '';


// --- FORM SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic data retrieval from POST
        $data = $_POST;
        $report_date = $data['report_date'] ?? date('Y-m-d');
        $operation_type = $data['operation_type'] ?? 'add';

        // --- Data Validation ---
        $total_patients = intval($data['total_patients'] ?? 0);
        $female_patients = intval($data['female_patients'] ?? 0);
        $pregnant_women = intval($data['pregnant_women'] ?? 0);

        if ($pregnant_women > $female_patients) {
            throw new Exception('จำนวนหญิงตั้งครรภ์ต้องไม่เกินจำนวนผู้ป่วยหญิงทั้งหมด');
        }

        $subGroups = [
            'disabled_patients', 'bedridden_patients', 'elderly_patients', 'child_patients',
            'chronic_disease_patients', 'diabetes_patients', 'hypertension_patients',
            'heart_disease_patients', 'mental_health_patients', 'kidney_disease_patients',
            'other_monitored_diseases'
        ];
        $subGroupsTotal = array_reduce($subGroups, fn($sum, $key) => $sum + (intval($data[$key] ?? 0)), 0);

        if ($subGroupsTotal > $total_patients) {
            throw new Exception("ยอดรวมในกลุ่มย่อย ({$subGroupsTotal}) ต้องไม่เกินจำนวนผู้ป่วยทั้งหมด ({$total_patients})");
        }
        
        // --- Database Operations ---
        $conn->begin_transaction();

        // 1. Get current occupancy from shelters table
        $stmt_current = $conn->prepare("SELECT current_occupancy FROM shelters WHERE id = ?");
        $stmt_current->bind_param("i", $shelter_id);
        $stmt_current->execute();
        $old_occupancy = $stmt_current->get_result()->fetch_assoc()['current_occupancy'] ?? 0;
        $stmt_current->close();
        
        // 2. Calculate new total occupancy
        $change_amount = $total_patients;
        $new_total = ($operation_type === 'subtract') 
            ? max(0, $old_occupancy - $change_amount)
            : $old_occupancy + $change_amount;
            
        // 3. Get existing daily report data
        $stmt_check = $conn->prepare("SELECT * FROM hospital_daily_reports WHERE shelter_id = ? AND report_date = ?");
        $stmt_check->bind_param("is", $shelter_id, $report_date);
        $stmt_check->execute();
        $existing_data = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();
        
        // 4. Calculate new values for each field
        $fields_to_update = [];
        $all_fields = array_merge(['total_patients', 'male_patients', 'female_patients', 'pregnant_women'], $subGroups);

        foreach ($all_fields as $field) {
            $change = intval($data[$field] ?? 0);
            $existing = intval($existing_data[$field] ?? 0);
            if ($operation_type === 'subtract') {
                $fields_to_update[$field] = max(0, $existing - $change);
            } else {
                $fields_to_update[$field] = $existing + $change;
            }
        }
        // Override total patients with the master calculation
        $fields_to_update['total_patients'] = $new_total;
        
        // 5. INSERT or UPDATE hospital_daily_reports
        if ($existing_data) {
            $sql_parts = [];
            foreach ($fields_to_update as $key => $value) $sql_parts[] = "`$key` = ?";
            $sql = "UPDATE `hospital_daily_reports` SET " . implode(', ', $sql_parts) . " WHERE `id` = ?";
            $types = str_repeat('i', count($fields_to_update)) . 'i';
            $params = array_values($fields_to_update);
            $params[] = $existing_data['id'];
            $stmt_report = $conn->prepare($sql);
            $stmt_report->bind_param($types, ...$params);
        } else {
            $created_by = $_SESSION['user_id'] ?? null;
            $keys = array_keys($fields_to_update);
            $sql = "INSERT INTO `hospital_daily_reports` (`shelter_id`, `report_date`, `created_by`, " . implode(', ', array_map(fn($k) => "`$k`", $keys)) . ") VALUES (?, ?, ?, " . rtrim(str_repeat('?, ', count($keys)), ', ') . ")";
            $types = 'isi' . str_repeat('i', count($fields_to_update));
            $params = array_merge([$shelter_id, $report_date, $created_by], array_values($fields_to_update));
            $stmt_report = $conn->prepare($sql);
            $stmt_report->bind_param($types, ...$params);
        }
        if (!$stmt_report->execute()) throw new Exception("Failed to save report: " . $stmt_report->error);
        $stmt_report->close();
        
        // 6. Update shelters table
        $stmt_shelter = $conn->prepare("UPDATE shelters SET current_occupancy = ? WHERE id = ?");
        $stmt_shelter->bind_param("ii", $new_total, $shelter_id);
        if (!$stmt_shelter->execute()) throw new Exception("Failed to update shelter total: " . $stmt_shelter->error);
        $stmt_shelter->close();

        // 7. Insert into shelter_logs
        if ($change_amount > 0) {
            $item_name = ($shelter['type'] === 'รพ.สต.') ? "ผู้ป่วย (รพ.สต.)" : "ผู้เข้าพัก (ศูนย์พักพิง)";
            $stmt_log = $conn->prepare("INSERT INTO shelter_logs (shelter_id, item_name, item_unit, change_amount, log_type, new_total) VALUES (?, ?, 'คน', ?, ?, ?)");
            $stmt_log->bind_param("isisi", $shelter_id, $item_name, $change_amount, $operation_type, $new_total);
            if (!$stmt_log->execute()) throw new Exception("Failed to create log: " . $stmt_log->error);
            $stmt_log->close();
        }

        $conn->commit();
        
        // Redirect on success
        header("Location: index.php?page=shelters&update=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="index.php?page=shelters" class="p-2 bg-gray-200 rounded-lg text-gray-600 hover:bg-gray-300 transition-colors" title="กลับไปหน้ารายการ">
            <i data-lucide="arrow-left" class="h-6 w-6"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
            <p class="text-gray-500">สำหรับศูนย์: <strong><?= htmlspecialchars($shelter['name']) ?></strong></p>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
            <p class="font-bold">เกิดข้อผิดพลาด</p>
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-lg">
        <form id="hospitalReportForm" method="POST" action="index.php?page=update_shelter_details&shelter_id=<?= $shelter_id ?>" class="space-y-6">
            <input type="hidden" name="report_date" value="<?= date('Y-m-d') ?>">
            
            <div class="bg-gray-50 p-4 rounded-lg border">
                <h4 class="font-semibold text-gray-800 mb-3">การดำเนินการ</h4>
                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="operation_type" value="add" class="h-4 w-4 text-green-600 border-gray-300 focus:ring-green-500" checked>
                        <span class="ml-2 font-medium text-green-700">เพิ่มยอด (+)</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" name="operation_type" value="subtract" class="h-4 w-4 text-red-600 border-gray-300 focus:ring-red-500">
                        <span class="ml-2 font-medium text-red-700">ลดยอด (-)</span>
                    </label>
                </div>
                 <p class="text-xs text-gray-500 mt-2">
                    <span class="font-medium">คำแนะนำ:</span> กรุณากรอกจำนวนที่ต้องการ "เปลี่ยนแปลง" จากยอดปัจจุบัน (ไม่ใช่ยอดรวมใหม่)
                </p>
            </div>
            
            <div class="bg-blue-50 p-4 rounded-lg shadow-sm border border-blue-200">
                <h4 class="font-semibold text-blue-800 mb-3">ข้อมูลทั่วไป</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชาย</label>
                        <input type="number" name="male_patients" id="malePatients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">หญิง</label>
                        <input type="number" name="female_patients" id="femalePatients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">หญิงตั้งครรภ์</label>
                        <input type="number" name="pregnant_women" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                     <div>
                        <label class="block text-sm font-medium text-gray-700">รวม (อัตโนมัติ)</label>
                        <input type="number" name="total_patients" id="totalPatients" class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-lg shadow-sm" readonly>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 p-4 rounded-lg shadow-sm border border-yellow-200">
                <h4 class="font-semibold text-yellow-800 mb-3">กลุ่มผู้ป่วยพิเศษ</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700">ผู้พิการ</label><input type="number" name="disabled_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-yellow-500 focus:ring-yellow-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700">ผู้ป่วยติดเตียง</label><input type="number" name="bedridden_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-yellow-500 focus:ring-yellow-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700">ผู้สูงอายุ</label><input type="number" name="elderly_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-yellow-500 focus:ring-yellow-500"></div>
                    <div><label class="block text-sm font-medium text-gray-700">เด็ก</label><input type="number" name="child_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-yellow-500 focus:ring-yellow-500"></div>
                </div>
            </div>

            <div class="bg-pink-50 p-4 rounded-lg shadow-sm border border-pink-200">
                <h4 class="font-semibold text-pink-800 mb-3">โรคเรื้อรังและโรคที่ต้องเฝ้าระวัง</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                     <div><label class="block text-sm font-medium text-gray-700">ผู้ป่วยโรคเรื้อรัง</label><input type="number" name="chronic_disease_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-pink-500 focus:ring-pink-500"></div>
                     <div><label class="block text-sm font-medium text-gray-700">โรคเบาหวาน</label><input type="number" name="diabetes_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-pink-500 focus:ring-pink-500"></div>
                     <div><label class="block text-sm font-medium text-gray-700">ความดันโลหิตสูง</label><input type="number" name="hypertension_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-pink-500 focus:ring-pink-500"></div>
                     <div><label class="block text-sm font-medium text-gray-700">โรคหัวใจ</label><input type="number" name="heart_disease_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-pink-500 focus:ring-pink-500"></div>
                     <div><label class="block text-sm font-medium text-gray-700">จิตเวช</label><input type="number" name="mental_health_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-pink-500 focus:ring-pink-500"></div>
                     <div><label class="block text-sm font-medium text-gray-700">ไตวาย (ฟอกไต)</label><input type="number" name="kidney_disease_patients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-pink-500 focus:ring-pink-500"></div>
                     <div class="md:col-span-3"><label class="block text-sm font-medium text-gray-700">โรคเฝ้าระวังอื่นๆ</label><input type="number" name="other_monitored_diseases" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-pink-500 focus:ring-pink-500"></div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t flex justify-end gap-4">
                 <a href="index.php?page=shelters" class="px-6 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 font-medium">ยกเลิก</a>
                 <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <i data-lucide="save" class="h-5 w-5"></i>
                    <span>บันทึกข้อมูล</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Auto-calculation and Validation Script ---
    const form = document.getElementById('hospitalReportForm');
    const maleInput = form.querySelector('#malePatients');
    const femaleInput = form.querySelector('#femalePatients');
    const totalInput = form.querySelector('#totalPatients');
    const pregnantInput = form.querySelector('input[name="pregnant_women"]');

    const calculateTotal = () => {
        const male = parseInt(maleInput.value) || 0;
        const female = parseInt(femaleInput.value) || 0;
        totalInput.value = male + female;
    };
    
    maleInput.addEventListener('input', calculateTotal);
    femaleInput.addEventListener('input', calculateTotal);
    calculateTotal(); // Initial calculation

    form.addEventListener('submit', (e) => {
        const total = parseInt(totalInput.value) || 0;
        const female = parseInt(femaleInput.value) || 0;
        const pregnant = parseInt(pregnantInput.value) || 0;

        // Validation 1: Pregnant vs Female
        if (pregnant > female) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: 'จำนวนหญิงตั้งครรภ์ต้องไม่เกินจำนวนผู้ป่วยหญิงทั้งหมด'
            });
            return;
        }

        // Validation 2: Sub-groups vs Total
        const subGroupKeys = [
            'disabled_patients', 'bedridden_patients', 'elderly_patients', 'child_patients',
            'chronic_disease_patients', 'diabetes_patients', 'hypertension_patients',
            'heart_disease_patients', 'mental_health_patients', 'kidney_disease_patients',
            'other_monitored_diseases'
        ];
        
        let subGroupsTotal = 0;
        subGroupKeys.forEach(key => {
            const input = form.querySelector(`input[name="${key}"]`);
            if(input) {
                subGroupsTotal += parseInt(input.value) || 0;
            }
        });

        if (subGroupsTotal > total) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: `ยอดรวมในกลุ่มย่อย (${subGroupsTotal}) ต้องไม่เกินจำนวนผู้ป่วยทั้งหมด (${total})`
            });
        }
    });
});
</script>
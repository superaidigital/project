<?php
// pages/dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Helper function to format date in Thai
function thai_date_full($time) {
    $thai_months = [
        1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน",
        5 => "พฤษภาคม", 6 => "มิถุนายน", 7 => "กรกฎาคม", 8 => "สิงหาคม",
        9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
    ];
    $day = date('d', $time);
    $month = $thai_months[date('n', $time)];
    $year = date('Y', $time) + 543;
    return "วันที่ {$day} เดือน {$month} พ.ศ. {$year}";
}


// --- Data Fetching with Error Handling ---

// 1. Cumulative Totals (ยอดสะสม)
$total_shelters = 0;
$total_shelters_q = $conn->query("SELECT COUNT(*) as count FROM shelters");
if ($total_shelters_q) {
    $total_shelters = $total_shelters_q->fetch_assoc()['count'] ?? 0;
}

$total_occupancy = 0;
$total_capacity = 0;
$occupancy_q = $conn->query("SELECT SUM(current_occupancy) as sum_occ, SUM(capacity) as sum_cap FROM shelters WHERE type NOT IN ('ศูนย์รับบริจาค', 'โรงครัวพระราชทาน')");
if ($occupancy_q) {
    $occupancy_data = $occupancy_q->fetch_assoc();
    $total_occupancy = $occupancy_data['sum_occ'] ?? 0;
    $total_capacity = $occupancy_data['sum_cap'] ?? 0;
}
$remaining_capacity = $total_capacity - $total_occupancy;

$total_donations = 0;
$donations_q = $conn->query("SELECT SUM(current_occupancy) as sum FROM shelters WHERE type = 'ศูนย์รับบริจาค'");
if ($donations_q) {
    $total_donations = $donations_q->fetch_assoc()['sum'] ?? 0;
}


// 2. Daily Changes (ยอดวันนี้)
$daily_occupancy_change = 0;
$daily_occupancy_change_q = $conn->query("
    SELECT COALESCE(SUM(IF(log_type = 'add', change_amount, -change_amount)), 0) as total_change
    FROM shelter_logs
    WHERE DATE(created_at) = CURDATE() AND item_unit = 'คน'
");
if ($daily_occupancy_change_q) {
    $daily_occupancy_change = $daily_occupancy_change_q->fetch_assoc()['total_change'] ?? 0;
}


$daily_donation_change = 0;
$daily_donation_change_q = $conn->query("
    SELECT COALESCE(SUM(IF(log_type = 'add', change_amount, -change_amount)), 0) as total_change
    FROM shelter_logs
    WHERE DATE(created_at) = CURDATE() AND item_unit != 'คน' AND shelter_id IN (SELECT id FROM shelters WHERE type = 'ศูนย์รับบริจาค')
");
if ($daily_donation_change_q) {
    $daily_donation_change = $daily_donation_change_q->fetch_assoc()['total_change'] ?? 0;
}


// 3. Data for Bar Chart (ข้อมูลสำหรับกราฟ)
$amphoe_labels = [];
$amphoe_values = [];
$amphoe_data_q = $conn->query("
    SELECT amphoe, SUM(current_occupancy) as total_occupancy
    FROM shelters 
    WHERE type NOT IN ('ศูนย์รับบริจาค', 'โรงครัวพระราชทาน') AND amphoe IS NOT NULL AND amphoe != ''
    GROUP BY amphoe 
    ORDER BY total_occupancy DESC
    LIMIT 10
");
if ($amphoe_data_q) {
    while($row = $amphoe_data_q->fetch_assoc()){
        $amphoe_labels[] = $row['amphoe'];
        $amphoe_values[] = $row['total_occupancy'];
    }
}


// 4. Recent Logs (การเคลื่อนไหวล่าสุด)
$recent_logs_q = $conn->query("
    SELECT sl.*, s.name as shelter_name 
    FROM shelter_logs sl
    JOIN shelters s ON sl.shelter_id = s.id
    ORDER BY sl.created_at DESC
    LIMIT 5
");

// 5. Data for Summary Report Table (ข้อมูลสำหรับตารางรายงานสรุป)
$summary_report_data = [];
$summary_q = $conn->query("
    WITH LatestReports AS (
        SELECT shelter_id, MAX(report_date) as max_date
        FROM hospital_daily_reports
        GROUP BY shelter_id
    )
    SELECT 
        SUM(r.total_patients) as total_evacuees,
        SUM(r.male_patients + r.female_patients) as general_screening,
        SUM(r.elderly_patients) as elderly,
        SUM(r.chronic_disease_patients) as chronic_disease,
        SUM(r.disabled_patients) as disabled,
        SUM(r.kidney_disease_patients) as kidney_dialysis,
        SUM(r.pregnant_women) as pregnant,
        SUM(r.child_patients) as child_0_5,
        SUM(r.bedridden_patients) as bedridden,
        SUM(r.heart_disease_patients) as heart_disease,
        SUM(r.diabetes_patients) as diabetes,
        SUM(r.hypertension_patients) as hypertension,
        SUM(r.mental_health_patients) as mental_health
    FROM hospital_daily_reports r
    JOIN LatestReports lr ON r.shelter_id = lr.shelter_id AND r.report_date = lr.max_date
");

if ($summary_q) {
    $summary_report_data = $summary_q->fetch_assoc();
}

// 6. Daily changes for Summary Report Table
$daily_changes_summary = [];
// **FIX: Check if table exists before querying to prevent crash**
$table_check_q = $conn->query("SHOW TABLES LIKE 'occupant_update_logs'");
if ($table_check_q && $table_check_q->num_rows > 0) {
    $daily_summary_q = $conn->query("
        SELECT
            COALESCE(SUM(IF(operation_type = 'add', total_change, -total_change)), 0) as total_evacuees_change,
            COALESCE(SUM(IF(operation_type = 'add', male_change + female_change, -(male_change + female_change))), 0) as general_screening_change,
            COALESCE(SUM(IF(operation_type = 'add', elderly_change, -elderly_change)), 0) as elderly_change,
            COALESCE(SUM(IF(operation_type = 'add', chronic_disease_change, -chronic_disease_change)), 0) as chronic_disease_change,
            COALESCE(SUM(IF(operation_type = 'add', disabled_change, -disabled_change)), 0) as disabled_change,
            COALESCE(SUM(IF(operation_type = 'add', kidney_disease_change, -kidney_disease_change)), 0) as kidney_dialysis_change,
            COALESCE(SUM(IF(operation_type = 'add', pregnant_change, -pregnant_change)), 0) as pregnant_change,
            COALESCE(SUM(IF(operation_type = 'add', child_change, -child_change)), 0) as child_0_5_change,
            COALESCE(SUM(IF(operation_type = 'add', bedridden_change, -bedridden_change)), 0) as bedridden_change,
            COALESCE(SUM(IF(operation_type = 'add', heart_disease_change, -heart_disease_change)), 0) as heart_disease_change,
            COALESCE(SUM(IF(operation_type = 'add', diabetes_change, -diabetes_change)), 0) as diabetes_change,
            COALESCE(SUM(IF(operation_type = 'add', hypertension_change, -hypertension_change)), 0) as hypertension_change,
            COALESCE(SUM(IF(operation_type = 'add', mental_health_change, -mental_health_change)), 0) as mental_health_change
        FROM occupant_update_logs
        WHERE DATE(log_timestamp) = CURDATE()
    ");
    if ($daily_summary_q) {
        $daily_changes_summary = $daily_summary_q->fetch_assoc();
    }
}
?>

<!-- Add html2canvas library for image export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
    /* Style for fullscreen card */
    #summary-report-card.fullscreen-card {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 5000;
        overflow-y: auto;
        padding: 2rem; /* Add some padding for better viewing */
    }
    body.fullscreen-active {
        overflow: hidden;
    }
</style>

<div id="dashboard-content-to-capture" class="bg-gray-100 p-1">
    <div class="space-y-8">
        <!-- Header with Title and Export Buttons -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">หน้าหลัก (Dashboard)</h1>
                <p class="text-gray-500">สรุปภาพรวมข้อมูล ณ วันที่ <?= date('d/m/Y') ?></p>
            </div>
            <div class="flex items-center gap-2">
                <button id="exportImageBtn" class="bg-white text-gray-700 font-semibold py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-100 flex items-center gap-2">
                    <i data-lucide="image" class="w-4 h-4"></i>
                    <span>บันทึกเป็นรูปภาพ</span>
                </button>
                <button id="exportCsvBtn" class="bg-white text-gray-700 font-semibold py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-100 flex items-center gap-2">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    <span>ส่งออกเป็น CSV</span>
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
             <!-- Card 1: Total Shelters -->
            <div class="bg-white p-6 rounded-xl shadow-md flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <p class="text-gray-600 font-semibold">ศูนย์ทั้งหมด</p>
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i data-lucide="archive" class="w-5 h-5 text-blue-600"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-gray-400">ยอดสะสม</p>
                    <p class="text-3xl font-bold text-gray-800"><?= number_format($total_shelters) ?> แห่ง</p>
                </div>
            </div>
            
            <!-- Card 2: Total Occupancy -->
            <div class="bg-white p-6 rounded-xl shadow-md flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <p class="text-gray-600 font-semibold">ผู้เข้าพักทั้งหมด</p>
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i data-lucide="users" class="w-5 h-5 text-green-600"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-gray-400">ยอดวันนี้</p>
                    <p class="text-3xl font-bold <?= $daily_occupancy_change > 0 ? 'text-green-600' : ($daily_occupancy_change < 0 ? 'text-red-600' : 'text-gray-800') ?>">
                        <i data-lucide="<?= $daily_occupancy_change > 0 ? 'arrow-up-right' : ($daily_occupancy_change < 0 ? 'arrow-down-right' : 'minus') ?>" class="w-6 h-6 inline-block"></i>
                        <?= number_format(abs($daily_occupancy_change)) ?>
                    </p>
                    <p class="text-sm text-gray-500 mt-1">ยอดสะสม: <?= number_format($total_occupancy) ?> / <?= number_format($total_capacity) ?> คน</p>
                </div>
            </div>

            <!-- Card 3: Total Donations -->
            <div class="bg-white p-6 rounded-xl shadow-md flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <p class="text-gray-600 font-semibold">ยอดบริจาค</p>
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i data-lucide="package" class="w-5 h-5 text-yellow-600"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-gray-400">ยอดวันนี้</p>
                    <p class="text-3xl font-bold <?= $daily_donation_change > 0 ? 'text-green-600' : ($daily_donation_change < 0 ? 'text-red-600' : 'text-gray-800') ?>">
                        <i data-lucide="<?= $daily_donation_change > 0 ? 'arrow-up-right' : ($daily_donation_change < 0 ? 'arrow-down-right' : 'minus') ?>" class="w-6 h-6 inline-block"></i>
                        <?= number_format(abs($daily_donation_change)) ?>
                    </p>
                    <p class="text-sm text-gray-500 mt-1">ยอดสะสม: <?= number_format($total_donations) ?> ชิ้น</p>
                </div>
            </div>

            <!-- Card 4: Remaining Capacity -->
            <div class="bg-white p-6 rounded-xl shadow-md flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <p class="text-gray-600 font-semibold">รองรับได้อีก</p>
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i data-lucide="user-plus" class="w-5 h-5 text-purple-600"></i>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-gray-400">คงเหลือ</p>
                    <p class="text-3xl font-bold text-gray-800"><?= number_format($remaining_capacity) ?> คน</p>
                </div>
            </div>
        </div>

        <!-- Charts and Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Bar Chart -->
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-bold text-gray-800 mb-4">จำนวนผู้เข้าพักตามอำเภอ (10 อันดับแรก)</h3>
                <canvas id="amphoeChart"></canvas>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-bold text-gray-800 mb-4">การเคลื่อนไหวล่าสุด</h3>
                <div class="space-y-4">
                    <?php if ($recent_logs_q && $recent_logs_q->num_rows > 0): ?>
                        <?php while($log = $recent_logs_q->fetch_assoc()): ?>
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-gray-100 rounded-full mt-1">
                                <i data-lucide="<?= $log['log_type'] == 'add' ? 'arrow-up' : 'arrow-down' ?>" class="h-5 w-5 <?= $log['log_type'] == 'add' ? 'text-green-500' : 'text-red-500' ?>"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-700 text-sm"><?= htmlspecialchars($log['shelter_name']) ?></p>
                                <p class="text-xs text-gray-500">
                                   <?= $log['log_type'] == 'add' ? 'เพิ่ม' : 'ลด' ?>: 
                                   <?= htmlspecialchars($log['item_name']) ?> 
                                   (<?= number_format($log['change_amount']) ?> <?= htmlspecialchars($log['item_unit']) ?>)
                                </p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-400 py-4">ยังไม่มีการเคลื่อนไหว</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- START: New Summary Report Section -->
        <div id="summary-report-card" class="bg-white p-6 rounded-xl shadow-md">
            <div class="relative mb-6">
                <div class="text-center">
                    <h2 class="text-xl font-bold text-gray-800">รายงานข้อมูลศูนย์พักพิงผู้ประสบภัย</h2>
                    <p class="text-gray-500">สรุปข้อมูลภาพรวมทั้งหมด</p>
                    <p class="text-gray-500 mt-1 text-sm"><?= thai_date_full(time()) ?></p>
                </div>
                <div class="absolute top-0 right-0 flex items-center gap-2">
                    <button id="fullscreenReportCardBtn" class="bg-gray-100 text-gray-600 p-2 rounded-lg hover:bg-gray-200" title="ขยายเต็มจอ">
                        <i data-lucide="maximize" class="w-5 h-5"></i>
                    </button>
                    <button id="exportReportCardImageBtn" class="bg-gray-100 text-gray-600 p-2 rounded-lg hover:bg-gray-200" title="บันทึกการ์ดนี้เป็นรูปภาพ">
                        <i data-lucide="camera" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            
            <?php
                function render_summary_item($title, $color_class, $current_value, $daily_change) {
                    $change_formatted = ($daily_change >= 0 ? '+' : '') . number_format($daily_change);
                    
                    echo "<div class='rounded-lg shadow overflow-hidden'>";
                    echo "<div class='p-2 {$color_class} text-center'><h4 class='font-semibold text-sm'>{$title}</h4></div>";
                    echo "<div class='p-4 bg-white text-center'>";
                    echo "<p class='text-2xl font-bold text-gray-800'>" . number_format($current_value ?? 0) . "</p>";
                    echo "<p class='text-sm text-red-500'>วันนี้: {$change_formatted}</p>";
                    echo "</div></div>";
                }
            ?>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <!-- Row 1 -->
                <?php render_summary_item('จำนวนผู้อพยพ', 'bg-blue-100 text-blue-800', $summary_report_data['total_evacuees'], $daily_changes_summary['total_evacuees_change'] ?? 0); ?>
                <?php render_summary_item('คัดกรองสุขภาพทั่วไป', 'bg-blue-100 text-blue-800', $summary_report_data['general_screening'], $daily_changes_summary['general_screening_change'] ?? 0); ?>
                <?php render_summary_item('ผู้สูงอายุ', 'bg-blue-100 text-blue-800', $summary_report_data['elderly'], $daily_changes_summary['elderly_change'] ?? 0); ?>
                <?php render_summary_item('มีโรคประจำตัว', 'bg-blue-100 text-blue-800', $summary_report_data['chronic_disease'], $daily_changes_summary['chronic_disease_change'] ?? 0); ?>
                <?php render_summary_item('ผู้พิการ', 'bg-blue-100 text-blue-800', $summary_report_data['disabled'], $daily_changes_summary['disabled_change'] ?? 0); ?>
                <?php render_summary_item('ผู้ป่วยฟอกไต', 'bg-blue-100 text-blue-800', $summary_report_data['kidney_dialysis'], $daily_changes_summary['kidney_dialysis_change'] ?? 0); ?>

                <!-- Row 2 -->
                <?php render_summary_item('หญิงตั้งครรภ์', 'bg-pink-100 text-pink-800', $summary_report_data['pregnant'], $daily_changes_summary['pregnant_change'] ?? 0); ?>
                <?php render_summary_item('เด็ก 0-5 ปี', 'bg-pink-100 text-pink-800', $summary_report_data['child_0_5'], $daily_changes_summary['child_0_5_change'] ?? 0); ?>
                <?php render_summary_item('ผู้มีภาวะซึมเศร้า', 'bg-pink-100 text-pink-800', $summary_report_data['mental_health'], $daily_changes_summary['mental_health_change'] ?? 0); ?>
                <?php render_summary_item('ผู้บาดเจ็บ', 'bg-pink-100 text-pink-800', 0, 0); ?>
                <?php render_summary_item('ผู้ป่วยทางเดินหายใจ', 'bg-pink-100 text-pink-800', 0, 0); ?>
                <?php render_summary_item('โรคทางเดินอาหาร', 'bg-pink-100 text-pink-800', 0, 0); ?>

                 <!-- Row 3 -->
                <?php render_summary_item('ผู้ป่วยเบาหวาน', 'bg-yellow-100 text-yellow-800', $summary_report_data['diabetes'], $daily_changes_summary['diabetes_change'] ?? 0); ?>
                <?php render_summary_item('ผู้ป่วยความดัน', 'bg-yellow-100 text-yellow-800', $summary_report_data['hypertension'], $daily_changes_summary['hypertension_change'] ?? 0); ?>
                <?php render_summary_item('ผู้ป่วยเรื้อรัง', 'bg-yellow-100 text-yellow-800', $summary_report_data['chronic_disease'], $daily_changes_summary['chronic_disease_change'] ?? 0); ?>
                <?php render_summary_item('ผู้ป่วยโรคหัวใจ', 'bg-yellow-100 text-yellow-800', $summary_report_data['heart_disease'], $daily_changes_summary['heart_disease_change'] ?? 0); ?>
                <?php render_summary_item('ผู้ป่วยโรคติดต่อ', 'bg-yellow-100 text-yellow-800', 0, 0); ?>
                <?php render_summary_item('มะเร็งระยะสุดท้าย', 'bg-yellow-100 text-yellow-800', 0, 0); ?>

                <!-- Row 4 -->
                <?php render_summary_item('ผู้ป่วยติดบ้านติดเตียง', 'bg-green-100 text-green-800', $summary_report_data['bedridden'], $daily_changes_summary['bedridden_change'] ?? 0); ?>
                <?php render_summary_item('ตกค้างในชุมชน', 'bg-green-100 text-green-800', 0, 0); ?>
                <?php render_summary_item('ผู้ป่วยยาเสพติด', 'bg-green-100 text-green-800', 0, 0); ?>
                <?php render_summary_item('ผู้เสียชีวิต', 'bg-green-100 text-green-800', 0, 0); ?>
                <?php render_summary_item('กลับบ้าน', 'bg-green-100 text-green-800', 0, 0); ?>
                <?php render_summary_item('ย้ายศูนย์อพยพ', 'bg-green-100 text-green-800', 0, 0); ?>
            </div>
        </div>
        <!-- END: New Summary Report Section -->

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bar Chart
    const ctx = document.getElementById('amphoeChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($amphoe_labels) ?>,
                datasets: [{
                    label: 'จำนวนผู้เข้าพัก',
                    data: <?= json_encode($amphoe_values) ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.8)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderRadius: 5,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: { family: 'Sarabun' }
                        }
                    },
                    x: {
                        ticks: {
                            font: { family: 'Sarabun' }
                        }
                    }
                }
            }
        });
    }

    // --- Image and CSV Export Logic ---

    // Generic function to show loading and capture an element
    function captureElement(element, filename) {
        Swal.fire({
            title: 'กำลังสร้างรูปภาพ...',
            text: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        html2canvas(element, { 
            backgroundColor: element.id === 'dashboard-content-to-capture' ? '#f3f4f6' : '#ffffff'
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL();
            link.click();
            Swal.close();
        }).catch(err => {
            console.error("html2canvas error:", err);
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถสร้างรูปภาพได้', 'error');
        });
    }

    // Export to Image Button (Whole Dashboard)
    const exportImageBtn = document.getElementById('exportImageBtn');
    if(exportImageBtn) {
        exportImageBtn.addEventListener('click', () => {
            const content = document.getElementById('dashboard-content-to-capture');
            captureElement(content, `dashboard-summary-all-${new Date().toISOString().slice(0,10)}.png`);
        });
    }

    // Export Specific Report Card to Image
    const exportReportCardImageBtn = document.getElementById('exportReportCardImageBtn');
    if(exportReportCardImageBtn) {
        exportReportCardImageBtn.addEventListener('click', () => {
            const card = document.getElementById('summary-report-card');
            const buttonContainer = card.querySelector('.absolute');
            const wasFullscreen = card.classList.contains('fullscreen-card');

            // Temporarily hide buttons and exit fullscreen for clean capture
            buttonContainer.style.display = 'none';
            if (wasFullscreen) {
                card.classList.remove('fullscreen-card');
                document.body.classList.remove('fullscreen-active');
            }
            
            // Use a timeout to allow the DOM to update before capturing
            setTimeout(() => {
                captureElement(card, `summary-report-${new Date().toISOString().slice(0,10)}.png`);
                
                // Restore button visibility and fullscreen state
                buttonContainer.style.display = 'flex';
                if (wasFullscreen) {
                    card.classList.add('fullscreen-card');
                    document.body.classList.add('fullscreen-active');
                }
            }, 100); // 100ms delay
        });
    }

    // Fullscreen Report Card Button
    const fullscreenBtn = document.getElementById('fullscreenReportCardBtn');
    if(fullscreenBtn) {
        fullscreenBtn.addEventListener('click', () => {
            const card = document.getElementById('summary-report-card');
            const icon = fullscreenBtn.querySelector('i');
            
            card.classList.toggle('fullscreen-card');
            document.body.classList.toggle('fullscreen-active');

            if (card.classList.contains('fullscreen-card')) {
                icon.setAttribute('data-lucide', 'minimize');
                fullscreenBtn.setAttribute('title', 'ย่อขนาด');
            } else {
                icon.setAttribute('data-lucide', 'maximize');
                 fullscreenBtn.setAttribute('title', 'ขยายเต็มจอ');
            }
            lucide.createIcons();
        });
    }


    // Export to CSV Button
    const exportCsvBtn = document.getElementById('exportCsvBtn');
    if(exportCsvBtn){
         exportCsvBtn.addEventListener('click', () => {
            const summaryData = [
                ['หัวข้อ', 'ยอดสะสม', 'เปลี่ยนแปลงวันนี้'],
                ['ศูนย์ทั้งหมด', '<?= $total_shelters ?> แห่ง', 'N/A'],
                ['ผู้เข้าพักทั้งหมด', '<?= $total_occupancy ?> คน', '<?= $daily_occupancy_change ?>'],
                ['ความจุทั้งหมด', '<?= $total_capacity ?> คน', 'N/A'],
                ['รองรับได้อีก', '<?= $remaining_capacity ?> คน', 'N/A'],
                ['ยอดบริจาค', '<?= $total_donations ?> ชิ้น', '<?= $daily_donation_change ?>']
            ];

            let csvContent = "data:text/csv;charset=utf-8,\uFEFF"; // Add BOM for Excel Thai support
            summaryData.forEach(rowArray => {
                let row = rowArray.map(item => `"${String(item).replace(/"/g, '""')}"`).join(",");
                csvContent += row + "\r\n";
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `dashboard_summary_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }
});
</script>

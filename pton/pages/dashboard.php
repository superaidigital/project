<?php
// pages/dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// This line might be redundant if index.php already requires it, but it's safe.
require_once 'db_connect.php';

// --- Data Fetching ---
// Summary Cards
$total_shelters = $conn->query("SELECT COUNT(*) as count FROM shelters")->fetch_assoc()['count'];
$total_occupancy = $conn->query("SELECT SUM(current_occupancy) as sum FROM shelters WHERE type != 'ศูนย์รับบริจาค'")->fetch_assoc()['sum'];
$total_capacity = $conn->query("SELECT SUM(capacity) as sum FROM shelters WHERE type != 'ศูนย์รับบริจาค'")->fetch_assoc()['sum'];
$total_donations = $conn->query("SELECT SUM(current_occupancy) as sum FROM shelters WHERE type = 'ศูนย์รับบริจาค'")->fetch_assoc()['sum'];

// Today's statistics from hospital_update_logs
$today = date('Y-m-d');

// คำนวณสถิติจาก logs ของวันนี้
$today_stats_query = "
    SELECT 
        SUM(CASE WHEN operation_type = 'add' THEN change_total_patients ELSE -change_total_patients END) as total_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_male_patients ELSE -change_male_patients END) as male_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_female_patients ELSE -change_female_patients END) as female_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_pregnant_women ELSE -change_pregnant_women END) as pregnant_women,
        SUM(CASE WHEN operation_type = 'add' THEN change_disabled_patients ELSE -change_disabled_patients END) as disabled_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_bedridden_patients ELSE -change_bedridden_patients END) as bedridden_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_elderly_patients ELSE -change_elderly_patients END) as elderly_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_child_patients ELSE -change_child_patients END) as child_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_chronic_disease_patients ELSE -change_chronic_disease_patients END) as chronic_disease_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_diabetes_patients ELSE -change_diabetes_patients END) as diabetes_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_hypertension_patients ELSE -change_hypertension_patients END) as hypertension_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_heart_disease_patients ELSE -change_heart_disease_patients END) as heart_disease_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_mental_health_patients ELSE -change_mental_health_patients END) as mental_health_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_kidney_disease_patients ELSE -change_kidney_disease_patients END) as kidney_disease_patients,
        SUM(CASE WHEN operation_type = 'add' THEN change_other_monitored_diseases ELSE -change_other_monitored_diseases END) as other_monitored_diseases
    FROM hospital_update_logs 
    WHERE DATE(created_at) = '$today'
";

$today_stats_result = $conn->query($today_stats_query);
$today_stats = $today_stats_result ? $today_stats_result->fetch_assoc() : [];

// Default values if no data และตรวจสอบค่าติดลบ
$today_stats = array_map(function($value) { 
    return max(0, $value ?? 0); // ไม่ให้แสดงค่าติดลบ
}, $today_stats ?: []);

// สถิติการเพิ่ม/ลดแยกประเภทของวันนี้
$today_operations_query = "
    SELECT 
        operation_type,
        SUM(change_total_patients) as total_patients,
        SUM(change_male_patients) as male_patients,
        SUM(change_female_patients) as female_patients,
        SUM(change_pregnant_women) as pregnant_women,
        SUM(change_disabled_patients) as disabled_patients,
        SUM(change_bedridden_patients) as bedridden_patients,
        SUM(change_elderly_patients) as elderly_patients,
        SUM(change_child_patients) as child_patients,
        SUM(change_chronic_disease_patients) as chronic_disease_patients,
        SUM(change_diabetes_patients) as diabetes_patients,
        SUM(change_hypertension_patients) as hypertension_patients,
        SUM(change_heart_disease_patients) as heart_disease_patients,
        SUM(change_mental_health_patients) as mental_health_patients,
        SUM(change_kidney_disease_patients) as kidney_disease_patients,
        SUM(change_other_monitored_diseases) as other_monitored_diseases,
        COUNT(*) as operation_count
    FROM hospital_update_logs 
    WHERE DATE(created_at) = '$today'
    GROUP BY operation_type
";

$operations_result = $conn->query($today_operations_query);
$today_operations = ['add' => [], 'subtract' => []];

while ($row = $operations_result->fetch_assoc()) {
    $today_operations[$row['operation_type']] = $row;
}

// ข้อมูลสถานการณ์ปัจจุบันรวม (ยอดสะสม)
$current_situation_query = "
    SELECT 
        SUM(total_patients) as total_patients,
        SUM(male_patients) as male_patients,
        SUM(female_patients) as female_patients,
        SUM(pregnant_women) as pregnant_women,
        SUM(disabled_patients) as disabled_patients,
        SUM(bedridden_patients) as bedridden_patients,
        SUM(elderly_patients) as elderly_patients,
        SUM(child_patients) as child_patients,
        SUM(chronic_disease_patients) as chronic_disease_patients,
        SUM(diabetes_patients) as diabetes_patients,
        SUM(hypertension_patients) as hypertension_patients,
        SUM(heart_disease_patients) as heart_disease_patients,
        SUM(mental_health_patients) as mental_health_patients,
        SUM(kidney_disease_patients) as kidney_disease_patients,
        SUM(other_monitored_diseases) as other_monitored_diseases
    FROM hospital_daily_reports hdr
    JOIN shelters s ON hdr.shelter_id = s.id
    WHERE hdr.report_date = (
        SELECT MAX(report_date) 
        FROM hospital_daily_reports hdr2 
        WHERE hdr2.shelter_id = hdr.shelter_id
    )
";

$current_situation_result = $conn->query($current_situation_query);
$current_situation = $current_situation_result ? $current_situation_result->fetch_assoc() : [];

// Default values if no data
$current_situation = array_map(function($value) { 
    return max(0, $value ?? 0);
}, $current_situation ?: []);

// Occupancy by Amphoe for Chart
$amphoe_data = $conn->query("
    SELECT amphoe, SUM(current_occupancy) as total_occupancy
    FROM shelters 
    WHERE type != 'ศูนย์รับบริจาค' AND amphoe IS NOT NULL
    GROUP BY amphoe 
    ORDER BY total_occupancy DESC
    LIMIT 10
");
$amphoe_labels = [];
$amphoe_values = [];
while($row = $amphoe_data->fetch_assoc()){
    $amphoe_labels[] = $row['amphoe'];
    $amphoe_values[] = $row['total_occupancy'];
}

// Recent Logs from hospital_update_logs (detailed)
$recent_detailed_logs = $conn->query("
    SELECT hul.*, s.name as shelter_name, s.type as shelter_type
    FROM hospital_update_logs hul
    JOIN shelters s ON hul.shelter_id = s.id
    ORDER BY hul.created_at DESC
    LIMIT 5
");

// Recent Logs from shelter_logs (general)
$recent_logs = $conn->query("
    SELECT sl.*, s.name as shelter_name 
    FROM shelter_logs sl
    JOIN shelters s ON sl.shelter_id = s.id
    ORDER BY sl.created_at DESC
    LIMIT 5
");
?>

<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">ภาพรวมระบบ</h1>
        <p class="text-gray-500">สรุปข้อมูลและสถานการณ์ล่าสุด</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-blue-100 rounded-full"><i data-lucide="archive" class="h-8 w-8 text-blue-600"></i></div>
            <div>
                <p class="text-gray-500 text-sm">ศูนย์ทั้งหมด</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format($total_shelters) ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-green-100 rounded-full"><i data-lucide="users" class="h-8 w-8 text-green-600"></i></div>
            <div>
                <p class="text-gray-500 text-sm">ผู้เข้าพักทั้งหมด</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format($total_occupancy ?? 0) ?> / <?= number_format($total_capacity ?? 0) ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-yellow-100 rounded-full"><i data-lucide="package" class="h-8 w-8 text-yellow-600"></i></div>
            <div>
                <p class="text-gray-500 text-sm">ยอดบริจาค (ชิ้น)</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format($total_donations ?? 0) ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-purple-100 rounded-full"><i data-lucide="user-plus" class="h-8 w-8 text-purple-600"></i></div>
            <div>
                <p class="text-gray-500 text-sm">รองรับได้อีก</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format(($total_capacity ?? 0) - ($total_occupancy ?? 0)) ?></p>
            </div>
        </div>
    </div>

    <!-- Today's Statistics Table -->
    <div class="relative bg-gradient-to-br from-white via-blue-50 to-purple-100 p-10 rounded-3xl shadow-2xl border-2 border-blue-300/50 hover:shadow-3xl transition-all duration-500 hover:scale-[1.01] overflow-hidden">
        <!-- Animated Background Elements -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 via-indigo-500/5 to-purple-500/10"></div>
        <div class="absolute -top-10 -right-10 w-40 h-40 bg-gradient-to-br from-blue-300/30 to-indigo-400/30 rounded-full blur-xl animate-pulse"></div>
        <div class="absolute -bottom-16 -left-16 w-32 h-32 bg-gradient-to-br from-purple-300/30 to-pink-400/30 rounded-full blur-xl animate-pulse delay-1000"></div>
        
        <div class="relative z-10">
            <div class="flex justify-between items-center mb-10">
                <div class="flex items-center gap-5">
                    <div class="relative">
                        <!-- Glowing effect -->
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-400 to-purple-600 rounded-2xl blur-md opacity-30 animate-pulse"></div>
                        <div class="relative p-4 bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 rounded-2xl shadow-xl transform hover:rotate-6 transition-all duration-500">
                            <i data-lucide="calendar-days" class="h-8 w-8 text-white drop-shadow-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-4xl font-black bg-gradient-to-r from-blue-800 via-indigo-700 to-purple-800 bg-clip-text text-transparent leading-tight drop-shadow-sm">
                            รายงานข้อมูลศูนย์พักพิงประจำวัน
                        </h3>
                        <p class="text-lg text-blue-700 font-bold mt-2 tracking-wide">ข้อมูลวันที่ <?= date('d/m/Y', strtotime($today)) ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-white/90 backdrop-blur-lg px-6 py-4 rounded-2xl border-2 border-blue-400/40 shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105">
                        <p class="text-base text-blue-900 font-black tracking-widest">องค์การบริหารส่วนจังหวัดศรีสะเกษ</p>
                    </div>
                </div>
            </div>
        
            <!-- Statistics Grid -->
            <div class="overflow-x-auto">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 min-w-full">
                    <!-- จำนวนรวม -->
                    <div class="group relative bg-gradient-to-br from-amber-200 via-yellow-300 to-orange-300 p-6 rounded-2xl text-center border-3 border-amber-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-amber-300/50 to-orange-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-amber-900 drop-shadow-md"><?= number_format($today_stats['total_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-amber-800 mt-2 tracking-wide">จำนวนรวม</div>
                        </div>
                    </div>
                    
                    <!-- ชาย -->
                    <div class="group relative bg-gradient-to-br from-emerald-200 via-green-300 to-teal-300 p-6 rounded-2xl text-center border-3 border-emerald-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-300/50 to-green-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-emerald-900 drop-shadow-md"><?= number_format($today_stats['male_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-emerald-800 mt-2 tracking-wide">ชาย</div>
                        </div>
                    </div>
                    
                    <!-- หญิง -->
                    <div class="group relative bg-gradient-to-br from-pink-200 via-rose-300 to-pink-300 p-6 rounded-2xl text-center border-3 border-pink-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-pink-300/50 to-rose-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-pink-900 drop-shadow-md"><?= number_format($today_stats['female_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-pink-800 mt-2 tracking-wide">หญิง</div>
                        </div>
                    </div>
                    
                    <!-- หญิงตั้งครรภ์ -->
                    <div class="group relative bg-gradient-to-br from-purple-200 via-violet-300 to-purple-300 p-6 rounded-2xl text-center border-3 border-purple-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-purple-300/50 to-violet-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-purple-900 drop-shadow-md"><?= number_format($today_stats['pregnant_women'] ?? 0) ?></div>
                            <div class="text-base font-bold text-purple-800 mt-2 tracking-wide">หญิงตั้งครรภ์</div>
                        </div>
                    </div>
                    
                    <!-- ผู้พิการ -->
                    <div class="group relative bg-gradient-to-br from-cyan-200 via-sky-300 to-cyan-300 p-6 rounded-2xl text-center border-3 border-cyan-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-cyan-300/50 to-sky-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-cyan-900 drop-shadow-md"><?= number_format($today_stats['disabled_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-cyan-800 mt-2 tracking-wide">ผู้พิการ</div>
                        </div>
                    </div>
                    
                    <!-- ผู้ป่วยติดเตียง -->
                    <div class="group relative bg-gradient-to-br from-red-200 via-rose-300 to-red-300 p-6 rounded-2xl text-center border-3 border-red-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-red-300/50 to-rose-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-red-900 drop-shadow-md"><?= number_format($today_stats['bedridden_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-red-800 mt-2 tracking-wide">ผู้ป่วยติดเตียง</div>
                        </div>
                    </div>
                </div>
                
                <!-- Second Row -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 mt-6">
                    <!-- ผู้สูงอายุ -->
                    <div class="group relative bg-gradient-to-br from-orange-200 via-amber-300 to-orange-300 p-6 rounded-2xl text-center border-3 border-orange-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-orange-300/50 to-amber-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-orange-900 drop-shadow-md"><?= number_format($today_stats['elderly_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-orange-800 mt-2 tracking-wide">ผู้สูงอายุ</div>
                        </div>
                    </div>
                    
                    <!-- เด็ก -->
                    <div class="group relative bg-gradient-to-br from-yellow-200 via-amber-300 to-yellow-300 p-6 rounded-2xl text-center border-3 border-yellow-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-yellow-300/50 to-amber-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-yellow-900 drop-shadow-md"><?= number_format($today_stats['child_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-yellow-800 mt-2 tracking-wide">เด็ก</div>
                        </div>
                    </div>
                    
                    <!-- ผู้ป่วยโรคเรื้อรัง -->
                    <div class="group relative bg-gradient-to-br from-red-200 via-pink-300 to-red-300 p-6 rounded-2xl text-center border-3 border-red-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-red-300/50 to-pink-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-red-900 drop-shadow-md"><?= number_format($today_stats['chronic_disease_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-red-800 mt-2 tracking-wide">ผู้ป่วยโรคเรื้อรัง</div>
                        </div>
                    </div>
                    
                    <!-- โรคเบาหวาน -->
                    <div class="group relative bg-gradient-to-br from-blue-200 via-indigo-300 to-blue-300 p-6 rounded-2xl text-center border-3 border-blue-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-300/50 to-indigo-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-blue-900 drop-shadow-md"><?= number_format($today_stats['diabetes_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-blue-800 mt-2 tracking-wide">โรคเบาหวาน</div>
                        </div>
                    </div>
                    
                    <!-- โรคความดันโลหิตสูง -->
                    <div class="group relative bg-gradient-to-br from-green-200 via-emerald-300 to-green-300 p-6 rounded-2xl text-center border-3 border-green-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-green-300/50 to-emerald-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-green-900 drop-shadow-md"><?= number_format($today_stats['hypertension_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-green-800 mt-2 tracking-wide">โรคความดันโลหิตสูง</div>
                        </div>
                    </div>
                    
                    <!-- โรคหัวใจ -->
                    <div class="group relative bg-gradient-to-br from-pink-200 via-rose-300 to-pink-300 p-6 rounded-2xl text-center border-3 border-pink-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-pink-300/50 to-rose-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-pink-900 drop-shadow-md"><?= number_format($today_stats['heart_disease_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-pink-800 mt-2 tracking-wide">โรคหัวใจ</div>
                        </div>
                    </div>
                </div>
                
                <!-- Third Row -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 mt-6">
                    <!-- จิตเวช -->
                    <div class="group relative bg-gradient-to-br from-purple-200 via-indigo-300 to-purple-300 p-6 rounded-2xl text-center border-3 border-purple-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-purple-300/50 to-indigo-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-purple-900 drop-shadow-md"><?= number_format($today_stats['mental_health_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-purple-800 mt-2 tracking-wide">จิตเวช</div>
                        </div>
                    </div>
                    
                    <!-- ไตวายระยะฟอกไต -->
                    <div class="group relative bg-gradient-to-br from-yellow-200 via-orange-300 to-yellow-300 p-6 rounded-2xl text-center border-3 border-yellow-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-yellow-300/50 to-orange-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-yellow-900 drop-shadow-md"><?= number_format($today_stats['kidney_disease_patients'] ?? 0) ?></div>
                            <div class="text-base font-bold text-yellow-800 mt-2 tracking-wide">ไตวายระยะฟอกไต</div>
                        </div>
                    </div>
                    
                    <!-- โรคที่ต้องเฝ้าระวังอื่นๆ -->
                    <div class="group relative bg-gradient-to-br from-gray-200 via-slate-300 to-gray-300 p-6 rounded-2xl text-center border-3 border-gray-400/60 shadow-xl hover:shadow-2xl transition-all duration-500 hover:scale-110 hover:rotate-2 transform cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-gray-300/50 to-slate-400/50 rounded-2xl blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="text-3xl font-black text-gray-900 drop-shadow-md"><?= number_format($today_stats['other_monitored_diseases'] ?? 0) ?></div>
                            <div class="text-base font-bold text-gray-800 mt-2 tracking-wide">โรคที่ต้องเฝ้าระวังอื่นๆ</div>
                        </div>
                    </div>
                    
                    <!-- ช่องว่าง -->
                    <div class="bg-gradient-to-br from-gray-100 to-gray-200 p-6 rounded-2xl text-center border-3 border-gray-300/40 opacity-30">
                        <div class="text-3xl font-bold text-gray-400">-</div>
                        <div class="text-base font-medium text-gray-400 mt-2">-</div>
                    </div>
                    
                    <!-- ช่องว่าง -->
                    <div class="bg-gradient-to-br from-gray-100 to-gray-200 p-6 rounded-2xl text-center border-3 border-gray-300/40 opacity-30">
                        <div class="text-3xl font-bold text-gray-400">-</div>
                        <div class="text-base font-medium text-gray-400 mt-2">-</div>
                    </div>
                    
                    <!-- ช่องว่าง -->
                    <div class="bg-gradient-to-br from-gray-100 to-gray-200 p-6 rounded-2xl text-center border-3 border-gray-300/40 opacity-30">
                        <div class="text-3xl font-bold text-gray-400">-</div>
                        <div class="text-base font-medium text-gray-400 mt-2">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Operations Summary -->
    <div class="relative bg-gradient-to-br from-white via-emerald-50 to-teal-100 p-6 rounded-2xl shadow-xl border-2 border-emerald-300/50 hover:shadow-2xl transition-all duration-300 hover:scale-[1.01] overflow-hidden">
        <!-- Animated Background Elements -->
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/8 via-teal-500/4 to-cyan-500/8"></div>
        <div class="absolute -top-4 -right-4 w-24 h-24 bg-gradient-to-br from-emerald-300/15 to-teal-400/15 rounded-full blur-xl animate-pulse delay-300"></div>
        <div class="absolute -bottom-6 -left-6 w-18 h-18 bg-gradient-to-br from-cyan-300/20 to-blue-400/20 rounded-full blur-lg animate-pulse delay-700"></div>
        
        <div class="relative z-10">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <!-- Simplified glow effect -->
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-400 to-teal-600 rounded-2xl blur-md opacity-30 animate-pulse"></div>
                        <div class="relative p-3 bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 rounded-2xl shadow-lg transform hover:rotate-6 hover:scale-105 transition-all duration-500">
                            <i data-lucide="activity" class="h-6 w-6 text-white drop-shadow-lg"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold bg-gradient-to-r from-emerald-800 via-teal-700 to-cyan-800 bg-clip-text text-transparent leading-tight">
                            สรุปการดำเนินการวันนี้
                        </h3>
                        <p class="text-sm text-emerald-700 font-medium mt-1">รายงานการเคลื่อนไหวประจำวัน</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="bg-white/90 backdrop-blur-lg px-4 py-2 rounded-xl border border-emerald-400/40 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                        <p class="text-sm text-emerald-900 font-bold">วันที่ <?= date('d/m/Y', strtotime($today)) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- การเพิ่มข้อมูล -->
                <div class="group relative bg-gradient-to-br from-emerald-100 via-green-200 to-teal-200 p-5 rounded-2xl border-2 border-emerald-400/50 shadow-lg hover:shadow-xl transition-all duration-500 hover:scale-105 hover:rotate-1 transform cursor-pointer overflow-hidden">
                    <!-- Simplified animated background -->
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-300/20 to-green-400/20 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-300/15 rounded-full -translate-y-8 translate-x-8 group-hover:scale-125 transition-transform duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="relative">
                                <div class="absolute inset-0 bg-gradient-to-br from-emerald-400 to-green-600 rounded-xl blur-sm opacity-40 animate-pulse"></div>
                                <div class="relative p-2.5 bg-gradient-to-br from-emerald-500 to-green-600 rounded-xl shadow-md transform group-hover:rotate-6 transition-transform duration-300">
                                    <i data-lucide="plus" class="h-5 w-5 text-white drop-shadow-md"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-emerald-900">การเพิ่มข้อมูล</h4>
                                <div class="bg-emerald-200/80 backdrop-blur-sm px-3 py-1 rounded-full border border-emerald-400/40 shadow-sm mt-1">
                                    <p class="text-sm text-emerald-800 font-bold">
                                        <?= ($today_operations['add']['operation_count'] ?? 0) ?> ครั้ง
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2.5">
                            <?php
                            $add_fields = [
                                'total_patients' => 'จำนวนรวม',
                                'male_patients' => 'ชาย',
                                'female_patients' => 'หญิง',
                                'pregnant_women' => 'หญิงตั้งครรภ์',
                                'disabled_patients' => 'ผู้พิการ',
                                'bedridden_patients' => 'ผู้ป่วยติดเตียง',
                                'elderly_patients' => 'ผู้สูงอายุ',
                                'child_patients' => 'เด็ก'
                            ];
                            
                            foreach ($add_fields as $field => $label) {
                                $value = $today_operations['add'][$field] ?? 0;
                                if ($value > 0) {
                                    echo "<div class='flex justify-between items-center bg-white/70 backdrop-blur-sm px-3 py-2 rounded-lg border border-emerald-300/40 shadow-sm hover:shadow-md transition-all duration-200 hover:scale-102'>";
                                    echo "<span class='text-gray-800 font-medium text-sm'>{$label}:</span>";
                                    echo "<span class='font-bold text-emerald-800 text-base'>+{$value}</span>";
                                    echo "</div>";
                                }
                            }
                            ?>
                        </div>
                        
                        <?php if (($today_operations['add']['chronic_disease_patients'] ?? 0) > 0 || 
                                 ($today_operations['add']['diabetes_patients'] ?? 0) > 0 || 
                                 ($today_operations['add']['hypertension_patients'] ?? 0) > 0): ?>
                        <div class="mt-4 pt-3 border-t-2 border-emerald-300/40">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="p-1.5 bg-emerald-300/60 rounded-full">
                                    <i data-lucide="heart-pulse" class="h-3.5 w-3.5 text-emerald-800"></i>
                                </div>
                                <p class="text-sm font-bold text-emerald-800">โรคเรื้อรัง:</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <?php
                                $disease_fields = [
                                    'chronic_disease_patients' => 'โรคเรื้อรัง',
                                    'diabetes_patients' => 'เบาหวาน',
                                    'hypertension_patients' => 'ความดันสูง',
                                    'heart_disease_patients' => 'โรคหัวใจ',
                                    'mental_health_patients' => 'จิตเวช',
                                    'kidney_disease_patients' => 'ไตวาย'
                                ];
                                
                                foreach ($disease_fields as $field => $label) {
                                    $value = $today_operations['add'][$field] ?? 0;
                                    if ($value > 0) {
                                        echo "<div class='flex justify-between items-center bg-white/60 backdrop-blur-sm px-2.5 py-1.5 rounded-md border border-emerald-200/50 shadow-sm hover:shadow-md transition-all duration-200'>";
                                        echo "<span class='text-gray-700 font-medium text-xs'>{$label}:</span>";
                                        echo "<span class='font-bold text-emerald-700 text-sm'>+{$value}</span>";
                                        echo "</div>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- การลดข้อมูล -->
                <div class="group relative bg-gradient-to-br from-red-100 via-rose-200 to-pink-200 p-5 rounded-2xl border-2 border-red-400/50 shadow-lg hover:shadow-xl transition-all duration-500 hover:scale-105 hover:rotate-1 transform cursor-pointer overflow-hidden">
                    <!-- Simplified animated background -->
                    <div class="absolute inset-0 bg-gradient-to-br from-red-300/20 to-rose-400/20 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-red-300/15 rounded-full -translate-y-8 translate-x-8 group-hover:scale-125 transition-transform duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="relative">
                                <div class="absolute inset-0 bg-gradient-to-br from-red-400 to-rose-600 rounded-xl blur-sm opacity-40 animate-pulse"></div>
                                <div class="relative p-2.5 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl shadow-md transform group-hover:rotate-6 transition-transform duration-300">
                                    <i data-lucide="minus" class="h-5 w-5 text-white drop-shadow-md"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-red-900">การลดข้อมูล</h4>
                                <div class="bg-red-200/80 backdrop-blur-sm px-3 py-1 rounded-full border border-red-400/40 shadow-sm mt-1">
                                    <p class="text-sm text-red-800 font-bold">
                                        <?= ($today_operations['subtract']['operation_count'] ?? 0) ?> ครั้ง
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2.5">
                            <?php
                            foreach ($add_fields as $field => $label) {
                                $value = $today_operations['subtract'][$field] ?? 0;
                                if ($value > 0) {
                                    echo "<div class='flex justify-between items-center bg-white/70 backdrop-blur-sm px-3 py-2 rounded-lg border border-red-300/40 shadow-sm hover:shadow-md transition-all duration-200 hover:scale-102'>";
                                    echo "<span class='text-gray-800 font-medium text-sm'>{$label}:</span>";
                                    echo "<span class='font-bold text-red-800 text-base'>-{$value}</span>";
                                    echo "</div>";
                                }
                            }
                            ?>
                        </div>
                        
                        <?php if (($today_operations['subtract']['chronic_disease_patients'] ?? 0) > 0 || 
                                 ($today_operations['subtract']['diabetes_patients'] ?? 0) > 0 || 
                                 ($today_operations['subtract']['hypertension_patients'] ?? 0) > 0): ?>
                        <div class="mt-4 pt-3 border-t-2 border-red-300/40">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="p-1.5 bg-red-300/60 rounded-full">
                                    <i data-lucide="heart-pulse" class="h-3.5 w-3.5 text-red-800"></i>
                                </div>
                                <p class="text-sm font-bold text-red-800">โรคเรื้อรัง:</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <?php
                                foreach ($disease_fields as $field => $label) {
                                    $value = $today_operations['subtract'][$field] ?? 0;
                                    if ($value > 0) {
                                        echo "<div class='flex justify-between items-center bg-white/60 backdrop-blur-sm px-2.5 py-1.5 rounded-md border border-red-200/50 shadow-sm hover:shadow-md transition-all duration-200'>";
                                        echo "<span class='text-gray-700 font-medium text-xs'>{$label}:</span>";
                                        echo "<span class='font-bold text-red-700 text-sm'>-{$value}</span>";
                                        echo "</div>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Situation Report -->
    <div class="bg-gradient-to-br from-white via-indigo-50 to-purple-50 p-8 rounded-xl shadow-lg border border-indigo-200 hover:shadow-xl transition-all duration-300">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full shadow-md">
                    <i data-lucide="bar-chart-3" class="h-6 w-6 text-white"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold bg-gradient-to-r from-indigo-700 to-purple-700 bg-clip-text text-transparent">รายงานข้อมูลสถานการณ์ปัจจุบัน</h3>
                    <p class="text-sm text-indigo-600 font-medium">ยอดสะสมทั้งหมด ณ ปัจจุบัน</p>
                </div>
            </div>
            <div class="text-right">
                <div class="bg-white/70 backdrop-blur-sm px-4 py-2 rounded-lg border border-indigo-300">
                    <p class="text-sm text-indigo-700 font-medium">ข้อมูล ณ วันที่ <?= date('d/m/Y') ?></p>
                </div>
            </div>
        </div>
        
        <!-- Current Statistics Grid -->
        <div class="overflow-x-auto">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 min-w-full">
                <!-- จำนวนรวม -->
                <div class="group relative bg-gradient-to-br from-amber-200 via-yellow-300 to-orange-300 p-7 rounded-3xl text-center border-3 border-amber-400/70 shadow-2xl hover:shadow-3xl transition-all duration-700 hover:scale-125 hover:rotate-6 transform cursor-pointer overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-300/60 to-orange-400/60 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-amber-200/40 rounded-full -translate-y-8 translate-x-8 group-hover:scale-200 transition-transform duration-700"></div>
                    <div class="relative z-10">
                        <div class="text-4xl font-black text-amber-900 drop-shadow-xl mb-2"><?= number_format($current_situation['total_patients'] ?? 0) ?></div>
                        <div class="text-lg font-black text-amber-800 tracking-wide">จำนวนรวม</div>
                    </div>
                </div>
                
                <!-- ชาย -->
                <div class="group relative bg-gradient-to-br from-emerald-200 via-green-300 to-teal-300 p-7 rounded-3xl text-center border-3 border-emerald-400/70 shadow-2xl hover:shadow-3xl transition-all duration-700 hover:scale-125 hover:rotate-6 transform cursor-pointer overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-300/60 to-green-400/60 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-200/40 rounded-full -translate-y-8 translate-x-8 group-hover:scale-200 transition-transform duration-700"></div>
                    <div class="relative z-10">
                        <div class="text-4xl font-black text-emerald-900 drop-shadow-xl mb-2"><?= number_format($current_situation['male_patients'] ?? 0) ?></div>
                        <div class="text-lg font-black text-emerald-800 tracking-wide">ชาย</div>
                    </div>
                </div>
                
                <!-- หญิง -->
                <div class="group relative bg-gradient-to-br from-pink-200 via-rose-300 to-pink-300 p-7 rounded-3xl text-center border-3 border-pink-400/70 shadow-2xl hover:shadow-3xl transition-all duration-700 hover:scale-125 hover:rotate-6 transform cursor-pointer overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-pink-300/60 to-rose-400/60 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-pink-200/40 rounded-full -translate-y-8 translate-x-8 group-hover:scale-200 transition-transform duration-700"></div>
                    <div class="relative z-10">
                        <div class="text-4xl font-black text-pink-900 drop-shadow-xl mb-2"><?= number_format($current_situation['female_patients'] ?? 0) ?></div>
                        <div class="text-lg font-black text-pink-800 tracking-wide">หญิง</div>
                    </div>
                </div>
                
                <!-- หญิงตั้งครรภ์ -->
                <div class="group relative bg-gradient-to-br from-purple-200 via-violet-300 to-purple-300 p-7 rounded-3xl text-center border-3 border-purple-400/70 shadow-2xl hover:shadow-3xl transition-all duration-700 hover:scale-125 hover:rotate-6 transform cursor-pointer overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-300/60 to-violet-400/60 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-purple-200/40 rounded-full -translate-y-8 translate-x-8 group-hover:scale-200 transition-transform duration-700"></div>
                    <div class="relative z-10">
                        <div class="text-4xl font-black text-purple-900 drop-shadow-xl mb-2"><?= number_format($current_situation['pregnant_women'] ?? 0) ?></div>
                        <div class="text-lg font-black text-purple-800 tracking-wide">หญิงตั้งครรภ์</div>
                    </div>
                </div>
                
                <!-- ผู้พิการ -->
                <div class="group relative bg-gradient-to-br from-cyan-200 via-sky-300 to-cyan-300 p-7 rounded-3xl text-center border-3 border-cyan-400/70 shadow-2xl hover:shadow-3xl transition-all duration-700 hover:scale-125 hover:rotate-6 transform cursor-pointer overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-cyan-300/60 to-sky-400/60 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-cyan-200/40 rounded-full -translate-y-8 translate-x-8 group-hover:scale-200 transition-transform duration-700"></div>
                    <div class="relative z-10">
                        <div class="text-4xl font-black text-cyan-900 drop-shadow-xl mb-2"><?= number_format($current_situation['disabled_patients'] ?? 0) ?></div>
                        <div class="text-lg font-black text-cyan-800 tracking-wide">ผู้พิการ</div>
                    </div>
                </div>
                
                <!-- ผู้ป่วยติดเตียง -->
                <div class="group relative bg-gradient-to-br from-red-200 via-rose-300 to-red-300 p-7 rounded-3xl text-center border-3 border-red-400/70 shadow-2xl hover:shadow-3xl transition-all duration-700 hover:scale-125 hover:rotate-6 transform cursor-pointer overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-red-300/60 to-rose-400/60 blur-lg opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <div class="absolute top-0 right-0 w-16 h-16 bg-red-200/40 rounded-full -translate-y-8 translate-x-8 group-hover:scale-200 transition-transform duration-700"></div>
                    <div class="relative z-10">
                        <div class="text-4xl font-black text-red-900 drop-shadow-xl mb-2"><?= number_format($current_situation['bedridden_patients'] ?? 0) ?></div>
                        <div class="text-lg font-black text-red-800 tracking-wide">ผู้ป่วยติดเตียง</div>
                    </div>
                </div>
            </div>
            
            <!-- Second Row -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mt-4">
                <!-- ผู้สูงอายุ -->
                <div class="bg-gradient-to-br from-orange-100 to-amber-200 p-5 rounded-xl text-center border-2 border-orange-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-orange-800"><?= number_format($current_situation['elderly_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-orange-700 mt-1">ผู้สูงอายุ</div>
                </div>
                
                <!-- เด็ก -->
                <div class="bg-gradient-to-br from-yellow-100 to-amber-200 p-5 rounded-xl text-center border-2 border-yellow-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-yellow-800"><?= number_format($current_situation['child_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-yellow-700 mt-1">เด็ก</div>
                </div>
                
                <!-- ผู้ป่วยโรคเรื้อรัง -->
                <div class="bg-gradient-to-br from-red-100 to-pink-200 p-5 rounded-xl text-center border-2 border-red-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-red-800"><?= number_format($current_situation['chronic_disease_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-red-700 mt-1">ผู้ป่วยโรคเรื้อรัง</div>
                </div>
                
                <!-- โรคเบาหวาน -->
                <div class="bg-gradient-to-br from-blue-100 to-indigo-200 p-5 rounded-xl text-center border-2 border-blue-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-blue-800"><?= number_format($current_situation['diabetes_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-blue-700 mt-1">โรคเบาหวาน</div>
                </div>
                
                <!-- โรคความดันโลหิตสูง -->
                <div class="bg-gradient-to-br from-green-100 to-emerald-200 p-5 rounded-xl text-center border-2 border-green-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-green-800"><?= number_format($current_situation['hypertension_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-green-700 mt-1">โรคความดันโลหิตสูง</div>
                </div>
                
                <!-- โรคหัวใจ -->
                <div class="bg-gradient-to-br from-pink-100 to-rose-200 p-5 rounded-xl text-center border-2 border-pink-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-pink-800"><?= number_format($current_situation['heart_disease_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-pink-700 mt-1">โรคหัวใจ</div>
                </div>
            </div>
            
            <!-- Third Row -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mt-4">
                <!-- จิตเวช -->
                <div class="bg-gradient-to-br from-purple-100 to-indigo-200 p-5 rounded-xl text-center border-2 border-purple-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-purple-800"><?= number_format($current_situation['mental_health_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-purple-700 mt-1">จิตเวช</div>
                </div>
                
                <!-- ไตวายระยะฟอกไต -->
                <div class="bg-gradient-to-br from-yellow-100 to-orange-200 p-5 rounded-xl text-center border-2 border-yellow-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-yellow-800"><?= number_format($current_situation['kidney_disease_patients'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-yellow-700 mt-1">ไตวายระยะฟอกไต</div>
                </div>
                
                <!-- โรคที่ต้องเฝ้าระวังอื่นๆ -->
                <div class="bg-gradient-to-br from-gray-100 to-slate-200 p-5 rounded-xl text-center border-2 border-gray-300 shadow-md hover:shadow-lg transition-all duration-300 hover:scale-105">
                    <div class="text-2xl font-bold text-gray-800"><?= number_format($current_situation['other_monitored_diseases'] ?? 0) ?></div>
                    <div class="text-sm font-semibold text-gray-700 mt-1">โรคที่ต้องเฝ้าระวังอื่นๆ</div>
                </div>
                
                <!-- ช่องว่าง -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-5 rounded-xl text-center border-2 border-gray-200 opacity-50">
                    <div class="text-2xl font-bold text-gray-400">-</div>
                    <div class="text-sm font-medium text-gray-400 mt-1">-</div>
                </div>
                
                <!-- ช่องว่าง -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-5 rounded-xl text-center border-2 border-gray-200 opacity-50">
                    <div class="text-2xl font-bold text-gray-400">-</div>
                    <div class="text-sm font-medium text-gray-400 mt-1">-</div>
                </div>
                
                <!-- ช่องว่าง -->
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-5 rounded-xl text-center border-2 border-gray-200 opacity-50">
                    <div class="text-2xl font-bold text-gray-400">-</div>
                    <div class="text-sm font-medium text-gray-400 mt-1">-</div>
                </div>
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
                <!-- Detailed Hospital Updates -->
                <?php if ($recent_detailed_logs->num_rows > 0): ?>
                    <div class="border-b pb-2 mb-2">
                        <h4 class="text-sm font-semibold text-blue-600 mb-2">การอัปเดตข้อมูลแบบละเอียด</h4>
                    </div>
                    <?php while($log = $recent_detailed_logs->fetch_assoc()): ?>
                    <div class="flex items-start gap-3 bg-blue-50 p-3 rounded-lg">
                        <div class="p-2 bg-blue-100 rounded-full mt-1">
                            <i data-lucide="<?= $log['operation_type'] == 'add' ? 'plus' : 'minus' ?>" class="h-4 w-4 <?= $log['operation_type'] == 'add' ? 'text-green-500' : 'text-red-500' ?>"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-700 text-sm"><?= htmlspecialchars($log['shelter_name']) ?></p>
                            <p class="text-xs text-gray-500">
                                <?= $log['operation_type'] == 'add' ? 'เพิ่ม' : 'ลด' ?>ข้อมูล: 
                                <?php
                                $changes = [];
                                if ($log['change_total_patients'] > 0) $changes[] = "จำนวนรวม {$log['change_total_patients']} คน";
                                if ($log['change_male_patients'] > 0) $changes[] = "ชาย {$log['change_male_patients']} คน";
                                if ($log['change_female_patients'] > 0) $changes[] = "หญิง {$log['change_female_patients']} คน";
                                echo htmlspecialchars(implode(', ', array_slice($changes, 0, 2)));
                                if (count($changes) > 2) echo '...';
                                ?>
                            </p>
                            <p class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
                
                <!-- General Logs -->
                <?php if ($recent_logs->num_rows > 0): ?>
                    <?php if ($recent_detailed_logs->num_rows > 0): ?>
                    <div class="border-b pb-2 mb-2 mt-4">
                        <h4 class="text-sm font-semibold text-gray-600 mb-2">กิจกรรมทั่วไป</h4>
                    </div>
                    <?php endif; ?>
                    <?php while($log = $recent_logs->fetch_assoc()): ?>
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-gray-100 rounded-full mt-1">
                            <i data-lucide="<?= $log['log_type'] == 'add' ? 'arrow-up' : 'arrow-down' ?>" class="h-4 w-4 <?= $log['log_type'] == 'add' ? 'text-green-500' : 'text-red-500' ?>"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-gray-700 text-sm"><?= htmlspecialchars($log['shelter_name']) ?></p>
                            <p class="text-xs text-gray-500">
                               <?= $log['log_type'] == 'add' ? 'เพิ่ม' : 'ลด' ?>: 
                               <?= htmlspecialchars($log['item_name']) ?> 
                               (<?= number_format($log['change_amount']) ?> <?= htmlspecialchars($log['item_unit']) ?>)
                            </p>
                            <p class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?php if ($recent_detailed_logs->num_rows == 0): ?>
                    <p class="text-center text-gray-400 py-4">ยังไม่มีการเคลื่อนไหว</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
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
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>

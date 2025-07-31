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

// Recent Logs
$recent_logs = $conn->query("
    SELECT sl.*, s.name as shelter_name 
    FROM shelter_logs sl
    JOIN shelters s ON sl.shelter_id = s.id
    ORDER BY sl.created_at DESC
    LIMIT 5
");
?>

<div class="space-y-8">


    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-blue-100 rounded-full">
                <i data-lucide="archive" class="w-6 h-6 text-blue-600"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">ศูนย์ทั้งหมด</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format($total_shelters) ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-green-100 rounded-full">
                <i data-lucide="users" class="w-6 h-6 text-green-600"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">ผู้เข้าพักทั้งหมด</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format($total_occupancy ?? 0) ?> / <?= number_format($total_capacity ?? 0) ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-yellow-100 rounded-full">
                <i data-lucide="package" class="w-6 h-6 text-yellow-600"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">ยอดบริจาค (ชิ้น)</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format($total_donations ?? 0) ?></p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-5">
            <div class="p-4 bg-purple-100 rounded-full">
                <i data-lucide="user-plus" class="w-6 h-6 text-purple-600"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">รองรับได้อีก</p>
                <p class="text-3xl font-bold text-gray-800"><?= number_format(($total_capacity ?? 0) - ($total_occupancy ?? 0)) ?></p>
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
                <?php if ($recent_logs->num_rows > 0): ?>
                    <?php while($log = $recent_logs->fetch_assoc()): ?>
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-gray-100 rounded-full mt-1">
                            <i data-lucide="<?= $log['log_type'] == 'add' ? 'arrow-up' : 'arrow-down' ?>" class="h-5 w-5 <?= $log['log_type'] == 'add' ? 'text-green-500' : 'text-red-500' ?>"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700"><?= htmlspecialchars($log['shelter_name']) ?></p>
                            <p class="text-sm text-gray-500">
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
<?php
// partials/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php'; 

$current_page = $_GET['page'] ?? 'dashboard';
$user_role = $_SESSION['role'] ?? 'Guest';
$user_name = $_SESSION['name'] ?? 'ผู้ใช้ทั่วไป';

$role_display_map = [
    'Admin' => 'ผู้ดูแลระบบ',
    'Coordinator' => 'เจ้าหน้าที่ประสานศูนย์',
    'HealthStaff' => 'เจ้าหน้าที่สาธารณสุข',
    'User' => 'ผู้ใช้ทั่วไป'
];
$display_role = $role_display_map[$user_role] ?? 'N/A';

// Fetch pending request count for Admin
$pending_request_count = 0;
if ($user_role === 'Admin') {
    $count_result = $conn->query("SELECT COUNT(id) as count FROM shelter_requests WHERE status = 'pending'");
    if ($count_result) {
        // --- FIXED LINE ---
        $count_data = $count_result->fetch_assoc();
        $pending_request_count = $count_data['count'];
    }
}

$navItems = [
    'dashboard' => ['label' => 'หน้าหลัก', 'icon' => 'home', 'roles' => ['Admin', 'Coordinator', 'HealthStaff', 'User']],
    'reports' => ['label' => 'รายงาน', 'icon' => 'bar-chart-2', 'roles' => ['Admin']],
    'shelters' => ['label' => 'จัดการข้อมูลศูนย์', 'icon' => 'building', 'roles' => ['Admin', 'Coordinator', 'HealthStaff']],
    'users' => ['label' => 'จัดการผู้ใช้งาน', 'icon' => 'users', 'roles' => ['Admin']],
    'settings' => ['label' => 'ตั้งค่าระบบ', 'icon' => 'settings', 'roles' => ['Admin']],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบประสานงานศูนย์ช่วยเหลือ</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .nav-link-active { color: #4f46e5; font-weight: 500; }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0 flex items-center">
                    <a href="index.php" class="text-xl font-bold text-gray-800">ระบบประสานงานฯ</a>
                </div>
                <div class="hidden md:flex items-center space-x-1">
                    <?php foreach ($navItems as $pageKey => $item): ?>
                        <?php if (in_array($user_role, $item['roles'])): ?>
                            <a href="index.php?page=<?= htmlspecialchars($pageKey) ?>" 
                               class="relative text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium <?= $current_page === $pageKey ? 'nav-link-active' : '' ?>">
                                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-4 h-4 inline-block mr-1"></i> 
                                <span><?= htmlspecialchars($item['label']) ?></span>
                                <?php if ($pageKey === 'users' && $pending_request_count > 0): ?>
                                    <span class="absolute top-1 right-1 flex h-5 w-5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-xs items-center justify-center"><?= $pending_request_count ?></span>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="flex items-center pl-4">
                        <span class="text-sm text-gray-600 mr-4">
                            สวัสดี, <strong><?= htmlspecialchars($user_name) ?></strong> (<?= htmlspecialchars($display_role) ?>)
                        </span>
                        <a href="logout.php" class="text-red-600 hover:text-red-700 px-3 py-2 rounded-md text-sm font-medium">
                            <i data-lucide="log-out" class="w-4 h-4 inline-block mr-1"></i> ออกจากระบบ
                        </a>
                    </div>
                </div>
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <?php foreach ($navItems as $pageKey => $item): ?>
                    <?php if (in_array($user_role, $item['roles'])): ?>
                        <a href="index.php?page=<?= htmlspecialchars($pageKey) ?>" 
                           class="relative block text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-base font-medium <?= $current_page === $pageKey ? 'nav-link-active bg-gray-100' : '' ?>">
                            <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-5 h-5 inline-block mr-2"></i>
                            <?= htmlspecialchars($item['label']) ?>
                            <?php if ($pageKey === 'users' && $pending_request_count > 0): ?>
                                <span class="absolute top-2 right-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full"><?= $pending_request_count ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="pt-4 pb-3 border-t border-gray-200">
                <div class="flex items-center px-5">
                    <div class="ml-3">
                        <div class="text-base font-medium text-gray-800"><?= htmlspecialchars($user_name) ?></div>
                        <div class="text-sm font-medium text-gray-500"><?= htmlspecialchars($display_role) ?></div>
                    </div>
                </div>
                <div class="mt-3 px-2 space-y-1">
                     <a href="logout.php" class="block text-red-600 hover:text-red-700 px-3 py-2 rounded-md text-base font-medium">
                        <i data-lucide="log-out" class="w-5 h-5 inline-block mr-2"></i>
                        ออกจากระบบ
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <script>
        lucide.createIcons();
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

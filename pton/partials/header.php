<?php
// partials/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Convert English role to Thai for display
$role_display = [
    'Admin' => 'ผู้ดูแลระบบ',
    'Coordinator' => 'เจ้าหน้าที่ประสานศูนย์',
    'User' => 'ผู้ใช้ทั่วไป'
];

$user_role = 'Guest';
if (isset($_SESSION['role'])) {
    $user_role = $_SESSION['role'];
}

$display_role = 'N/A';
if (isset($role_display[$user_role])) {
    $display_role = $role_display[$user_role];
}

$user_name = 'ผู้ใช้ทั่วไป';
if (isset($_SESSION['name'])) {
    $user_name = $_SESSION['name'];
}

// Fetch menu settings from DB to use here
require_once 'db_connect.php';
$settings_result = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'menu_%'");
$menu_settings = [];
while($row = $settings_result->fetch_assoc()) {
    $menu_settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการศูนย์ช่วยเหลือ อบจ.ศรีสะเกษ</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> --><link rel="icon" type="image/x-icon" href="https://www.pao-sisaket.go.th/image/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .sidebar-link.active { background-color: #4f46e5; color: white; }
        .sidebar { 
            transition: width 0.3s ease; 
            width: 16rem; 
            overflow: hidden;
        }
        .sidebar.collapsed { 
            width: 5rem; 
        }
        .sidebar .sidebar-text { 
            transition: opacity 0.3s ease;
            opacity: 1;
        }
        .sidebar.collapsed .sidebar-text,
        .sidebar.collapsed .user-info,
        .sidebar.collapsed .logo-text { 
            opacity: 0;
            display: none; 
        }
        .sidebar.collapsed .sidebar-link { 
            justify-content: center; 
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .sidebar.collapsed .logo-container { 
            justify-content: center; 
        }
        .main-content { 
            transition: margin-left 0.3s ease; 
            margin-left: 16rem;
        }
        body.sidebar-collapsed .main-content { 
            margin-left: 5rem; 
        }
        /* Logo container adjustments */
        .logo-container {
            position: relative;
            transition: all 0.3s ease;
        }
        
        /* Container for collapsed toggle button */
        .collapsed-toggle-container {
            display: none;
            justify-content: center;
            margin-top: 0.75rem;
        }
        
        /* Toggle button inside sidebar styles */
        .sidebar-toggle-btn-inside {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            background-color: transparent;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #6b7280;
            flex-shrink: 0;
        }
        .sidebar-toggle-btn-inside:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            color: #4f46e5;
        }
        .sidebar-toggle-btn-inside:active {
            transform: scale(0.95);
            background-color: #e5e7eb;
        }
        
        /* Toggle button when collapsed (above logo) */
        .sidebar-toggle-btn-collapsed {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            background-color: transparent;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #6b7280;
        }
        .sidebar-toggle-btn-collapsed:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            color: #4f46e5;
        }
        .sidebar-toggle-btn-collapsed:active {
            transform: scale(0.95);
            background-color: #e5e7eb;
        }
        
        /* Show/hide toggle buttons and adjust layout based on sidebar state */
        .sidebar:not(.collapsed) .collapsed-toggle-container {
            display: none;
        }
        .sidebar.collapsed .sidebar-toggle-btn-inside {
            display: none;
        }
        .sidebar.collapsed .collapsed-toggle-container {
            display: flex;
        }
        .sidebar.collapsed .logo-container {
            text-align: center;
            padding-bottom: 1rem;
        }
        .sidebar.collapsed .logo-container .flex {
            justify-content: center;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        .sidebar.collapsed .logo-container img {
            margin-bottom: 0.25rem;
        }
        
        /* Icon transition animation */
        .sidebar-toggle-btn-inside i,
        .sidebar-toggle-btn-collapsed i {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="bg-gray-100">

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar bg-white h-screen fixed top-0 left-0 flex flex-col shadow-lg z-10">
        <div class="p-4 border-b logo-container">            
            <!-- Main content container -->
            <div class="flex items-center gap-3">
                <img src="https://www.pao-sisaket.go.th/image/logo.png" alt="Logo" class="h-10 w-10">
                <div class="logo-text flex-1">
                     <h1 class="text-lg font-bold text-gray-800">อบจ.ศรีสะเกษ</h1>
                     <p class="text-xs text-gray-500">ระบบจัดการศูนย์ช่วยเหลือ</p>
                </div>
                <!-- Toggle button when expanded -->
                <button type="button" id="sidebarToggle" class="sidebar-toggle-btn-inside" title="ย่อ Sidebar">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </button>
            </div>
            
            <!-- Toggle button when collapsed (below logo) -->
            <div class="collapsed-toggle-container">
                <button type="button" id="sidebarToggleCollapsed" class="sidebar-toggle-btn-collapsed" title="ขยาย Sidebar">
                    <i data-lucide="menu" class="h-5 w-5"></i>
                </button>
            </div>
        </div>

        <nav class="flex-grow p-4 space-y-2">
            <?php if ($menu_settings['menu_dashboard'] == '1'): ?>
            <a href="index.php?page=dashboard" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-200 <?= $current_page == 'dashboard' ? 'active' : '' ?>" <?= $current_page == 'dashboard' ? 'aria-current="page"' : '' ?>>
                <i data-lucide="layout-dashboard"></i><span class="sidebar-text">ภาพรวมระบบ</span>
            </a>
            <?php endif; ?>

            <?php if ($menu_settings['menu_shelters'] == '1'): ?>
            <a href="index.php?page=shelters" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-200 <?= $current_page == 'shelters' ? 'active' : '' ?>" <?= $current_page == 'shelters' ? 'aria-current="page"' : '' ?>>
                <i data-lucide="archive"></i><span class="sidebar-text">จัดการข้อมูลศูนย์</span>
            </a>
            <?php endif; ?>

            <?php if ($user_role === 'Admin' && $menu_settings['menu_users'] == '1'): ?>
            <a href="index.php?page=users" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-200 <?= $current_page == 'users' ? 'active' : '' ?>" <?= $current_page == 'users' ? 'aria-current="page"' : '' ?>>
                <i data-lucide="users"></i><span class="sidebar-text">จัดการผู้ใช้งาน</span>
            </a>
            <?php endif; ?>

            <?php if ($user_role === 'Admin' && $menu_settings['menu_settings'] == '1'): ?>
            <a href="index.php?page=settings" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-200 <?= $current_page == 'settings' ? 'active' : '' ?>" <?= $current_page == 'settings' ? 'aria-current="page"' : '' ?>>
                <i data-lucide="sliders-horizontal"></i><span class="sidebar-text">ตั้งค่าระบบ</span>
            </a>
            <?php endif; ?>
        </nav>

        <div class="p-4 border-t">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center flex-shrink-0">
                    <i data-lucide="user"></i>
                </div>
                <div class="user-info">
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($display_role) ?></p>
                </div>
            </div>
            <a href="logout.php" class="mt-4 w-full flex items-center justify-center gap-2 py-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100">
                <i data-lucide="log-out" class="h-5 w-5"></i>
                <span class="sidebar-text">ออกจากระบบ</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main id="mainContent" class="main-content flex-1 p-8 overflow-y-auto">
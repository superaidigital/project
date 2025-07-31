<?php
/**
 * เมนูนำทางแบบไดนามิก
 * * ไฟล์นี้ต้องการตัวแปรต่อไปนี้เพื่อทำงานอย่างถูกต้อง:
 * @var array $navItems       - Array ของข้อมูลเมนูทั้งหมด (label, icon, roles)
 * @var array $menu_settings  - Array ของการตั้งค่าที่ดึงจากฐานข้อมูล (เช่น ['menu_dashboard' => '1', 'menu_shelters' => '0'])
 * @var string $currentUserRole - บทบาทของผู้ใช้ปัจจุบันจาก $_SESSION['role']
 * @var string $page           - หน้าปัจจุบันจาก $_GET['page']
 */

// ตรวจสอบว่าตัวแปรที่จำเป็นถูกส่งมาหรือไม่ เพื่อป้องกัน error
$navItems = $navItems ?? [];
$menu_settings = $menu_settings ?? [];
$currentUserRole = $currentUserRole ?? 'Guest';
$page = $page ?? 'dashboard';

?>
<nav>
    <ul>
        <?php foreach ($navItems as $pageKey => $item): ?>
            <?php
                // 1. ตรวจสอบสิทธิ์การเข้าถึงของผู้ใช้
                $has_permission = in_array($currentUserRole, $item['roles']);

                // 2. ตรวจสอบว่าเมนูนี้ถูกเปิดใช้งานในหน้า "ตั้งค่าระบบ" หรือไม่
                $setting_key = 'menu_' . $pageKey;
                // ถ้าไม่มีการตั้งค่าใน DB ให้ถือว่าเมนูนั้น "เปิด" เป็นค่าเริ่มต้น (fallback)
                $is_menu_enabled = ($menu_settings[$setting_key] ?? '1') == '1';
            ?>
            
            <?php // แสดงผลเมนู ก็ต่อเมื่อ ผู้ใช้ "มีสิทธิ์" และ เมนู "ถูกเปิดใช้งาน" ?>
            <?php if ($has_permission && $is_menu_enabled): ?>
                <li class="<?= $page === $pageKey ? 'active' : '' ?>">
                    <a href="index.php?page=<?= htmlspecialchars($pageKey) ?>">
                        <i data-lucide="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</nav>
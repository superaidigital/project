<?php
// File: includes/permissions.php
// DESCRIPTION: รวมฟังก์ชันสำหรับตรวจสอบสิทธิ์การเข้าถึงข้อมูลส่วนต่างๆ

/**
 * Checks if the current user has permission to manage a specific shelter.
 *
 * @param int $shelter_id The ID of the shelter to check.
 * @return bool True if the user has management rights, false otherwise.
 */
function canManageShelter($shelter_id) {
    if (!isset($_SESSION['role'])) {
        return false;
    }

    // Admins can manage everything.
    if ($_SESSION['role'] === 'Admin') {
        return true;
    }
    
    // HealthStaff can manage shelters they are assigned to.
    if ($_SESSION['role'] === 'HealthStaff') {
        return in_array($shelter_id, $_SESSION['permissions']['allowed_shelters'] ?? []);
    }
    
    // Coordinators can manage their single assigned shelter.
    if ($_SESSION['role'] === 'Coordinator') {
        return ($_SESSION['permissions']['assigned_shelter_id'] ?? null) == $shelter_id;
    }
    
    return false;
}

?>
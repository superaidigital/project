<?php
// includes/permissions.php

/**
 * Checks if the current user has permission to manage a specific shelter.
 * This is the central function for permission checks related to shelters.
 *
 * @param int $shelter_id The ID of the shelter to check.
 * @return bool True if the user has permission, false otherwise.
 */
function canManageShelter($shelter_id) {
    // Admins can manage everything.
    if ($_SESSION['role'] === 'Admin') {
        return true;
    }
    
    // HealthStaff can manage shelters they are assigned to.
    if ($_SESSION['role'] === 'HealthStaff') {
        // Use the permissions array stored in the session for efficiency.
        return in_array($shelter_id, $_SESSION['permissions']['allowed_shelters'] ?? []);
    }
    
    // Coordinators can manage their single assigned shelter.
    if ($_SESSION['role'] === 'Coordinator') {
        return ($_SESSION['permissions']['assigned_shelter'] ?? null) == $shelter_id;
    }
    
    // Other roles (like User) cannot manage any shelters.
    return false;
}

/**
 * Checks if the current user can view a specific shelter's details.
 * In this system, anyone logged in can view, but only certain roles can manage.
 *
 * @param int $shelter_id The ID of the shelter.
 * @return bool True if the user can view, false otherwise.
 */
function canViewShelter($shelter_id) {
    // For this project, we allow all logged-in users to view shelter details.
    // If rules change, this is the place to modify them.
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

<?php
function canManageShelter($shelter_id) {
    if ($_SESSION['role'] === 'Admin') {
        return true;
    }
    
    if ($_SESSION['role'] === 'HealthStaff') {
        return in_array($shelter_id, $_SESSION['allowed_shelters'] ?? []);
    }
    
    if ($_SESSION['role'] === 'Coordinator') {
        return $_SESSION['assigned_shelter'] == $shelter_id;
    }
    
    return false;
}

function canViewShelter($shelter_id) {
    return $_SESSION['role'] === 'Admin' || 
           $_SESSION['role'] === 'User' || 
           canManageShelter($shelter_id);
}
<?php
// File: includes/functions.php
// DESCRIPTION: รวมฟังก์ชันที่ใช้บ่อยในระบบ

/**
 * Checks if a user (Coordinator or HealthStaff) has been assigned to any shelter.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user to check.
 * @param string $role The role of the user.
 * @return bool True if the user is unassigned, false otherwise.
 */
function isUserUnassigned($conn, $user_id, $role) {
    if ($role === 'Coordinator') {
        $stmt = $conn->prepare("SELECT assigned_shelter_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // Unassigned if the assigned_shelter_id is NULL
        return is_null($result['assigned_shelter_id']);
    }

    if ($role === 'HealthStaff') {
        $stmt = $conn->prepare("SELECT COUNT(user_id) as count FROM healthstaff_shelters WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // Unassigned if their count in the assignment table is 0
        return $result['count'] == 0;
    }

    return false; // Admins and Users are never "unassigned" in this context
}


/**
 * Retrieves the permissions for a given user based on their role.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The user's ID.
 * @param string $role The user's role.
 * @return array An array containing allowed pages and shelter access information.
 */
function getUserPermissions($conn, $user_id, $role) {
    $permissions = [
        'allowed_pages' => ['dashboard'], // All logged-in users can see the dashboard
        'allowed_shelters' => [],
        'assigned_shelter_id' => null
    ];

    switch($role) {
        case 'Admin':
            $permissions['allowed_pages'] = ['dashboard', 'shelters', 'users', 'settings', 'reports'];
            break;

        case 'HealthStaff':
            $permissions['allowed_pages'] = ['dashboard', 'shelters', 'assign_shelter'];
            $stmt = $conn->prepare("SELECT shelter_id FROM healthstaff_shelters WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $permissions['allowed_shelters'][] = $row['shelter_id'];
                }
            }
            $stmt->close();
            break;

        case 'Coordinator':
            $permissions['allowed_pages'] = ['dashboard', 'shelters', 'assign_shelter'];
            $stmt = $conn->prepare("SELECT assigned_shelter_id FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $permissions['assigned_shelter_id'] = $row['assigned_shelter_id'];
                    if ($row['assigned_shelter_id']) {
                        $permissions['allowed_shelters'][] = $row['assigned_shelter_id'];
                    }
                }
            }
            $stmt->close();
            break;
        
        case 'User':
            // General users can only see the dashboard
            break;
    }

    return $permissions;
}

?>
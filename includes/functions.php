<?php
/**
 * Check database connection
 * @param mysqli $conn Connection object
 * @return bool Connection status
 */
function checkDatabaseConnection($conn) {
    try {
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        if (!$conn->ping()) {
            throw new Exception("Could not ping database");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Database Check Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check shelter access permission
 * @param mysqli $conn Connection object
 * @param int $shelter_id Shelter ID
 * @return bool Access permission
 */
function checkShelterPermission($conn, $shelter_id) {
    if ($_SESSION['role'] === 'Admin') return true;
    
    if ($_SESSION['role'] === 'HealthStaff') {
        $stmt = $conn->prepare("
            SELECT 1 FROM healthstaff_shelters 
            WHERE user_id = ? AND shelter_id = ?
        ");
        $stmt->bind_param("ii", $_SESSION['id'], $shelter_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    return false;
}

function getUserPermissions($conn, $user_id, $role) {
    $permissions = [
        'allowed_pages' => ['dashboard'],
        'can_manage_all' => false,
        'allowed_shelters' => [],
        'assigned_shelter' => null
    ];

    switch($role) {
        case 'Admin':
            $permissions['allowed_pages'] = ['dashboard', 'shelters', 'users', 'settings', 'reports', 'update_shelter_details'];
            $permissions['can_manage_all'] = true;
            break;

        case 'HealthStaff':
            $permissions['allowed_pages'] = ['dashboard', 'shelters', 'update_shelter_details'];
            // ดึงข้อมูลศูนย์ที่รับผิดชอบ
            $stmt = $conn->prepare("
                SELECT shelter_id 
                FROM healthstaff_shelters 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $permissions['allowed_shelters'][] = $row['shelter_id'];
                }
            }
            break;

        case 'Coordinator':
            $permissions['allowed_pages'] = ['dashboard', 'shelters', 'update_shelter_details'];
            // ดึงข้อมูลศูนย์ที่รับผิดชอบ
            $stmt = $conn->prepare("
                SELECT assigned_shelter_id 
                FROM users 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $permissions['assigned_shelter'] = $row['assigned_shelter_id'];
                }
            }
            break;
    }

    return $permissions;
}
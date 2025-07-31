<?php
require_once "../db_connect.php";
require_once "../includes/permissions.php";

// ตรวจสอบ session
session_start();
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['HealthStaff', 'Admin'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']));
}

// ตรวจสอบ method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method ไม่ถูกต้อง']));
}

// ตรวจสอบข้อมูลที่ส่งมา
$required_fields = ['shelter_id', 'male', 'female', 'status'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => "กรุณาระบุข้อมูล $field"]));
    }
}

// ตรวจสอบสิทธิ์การเข้าถึงศูนย์
if (!canManageShelter($_POST['shelter_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์จัดการศูนย์นี้']));
}

try {
    $conn->begin_transaction();

    // เตรียมข้อมูลสำหรับ shelter_occupant_details
    $occupant_data = [
        'shelter_id' => (int)$_POST['shelter_id'],
        'male' => (int)$_POST['male'],
        'female' => (int)$_POST['female'],
        'pregnant' => (int)($_POST['pregnant'] ?? 0),
        'disabled' => (int)($_POST['disabled'] ?? 0),
        'bedridden' => (int)($_POST['bedridden'] ?? 0),
        'elderly' => (int)($_POST['elderly'] ?? 0),
        'children' => (int)($_POST['children'] ?? 0),
        'diabetes' => (int)($_POST['diabetes'] ?? 0),
        'hypertension' => (int)($_POST['hypertension'] ?? 0),
        'heart_disease' => (int)($_POST['heart_disease'] ?? 0),
        'psychiatric' => (int)($_POST['psychiatric'] ?? 0),
        'kidney_dialysis' => (int)($_POST['kidney_dialysis'] ?? 0),
        'other_conditions' => $_POST['other_conditions'] ?? '',
        'status' => $_POST['status'],
        'created_by' => $_SESSION['id']
    ];

    // Insert into shelter_occupant_details
    $stmt = $conn->prepare("INSERT INTO shelter_occupant_details (
        shelter_id, male, female, pregnant, disabled, bedridden, elderly, children,
        diabetes, hypertension, heart_disease, psychiatric, kidney_dialysis, 
        other_conditions, status, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("iiiiiiiiiiiiissi", 
        $occupant_data['shelter_id'],
        $occupant_data['male'],
        $occupant_data['female'],
        $occupant_data['pregnant'],
        $occupant_data['disabled'],
        $occupant_data['bedridden'],
        $occupant_data['elderly'],
        $occupant_data['children'],
        $occupant_data['diabetes'],
        $occupant_data['hypertension'],
        $occupant_data['heart_disease'],
        $occupant_data['psychiatric'],
        $occupant_data['kidney_dialysis'],
        $occupant_data['other_conditions'],
        $occupant_data['status'],
        $occupant_data['created_by']
    );

    if (!$stmt->execute()) {
        throw new Exception("เกิดข้อผิดพลาดในการบันทึกข้อมูลผู้พักพิง: " . $stmt->error);
    }

    // Update shelter_logs
    $total = $occupant_data['male'] + $occupant_data['female'];
    
    $stmt2 = $conn->prepare("INSERT INTO shelter_logs (
        shelter_id, log_type, change_amount, status, created_by, note
    ) VALUES (?, 'update', ?, ?, ?, 'อัพเดทข้อมูลผู้พักพิง')");
    
    $stmt2->bind_param("iisi", 
        $occupant_data['shelter_id'],
        $total,
        $occupant_data['status'],
        $_SESSION['id']
    );

    if (!$stmt2->execute()) {
        throw new Exception("เกิดข้อผิดพลาดในการบันทึก log: " . $stmt2->error);
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
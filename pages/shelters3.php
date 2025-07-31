<?php
// pages/shelters.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// --- API Logic ---
if (isset($_GET['api'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    $db_connect_path = __DIR__ . '/../db_connect.php';
    if (!file_exists($db_connect_path)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (db_connect.php)']);
        exit();
    }
    require $db_connect_path;
    if (!$conn || $conn->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'การเชื่อมต่อฐานข้อมูลล้มเหลว: ' . ($conn ? $conn->connect_error : 'Unknown error')]);
        exit();
    }
    $data = json_decode(file_get_contents('php://input'), true);
    switch ($_GET['api']) {
        case 'get_shelters':
            $result = null;

            if ($_SESSION['role'] === 'Admin') {
                $sql = "SELECT * FROM shelters ORDER BY name ASC";
                $result = $conn->query($sql);
            }
            elseif ($_SESSION['role'] === 'Coordinator' && isset($_SESSION['assigned_shelter_id'])) {
                $assigned_id = intval($_SESSION['assigned_shelter_id']);
                $stmt = $conn->prepare("SELECT * FROM shelters WHERE id = ?");
                $stmt->bind_param("i", $assigned_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
            }
            elseif ($_SESSION['role'] === 'HealthStaff') {
                $allowed_shelters = $_SESSION['permissions']['allowed_shelters'] ?? [];

                if (!empty($allowed_shelters)) {
                    $allowed_ids = array_map('intval', $allowed_shelters);
                    $id_list = implode(',', $allowed_ids);
                    $sql = "SELECT * FROM shelters WHERE id IN ($id_list) ORDER BY name ASC";
                    $result = $conn->query($sql);
                }
            }
            else {
                $sql = "SELECT * FROM shelters ORDER BY name ASC";
                $result = $conn->query($sql);
            }

            $shelters = [];
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $shelters[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $shelters]);
            break;
            
        case 'add_shelter':
            if ($_SESSION['role'] !== 'Admin') {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ดำเนินการ']); exit();
            }
            $check_stmt = $conn->prepare("SELECT id FROM shelters WHERE name = ?");
            $check_stmt->bind_param("s", $data['name']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'ชื่อศูนย์นี้มีอยู่ในระบบแล้ว']);
                $check_stmt->close();
                exit();
            }
            $check_stmt->close();

            $stmt = $conn->prepare("INSERT INTO shelters (name, type, capacity, coordinator, phone, amphoe, tambon, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $capacity = isset($data['capacity']) && $data['capacity'] !== '' ? intval($data['capacity']) : 0;
            $stmt->bind_param("ssissssss", $data['name'], $data['type'], $capacity, $data['coordinator'], $data['phone'], $data['amphoe'], $data['tambon'], $data['latitude'], $data['longitude']);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'เพิ่มข้อมูลศูนย์สำเร็จ']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล: ' . $stmt->error]);
            }
            $stmt->close();
            break;
        case 'edit_shelter':
            $shelter_id_to_edit = intval($data['id']);
            if ($_SESSION['role'] === 'Coordinator' && $shelter_id_to_edit !== intval($_SESSION['assigned_shelter_id'])) {
                 echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขศูนย์นี้']); exit();
            }
             $stmt = $conn->prepare("UPDATE shelters SET name=?, type=?, capacity=?, coordinator=?, phone=?, amphoe=?, tambon=?, latitude=?, longitude=? WHERE id=?");
             $capacity = isset($data['capacity']) && $data['capacity'] !== '' ? intval($data['capacity']) : 0;
             $stmt->bind_param("ssissssssi", $data['name'], $data['type'], $capacity, $data['coordinator'], $data['phone'], $data['amphoe'], $data['tambon'], $data['latitude'], $data['longitude'], $data['id']);
             if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'แก้ไขข้อมูลสำเร็จ']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . $stmt->error]);
            }
            $stmt->close();
            break;
        case 'delete_shelter':
            if ($_SESSION['role'] !== 'Admin') {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ดำเนินการ']); exit();
            }
            $stmt = $conn->prepare("DELETE FROM shelters WHERE id = ?");
            $stmt->bind_param("i", $data['id']);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลสำเร็จ']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $stmt->error]);
            }
            $stmt->close();
            break;
        case 'update_amount':
            $shelter_id = intval($data['shelter_id']);
            if ($_SESSION['role'] === 'Coordinator' && $shelter_id !== intval($_SESSION['assigned_shelter_id'])) {
                 echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์อัปเดตศูนย์นี้']); exit();
            }
            $change_amount = intval($data['change_amount']);
            $log_type = $data['log_type'];
            $item_name = isset($data['item_name']) ? trim($data['item_name']) : 'ผู้เข้าพัก/ใช้บริการ';
            $item_unit = isset($data['item_unit']) ? trim($data['item_unit']) : 'คน';
            $conn->begin_transaction();
            try {
                $result = $conn->query("SELECT current_occupancy FROM shelters WHERE id = $shelter_id FOR UPDATE");
                $shelter = $result->fetch_assoc();
                $current_total = $shelter['current_occupancy'];
                $new_total = ($log_type == 'add') ? $current_total + $change_amount : max(0, $current_total - $change_amount);
                $stmt_update = $conn->prepare("UPDATE shelters SET current_occupancy = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $new_total, $shelter_id);
                $stmt_update->execute();
                $stmt_log = $conn->prepare("INSERT INTO shelter_logs (shelter_id, item_name, item_unit, change_amount, log_type, new_total) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_log->bind_param("issisi", $shelter_id, $item_name, $item_unit, $change_amount, $log_type, $new_total);
                $stmt_log->execute();
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'อัปเดตยอดสำเร็จ', 'new_total' => $new_total]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
            }
            break;
        case 'import_shelters':
            if ($_SESSION['role'] !== 'Admin') {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ดำเนินการ']);
                exit();
            }
        
            if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์ที่อัปโหลดหรือเกิดข้อผิดพลาด']);
                exit();
            }
        
            $file_path = $_FILES['csvFile']['tmp_name'];
            $file_mime = mime_content_type($file_path);
        
            if ($file_mime !== 'text/plain' && $file_mime !== 'text/csv') {
                echo json_encode(['status' => 'error', 'message' => 'รูปแบบไฟล์ไม่ถูกต้อง ต้องเป็น .csv เท่านั้น']);
                exit();
            }
            
            $file_handle = fopen($file_path, 'r');
            if (!$file_handle) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเปิดไฟล์ CSV ได้']);
                exit();
            }
            
            fseek($file_handle, 0);
            if (fgets($file_handle, 4) !== "\xef\xbb\xbf") {
                rewind($file_handle);
            }
        
            $header = fgetcsv($file_handle);
            if (!$header) {
                echo json_encode(['status' => 'error', 'message' => 'ไฟล์ CSV ว่างเปล่าหรือไม่สามารถอ่าน Header ได้']);
                fclose($file_handle);
                exit();
            }
            
            $required_headers = ['name', 'type'];
            foreach($required_headers as $rh) {
                if (!in_array($rh, $header)) {
                     echo json_encode(['status' => 'error', 'message' => "ไม่พบคอลัมน์ที่จำเป็น: " . $rh]);
                     fclose($file_handle);
                     exit();
                }
            }
        
            $conn->begin_transaction();
            $inserted_count = 0;
            $updated_count = 0;
            $error_count = 0;
        
            try {
                $insert_stmt = $conn->prepare("INSERT INTO shelters (name, type, capacity, coordinator, phone, amphoe, tambon, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $update_stmt = $conn->prepare("UPDATE shelters SET name=?, type=?, capacity=?, coordinator=?, phone=?, amphoe=?, tambon=?, latitude=?, longitude=? WHERE id=?");
        
                while (($row = fgetcsv($file_handle)) !== false) {
                    $data = array_combine($header, $row);
        
                    $name = trim($data['name'] ?? '');
                    $type = trim($data['type'] ?? '');
                    if (empty($name) || empty($type)) {
                        $error_count++;
                        continue;
                    }
        
                    $capacity = !empty($data['capacity']) ? intval($data['capacity']) : 0;
                    $coordinator = trim($data['coordinator'] ?? null);
                    $phone = trim($data['phone'] ?? null);
                    $amphoe = trim($data['amphoe'] ?? null);
                    $tambon = trim($data['tambon'] ?? null);
                    $latitude = trim($data['latitude'] ?? null);
                    $longitude = trim($data['longitude'] ?? null);
                    $id = !empty($data['id']) ? intval($data['id']) : null;
        
                    if ($id) {
                        $update_stmt->bind_param("ssissssssi", $name, $type, $capacity, $coordinator, $phone, $amphoe, $tambon, $latitude, $longitude, $id);
                        $update_stmt->execute();
                        if ($update_stmt->affected_rows > 0) {
                            $updated_count++;
                        }
                    } else {
                        $insert_stmt->bind_param("ssissssss", $name, $type, $capacity, $coordinator, $phone, $amphoe, $tambon, $latitude, $longitude);
                        if ($insert_stmt->execute()) {
                            $inserted_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                
                $conn->commit();
                $insert_stmt->close();
                $update_stmt->close();
                fclose($file_handle);
                
                $message = "นำเข้าสำเร็จ! เพิ่มข้อมูลใหม่ {$inserted_count} รายการ, อัปเดตข้อมูล {$updated_count} รายการ";
                if ($error_count > 0) {
                    $message .= ", เกิดข้อผิดพลาด {$error_count} รายการ";
                }
        
                echo json_encode(['status' => 'success', 'message' => $message]);
        
            } catch (Exception $e) {
                $conn->rollback();
                fclose($file_handle);
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดระหว่างการนำเข้าข้อมูล: ' . $e->getMessage()]);
            }
            break;
        case 'get_hospital_report':
            $shelter_id = intval($_GET['shelter_id']);
            $report_date = $_GET['report_date'] ?? date('Y-m-d');
            $stmt = $conn->prepare("SELECT * FROM hospital_daily_reports WHERE shelter_id = ? AND report_date = ?");
            $stmt->bind_param("is", $shelter_id, $report_date);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            echo json_encode(['status' => 'success', 'data' => $data]);
            $stmt->close();
            break;
        case 'save_hospital_report':
            $conn->begin_transaction();
            try {
                $data = $_POST;
                $shelter_id = intval($data['shelter_id']);
                $report_date = $data['report_date'] ?? date('Y-m-d');
                $operation_type = $data['operation_type'] ?? 'add';
                
                if (!$shelter_id) {
                    throw new Exception('ไม่พบ ID ศูนย์');
                }
                
                // START: Server-side validation
                $total_patients_change = intval($data['total_patients'] ?? 0);
                $subgroup_keys = [
                    'pregnant_women', 'disabled_patients', 'bedridden_patients', 'elderly_patients', 'child_patients',
                    'chronic_disease_patients', 'diabetes_patients', 'hypertension_patients', 'heart_disease_patients',
                    'mental_health_patients', 'kidney_disease_patients', 'other_monitored_diseases'
                ];
                $subgroups_total_change = 0;
                foreach($subgroup_keys as $key) {
                    $subgroups_total_change += intval($data[$key] ?? 0);
                }
                if ($subgroups_total_change > $total_patients_change) {
                     throw new Exception("ยอดรวมในกลุ่มย่อย ({$subgroups_total_change}) ต้องไม่เกินจำนวนรวมทั้งหมด ({$total_patients_change})");
                }
                // END: Server-side validation

                $current_stmt = $conn->prepare("SELECT current_occupancy FROM shelters WHERE id = ? FOR UPDATE");
                $current_stmt->bind_param("i", $shelter_id);
                $current_stmt->execute();
                $current_data = $current_stmt->get_result()->fetch_assoc();
                $old_occupancy = $current_data ? intval($current_data['current_occupancy']) : 0;
                $current_stmt->close();
                
                $change_amount = intval($data['total_patients'] ?? 0);
                $new_total = ($operation_type === 'add') ? $old_occupancy + $change_amount : max(0, $old_occupancy - $change_amount);
                
                $field_keys = ['male', 'female', 'pregnant_women', 'disabled', 'bedridden', 'elderly', 'child', 'chronic_disease', 'diabetes', 'hypertension', 'heart_disease', 'mental_health', 'kidney_disease', 'other_monitored_diseases'];
                $changes = [];
                foreach($field_keys as $key){
                    $changes[$key] = intval($data[$key.'_patients'] ?? $data[$key] ?? 0);
                }

                $check_stmt = $conn->prepare("SELECT * FROM hospital_daily_reports WHERE shelter_id = ? AND report_date = ? FOR UPDATE");
                $check_stmt->bind_param("is", $shelter_id, $report_date);
                $check_stmt->execute();
                $existing_data = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();
                
                $new_values = [];
                foreach($changes as $key => $change) {
                    $db_key_part = ($key === 'pregnant_women' || $key === 'other_monitored_diseases') ? $key : $key . '_patients';
                    if ($operation_type === 'subtract') {
                        $new_values[$db_key_part] = max(0, ($existing_data[$db_key_part] ?? 0) - $change);
                    } else {
                        $new_values[$db_key_part] = ($existing_data[$db_key_part] ?? 0) + $change;
                    }
                }
                $new_values['total_patients'] = $new_total;
                
                if ($existing_data) {
                    $sql_parts = [];
                    foreach ($new_values as $key => $value) $sql_parts[] = "`$key` = ?";
                    $sql = "UPDATE hospital_daily_reports SET " . implode(', ', $sql_parts) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $types = str_repeat('i', count($new_values)) . 'i';
                    $params = array_merge(array_values($new_values), [$existing_data['id']]);
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                } else {
                    $created_by = $_SESSION['user_id'] ?? null;
                    $keys = array_keys($new_values);
                    $sql = "INSERT INTO hospital_daily_reports (shelter_id, report_date, created_by, " . implode(', ', array_map(fn($k) => "`$k`", $keys)) . ") VALUES (?, ?, ?, " . rtrim(str_repeat('?, ', count($keys)), ', ') . ")";
                    $types = 'isi' . str_repeat('i', count($new_values));
                    $params = array_merge([$shelter_id, $report_date, $created_by], array_values($new_values));
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("เกิดข้อผิดพลาดในการบันทึกข้อมูลรายงาน: " . $stmt->error);
                }
                $stmt->close();
                
                $update_shelter = $conn->prepare("UPDATE shelters SET current_occupancy = ? WHERE id = ?");
                $update_shelter->bind_param("ii", $new_total, $shelter_id);
                $update_shelter->execute();
                $update_shelter->close();
                
                if ($change_amount > 0) {
                    $shelter_type_stmt = $conn->prepare("SELECT type FROM shelters WHERE id = ?");
                    $shelter_type_stmt->bind_param("i", $shelter_id);
                    $shelter_type_stmt->execute();
                    $shelter_type_data = $shelter_type_stmt->get_result()->fetch_assoc();
                    $item_name = ($shelter_type_data && $shelter_type_data['type'] === 'รพ.สต.') ? "ผู้ป่วย (รพ.สต.)" : "ผู้เข้าพัก (ศูนย์พักพิง)";
                    $shelter_type_stmt->close();
                    
                    $log_stmt = $conn->prepare("INSERT INTO shelter_logs (shelter_id, item_name, item_unit, change_amount, log_type, new_total) VALUES (?, ?, 'คน', ?, ?, ?)");
                    $log_stmt->bind_param("isisi", $shelter_id, $item_name, $change_amount, $operation_type, $new_total);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                // START: Add log to occupant_update_logs
                $log_stmt_occupant = $conn->prepare("
                    INSERT INTO occupant_update_logs (
                        shelter_id, user_id, operation_type,
                        total_change, male_change, female_change, pregnant_change,
                        disabled_change, bedridden_change, elderly_change, child_change,
                        chronic_disease_change, diabetes_change, hypertension_change,
                        heart_disease_change, mental_health_change, kidney_disease_change,
                        other_monitored_diseases_change
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $user_id_log = $_SESSION['user_id'] ?? null;
                $total_change_log = intval($data['total_patients'] ?? 0);
                $male_change_log = intval($data['male_patients'] ?? 0);
                $female_change_log = intval($data['female_patients'] ?? 0);
                $pregnant_change_log = intval($data['pregnant_women'] ?? 0);
                $disabled_change_log = intval($data['disabled_patients'] ?? 0);
                $bedridden_change_log = intval($data['bedridden_patients'] ?? 0);
                $elderly_change_log = intval($data['elderly_patients'] ?? 0);
                $child_change_log = intval($data['child_patients'] ?? 0);
                $chronic_disease_change_log = intval($data['chronic_disease_patients'] ?? 0);
                $diabetes_change_log = intval($data['diabetes_patients'] ?? 0);
                $hypertension_change_log = intval($data['hypertension_patients'] ?? 0);
                $heart_disease_change_log = intval($data['heart_disease_patients'] ?? 0);
                $mental_health_change_log = intval($data['mental_health_patients'] ?? 0);
                $kidney_disease_change_log = intval($data['kidney_disease_patients'] ?? 0);
                $other_monitored_diseases_change_log = intval($data['other_monitored_diseases'] ?? 0);

                $log_stmt_occupant->bind_param("iisiiiiiiiiiiiiiii",
                    $shelter_id, $user_id_log, $operation_type,
                    $total_change_log, $male_change_log, $female_change_log, $pregnant_change_log,
                    $disabled_change_log, $bedridden_change_log, $elderly_change_log, $child_change_log,
                    $chronic_disease_change_log, $diabetes_change_log, $hypertension_change_log,
                    $heart_disease_change_log, $mental_health_change_log, $kidney_disease_change_log,
                    $other_monitored_diseases_change_log
                );
                $log_stmt_occupant->execute();
                $log_stmt_occupant->close();
                // END: Add log to occupant_update_logs
                
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลสำเร็จ']);

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Save Hospital Report Error: " . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
            }
            break;
        case 'get_shelter_logs':
            if (!isset($_GET['shelter_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่ได้ระบุ shelter_id']);
                exit();
            }
            
            $shelter_id = intval($_GET['shelter_id']);
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(5, min(50, intval($_GET['limit']))) : 10;
            $offset = ($page - 1) * $limit;
            
            if ($_SESSION['role'] === 'Coordinator' && isset($_SESSION['assigned_shelter_id']) && $shelter_id !== intval($_SESSION['assigned_shelter_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลของศูนย์นี้']);
                exit();
            }
            
            try {
                $shelter_stmt = $conn->prepare("SELECT name FROM shelters WHERE id = ?");
                $shelter_stmt->bind_param("i", $shelter_id);
                $shelter_stmt->execute();
                $shelter_data = $shelter_stmt->get_result()->fetch_assoc();
                $shelter_stmt->close();
                
                if (!$shelter_data) {
                    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลศูนย์']);
                    exit();
                }
                
                $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM shelter_logs WHERE shelter_id = ?");
                $count_stmt->bind_param("i", $shelter_id);
                $count_stmt->execute();
                $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
                $count_stmt->close();
                
                $logs_stmt = $conn->prepare("SELECT * FROM shelter_logs WHERE shelter_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $logs_stmt->bind_param("iii", $shelter_id, $limit, $offset);
                $logs_stmt->execute();
                $logs_result = $logs_stmt->get_result();
                
                $logs = [];
                while ($row = $logs_result->fetch_assoc()) {
                    $logs[] = $row;
                }
                $logs_stmt->close();
                
                $total_pages = ceil($total_records / $limit);
                
                echo json_encode([
                    'status' => 'success', 
                    'data' => [
                        'shelter_name' => $shelter_data['name'],
                        'logs' => $logs,
                        'pagination' => [
                            'current_page' => $page,
                            'total_pages' => $total_pages,
                            'total_records' => $total_records,
                            'per_page' => $limit,
                            'has_prev' => $page > 1,
                            'has_next' => $page < $total_pages,
                            'showing_from' => $total_records > 0 ? $offset + 1 : 0,
                            'showing_to' => min($offset + $limit, $total_records)
                        ]
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
            }
            break;
        case 'get_current_details':
            if (!isset($_GET['shelter_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่ได้ระบุ shelter_id']);
                exit();
            }
            
            $shelter_id = intval($_GET['shelter_id']);

            if ($_SESSION['role'] === 'Coordinator' && isset($_SESSION['assigned_shelter_id']) && $shelter_id !== intval($_SESSION['assigned_shelter_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลของศูนย์นี้']);
                exit();
            }
            
            try {
                $shelter_stmt = $conn->prepare("SELECT * FROM shelters WHERE id = ?");
                $shelter_stmt->bind_param("i", $shelter_id);
                $shelter_stmt->execute();
                $shelter_data = $shelter_stmt->get_result()->fetch_assoc();
                $shelter_stmt->close();
                
                if (!$shelter_data) {
                    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลศูนย์']);
                    exit();
                }
                
                $details = null;
                if ($shelter_data['type'] === 'รพ.สต.' || $shelter_data['type'] === 'ศูนย์พักพิง') {
                    $report_stmt = $conn->prepare("SELECT * FROM hospital_daily_reports WHERE shelter_id = ? ORDER BY report_date DESC, updated_at DESC LIMIT 1");
                    $report_stmt->bind_param("i", $shelter_id);
                    $report_stmt->execute();
                    $details = $report_stmt->get_result()->fetch_assoc();
                    $report_stmt->close();
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'shelter' => $shelter_data,
                        'details' => $details
                    ]
                ]);

            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
            }
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid API call']);
            break;
    }
    $conn->close();
    exit();
}
?>
<div class="space-y-6">

    <?php if ($_SESSION['role'] === 'Admin'): ?>
    <div class="bg-white p-4 rounded-xl shadow-md">
        <div class="grid grid-cols-1 md:grid-cols-5 lg:grid-cols-7 gap-4 items-center">
            <div class="md:col-span-2 lg:col-span-2">
                 <label for="searchInput" class="text-sm font-medium text-gray-700">ค้นหาชื่อศูนย์</label>
                 <div class="relative mt-1">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400"></i>
                    <input type="text" id="searchInput" placeholder="พิมพ์ชื่อศูนย์, ผู้ประสานงาน..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg">
                 </div>
            </div>
            <div>
                <label for="typeFilter" class="text-sm font-medium text-gray-700">ประเภท</label>
                <select id="typeFilter" class="w-full mt-1 py-2 border border-gray-300 rounded-lg">
                    <option value="">ทุกประเภท</option>
                    <option>ศูนย์พักพิง</option>
                    <option>ศูนย์รับบริจาค</option>
                    <option>รพ.สต.</option>
                    <option>โรงพยาบาล</option>
                    <option>โรงครัวพระราชทาน</option>
                </select>
            </div>
            <div>
                <label for="amphoeFilter" class="text-sm font-medium text-gray-700">อำเภอ</label>
                <select id="amphoeFilter" class="w-full mt-1 py-2 border border-gray-300 rounded-lg">
                    <option value="">ทุกอำเภอ</option>
                </select>
            </div>
            <div>
                <label for="tambonFilter" class="text-sm font-medium text-gray-700">ตำบล</label>
                <select id="tambonFilter" class="w-full mt-1 py-2 border border-gray-300 rounded-lg" disabled>
                    <option value="">ทุกตำบล</option>
                </select>
            </div>
            <div class="flex items-end h-full gap-2 lg:col-span-2 justify-self-end">
                <div class="bg-gray-200 p-1 rounded-lg flex">
                    <button id="viewGridBtn" class="p-2 rounded-md bg-white shadow" title="มุมมองการ์ด"><i data-lucide="layout-grid" class="h-5 w-5 pointer-events-none"></i></button>
                    <button id="viewListBtn" class="p-2 rounded-md text-gray-500" title="มุมมองตาราง"><i data-lucide="list" class="h-5 w-5 pointer-events-none"></i></button>
                </div>
                 <button id="resetFilterBtn" class="p-2.5 bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200" title="ล้างค่า"><i data-lucide="rotate-cw" class="h-5 w-5"></i></button>
                 <button id="importCsvBtn" class="p-2.5 bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200" title="นำเข้าข้อมูลจาก CSV"><i data-lucide="file-up" class="h-5 w-5"></i></button>
                 <button id="exportCsvBtn" class="p-2.5 bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200" title="บันทึกเป็น CSV"><i data-lucide="file-down" class="h-5 w-5"></i></button>
                 <button id="addShelterBtn" class="p-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700" title="เพิ่มศูนย์ใหม่"><i data-lucide="plus" class="h-5 w-5"></i></button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div id="dataDisplayContainer" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <p class="text-center text-gray-500 py-12 col-span-full">กำลังโหลดข้อมูล...</p>
    </div>
</div>
<div id="shelterModal" class="fixed inset-0 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-8 border border-gray-200 w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
        <button id="closeShelterModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
        <h3 id="shelterModalTitle" class="text-2xl leading-6 font-bold text-gray-900 mb-6"></h3>
        <form id="shelterForm" class="space-y-4">
            <input type="hidden" id="shelterId" name="id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">ชื่อศูนย์</label>
                    <input type="text" id="name" name="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">ประเภทสถานที่</label>
                        <select id="type" name="type" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg">
                           <option>ศูนย์พักพิง</option>
                           <option>ศูนย์รับบริจาค</option>
                           <option>รพ.สต.</option>
                           <option>โรงพยาบาล</option>
                           <option>โรงครัวพระราชทาน</option>
                        </select>
                    </div>
                    <div>
                        <label for="capacity" class="block text-sm font-medium text-gray-700">เป้าหมาย/ความจุ</label>
                        <input type="number" id="capacity" name="capacity" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="500">
                    </div>
                </div>
                 <div>
                    <label for="coordinator" class="block text-sm font-medium text-gray-700">ผู้ประสานงาน</label>
                    <input type="text" id="coordinator" name="coordinator" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">เบอร์โทรติดต่อ</label>
                    <input type="tel" id="phone" name="phone" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                 <div>
                    <label for="modalAmphoe" class="block text-sm font-medium text-gray-700">อำเภอ</label>
                    <select id="modalAmphoe" name="amphoe" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required></select>
                </div>
                 <div>
                    <label for="modalTambon" class="block text-sm font-medium text-gray-700">ตำบล</label>
                    <select id="modalTambon" name="tambon" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required></select>
                </div>
                 <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">พิกัด (ละติจูด, ลองจิจูด)</label>
                    <div class="flex items-center gap-2 mt-1">
                        <input type="text" id="latitude" name="latitude" placeholder="เช่น 15.123456" class="block w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <input type="text" id="longitude" name="longitude" placeholder="เช่น 104.56789" class="block w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <button type="button" id="getCurrentLocation" class="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200" title="ดึงพิกัดปัจจุบัน"><i data-lucide="map-pin"></i></button>
                    </div>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                 <button type="button" id="cancelShelterModal" class="px-6 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">ยกเลิก</button>
                 <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<div id="importModal" class="fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-8 border w-full max-w-lg shadow-lg rounded-2xl bg-white">
        <button id="closeImportModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
        <h3 id="importModalTitle" class="text-2xl leading-6 font-bold text-gray-900 mb-6">นำเข้าข้อมูลศูนย์จากไฟล์ CSV</h3>
        <form id="importForm">
            <div class="space-y-4">
                <div>
                    <label for="csvFile" class="block text-sm font-medium text-gray-700">เลือกไฟล์ .csv</label>
                    <input type="file" id="csvFile" name="csvFile" accept=".csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-600">
                    <p class="font-semibold mb-2">คำแนะนำ:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>ไฟล์ต้องเป็น UTF-8 encoded CSV</li>
                        <li>Header ที่ต้องมี: `name`, `type`, `capacity`, `coordinator`, `phone`, `amphoe`, `tambon`, `latitude`, `longitude`</li>
                        <li>คอลัมน์ `name` และ `type` จำเป็นต้องมีข้อมูล</li>
                        <li>หากต้องการอัปเดตข้อมูลเดิม ให้เพิ่มคอลัมน์ `id` เข้ามาด้วย</li>
                    </ul>
                     <button type="button" id="downloadTemplateBtn" class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i data-lucide="download" class="h-4 w-4"></i>
                        <span>ดาวน์โหลดเทมเพลต</span>
                    </button>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                 <button type="button" id="cancelImportModal" class="px-6 py-2.5 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</button>
                 <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700">นำเข้าข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<div id="updateAmountModal" class="fixed inset-0 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-8 border border-gray-200 w-full max-w-lg shadow-2xl rounded-2xl bg-white">
        <button id="closeUpdateAmountModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
        <h3 id="updateAmountModalTitle" class="text-2xl leading-6 font-bold text-gray-900 mb-2"></h3>
        <p id="updateAmountModalSubtitle" class="text-gray-500 mb-6"></p>
        <form id="updateAmountForm" class="space-y-4">
            <input type="hidden" id="updateShelterId" name="shelter_id">
            <div id="occupantUpdateView" class="hidden">
                 <div>
                    <label for="occupantAmount" class="block text-sm font-medium text-gray-700">จำนวน (คน)</label>
                    <input type="number" id="occupantAmount" name="occupant_amount" min="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg text-center text-xl" required>
                </div>
            </div>
            <div id="donationUpdateView" class="hidden space-y-4">
                <div>
                    <label for="itemName" class="block text-sm font-medium text-gray-700">รายการสิ่งของ</label>
                    <input type="text" id="itemName" name="item_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="เช่น บะหมี่กึ่งสำเร็จรูป" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                     <div>
                        <label for="donationAmount" class="block text-sm font-medium text-gray-700">จำนวน</label>
                        <input type="number" id="donationAmount" name="donation_amount" min="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label for="itemUnit" class="block text-sm font-medium text-gray-700">หน่วยนับ</label>
                        <input type="text" id="itemUnit" name="item_unit" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="เช่น แพ็ค, ขวด" required>
                    </div>
                </div>
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">ประเภทการเปลี่ยนแปลง</span>
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <input type="radio" id="logTypeAdd" name="log_type" value="add" class="sr-only peer" checked>
                    <label for="logTypeAdd" class="flex flex-col items-center justify-center text-center p-4 rounded-lg cursor-pointer border-2 border-gray-200 peer-checked:border-blue-600 peer-checked:bg-blue-50">
                        <span class="text-green-600 font-bold text-2xl">+</span><span class="font-semibold">เพิ่ม</span>
                    </label>
                    <input type="radio" id="logTypeSubtract" name="log_type" value="subtract" class="sr-only peer">
                    <label for="logTypeSubtract" class="flex flex-col items-center justify-center text-center p-4 rounded-lg cursor-pointer border-2 border-gray-200 peer-checked:border-red-600 peer-checked:bg-red-50">
                         <span class="text-red-600 font-bold text-2xl">-</span><span class="font-semibold">ลบ</span>
                    </label>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                 <button type="button" id="cancelUpdateAmountModal" class="px-6 py-2.5 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</button>
                 <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- START: Updated Hospital Report Modal with 2 Steps -->
<div id="hospitalReportModal" class="fixed inset-0 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-6 md:p-8 border-gray-200 w-full max-w-3xl shadow-2xl rounded-2xl bg-white my-8">
        <button id="closeHospitalReportModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
        <h3 id="hospitalReportModalTitle" class="text-2xl leading-6 font-bold text-gray-900 mb-6"></h3>
        
        <form id="hospitalReportForm">
            <input type="hidden" id="hospitalShelterId" name="shelter_id">
            <input type="hidden" id="hospitalReportDate" name="report_date">
            
            <!-- Step 1: Choose Operation -->
            <div id="formStep1" class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <h4 class="font-semibold text-gray-800 mb-3">ขั้นตอนที่ 1: เลือกการดำเนินการ</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="radio" name="operation_type" value="add" id="op_add" class="peer sr-only">
                        <label for="op_add" class="flex items-center justify-center gap-2 p-4 rounded-lg border-2 border-gray-300 text-gray-800 cursor-pointer peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700">
                            <i data-lucide="plus-circle" class="h-5 w-5"></i>
                            <span class="font-semibold">เพิ่มยอด</span>
                        </label>
                        <input type="radio" name="operation_type" value="subtract" id="op_subtract" class="peer sr-only">
                        <label for="op_subtract" class="flex items-center justify-center gap-2 p-4 rounded-lg border-2 border-gray-300 text-gray-800 cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700">
                            <i data-lucide="minus-circle" class="h-5 w-5"></i>
                            <span class="font-semibold">ลดยอด</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-4 pt-4 border-t">
                     <button type="button" id="cancelHospitalReportModalStep1" class="px-6 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 font-medium">ยกเลิก</button>
                     <button type="button" id="nextStepBtn" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 flex items-center gap-2 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>
                        <span>ถัดไป</span><i data-lucide="arrow-right" class="h-5 w-5"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Fill Details -->
            <div id="formStep2" class="hidden space-y-6">
                <div>
                    <h4 class="font-semibold text-gray-800 mb-3">ขั้นตอนที่ 2: กรอกจำนวนที่เปลี่ยนแปลง</h4>
                    <p class="text-xs text-gray-500 -mt-2 mb-3">
                        <span class="font-medium">คำแนะนำ:</span> กรุณากรอกจำนวนที่ต้องการ "เปลี่ยนแปลง" จากยอดปัจจุบัน
                    </p>
                </div>
                <!-- General Info Section -->
                <div class="bg-blue-50 p-4 rounded-lg shadow-sm border border-blue-200">
                    <h4 class="font-semibold text-blue-800 mb-3">ข้อมูลทั่วไป</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="totalPatients" class="block text-sm font-medium text-gray-700">จำนวนรวม *</label>
                            <input type="number" name="total_patients" id="totalPatients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label for="malePatients" class="block text-sm font-medium text-gray-700">ชาย</label>
                            <input type="number" name="male_patients" id="malePatients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="femalePatients" class="block text-sm font-medium text-gray-700">หญิง</label>
                            <input type="number" name="female_patients" id="femalePatients" min="0" value="0" class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <span class="font-medium">หมายเหตุ:</span> หากกรอก 'ชาย' และ 'หญิง' ระบบจะคำนวณ 'จำนวนรวม' ให้อัตโนมัติ หากไม่ทราบให้กรอกแค่ 'จำนวนรวม'
                    </p>
                </div>
                <!-- Accordions -->
                <div class="accordion-item bg-white rounded-lg border">
                    <button type="button" class="accordion-toggle flex justify-between items-center w-full p-4 text-left font-semibold text-gray-800 hover:bg-gray-50">
                        <span>กลุ่มผู้ป่วยพิเศษ (คลิกเพื่อแสดง/ซ่อน)</span>
                        <i data-lucide="chevron-down" class="chevron-icon transition-transform"></i>
                    </button>
                    <div class="accordion-content overflow-hidden" style="max-height: 0;">
                        <div class="p-4 border-t grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700">หญิงตั้งครรภ์</label><input type="number" name="pregnant_women" min="0" value="0" class="form-input-subgroup"></div>
                            <div><label class="block text-sm font-medium text-gray-700">ผู้พิการ</label><input type="number" name="disabled_patients" min="0" value="0" class="form-input-subgroup"></div>
                            <div><label class="block text-sm font-medium text-gray-700">ผู้ป่วยติดเตียง</label><input type="number" name="bedridden_patients" min="0" value="0" class="form-input-subgroup"></div>
                            <div><label class="block text-sm font-medium text-gray-700">ผู้สูงอายุ</label><input type="number" name="elderly_patients" min="0" value="0" class="form-input-subgroup"></div>
                            <div><label class="block text-sm font-medium text-gray-700">เด็ก</label><input type="number" name="child_patients" min="0" value="0" class="form-input-subgroup"></div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item bg-white rounded-lg border">
                    <button type="button" class="accordion-toggle flex justify-between items-center w-full p-4 text-left font-semibold text-gray-800 hover:bg-gray-50">
                        <span>โรคเรื้อรังและโรคที่ต้องเฝ้าระวัง (คลิกเพื่อแสดง/ซ่อน)</span>
                        <i data-lucide="chevron-down" class="chevron-icon transition-transform"></i>
                    </button>
                    <div class="accordion-content overflow-hidden" style="max-height: 0;">
                        <div class="p-4 border-t grid grid-cols-2 md:grid-cols-3 gap-4">
                           <div><label class="block text-sm font-medium text-gray-700">ผู้ป่วยโรคเรื้อรัง</label><input type="number" name="chronic_disease_patients" min="0" value="0" class="form-input-subgroup"></div>
                           <div><label class="block text-sm font-medium text-gray-700">โรคเบาหวาน</label><input type="number" name="diabetes_patients" min="0" value="0" class="form-input-subgroup"></div>
                           <div><label class="block text-sm font-medium text-gray-700">โรคความดันโลหิตสูง</label><input type="number" name="hypertension_patients" min="0" value="0" class="form-input-subgroup"></div>
                           <div><label class="block text-sm font-medium text-gray-700">โรคหัวใจ</label><input type="number" name="heart_disease_patients" min="0" value="0" class="form-input-subgroup"></div>
                           <div><label class="block text-sm font-medium text-gray-700">จิตเวช</label><input type="number" name="mental_health_patients" min="0" value="0" class="form-input-subgroup"></div>
                           <div><label class="block text-sm font-medium text-gray-700">ไตวายระยะฟอกไต</label><input type="number" name="kidney_disease_patients" min="0" value="0" class="form-input-subgroup"></div>
                           <div class="md:col-span-3"><label class="block text-sm font-medium text-gray-700">โรคที่ต้องเฝ้าระวังอื่นๆ</label><input type="number" name="other_monitored_diseases" min="0" value="0" class="form-input-subgroup"></div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between gap-4 pt-4 border-t border-gray-200">
                    <button type="button" id="prevStepBtn" class="px-6 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 font-medium flex items-center gap-2">
                        <i data-lucide="arrow-left" class="h-5 w-5"></i><span>ย้อนกลับ</span>
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 flex items-center gap-2">
                        <i data-lucide="save" class="h-5 w-5"></i><span>บันทึกข้อมูล</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    /* Add this style for the accordion and new inputs */
    .accordion-toggle .chevron-icon { transition: transform 0.3s ease; }
    .accordion-toggle.active .chevron-icon { transform: rotate(180deg); }
    .accordion-content { transition: max-height 0.3s ease-in-out, padding 0.3s ease-in-out; }
    .form-input-subgroup {
        margin-top: 0.25rem;
        display: block;
        width: 100%;
        border-color: #d1d5db; /* border-gray-300 */
        border-radius: 0.5rem; /* rounded-lg */
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); /* shadow-sm */
    }
    .form-input-subgroup:focus {
        border-color: #3b82f6; /* focus:border-blue-500 */
        --tw-ring-color: #3b82f6; /* focus:ring-blue-500 */
    }
</style>
<!-- END: Updated Hospital Report Modal -->


<div id="historyModal" class="fixed inset-0 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-3 border border-gray-200 w-full max-w-6xl shadow-2xl rounded-xl bg-white my-2">
        <button id="closeHistoryModal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
        <h3 id="historyModalTitle" class="text-lg leading-5 font-bold text-gray-900 mb-3">ประวัติการเปลี่ยนแปลงข้อมูล</h3>
        
        <div class="mb-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <div>
                <p class="text-sm text-gray-600">ศูนย์: <span id="historyShelterName" class="font-semibold"></span></p>
                <p class="text-sm text-gray-500" id="historyPaginationInfo">-</p>
            </div>
            <div class="flex items-center gap-2">
                <label for="historyPerPage" class="text-sm text-gray-600">แสดง:</label>
                <select id="historyPerPage" class="text-sm border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="30">30</option>
                    <option value="50">50</option>
                </select>
                <span class="text-sm text-gray-600">รายการต่อหน้า</span>
            </div>
        </div>
        
        <div id="historyLoadingIndicator" class="text-center py-4 hidden">
            <div class="inline-flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm">กำลังโหลดข้อมูล...</span>
            </div>
        </div>
        
        <div id="historyContent">
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">วันที่/เวลา</th>
                            <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">รายการ</th>
                            <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">หน่วย</th>
                            <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">การเปลี่ยนแปลง</th>
                            <th class="px-3 py-2 text-left text-sm font-medium text-gray-500 uppercase tracking-wider">ยอดใหม่</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="bg-white divide-y divide-gray-200">
                        </tbody>
                </table>
            </div>
            
            <div id="historyPagination" class="flex items-center justify-between bg-white px-3 py-2 border-t border-gray-200 sm:px-4 hidden">
                <div class="flex-1 flex justify-between sm:hidden">
                    <button id="historyPrevMobile" class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        ก่อนหน้า
                    </button>
                    <button id="historyNextMobile" class="ml-2 relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        ถัดไป
                    </button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700" id="historyPaginationText">
                            แสดง <span class="font-medium" id="historyShowingFrom">1</span> ถึง <span class="font-medium" id="historyShowingTo">10</span> จาก <span class="font-medium" id="historyTotalRecords">100</span> รายการ
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" id="historyPageNumbers">
                            </nav>
                    </div>
                </div>
            </div>
            
            <div id="noHistoryMessage" class="text-center py-6 text-gray-500 hidden">
                <i data-lucide="inbox" class="h-10 w-10 mx-auto mb-2 text-gray-300"></i>
                <p class="text-sm">ไม่มีประวัติการเปลี่ยนแปลงข้อมูล</p>
            </div>
        </div>
        
        <div class="flex justify-end gap-2 pt-3 border-t mt-3">
            <button type="button" id="closeHistoryModalBtn" class="px-3 py-1.5 bg-gray-200 text-sm rounded-lg hover:bg-gray-300">ปิด</button>
        </div>
    </div>
</div>

<div id="currentDetailsModal" class="fixed inset-0 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-3 border border-gray-200 w-full max-w-4xl shadow-2xl rounded-xl bg-white my-2">
        <button id="closeCurrentDetailsModal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600">
            <i data-lucide="x" class="h-4 w-4"></i>
        </button>
        <h3 id="currentDetailsModalTitle" class="text-lg leading-5 font-bold text-gray-900 mb-3">รายละเอียดปัจจุบัน</h3>
        
        <div class="mb-3">
            <p class="text-sm text-gray-600">ศูนย์: <span id="currentDetailsShelterName" class="font-semibold"></span></p>
            <p class="text-sm text-gray-600">ประเภท: <span id="currentDetailsShelterType" class="font-semibold"></span></p>
        </div>
        
        <div id="currentDetailsLoadingIndicator" class="text-center py-6 hidden">
            <div class="inline-flex items-center">
                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm">กำลังโหลดข้อมูล...</span>
            </div>
        </div>
        
        <div id="currentDetailsContent" class="hidden">
            <div class="bg-blue-50 p-3 rounded-lg mb-3">
                <h4 class="font-semibold text-blue-800 mb-2 text-sm">ข้อมูลสรุป</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="text-center">
                        <p class="text-xl font-bold text-blue-600" id="currentTotalCount">0</p>
                        <p class="text-sm text-gray-600" id="currentTotalLabel">จำนวนรวม</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-green-600" id="currentMaleCount">0</p>
                        <p class="text-sm text-gray-600">ชาย</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-pink-600" id="currentFemaleCount">0</p>
                        <p class="text-sm text-gray-600">หญิง</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-purple-600" id="currentPregnantCount">0</p>
                        <p class="text-sm text-gray-600">หญิงตั้งครรภ์</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-yellow-50 p-3 rounded-lg mb-3">
                <h4 class="font-semibold text-yellow-800 mb-2 text-sm">กลุ่มผู้ป่วย/ผู้เข้าพักพิเศษ</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <p class="text-lg font-semibold" id="currentDisabledCount">0</p>
                        <p class="text-sm text-gray-600">ผู้พิการ</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentBedriddenCount">0</p>
                        <p class="text-sm text-gray-600">ผู้ป่วยติดเตียง</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentElderlyCount">0</p>
                        <p class="text-sm text-gray-600">ผู้สูงอายุ</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentChildCount">0</p>
                        <p class="text-sm text-gray-600">เด็ก</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-red-50 p-3 rounded-lg mb-3">
                <h4 class="font-semibold text-red-800 mb-2 text-sm">โรคเรื้อรังและโรคที่ต้องเฝ้าระวัง</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div>
                        <p class="text-lg font-semibold" id="currentChronicCount">0</p>
                        <p class="text-sm text-gray-600">โรคเรื้อรัง</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentDiabetesCount">0</p>
                        <p class="text-sm text-gray-600">เบาหวาน</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentHypertensionCount">0</p>
                        <p class="text-sm text-gray-600">ความดันโลหิตสูง</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentHeartCount">0</p>
                        <p class="text-sm text-gray-600">โรคหัวใจ</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentMentalCount">0</p>
                        <p class="text-sm text-gray-600">จิตเวช</p>
                    </div>
                    <div>
                        <p class="text-lg font-semibold" id="currentKidneyCount">0</p>
                        <p class="text-sm text-gray-600">ไตวายระยะฟอกไต</p>
                    </div>
                    <div class="md:col-span-3">
                        <p class="text-lg font-semibold" id="currentOtherCount">0</p>
                        <p class="text-sm text-gray-600">โรคที่ต้องเฝ้าระวังอื่นๆ</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded-lg">
                <h4 class="font-semibold text-gray-800 mb-1 text-sm">ข้อมูลรายงาน</h4>
                <div class="text-sm text-gray-600">
                    <p>วันที่รายงานล่าสุด: <span id="currentReportDate" class="font-medium">-</span></p>
                    <p>อัปเดตล่าสุด: <span id="currentUpdateTime" class="font-medium">-</span></p>
                </div>
            </div>
        </div>
        
        <div id="noDetailsMessage" class="text-center py-6 text-gray-500 hidden">
            <i data-lucide="file-x" class="h-10 w-10 mx-auto mb-2 text-gray-300"></i>
            <p class="text-sm">ยังไม่มีข้อมูลรายละเอียด</p>
            <p class="text-sm">กรุณาทำการอัปเดตยอดเพื่อสร้างข้อมูลรายละเอียด</p>
        </div>
        
        <div class="flex justify-end gap-2 pt-3 border-t mt-3">
            <button type="button" id="closeCurrentDetailsModalBtn" class="px-3 py-1.5 bg-gray-200 text-xs rounded-lg hover:bg-gray-300">ปิด</button>
        </div>
    </div>
</div>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Universal variables
    let allShelters = [];
    let currentView = 'grid'; 
    const dataContainer = document.getElementById('dataDisplayContainer');
    const shelterModal = document.getElementById('shelterModal');
    const shelterForm = document.getElementById('shelterForm');
    const shelterModalTitle = document.getElementById('shelterModalTitle');
    const closeShelterModalBtn = document.getElementById('closeShelterModal');
    const cancelShelterModalBtn = document.getElementById('cancelShelterModal');
    const modalAmphoe = document.getElementById('modalAmphoe');
    const modalTambon = document.getElementById('modalTambon');
    const updateAmountModal = document.getElementById('updateAmountModal');
    const updateAmountForm = document.getElementById('updateAmountForm');
    const updateAmountModalTitle = document.getElementById('updateAmountModalTitle');
    const updateAmountModalSubtitle = document.getElementById('updateAmountModalSubtitle');
    const occupantUpdateView = document.getElementById('occupantUpdateView');
    const donationUpdateView = document.getElementById('donationUpdateView');
    const closeUpdateAmountModalBtn = document.getElementById('closeUpdateAmountModal');
    const cancelUpdateAmountModalBtn = document.getElementById('cancelUpdateAmountModal');
    const hospitalReportModal = document.getElementById('hospitalReportModal');
    const hospitalReportForm = document.getElementById('hospitalReportForm');
    const closeHospitalReportModalBtn = document.getElementById('closeHospitalReportModal');
    const API_URL = 'pages/shelters.php';
    const sisaketData = {"กันทรลักษ์": ["กระแชง", "กุดเสลา", "ขนุน", "ชำ", "จานใหญ่", "ตระกาจ", "น้ำอ้อม", "บักดอง", "พราน", "ภูเงิน", "ภูผาหมอก", "เมือง", "เมืองคง", "ละลาย", "สังเม็ก", "สวนกล้วย", "เสาธงชัย", "หนองหว้า", "หนองหญ้าลาด", "เวียงเหนือ"], "กันทรารมย์": ["คำเนียม", "จาน", "ดูน", "ทาม", "บัวน้อย", "ผักแพว", "เมืองแคน", "เมืองน้อย", "ยาง", "ละทาย", "หนองบัว", "หนองหัวช้าง", "หนองแวง", "หนองแก้ว", "หนองไอ้คูน", "อีปาด"], "ขุขันธ์": ["กฤษณา", "กันทรอม", "จะกง", "ใจดี", "ดองกำเม็ด", "ตาอุด", "นิคมพัฒนา", "ปราสาท", "ปรือใหญ่", "ยางชุมพัฒนา", "ศรีตระกูล", "สะเดาใหญ่", "สำโรงตาเจ็น", "โสน", "หนองฉลอง", "หนองสองห้อง", "หัวเสือ", "ห้วยเหนือ", "ห้วยใต้", "ห้วยสำราญ", "โคกเพชร", "ลมศักดิ์"], "ขุนหาญ": ["กันทรอม", "กระหวัน", "ดินอุดม", "บักดอง", "พราน", "ไพร", "ภูฝ้าย", "สิ", "ห้วยจันทร์", "โนนสูง", "โพธิ์กระสังข์", "โพธิ์วงศ์"], "น้ำเกลี้ยง": ["คูบ", "เขิน", "ตองปิด", "น้ำเกลี้ยง", "รุ่งระวี", "ละเอาะ"], "โนนคูณ": ["บก", "โพธิ์", "เหล่ากวาง", "หนองกุง", "โนนค้อ"], "บึงบูรพ์": ["บึงบูรพ์", "เป๊าะ"], "เบญจลักษ์": ["ท่าคล้อ", "หนองงูเหลือม", "หนองหว้า", "หนองฮาง", "เสียว"], "ปรางค์กู่": ["กู่", "ดู่", "ตูม", "พิมาย", "พิมายเหนือ", "สวาย", "สมอ", "สำโรงปราสาท", "หนองเชียงทูน", "โพธิ์ศรี"], "พยุห์": ["ตำแย", "พยุห์", "พรหมสวัสดิ์", "หนองค้า", "โนนเพ็ก"], "ไพรบึง": ["แข้", "ดินแดง", "ไพรบึง", "ปราสาทเยอ", "สุขสวัสดิ์", "สำโรงพลัน"], "โพธิ์ศรีสุวรรณ": ["โดด", "ผือใหญ่", "หนองม้า", "อีเซ", "เสียว"], "ภูสิงห์": ["โคกตาล", "ดงรัก", "ตะเคียนราม", "ภูสิงห์", "ละลม", "ห้วยตึ๊กชู", "ห้วยตามอญ"], "เมืองจันทร์": ["ตาโกน", "เมืองจันทร์", "หนองใหญ่"], "เมืองศรีสะเกษ": ["คูซอด", "จาน", "ซำ", "ตะดอบ", "ทุ่ม", "น้ำคำ", "เมืองเหนือ", "เมืองใต้", "โพนข่า", "โพนค้อ", "โพนเขวา", "โพนเพ็ค", "หนองครก", "หนองค้า", "หนองไผ่", "หนองแก้ว", "หญ้าปล้อง"], "ยางชุมน้อย": ["กุดเมืองฮาม", "คอนกาม", "ขี้เหล็ก", "โนนคูณ", "บึงบอน", "ยางชุมน้อย", "ยางชุมใหญ่", "ลิ้นฟ้า"], "ราษีไศล": ["ด่าน", "ดู่", "บัวหุ่ง", "ไผ่", "สร้างปี่", "เมืองคง", "เมืองแคน", "ส้มป่อย", "หนองแค", "หนองหมี", "หนองหลวง", "หนองอึ่ง", "หว้านคำ"], "วังหิน": ["ดวนใหญ่", "ทุ่งสว่าง", "ธาตุ", "บ่อแก้ว", "บุสูง", "วังหิน", "ศรีสำราญ", "โพนยาง"], "ศรีรัตนะ": ["ตูม", "พิงพวย", "ศรีแก้ว", "ศรีโนนงาม", "สระเยาว์", "สะพุง", "เสื่องข้าว"], "ศิลาลาด": ["กุง", "คลีกลิ้ง", "โจดม่วง", "หนองบัวดง"], "ห้วยทับทัน": ["กล้วยกว้าง", "จานแสนไชย", "ปราสาท", "ผักไหม", "เมืองหลวง", "ห้วยทับทัน"], "อุทุมพรพิสัย": ["กำแพง", "แขม", "แข้", "ขะยูง", "โคกจาน", "โคกหล่าม", "ตาเกษ", "แต้", "ทุ่งไชย", "บก", "ปะอาว", "โพธิ์ชัย", "เมืองจันทร์", "ลิ้นฟ้า", "สระกำแพงใหญ่", "สำโรง", "หนองห้าง", "หนองไฮ", "อุทุมพรพิสัย", "อีหล่ำ"]};
    const amphoes = Object.keys(sisaketData).sort((a,b) => a.localeCompare(b, 'th'));
    const TYPE_STYLES = {
        'ศูนย์พักพิง':    { icon: 'home',         color: 'blue' },
        'ศูนย์รับบริจาค': { icon: 'package',      color: 'purple' },
        'รพ.สต.':         { icon: 'heart',        color: 'teal' },
        'โรงพยาบาล':     { icon: 'cross',        color: 'pink' },
        'โรงครัวพระราชทาน': { icon: 'utensils-crossed', color: 'orange' }
    };
    const showAlert = (icon, title, text = '') => Swal.fire({ icon, title, text, confirmButtonColor: '#2563EB' });
    
    // --- Functions ---
    function populateAmphoeDropdowns() {
        const amphoeFilter = document.getElementById('amphoeFilter');
        if (amphoeFilter) {
            amphoeFilter.innerHTML = '<option value="">ทุกอำเภอ</option>';
            amphoes.forEach(amphoe => amphoeFilter.add(new Option(amphoe, amphoe)));
        }
        modalAmphoe.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
        amphoes.forEach(amphoe => modalAmphoe.add(new Option(amphoe, amphoe)));
    }

    function populateTambonDropdown(amphoeName, selectElement) {
        if (!selectElement) return;
        selectElement.innerHTML = '';
        selectElement.disabled = true;
        const defaultOption = selectElement.id === 'tambonFilter' ? 'ทุกตำบล' : '-- เลือกตำบล --';
        selectElement.add(new Option(defaultOption, ''));
        if (amphoeName && sisaketData[amphoeName]) {
            selectElement.disabled = false;
            sisaketData[amphoeName].forEach(tambon => selectElement.add(new Option(tambon, tambon)));
        }
    }

    function render() {
        let filteredShelters = allShelters;
        const searchInput = document.getElementById('searchInput'); // Element for Admin
        if (searchInput) {
            const typeFilter = document.getElementById('typeFilter');
            const amphoeFilter = document.getElementById('amphoeFilter');
            const tambonFilter = document.getElementById('tambonFilter');
            const filters = {
                search: searchInput.value.toLowerCase(),
                type: typeFilter.value,
                amphoe: amphoeFilter.value,
                tambon: tambonFilter.value,
            };
            filteredShelters = allShelters.filter(s => {
                const searchMatch = filters.search === '' || (s.name && s.name.toLowerCase().includes(filters.search)) || (s.coordinator && s.coordinator.toLowerCase().includes(filters.search));
                const typeMatch = filters.type === '' || s.type === filters.type;
                const amphoeMatch = filters.amphoe === '' || s.amphoe === filters.amphoe;
                const tambonMatch = filters.tambon === '' || s.tambon === filters.tambon;
                return searchMatch && typeMatch && amphoeMatch && tambonMatch;
            });
        }
        if (filteredShelters.length === 0) {
            dataContainer.innerHTML = '<p class="text-center text-gray-500 py-12 col-span-full">ไม่พบข้อมูลศูนย์ที่ตรงกับเงื่อนไข</p>';
            return;
        }
        if (currentView === 'grid') { renderGridView(filteredShelters); } else { renderListView(filteredShelters); }
    }

    function renderGridView(shelters) {
        const userRole = "<?= $_SESSION['role'] ?? 'User' ?>";
        dataContainer.innerHTML = shelters.map(s => {
            const style = TYPE_STYLES[s.type] || TYPE_STYLES['ศูนย์พักพิง'];
            let adminButtons = '';
            if (userRole === 'Admin') {
                adminButtons = `<button class="edit-btn text-gray-400 hover:text-blue-600" data-shelter='${JSON.stringify(s)}' title="แก้ไขข้อมูล"><i class="h-5 w-5 pointer-events-none" data-lucide="file-pen-line"></i></button>
                              <button class="delete-btn text-gray-400 hover:text-red-600" data-id="${s.id}" data-name="${s.name}" title="ลบศูนย์"><i class="h-5 w-5 pointer-events-none" data-lucide="trash-2"></i></button>`;
            }

            let updateButtonHTML = (s.type === 'รพ.สต.' || s.type === 'ศูนย์พักพิง') 
                ? `<button class="hospital-report-btn flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold" data-shelter='${JSON.stringify(s)}'><i data-lucide="plus" class="h-4 w-4 mr-2"></i>อัปเดต</button>`
                : `<button class="update-amount-btn flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold" data-shelter='${JSON.stringify(s)}'><i data-lucide="plus" class="h-4 w-4 mr-2"></i>อัปเดต</button>`;

            let manageDropdownHTML = (s.type === 'รพ.สต.' || s.type === 'ศูนย์พักพิง') 
                ? `<div class="relative inline-block text-left">
                        <button type="button" class="manage-data-dropdown-toggle inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            จัดการข้อมูล
                            <i data-lucide="chevron-down" class="ml-2 -mr-1 h-5 w-5"></i>
                        </button>
                        <div class="manage-data-dropdown-menu hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                            <div class="py-1">
                                <a href="#" class="view-current-details-btn text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" data-shelter='${JSON.stringify(s)}'>รายละเอียด</a>
                                <a href="#" class="view-history-btn text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" data-shelter='${JSON.stringify(s)}'>ประวัติ</a>
                            </div>
                        </div>
                   </div>`
                : `<div class="relative inline-block text-left">
                        <button type="button" class="manage-data-dropdown-toggle inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            จัดการข้อมูล
                            <i data-lucide="chevron-down" class="ml-2 -mr-1 h-5 w-5"></i>
                        </button>
                        <div class="manage-data-dropdown-menu hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                            <div class="py-1">
                                <a href="#" class="view-history-btn text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" data-shelter='${JSON.stringify(s)}'>ประวัติ</a>
                            </div>
                        </div>
                   </div>`;

            let phoneButtonHTML = s.phone 
                ? `<a href="tel:${s.phone}" class="inline-flex items-center px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium hover:bg-green-200">
                     <i data-lucide="phone" class="h-3 w-3 mr-1.5"></i>
                     ${s.phone}
                   </a>`
                : `<span>-</span>`;

            return `
            <div class="bg-white rounded-xl shadow-md p-5 flex flex-col hover:shadow-lg transition-shadow">
                <div class="flex-grow">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-${style.color}-100 rounded-lg"><i data-lucide="${style.icon}" class="h-6 w-6 text-${style.color}-600"></i></div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">${s.name}</h3><p class="text-sm text-gray-500">ต.${s.tambon || '-'}, อ.${s.amphoe || '-'}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                           ${adminButtons}
                        </div>
                    </div>
                    <div class="border-t pt-4 mt-4 space-y-2 text-sm">
                        <p><span class="font-medium text-gray-600">ผู้ประสานงาน:</span> ${s.coordinator || '-'}</p>
                        <p class="flex items-center"><span class="font-medium text-gray-600 mr-2">โทร:</span> ${phoneButtonHTML}</p>
                    </div>
                </div>
                <div class="border-t mt-4 pt-4 flex justify-between items-center">
                     <div>
                        <p class="text-sm text-gray-500">${s.type === 'ศูนย์รับบริจาค' ? 'ยอดบริจาค' : 'ผู้เข้าพัก'}</p>
                        <p class="text-2xl font-bold">${s.current_occupancy || 0} <span class="text-base font-normal">${s.type === 'ศูนย์รับบริจาค' ? 'ชิ้น' : ('/ ' + (s.capacity || 0) + ' คน')}</span></p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        ${updateButtonHTML}
                        ${manageDropdownHTML}
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
        if (typeof lucide !== 'undefined') {
            setTimeout(() => lucide.createIcons(), 10);
        }
    }
    
    function renderListView(shelters) {
        dataContainer.classList.remove('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-6');
        const userRole = "<?= $_SESSION['role'] ?? 'User' ?>";
        let adminActions = (s) => userRole === 'Admin' ? `<a href="#" class="edit-btn text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" data-shelter='${JSON.stringify(s)}'>แก้ไข</a><a href="#" class="delete-btn text-red-700 block px-4 py-2 text-sm hover:bg-gray-100" data-id="${s.id}" data-name="${s.name}">ลบ</a>` : '';
        
        dataContainer.innerHTML = `<div class="overflow-x-auto bg-white rounded-xl shadow-md"><table class="min-w-full"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อศูนย์</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ผู้ประสานงาน</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ยอดปัจจุบัน</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">จัดการ</th></tr></thead><tbody class="divide-y divide-gray-200">${shelters.map(s => {
            let updateButtonHTML = (s.type === 'รพ.สต.' || s.type === 'ศูนย์พักพิง') 
                ? `<button class="hospital-report-btn flex items-center text-green-600 hover:text-green-900" data-shelter='${JSON.stringify(s)}'><i data-lucide="plus" class="h-4 w-4 mr-1"></i>อัปเดต</button>`
                : `<button class="update-amount-btn flex items-center text-green-600 hover:text-green-900" data-shelter='${JSON.stringify(s)}'><i data-lucide="plus" class="h-4 w-4 mr-1"></i>อัปเดต</button>`;
            
            let detailsLink = (s.type === 'รพ.สต.' || s.type === 'ศูนย์พักพิง')
                ? `<a href="#" class="view-current-details-btn text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" data-shelter='${JSON.stringify(s)}'>รายละเอียด</a>` : '';
            
            let phoneButtonListHTML = s.phone
                ? `<a href="tel:${s.phone}" class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-medium hover:bg-green-200 ml-2">
                    <i data-lucide="phone" class="h-3 w-3 mr-1"></i>
                    โทร
                  </a>`
                : '';

            return `<tr>
                <td class="px-6 py-4 whitespace-nowrap"><div class="font-medium">${s.name}</div><div class="text-sm text-gray-500">${s.type} | ต.${s.tambon}, อ.${s.amphoe}</div></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><span>${s.coordinator || '-'}</span>${phoneButtonListHTML}</td>
                <td class="px-6 py-4 whitespace-nowrap">${s.current_occupancy || 0}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end gap-4">
                        ${updateButtonHTML}
                        <div class="relative inline-block text-left">
                            <button type="button" class="manage-data-dropdown-toggle inline-flex items-center text-gray-500 hover:text-gray-700">
                                จัดการข้อมูล
                                <i data-lucide="chevron-down" class="ml-1 h-4 w-4"></i>
                            </button>
                            <div class="manage-data-dropdown-menu hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                <div class="py-1">
                                    ${detailsLink}
                                    <a href="#" class="view-history-btn text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" data-shelter='${JSON.stringify(s)}'>ประวัติ</a>
                                    ${adminActions(s)}
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>`;
        }).join('')}</tbody></table></div>`;
        
        if (typeof lucide !== 'undefined') {
            setTimeout(() => lucide.createIcons(), 10);
        }
    }
    
    // --- Main Fetch and Submit Functions ---
    async function mainFetch() {
        try {
            const response = await fetch(`${API_URL}?api=get_shelters`);
            const result = await response.json();
            if (result.status === 'success') {
                allShelters = result.data;
                render();
            } else { showAlert('error', 'เกิดข้อผิดพลาด', result.message); }
        } catch (error) { console.error('Fetch error:', error); showAlert('error', 'การเชื่อมต่อล้มเหลว', 'ไม่สามารถดึงข้อมูลจากเซิร์ฟเวอร์ได้'); }
    }
    async function submitForm(url, data) {
         try {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            const result = await response.json();
            if (result.status === 'success') {
                mainFetch(); return { success: true, message: result.message };
            } else { return { success: false, message: result.message }; }
        } catch (error) { console.error('Submit error:', error); return { success: false, message: 'การเชื่อมต่อล้มเหลว' }; }
    }

    // --- Modal Functions ---
    function openUpdateModal(shelter) {
        updateAmountForm.reset();
        document.getElementById('updateShelterId').value = shelter.id;
        const isDonationCenter = shelter.type === 'ศูนย์รับบริจาค';
        updateAmountModalTitle.textContent = isDonationCenter ? 'อัปเดตยอดบริจาค' : 'อัปเดตยอดผู้เข้าพัก';
        updateAmountModalSubtitle.textContent = `สำหรับศูนย์: ${shelter.name}`;
        const occupantAmountInput = document.getElementById('occupantAmount');
        const itemNameInput = document.getElementById('itemName');
        const donationAmountInput = document.getElementById('donationAmount');
        const itemUnitInput = document.getElementById('itemUnit');
        if (isDonationCenter) {
            occupantUpdateView.classList.add('hidden'); donationUpdateView.classList.remove('hidden'); occupantAmountInput.required = false; itemNameInput.required = true; donationAmountInput.required = true; itemUnitInput.required = true;
        } else {
            occupantUpdateView.classList.remove('hidden'); donationUpdateView.classList.add('hidden'); occupantAmountInput.required = true; itemNameInput.required = false; donationAmountInput.required = false; itemUnitInput.required = false; occupantAmountInput.value = 0;
        }
        updateAmountModal.classList.remove('hidden');
    }

    // --- START: Updated Hospital Report Modal Logic ---
    function setupFormCalculation(form) {
        const totalInput = form.querySelector('#totalPatients');
        const maleInput = form.querySelector('#malePatients');
        const femaleInput = form.querySelector('#femalePatients');

        const calculateTotal = () => {
            const male = parseInt(maleInput.value) || 0;
            const female = parseInt(femaleInput.value) || 0;
            if (male > 0 || female > 0) {
                totalInput.value = male + female;
            }
        };
        
        maleInput.addEventListener('input', calculateTotal);
        femaleInput.addEventListener('input', calculateTotal);
    }

    async function openHospitalReportModal(shelter) {
        const modal = document.getElementById('hospitalReportModal');
        const form = document.getElementById('hospitalReportForm');
        form.reset();

        document.getElementById('hospitalShelterId').value = shelter.id;
        document.getElementById('hospitalReportDate').value = new Date().toISOString().split('T')[0];
        
        const isHospital = shelter.type === 'รพ.สต.';
        modal.querySelector('#hospitalReportModalTitle').textContent = isHospital 
            ? `เพิ่ม/ลดจำนวนผู้ป่วย - ${shelter.name}` 
            : `เพิ่ม/ลดจำนวนผู้เข้าพัก - ${shelter.name}`;
        
        // Reset to step 1
        modal.querySelector('#formStep1').classList.remove('hidden');
        modal.querySelector('#formStep2').classList.add('hidden');
        modal.querySelector('#op_add').checked = false;
        modal.querySelector('#op_subtract').checked = false;
        modal.querySelector('#nextStepBtn').disabled = true;

        setupFormCalculation(form);
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        if (typeof lucide !== 'undefined') {
            setTimeout(() => lucide.createIcons(), 50);
        }
    }
    
    // Moved Accordion logic to a more reliable place
    document.getElementById('hospitalReportModal').addEventListener('click', function(e) {
        const accordionToggle = e.target.closest('.accordion-toggle');
        if (accordionToggle) {
            const content = accordionToggle.nextElementSibling;
            const wasActive = accordionToggle.classList.contains('active');

            // Close all accordions in this modal first
            this.querySelectorAll('.accordion-toggle').forEach(otherToggle => {
                otherToggle.classList.remove('active');
                if (otherToggle.nextElementSibling) {
                   otherToggle.nextElementSibling.style.maxHeight = null;
                }
            });

            // If the clicked one wasn't active, open it
            if (!wasActive && content) {
                accordionToggle.classList.add('active');
                content.style.maxHeight = content.scrollHeight + "px";
            }
        }
    });


    hospitalReportForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());

        const totalPatients = parseInt(data.total_patients) || 0;
        const malePatients = parseInt(data.male_patients) || 0;
        const femalePatients = parseInt(data.female_patients) || 0;
        const pregnantWomen = parseInt(data.pregnant_women) || 0;

        if (totalPatients === 0) {
            showAlert('warning', 'กรุณากรอกข้อมูล', 'จำนวนที่เปลี่ยนแปลงต้องไม่เป็น 0');
            return;
        }
        if (malePatients + femalePatients > 0 && malePatients + femalePatients !== totalPatients) {
            showAlert('error', 'ข้อมูลไม่ถูกต้อง', 'ยอดรวมของชายและหญิงไม่ตรงกับจำนวนรวมที่กรอก');
            return;
        }
        if (pregnantWomen > femalePatients) {
            showAlert('error', 'ข้อมูลไม่ถูกต้อง', 'จำนวนหญิงตั้งครรภ์ต้องไม่เกินจำนวนผู้เข้าพัก/ผู้ป่วยหญิงทั้งหมด');
            return;
        }

        // START: New validation for subgroups total
        const subGroupKeys = [
            'pregnant_women', 'disabled_patients', 'bedridden_patients', 'elderly_patients', 'child_patients',
            'chronic_disease_patients', 'diabetes_patients', 'hypertension_patients', 'heart_disease_patients',
            'mental_health_patients', 'kidney_disease_patients', 'other_monitored_diseases'
        ];
        
        const subGroupsTotal = subGroupKeys.reduce((sum, key) => {
            return sum + (parseInt(data[key]) || 0);
        }, 0);

        if (subGroupsTotal > totalPatients) {
            showAlert('error', 'ข้อมูลไม่ถูกต้อง', `ยอดรวมในกลุ่มย่อย (${subGroupsTotal}) ต้องไม่เกินจำนวนรวมทั้งหมด (${totalPatients})`);
            return;
        }
        // END: New validation for subgroups total
        
        const operationText = data.operation_type === 'add' ? 'เพิ่ม' : 'ลด';
        const operationColor = data.operation_type === 'add' ? 'text-red-700' : 'text-blue-700';

        let confirmationHtml = `<div class='text-left space-y-2'>
            <p><strong>การดำเนินการ:</strong> <span class='font-bold ${operationColor}'>${operationText}</span></p>
            <p><strong>จำนวนรวม:</strong> ${totalPatients} คน</p>`;

        const details = [];
        if (malePatients > 0) details.push(`ชาย: ${malePatients}`);
        if (femalePatients > 0) details.push(`หญิง: ${femalePatients}`);
        if (pregnantWomen > 0) details.push(`ตั้งครรภ์: ${pregnantWomen}`);
        if (data.disabled_patients > 0) details.push(`ผู้พิการ: ${data.disabled_patients}`);
        if (data.bedridden_patients > 0) details.push(`ติดเตียง: ${data.bedridden_patients}`);
        if (data.elderly_patients > 0) details.push(`สูงอายุ: ${data.elderly_patients}`);
        if (data.child_patients > 0) details.push(`เด็ก: ${data.child_patients}`);
        if (data.chronic_disease_patients > 0) details.push(`โรคเรื้อรัง: ${data.chronic_disease_patients}`);
        if (data.diabetes_patients > 0) details.push(`เบาหวาน: ${data.diabetes_patients}`);
        if (data.hypertension_patients > 0) details.push(`ความดันสูง: ${data.hypertension_patients}`);
        if (data.heart_disease_patients > 0) details.push(`โรคหัวใจ: ${data.heart_disease_patients}`);
        if (data.mental_health_patients > 0) details.push(`จิตเวช: ${data.mental_health_patients}`);
        if (data.kidney_disease_patients > 0) details.push(`ฟอกไต: ${data.kidney_disease_patients}`);
        if (data.other_monitored_diseases > 0) details.push(`เฝ้าระวังอื่นๆ: ${data.other_monitored_diseases}`);

        if (details.length > 0) {
            confirmationHtml += `<p><strong>รายละเอียด:</strong> ${details.join(', ')}</p>`;
        }
        confirmationHtml += `</div>`;

        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล?',
            html: confirmationHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'กำลังบันทึก...',
                    text: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading() }
                });

                try {
                    const response = await fetch(`${API_URL}?api=save_hospital_report`, { method: 'POST', body: formData });
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        hospitalReportModal.classList.add('hidden');
                        await Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: result.message, timer: 1500, showConfirmButton: false });
                        mainFetch();
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error saving hospital report:', error);
                    Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                }
            }
        });
    });
    
    // Listen to radio button changes to enable Next button
    const opRadios = document.querySelectorAll('input[name="operation_type"]');
    opRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            document.getElementById('nextStepBtn').disabled = false;
        });
    });

    // --- END: Updated Hospital Report Modal Logic ---
    
    // History & Details Modal Functions...
    async function openHistoryModal(shelter) {
        const historyModal = document.getElementById('historyModal');
        const historyShelterName = document.getElementById('historyShelterName');
        const historyPerPage = document.getElementById('historyPerPage');
        
        window.currentHistoryShelter = shelter;
        window.currentHistoryPage = 1;
        
        historyShelterName.textContent = shelter.name;
        
        historyModal.classList.remove('hidden');
        historyModal.classList.add('flex');
        
        await loadHistoryPage(1, parseInt(historyPerPage.value));
        
        setupHistoryPaginationListeners();
    }
    async function loadHistoryPage(page = 1, limit = 10) {
        const shelter = window.currentHistoryShelter;
        const historyLoadingIndicator = document.getElementById('historyLoadingIndicator');
        const historyTableBody = document.getElementById('historyTableBody');
        const noHistoryMessage = document.getElementById('noHistoryMessage');
        const historyPagination = document.getElementById('historyPagination');
        const historyPaginationInfo = document.getElementById('historyPaginationInfo');
        
        historyLoadingIndicator.classList.remove('hidden');
        historyTableBody.innerHTML = '';
        noHistoryMessage.classList.add('hidden');
        historyPagination.classList.add('hidden');
        
        try {
            const response = await fetch(`${API_URL}?api=get_shelter_logs&shelter_id=${shelter.id}&page=${page}&limit=${limit}`);
            const result = await response.json();
            
            historyLoadingIndicator.classList.add('hidden');
            
            if (result.status === 'success') {
                const logs = result.data.logs;
                const pagination = result.data.pagination;
                
                window.currentHistoryPage = page;
                
                if (pagination.total_records > 0) {
                    historyPaginationInfo.textContent = `แสดง ${pagination.showing_from}-${pagination.showing_to} จาก ${pagination.total_records} รายการ`;
                } else {
                    historyPaginationInfo.textContent = 'ไม่มีรายการ';
                }
                
                if (logs.length === 0) {
                    noHistoryMessage.classList.remove('hidden');
                } else {
                    historyTableBody.innerHTML = logs.map(log => {
                        const date = new Date(log.created_at);
                        const formattedDate = date.toLocaleDateString('th-TH') + ' ' + date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                        const changeText = log.log_type === 'add' ? `+${log.change_amount}` : `-${log.change_amount}`;
                        const changeClass = log.log_type === 'add' ? 'text-green-600' : 'text-red-600';
                        
                        return `
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-sm text-gray-900">${formattedDate}</td>
                                <td class="px-3 py-2 text-sm text-gray-900">${log.item_name || 'ผู้เข้าพัก'}</td>
                                <td class="px-3 py-2 text-sm text-gray-500">${log.item_unit || 'คน'}</td>
                                <td class="px-3 py-2 text-sm font-medium ${changeClass}">${changeText}</td>
                                <td class="px-3 py-2 text-sm text-gray-900">${log.new_total}</td>
                            </tr>
                        `;
                    }).join('');
                    
                    if (pagination.total_pages > 1) {
                        updateHistoryPaginationControls(pagination);
                        historyPagination.classList.remove('hidden');
                    }
                }
            } else {
                noHistoryMessage.classList.remove('hidden');
                showAlert('error', 'เกิดข้อผิดพลาด', result.message);
            }
        } catch (error) {
            console.error('Error fetching shelter logs:', error);
            historyLoadingIndicator.classList.add('hidden');
            noHistoryMessage.classList.remove('hidden');
            showAlert('error', 'เกิดข้อผิดพลาด', 'ไม่สามารถดึงข้อมูลประวัติได้');
        }
    }
    function updateHistoryPaginationControls(pagination) {
        const historyShowingFrom = document.getElementById('historyShowingFrom');
        const historyShowingTo = document.getElementById('historyShowingTo');
        const historyTotalRecords = document.getElementById('historyTotalRecords');
        const historyPageNumbers = document.getElementById('historyPageNumbers');
        const historyPrevMobile = document.getElementById('historyPrevMobile');
        const historyNextMobile = document.getElementById('historyNextMobile');
        
        historyShowingFrom.textContent = pagination.showing_from;
        historyShowingTo.textContent = pagination.showing_to;
        historyTotalRecords.textContent = pagination.total_records;
        
        historyPrevMobile.disabled = !pagination.has_prev;
        historyNextMobile.disabled = !pagination.has_next;
        
        let paginationHTML = '';
        
        if (pagination.has_prev) {
            paginationHTML += `<button class="history-page-btn relative inline-flex items-center px-1.5 py-1.5 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" data-page="${pagination.current_page - 1}"><i class="h-3 w-3" data-lucide="chevron-left"></i></button>`;
        } else {
            paginationHTML += `<button class="relative inline-flex items-center px-1.5 py-1.5 rounded-l-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-300 cursor-not-allowed" disabled><i class="h-3 w-3" data-lucide="chevron-left"></i></button>`;
        }
        
        const maxPages = 7;
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxPages / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxPages - 1);
        
        if (endPage - startPage + 1 < maxPages) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }
        
        if (startPage > 1) {
            paginationHTML += `<button class="history-page-btn relative inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50" data-page="1">1</button>`;
            if (startPage > 2) {
                paginationHTML += `<span class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === pagination.current_page) {
                paginationHTML += `<button class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600 cursor-default">${i}</button>`;
            } else {
                paginationHTML += `<button class="history-page-btn relative inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50" data-page="${i}">${i}</button>`;
            }
        }
        
        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                paginationHTML += `<span class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>`;
            }
            paginationHTML += `<button class="history-page-btn relative inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50" data-page="${pagination.total_pages}">${pagination.total_pages}</button>`;
        }
        
        if (pagination.has_next) {
            paginationHTML += `<button class="history-page-btn relative inline-flex items-center px-1.5 py-1.5 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" data-page="${pagination.current_page + 1}"><i class="h-3 w-3" data-lucide="chevron-right"></i></button>`;
        } else {
            paginationHTML += `<button class="relative inline-flex items-center px-1.5 py-1.5 rounded-r-md border border-gray-300 bg-gray-50 text-sm font-medium text-gray-300 cursor-not-allowed" disabled><i class="h-3 w-3" data-lucide="chevron-right"></i></button>`;
        }
        
        historyPageNumbers.innerHTML = paginationHTML;
        
        if (typeof lucide !== 'undefined') {
            setTimeout(() => lucide.createIcons(), 10);
        }
    }
    function setupHistoryPaginationListeners() {
        const historyPerPage = document.getElementById('historyPerPage');
        const historyPrevMobile = document.getElementById('historyPrevMobile');
        const historyNextMobile = document.getElementById('historyNextMobile');
        const historyPageNumbers = document.getElementById('historyPageNumbers');
        
        historyPerPage.addEventListener('change', async (e) => {
            await loadHistoryPage(1, parseInt(e.target.value));
        });
        historyPrevMobile.addEventListener('click', async () => {
            if (window.currentHistoryPage > 1) {
                await loadHistoryPage(window.currentHistoryPage - 1, parseInt(historyPerPage.value));
            }
        });
        historyNextMobile.addEventListener('click', async () => {
            await loadHistoryPage(window.currentHistoryPage + 1, parseInt(historyPerPage.value));
        });
        historyPageNumbers.addEventListener('click', async (e) => {
            const pageBtn = e.target.closest('.history-page-btn');
            if (pageBtn) {
                const page = parseInt(pageBtn.dataset.page);
                if (page && page !== window.currentHistoryPage) {
                    await loadHistoryPage(page, parseInt(historyPerPage.value));
                }
            }
        });
    }
    async function openCurrentDetailsModal(shelter) {
        const currentDetailsModal = document.getElementById('currentDetailsModal');
        const currentDetailsShelterName = document.getElementById('currentDetailsShelterName');
        const currentDetailsShelterType = document.getElementById('currentDetailsShelterType');
        const currentDetailsLoadingIndicator = document.getElementById('currentDetailsLoadingIndicator');
        const currentDetailsContent = document.getElementById('currentDetailsContent');
        const noDetailsMessage = document.getElementById('noDetailsMessage');
        
        currentDetailsShelterName.textContent = shelter.name;
        currentDetailsShelterType.textContent = shelter.type;
        
        currentDetailsModal.classList.remove('hidden');
        currentDetailsModal.classList.add('flex');
        
        currentDetailsLoadingIndicator.classList.remove('hidden');
        currentDetailsContent.classList.add('hidden');
        noDetailsMessage.classList.add('hidden');
        
        try {
            const response = await fetch(`${API_URL}?api=get_current_details&shelter_id=${shelter.id}`);
            const result = await response.json();
            
            currentDetailsLoadingIndicator.classList.add('hidden');
            
            if (result.status === 'success') {
                const detailsData = result.data.details;
                
                if (detailsData) {
                    const isHospital = shelter.type === 'รพ.สต.';
                    document.getElementById('currentTotalLabel').textContent = isHospital ? 'ผู้ป่วยรวม' : 'ผู้เข้าพักรวม';
                    document.getElementById('currentTotalCount').textContent = detailsData.total_patients || 0;
                    document.getElementById('currentMaleCount').textContent = detailsData.male_patients || 0;
                    document.getElementById('currentFemaleCount').textContent = detailsData.female_patients || 0;
                    document.getElementById('currentPregnantCount').textContent = detailsData.pregnant_women || 0;
                    document.getElementById('currentDisabledCount').textContent = detailsData.disabled_patients || 0;
                    document.getElementById('currentBedriddenCount').textContent = detailsData.bedridden_patients || 0;
                    document.getElementById('currentElderlyCount').textContent = detailsData.elderly_patients || 0;
                    document.getElementById('currentChildCount').textContent = detailsData.child_patients || 0;
                    document.getElementById('currentChronicCount').textContent = detailsData.chronic_disease_patients || 0;
                    document.getElementById('currentDiabetesCount').textContent = detailsData.diabetes_patients || 0;
                    document.getElementById('currentHypertensionCount').textContent = detailsData.hypertension_patients || 0;
                    document.getElementById('currentHeartCount').textContent = detailsData.heart_disease_patients || 0;
                    document.getElementById('currentMentalCount').textContent = detailsData.mental_health_patients || 0;
                    document.getElementById('currentKidneyCount').textContent = detailsData.kidney_disease_patients || 0;
                    document.getElementById('currentOtherCount').textContent = detailsData.other_monitored_diseases || 0;
                    
                    if (detailsData.report_date) {
                        document.getElementById('currentReportDate').textContent = new Date(detailsData.report_date).toLocaleDateString('th-TH');
                    } else {
                        document.getElementById('currentReportDate').textContent = '-';
                    }
                    
                    if (detailsData.updated_at) {
                        const updateTime = new Date(detailsData.updated_at);
                        document.getElementById('currentUpdateTime').textContent = updateTime.toLocaleDateString('th-TH') + ' ' + updateTime.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                    } else if (detailsData.created_at) {
                        const createTime = new Date(detailsData.created_at);
                        document.getElementById('currentUpdateTime').textContent = createTime.toLocaleDateString('th-TH') + ' ' + createTime.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                    } else {
                        document.getElementById('currentUpdateTime').textContent = '-';
                    }
                    
                    currentDetailsContent.classList.remove('hidden');
                } else {
                    noDetailsMessage.classList.remove('hidden');
                }
            } else {
                noDetailsMessage.classList.remove('hidden');
                showAlert('error', 'เกิดข้อผิดพลาด', result.message);
            }
        } catch (error) {
            console.error('Error fetching current details:', error);
            currentDetailsLoadingIndicator.classList.add('hidden');
            noDetailsMessage.classList.remove('hidden');
            showAlert('error', 'เกิดข้อผิดพลาด', 'ไม่สามารถดึงข้อมูลรายละเอียดได้');
        }
    }


    // --- Event Listeners ---
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const openDropdowns = document.querySelectorAll('.manage-data-dropdown-menu:not(.hidden)');
        openDropdowns.forEach(dropdown => {
            if (!dropdown.parentElement.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    });

    dataContainer.addEventListener('click', e => {
        const dropdownToggle = e.target.closest('.manage-data-dropdown-toggle');
        if (dropdownToggle) {
            e.preventDefault();
            const menu = dropdownToggle.nextElementSibling;
            document.querySelectorAll('.manage-data-dropdown-menu').forEach(m => {
                if (m !== menu) m.classList.add('hidden');
            });
            menu.classList.toggle('hidden');
        }

        const editBtn = e.target.closest('.edit-btn');
        const deleteBtn = e.target.closest('.delete-btn');
        const updateAmountBtn = e.target.closest('.update-amount-btn');
        const hospitalReportBtn = e.target.closest('.hospital-report-btn');
        const viewHistoryBtn = e.target.closest('.view-history-btn');
        const viewCurrentDetailsBtn = e.target.closest('.view-current-details-btn');
        
        if (editBtn) {
            const shelterData = JSON.parse(editBtn.dataset.shelter);
            openShelterModal(shelterData);
        }
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const name = deleteBtn.dataset.name;
            Swal.fire({ title: 'ยืนยันการลบ?', text: `คุณแน่ใจหรือไม่ว่าต้องการลบ "${name}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3B82F6', confirmButtonText: 'ใช่, ลบเลย!', cancelButtonText: 'ยกเลิก' }).then(async (result) => { if (result.isConfirmed) { const res = await submitForm(`${API_URL}?api=delete_shelter`, { id }); showAlert(res.success ? 'success' : 'error', res.message); } });
        }
        if (updateAmountBtn) { const shelterData = JSON.parse(updateAmountBtn.dataset.shelter); openUpdateModal(shelterData); }
        if (hospitalReportBtn) { const shelterData = JSON.parse(hospitalReportBtn.dataset.shelter); openHospitalReportModal(shelterData); }
        if (viewHistoryBtn) { const shelterData = JSON.parse(viewHistoryBtn.dataset.shelter); openHistoryModal(shelterData); }
        if (viewCurrentDetailsBtn) { const shelterData = JSON.parse(viewCurrentDetailsBtn.dataset.shelter); openCurrentDetailsModal(shelterData); }
    });
    modalAmphoe.addEventListener('change', () => populateTambonDropdown(modalAmphoe.value, modalTambon));
    closeShelterModalBtn.addEventListener('click', () => shelterModal.classList.add('hidden'));
    cancelShelterModalBtn.addEventListener('click', () => shelterModal.classList.add('hidden'));
    shelterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target).entries());
        const url = data.id ? `${API_URL}?api=edit_shelter` : `${API_URL}?api=add_shelter`;
        const result = await submitForm(url, data);
        if (result.success) { shelterModal.classList.add('hidden'); showAlert('success', result.message); } else { showAlert('error', 'เกิดข้อผิดพลาด', result.message); }
    });
    
    // Hospital Report Modal Step Buttons
    const nextStepBtn = document.getElementById('nextStepBtn');
    if(nextStepBtn) {
        nextStepBtn.addEventListener('click', () => {
            document.getElementById('formStep1').classList.add('hidden');
            document.getElementById('formStep2').classList.remove('hidden');
        });
    }

    const prevStepBtn = document.getElementById('prevStepBtn');
    if(prevStepBtn) {
        prevStepBtn.addEventListener('click', () => {
            document.getElementById('formStep2').classList.add('hidden');
            document.getElementById('formStep1').classList.remove('hidden');
        });
    }

    const cancelStep1Btn = document.getElementById('cancelHospitalReportModalStep1');
    if(cancelStep1Btn) cancelStep1Btn.addEventListener('click', () => hospitalReportModal.classList.add('hidden'));


    closeUpdateAmountModalBtn.addEventListener('click', () => updateAmountModal.classList.add('hidden'));
    cancelUpdateAmountModalBtn.addEventListener('click', () => updateAmountModal.classList.add('hidden'));
    closeHospitalReportModalBtn.addEventListener('click', () => hospitalReportModal.classList.add('hidden'));
    
    const historyModal = document.getElementById('historyModal');
    if(historyModal) {
        document.getElementById('closeHistoryModal').addEventListener('click', () => historyModal.classList.add('hidden'));
        document.getElementById('closeHistoryModalBtn').addEventListener('click', () => historyModal.classList.add('hidden'));
    }
    
    const currentDetailsModal = document.getElementById('currentDetailsModal');
    if(currentDetailsModal) {
        document.getElementById('closeCurrentDetailsModal').addEventListener('click', () => currentDetailsModal.classList.add('hidden'));
        document.getElementById('closeCurrentDetailsModalBtn').addEventListener('click', () => currentDetailsModal.classList.add('hidden'));
    }

    updateAmountForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const shelterId = document.getElementById('updateShelterId').value;
        const shelter = allShelters.find(s => s.id == shelterId);
        if (!shelter) return;
        const isDonationCenter = shelter.type === 'ศูนย์รับบริจาค';
        const formData = new FormData(e.target);
        const data = { shelter_id: shelterId, log_type: formData.get('log_type'), change_amount: isDonationCenter ? formData.get('donation_amount') : formData.get('occupant_amount'), item_name: isDonationCenter ? formData.get('item_name') : null, item_unit: isDonationCenter ? formData.get('item_unit') : null, };
        const result = await submitForm(`${API_URL}?api=update_amount`, data);
        if (result.success) { updateAmountModal.classList.add('hidden'); showAlert('success', result.message); } else { showAlert('error', 'เกิดข้อผิดพลาด', result.message); }
    });

    document.getElementById('getCurrentLocation').addEventListener('click', () => { if (!navigator.geolocation) return showAlert('warning', 'ไม่รองรับ'); navigator.geolocation.getCurrentPosition( pos => { shelterForm.elements.latitude.value = pos.coords.latitude.toFixed(6); shelterForm.elements.longitude.value = pos.coords.longitude.toFixed(6); }, () => showAlert('error', 'ไม่สามารถดึงพิกัดได้') ); });

    // --- Admin-only script initialization ---
    const userRole = "<?= $_SESSION['role'] ?? 'User' ?>";
    if (userRole === 'Admin') {
        const importCsvBtn = document.getElementById('importCsvBtn');
        const importModal = document.getElementById('importModal');
        const importForm = document.getElementById('importForm');
        const closeImportModalBtn = document.getElementById('closeImportModal');
        const cancelImportModalBtn = document.getElementById('cancelImportModal');
        const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');

        if (importCsvBtn) {
            importCsvBtn.addEventListener('click', () => {
                importForm.reset();
                importModal.classList.remove('hidden');
                importModal.classList.add('flex');
            });
        }
        if(closeImportModalBtn) closeImportModalBtn.addEventListener('click', () => importModal.classList.add('hidden'));
        if(cancelImportModalBtn) cancelImportModalBtn.addEventListener('click', () => importModal.classList.add('hidden'));

        if(downloadTemplateBtn) {
             downloadTemplateBtn.addEventListener('click', () => {
                const headers = "id,name,type,capacity,coordinator,phone,amphoe,tambon,latitude,longitude";
                const csvContent = "\uFEFF" + headers + "\n"; // BOM for UTF-8 Excel support
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement("a");
                const url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", "template_shelters.csv");
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }

        if(importForm) {
            importForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const csvFileInput = document.getElementById('csvFile');
                if (!csvFileInput.files || csvFileInput.files.length === 0) {
                    showAlert('warning', 'กรุณาเลือกไฟล์', 'คุณยังไม่ได้เลือกไฟล์ CSV ที่จะนำเข้า');
                    return;
                }
    
                const formData = new FormData();
                formData.append('csvFile', csvFileInput.files[0]);
    
                Swal.fire({
                    title: 'กำลังนำเข้าข้อมูล...',
                    text: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
    
                try {
                    const response = await fetch(`${API_URL}?api=import_shelters`, {
                        method: 'POST',
                        body: formData
                    });
    
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        importModal.classList.add('hidden');
                        Swal.fire({
                            icon: 'success',
                            title: 'นำเข้าสำเร็จ!',
                            text: result.message
                        });
                        mainFetch(); 
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'นำเข้าไม่สำเร็จ',
                            text: result.message
                        });
                    }
                } catch (error) {
                     Swal.fire({
                        icon: 'error',
                        title: 'การเชื่อมต่อล้มเหลว',
                        text: 'ไม่สามารถส่งข้อมูลไปยังเซิร์ฟเวอร์ได้'
                    });
                    console.error('Import error:', error);
                }
            });
        }

        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');
        const amphoeFilter = document.getElementById('amphoeFilter');
        const tambonFilter = document.getElementById('tambonFilter');
        const viewGridBtn = document.getElementById('viewGridBtn');
        const viewListBtn = document.getElementById('viewListBtn');
        const addShelterBtn = document.getElementById('addShelterBtn');
        const resetFilterBtn = document.getElementById('resetFilterBtn');
        const exportCsvBtn = document.getElementById('exportCsvBtn');

        if(searchInput && typeFilter && amphoeFilter && tambonFilter && viewGridBtn && viewListBtn && addShelterBtn && resetFilterBtn && exportCsvBtn) {
             [searchInput, typeFilter, amphoeFilter, tambonFilter].forEach(el => el.addEventListener('input', render));
             amphoeFilter.addEventListener('change', () => { populateTambonDropdown(amphoeFilter.value, tambonFilter); render(); });
             resetFilterBtn.addEventListener('click', () => { searchInput.value = ''; typeFilter.value = ''; amphoeFilter.value = ''; populateTambonDropdown('', tambonFilter); render(); });
             viewGridBtn.addEventListener('click', () => { if (currentView === 'list') { currentView = 'grid'; viewGridBtn.classList.add('bg-white', 'shadow'); viewGridBtn.classList.remove('text-gray-500'); viewListBtn.classList.remove('bg-white', 'shadow'); viewListBtn.classList.add('text-gray-500'); dataContainer.classList.add('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-6'); render(); } });
             viewListBtn.addEventListener('click', () => { if (currentView === 'grid') { currentView = 'list'; viewListBtn.classList.add('bg-white', 'shadow'); viewListBtn.classList.remove('text-gray-500'); viewGridBtn.classList.remove('bg-white', 'shadow'); viewGridBtn.classList.add('text-gray-500'); render(); } });
             addShelterBtn.addEventListener('click', () => { shelterModalTitle.textContent = 'เพิ่มศูนย์ช่วยเหลือใหม่'; shelterForm.reset(); populateTambonDropdown('', modalTambon); shelterModal.classList.remove('hidden'); });
             exportCsvBtn.addEventListener('click', () => {
                let filteredData = allShelters;
                const filters = { search: searchInput.value.toLowerCase(), type: typeFilter.value, amphoe: amphoeFilter.value, tambon: tambonFilter.value };
                filteredData = allShelters.filter(s => {
                    const searchMatch = filters.search === '' || (s.name && s.name.toLowerCase().includes(filters.search)) || (s.coordinator && s.coordinator.toLowerCase().includes(filters.search));
                    const typeMatch = filters.type === '' || s.type === filters.type;
                    const amphoeMatch = filters.amphoe === '' || s.amphoe === filters.amphoe;
                    const tambonMatch = filters.tambon === '' || s.tambon === filters.tambon;
                    return searchMatch && typeMatch && amphoeMatch && tambonMatch;
                });

                const headers = ["id", "name", "type", "capacity", "current_occupancy", "coordinator", "phone", "amphoe", "tambon", "latitude", "longitude"];
                const rows = filteredData.map(s => [s.id, s.name, s.type, s.capacity, s.current_occupancy, s.coordinator, s.phone, s.amphoe, s.tambon, s.latitude, s.longitude].map(val => `"${String(val || '').replace(/"/g, '""')}"`));
                let csvContent = "\uFEFF" + headers.join(",") + "\n" + rows.map(e => e.join(",")).join("\n");
                var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                if (link.download !== undefined) {
                    var url = URL.createObjectURL(blob);
                    link.setAttribute("href", url);
                    link.setAttribute("download", "shelters_export.csv");
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            });
        }
    }
    
    // Initial Load
    populateAmphoeDropdowns();
    mainFetch();
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php
// File: pages/users.php
// DESCRIPTION: หน้าสำหรับจัดการผู้ใช้งาน พร้อมระบบค้นหา, กรองข้อมูล, และจัดการคำร้องขอ
// (FIXED: Added exit() to API logic to prevent HTML output on API calls)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- API Logic ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ในการดำเนินการ']);
        exit(); // IMPORTANT: Stop execution
    }
    
    require_once __DIR__ . '/../db_connect.php'; 
    
    if (!isset($conn) || $conn->connect_error) {
        http_response_code(500);
        error_log("Database Connection Failed: " . ($conn->connect_error ?? 'Unknown error'));
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']);
        exit(); // IMPORTANT: Stop execution
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $logged_in_user_id = $_SESSION['user_id'];
    
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        switch ($_GET['api']) {
            case 'get_data':
                $users = [];
                $shelters = [];
                $user_sql = "
                    SELECT u.*, s.name AS shelter_name,
                    GROUP_CONCAT(hs.shelter_id) as health_shelter_ids
                    FROM users u 
                    LEFT JOIN shelters s ON u.assigned_shelter_id = s.id
                    LEFT JOIN healthstaff_shelters hs ON u.id = hs.user_id
                    GROUP BY u.id
                    ORDER BY u.status = 'Pending' DESC, u.created_at DESC
                ";
                $user_result = $conn->query($user_sql);
                while($row = $user_result->fetch_assoc()) {
                    $users[] = $row;
                }
                $shelter_result = $conn->query("SELECT id, name FROM shelters ORDER BY name ASC");
                 while($row = $shelter_result->fetch_assoc()) {
                    $shelters[] = $row;
                }
                echo json_encode(['status' => 'success', 'users' => $users, 'shelters' => $shelters]);
                break;

            case 'add_user':
                $conn->begin_transaction();
                $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $assigned_shelter = ($data['role'] === 'Coordinator' && !empty($data['assigned_shelter_id'])) 
                    ? intval($data['assigned_shelter_id']) 
                    : NULL;
                
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, assigned_shelter_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $data['name'], $data['email'], $password_hash, $data['role'], $data['status'], $assigned_shelter);
                $stmt->execute();
                $new_user_id = $stmt->insert_id;
                
                if ($data['role'] === 'HealthStaff' && !empty($data['multi_shelters'])) {
                    $shelter_stmt = $conn->prepare("INSERT INTO healthstaff_shelters (user_id, shelter_id) VALUES (?, ?)");
                    foreach ($data['multi_shelters'] as $shelter_id) {
                        $shelter_stmt->bind_param("ii", $new_user_id, $shelter_id);
                        $shelter_stmt->execute();
                    }
                    $shelter_stmt->close();
                }
                
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'เพิ่มผู้ใช้งานสำเร็จ']);
                $stmt->close();
                break;

            case 'edit_user':
                $conn->begin_transaction();
                $user_id_to_edit = intval($data['id']);
                if ($user_id_to_edit === $logged_in_user_id) {
                    if ($data['role'] !== 'Admin' || $data['status'] !== 'Active') {
                        throw new Exception('ไม่สามารถเปลี่ยนบทบาทหรือสถานะของบัญชีตัวเองได้');
                    }
                }

                $query_parts = [];
                $params = [];
                $types = '';

                $fields_to_update = ['name', 'email', 'role', 'status'];
                foreach ($fields_to_update as $field) {
                    $query_parts[] = "$field = ?";
                    $params[] = $data[$field];
                    $types .= 's';
                }

                if ($data['role'] === 'Coordinator' && !empty($data['assigned_shelter_id'])) {
                    $query_parts[] = "assigned_shelter_id = ?";
                    $params[] = intval($data['assigned_shelter_id']);
                    $types .= 'i';
                } else {
                    $query_parts[] = "assigned_shelter_id = NULL";
                }
                
                if (!empty($data['password'])) {
                    $query_parts[] = "password = ?";
                    $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
                    $types .= 's';
                }

                $params[] = $user_id_to_edit;
                $types .= 'i';

                $sql = "UPDATE users SET " . implode(', ', $query_parts) . " WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();

                $conn->query("DELETE FROM healthstaff_shelters WHERE user_id = " . $user_id_to_edit);
                if ($data['role'] === 'HealthStaff' && !empty($data['multi_shelters'])) {
                    $shelter_stmt = $conn->prepare("INSERT INTO healthstaff_shelters (user_id, shelter_id) VALUES (?, ?)");
                    foreach ($data['multi_shelters'] as $shelter_id) {
                        $shelter_stmt->bind_param("ii", $user_id_to_edit, $shelter_id);
                        $shelter_stmt->execute();
                    }
                    $shelter_stmt->close();
                }
                
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'แก้ไขข้อมูลสำเร็จ']);
                $stmt->close();
                break;

            case 'delete_user':
                $user_id_to_delete = intval($data['id']);
                if ($user_id_to_delete === $logged_in_user_id) {
                    throw new Exception('ไม่สามารถลบบัญชีของตัวเองได้');
                }
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id_to_delete);
                $stmt->execute();
                echo json_encode(['status' => 'success', 'message' => 'ลบผู้ใช้งานสำเร็จ']);
                $stmt->close();
                break;

            case 'approve_user':
                $user_id_to_approve = intval($data['id']);
                $stmt = $conn->prepare("UPDATE users SET status = 'Active', role = 'Coordinator' WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param("i", $user_id_to_approve);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['status' => 'success', 'message' => 'อนุมัติผู้ใช้งานและเปลี่ยนบทบาทเป็น Coordinator สำเร็จ']);
                } else {
                    throw new Exception('ไม่พบผู้ใช้ที่รออนุมัติ หรืออาจเกิดข้อผิดพลาด');
                }
                $stmt->close();
                break;
            
            case 'get_requests':
                $requests = [];
                $sql = "SELECT sr.*, u.name as user_name, u.email as user_email, s.name as shelter_name 
                        FROM shelter_requests sr
                        JOIN users u ON sr.user_id = u.id
                        LEFT JOIN shelters s ON sr.shelter_id = s.id
                        WHERE sr.status = 'pending'
                        ORDER BY sr.created_at ASC";
                $result = $conn->query($sql);
                while($row = $result->fetch_assoc()) {
                    if ($row['request_type'] === 'create') {
                        $row['new_shelter_data'] = json_decode($row['new_shelter_data'], true);
                    }
                    $requests[] = $row;
                }
                echo json_encode(['status' => 'success', 'data' => $requests]);
                break;

            case 'process_request':
                $request_id = intval($data['request_id']);
                $action = $data['action']; // 'approve' or 'reject'
                $admin_notes = trim($data['admin_notes'] ?? '');

                $conn->begin_transaction();
                
                $req_stmt = $conn->prepare("SELECT * FROM shelter_requests WHERE id = ? AND status = 'pending'");
                $req_stmt->bind_param("i", $request_id);
                $req_stmt->execute();
                $request = $req_stmt->get_result()->fetch_assoc();
                $req_stmt->close();

                if (!$request) {
                    throw new Exception('ไม่พบคำร้องขอ หรือคำร้องนี้ถูกจัดการไปแล้ว');
                }

                $user_id_to_process = $request['user_id'];
                $verification_code = null;

                if ($action === 'approve') {
                    if ($request['request_type'] === 'create') {
                        $new_data = json_decode($request['new_shelter_data'], true);
                        $insert_shelter = $conn->prepare("INSERT INTO shelters (name, type) VALUES (?, ?)");
                        $insert_shelter->bind_param("ss", $new_data['name'], $new_data['type']);
                        $insert_shelter->execute();
                        $new_shelter_id = $conn->insert_id;
                        $insert_shelter->close();

                        $verification_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        $update_user = $conn->prepare("UPDATE users SET assigned_shelter_id = ?, verification_code = ?, is_verified = 0 WHERE id = ?");
                        $update_user->bind_param("isi", $new_shelter_id, $verification_code, $user_id_to_process);
                        $update_user->execute();
                        $update_user->close();
                    }
                    
                    $update_req = $conn->prepare("UPDATE shelter_requests SET status = 'approved', admin_notes = ?, processed_by = ? WHERE id = ?");
                    $update_req->bind_param("sii", $admin_notes, $logged_in_user_id, $request_id);
                    $update_req->execute();
                    $update_req->close();

                } elseif ($action === 'reject') {
                     $update_req = $conn->prepare("UPDATE shelter_requests SET status = 'rejected', admin_notes = ?, processed_by = ? WHERE id = ?");
                     $update_req->bind_param("sii", $admin_notes, $logged_in_user_id, $request_id);
                     $update_req->execute();
                     $update_req->close();
                }

                $update_user_flag = $conn->prepare("UPDATE users SET has_pending_request = 0 WHERE id = ?");
                $update_user_flag->bind_param("i", $user_id_to_process);
                $update_user_flag->execute();
                $update_user_flag->close();
                
                $conn->commit();
                $response = ['status' => 'success', 'message' => 'ดำเนินการตามคำร้องสำเร็จ'];
                if ($verification_code) {
                    $response['verification_code'] = $verification_code;
                }
                echo json_encode($response);
                break;

            default:
                throw new Exception('Invalid API call');
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->in_transaction) {
            $conn->rollback();
        }
        http_response_code(500);
        error_log("API Error in users.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์']);
    } finally {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }
    exit(); // IMPORTANT: Stop script execution for all API calls
}
?>

<div class="space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <h1 class="text-3xl font-bold text-gray-800">จัดการผู้ใช้งาน</h1>
        <div class="flex items-center gap-2">
            <button id="manageRequestsBtn" class="bg-yellow-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-yellow-600 flex items-center gap-2 relative">
                <i data-lucide="mail-question"></i><span>จัดการคำขอ</span>
                <span id="request-count-badge" class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
            </button>
            <button id="addUserBtn" class="bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-indigo-700 flex items-center gap-2">
                <i data-lucide="plus"></i><span>เพิ่มผู้ใช้งาน</span>
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-xl shadow-md">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="md:col-span-1">
                <label for="searchInput" class="text-sm font-medium text-gray-700">ค้นหา</label>
                <div class="relative mt-1">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400"></i>
                    <input type="text" id="searchInput" placeholder="ชื่อ หรือ อีเมล..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div>
                <label for="roleFilter" class="text-sm font-medium text-gray-700">บทบาท</label>
                <select id="roleFilter" class="w-full mt-1 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">ทุกบทบาท</option>
                    <option value="Admin">ผู้ดูแลระบบ</option>
                    <option value="Coordinator">เจ้าหน้าที่ประสานศูนย์</option>
                    <option value="HealthStaff">เจ้าหน้าที่สาธารณสุข</option>
                    <option value="User">ผู้ใช้ทั่วไป</option>
                </select>
            </div>
             <div>
                <label for="statusFilter" class="text-sm font-medium text-gray-700">สถานะ</label>
                <select id="statusFilter" class="w-full mt-1 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">ทุกสถานะ</option>
                    <option value="Active">เปิดใช้งาน</option>
                    <option value="Inactive">ปิดใช้งาน</option>
                    <option value="Pending">รออนุมัติ</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-md overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อ-สกุล</th>
                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">อีเมล</th>
                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">บทบาท</th>
                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ศูนย์ที่รับผิดชอบ</th>
                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">สถานะ</th>
                   <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                </tr>
            </thead>
            <tbody id="userTableBody" class="divide-y divide-gray-200">
                <tr><td colspan="6" class="text-center p-8 text-gray-500">กำลังโหลดข้อมูล...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals -->
<div id="userModal" class="fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-8 border w-full max-w-lg shadow-lg rounded-2xl bg-white">
        <button id="closeUserModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
        <h3 id="userModalTitle" class="text-2xl leading-6 font-bold text-gray-900 mb-6"></h3>
        <form id="userForm" class="space-y-4">
            <input type="hidden" id="userId" name="id">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">ชื่อ-สกุล</label>
                <input type="text" id="name" name="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
                <input type="email" id="email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยน">
            </div>
            <div class="grid grid-cols-2 gap-4">
                 <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">บทบาท</label>
                    <select id="role" name="role" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        <option value="Admin">ผู้ดูแลระบบ</option>
                        <option value="Coordinator">เจ้าหน้าที่ประสานศูนย์</option>
                        <option value="HealthStaff">เจ้าหน้าที่สาธารณสุข</option>
                        <option value="User">ผู้ใช้ทั่วไป</option>
                    </select>
                </div>
                 <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">สถานะ</label>
                    <select id="status" name="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        <option value="Active">เปิดใช้งาน</option>
                        <option value="Inactive">ปิดใช้งาน</option>
                        <option value="Pending">รอการอนุมัติ</option>
                    </select>
                </div>
            </div>
            <div id="shelterAssignmentContainer" class="hidden">
                <label for="assigned_shelter_id" class="block text-sm font-medium text-gray-700">ศูนย์ที่รับผิดชอบ</label>
                <select id="assigned_shelter_id" name="assigned_shelter_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">-- ไม่กำหนด --</option>
                </select>
            </div>
            <div id="multiShelterContainer" class="hidden">
                <label class="block text-sm font-medium text-gray-700">ศูนย์ที่รับผิดชอบ (เลือกได้หลายศูนย์)</label>
                <p class="select-tooltip">กด Ctrl (Windows) หรือ Command (Mac) เพื่อเลือกหลายรายการ</p>
                <select id="multi_shelters" name="multi_shelters[]" multiple 
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg">
                </select>
                <p class="select-tooltip text-red-600" id="multiShelterError" style="display: none;">
                    กรุณาเลือกอย่างน้อย 1 ศูนย์
                </p>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                 <button type="button" id="cancelUserModal" class="px-6 py-2.5 bg-gray-200 rounded-lg hover:bg-gray-300">ยกเลิก</button>
                 <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700">บันทึก</button>
            </div>
        </form>
    </div>
</div>
<style>
    select[multiple] { height: auto; min-height: 120px; padding: 8px; }
    select[multiple] option { padding: 8px; margin: 2px 0; border-radius: 4px; cursor: pointer; }
    select[multiple] option:checked { background-color: #4f46e5 !important; color: white; }
    .select-tooltip { font-size: 0.75rem; color: #6B7280; margin-top: 0.25rem; }
</style>

<div id="requestsModal" class="fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-6 border w-full max-w-4xl shadow-lg rounded-2xl bg-white">
        <button id="closeRequestsModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
        <h3 class="text-2xl font-bold text-gray-900 mb-4">คำร้องขอที่รอการอนุมัติ</h3>
        <div id="requestsListContainer" class="space-y-4 max-h-[70vh] overflow-y-auto p-2">
            <!-- Requests will be loaded here -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const API_URL = 'index.php?page=users';
    let allUsers = [];
    let allShelters = [];
    const loggedInUserId = <?= $_SESSION['user_id'] ?? 'null' ?>;
    const userTableBody = document.getElementById('userTableBody');
    const addUserBtn = document.getElementById('addUserBtn');
    const userModal = document.getElementById('userModal');
    const userForm = document.getElementById('userForm');
    const userModalTitle = document.getElementById('userModalTitle');
    const roleSelect = document.getElementById('role');
    const shelterAssignmentContainer = document.getElementById('shelterAssignmentContainer');
    const multiShelterContainer = document.getElementById('multiShelterContainer');
    const assignedShelterSelect = document.getElementById('assigned_shelter_id');
    const manageRequestsBtn = document.getElementById('manageRequestsBtn');
    const requestsModal = document.getElementById('requestsModal');
    const closeRequestsModal = document.getElementById('closeRequestsModal');
    const requestsListContainer = document.getElementById('requestsListContainer');
    const requestCountBadge = document.getElementById('request-count-badge');
    
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');

    const showAlert = (icon, title, text = '') => Swal.fire({ icon, title, text, confirmButtonColor: '#4f46e5' });
    const closeUserModal = () => userModal.classList.add('hidden');

    function renderUsers() {
        if (!userTableBody) return;
        const roleDisplay = { 'Admin': 'ผู้ดูแลระบบ', 'Coordinator': 'เจ้าหน้าที่ประสานศูนย์', 'HealthStaff': 'เจ้าหน้าที่สาธารณสุข', 'User': 'ผู้ใช้ทั่วไป' };
        const statusDisplay = {
            'Active': { text: 'เปิดใช้งาน', class: 'bg-green-100 text-green-800' },
            'Inactive': { text: 'ปิดใช้งาน', class: 'bg-red-100 text-red-800' },
            'Pending': { text: 'รออนุมัติ', class: 'bg-yellow-100 text-yellow-800' }
        };

        const searchTerm = searchInput.value.toLowerCase();
        const roleValue = roleFilter.value;
        const statusValue = statusFilter.value;

        const filteredUsers = allUsers.filter(user => {
            const nameMatch = user.name.toLowerCase().includes(searchTerm);
            const emailMatch = user.email.toLowerCase().includes(searchTerm);
            const roleMatch = !roleValue || user.role === roleValue;
            const statusMatch = !statusValue || user.status === statusValue;
            return (nameMatch || emailMatch) && roleMatch && statusMatch;
        });

        if (filteredUsers.length === 0) {
            userTableBody.innerHTML = '<tr><td colspan="6" class="text-center p-8 text-gray-500">ไม่พบข้อมูลผู้ใช้งานที่ตรงกับเงื่อนไข</td></tr>';
            return;
        }

        userTableBody.innerHTML = filteredUsers.map(user => {
            let shelterInfo = '-';
            if (user.role === 'Coordinator') {
                shelterInfo = user.shelter_name || '<span class="text-gray-400">ยังไม่กำหนด</span>';
            } else if (user.role === 'HealthStaff' && user.health_shelter_ids) {
                const shelterIds = user.health_shelter_ids.split(',');
                const shelterNames = shelterIds.map(id => allShelters.find(s => s.id == id)?.name).filter(Boolean);
                shelterInfo = shelterNames.join('<br>') || '<span class="text-gray-400">ยังไม่กำหนด</span>';
            }
            
            const statusInfo = statusDisplay[user.status] || { text: user.status, class: 'bg-gray-100 text-gray-800' };
            let actionButtons = `<button class="edit-btn text-indigo-600 hover:text-indigo-900" data-user='${JSON.stringify(user)}'>แก้ไข</button>
                                 <button class="delete-btn text-red-600 hover:text-red-900 ml-4" data-id="${user.id}" data-name="${user.name}">ลบ</button>`;
            if (user.status === 'Pending') {
                actionButtons = `<button class="approve-btn text-green-600 hover:text-green-900" data-id="${user.id}" data-name="${user.name}">อนุมัติ</button>` + actionButtons;
            }

            return `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">${user.name || ''}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-500">${user.email || ''}</td>
                <td class="px-6 py-4 whitespace-nowrap text-gray-500">${roleDisplay[user.role] || user.role}</td>
                <td class="px-6 py-4 whitespace-normal text-gray-500">${shelterInfo}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusInfo.class}">${statusInfo.text}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">${actionButtons}</td>
            </tr>`;
        }).join('');
    }

    function populateShelterDropdowns() {
        assignedShelterSelect.innerHTML = '<option value="">-- ไม่กำหนด --</option>';
        const multiShelterSelect = document.getElementById('multi_shelters');
        multiShelterSelect.innerHTML = '';
        allShelters.forEach(shelter => {
            assignedShelterSelect.add(new Option(shelter.name, shelter.id));
            multiShelterSelect.add(new Option(shelter.name, shelter.id));
        });
    }

    async function mainFetch() {
        try {
            const response = await fetch(`${API_URL}&api=get_data`);
            const result = await response.json();
            if (result.status === 'success') {
                allUsers = result.users;
                allShelters = result.shelters;
                renderUsers();
                populateShelterDropdowns();
            } else { showAlert('error', 'เกิดข้อผิดพลาด', result.message); }
        } catch (error) { console.error('Fetch error:', error); showAlert('error', 'การเชื่อมต่อล้มเหลว', 'ไม่สามารถดึงข้อมูลจากเซิร์ฟเวอร์ได้'); }
    }

    async function submitForm(url, data) {
        if (data.role === 'HealthStaff') {
            const multiSelect = document.getElementById('multi_shelters');
            data.multi_shelters = Array.from(multiSelect.selectedOptions).map(opt => opt.value);
        }
        try {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            const result = await response.json();
            if (result.status === 'success') {
                mainFetch();
                return { success: true, message: result.message, verification_code: result.verification_code };
            } else { return { success: false, message: result.message }; }
        } catch (error) { console.error('Submit error:', error); return { success: false, message: 'การเชื่อมต่อล้มเหลว' }; }
    }

    function openUserModal(user = null) {
        userForm.reset();
        shelterAssignmentContainer.classList.add('hidden');
        multiShelterContainer.classList.add('hidden');
        populateShelterDropdowns();
        if (user) {
            userModalTitle.textContent = 'แก้ไขข้อมูลผู้ใช้งาน';
            const { password, ...userData } = user;
            Object.keys(userData).forEach(key => {
                const input = userForm.elements[key];
                if (input) {
                    if (key === 'assigned_shelter_id') { assignedShelterSelect.value = userData[key] || ''; } 
                    else if (key === 'health_shelter_ids' && userData[key]) {
                        const selectedIds = userData[key].split(',');
                        const multiSelect = document.getElementById('multi_shelters');
                        Array.from(multiSelect.options).forEach(option => {
                            option.selected = selectedIds.includes(option.value);
                        });
                    } else { input.value = userData[key]; }
                }
            });
            userForm.elements.password.value = '';
            userForm.elements.password.placeholder = "เว้นว่างไว้หากไม่ต้องการเปลี่ยน";
            userForm.elements.password.required = false;
            if (user.role === 'Coordinator') { shelterAssignmentContainer.classList.remove('hidden'); }
            if (user.role === 'HealthStaff') { multiShelterContainer.classList.remove('hidden'); }
            if (user.id == loggedInUserId) {
                document.getElementById('role').disabled = true;
                document.getElementById('status').disabled = true;
            } else {
                document.getElementById('role').disabled = false;
                document.getElementById('status').disabled = false;
            }
        } else {
            userModalTitle.textContent = 'เพิ่มผู้ใช้งานใหม่';
            userForm.elements.password.placeholder = "";
            userForm.elements.password.required = true;
            document.getElementById('role').disabled = false;
            document.getElementById('status').disabled = false;
        }
        userModal.classList.remove('hidden');
    }

    async function loadRequests() {
        const response = await fetch(`${API_URL}&api=get_requests`);
        const result = await response.json();
        if (result.status === 'success') {
            const requests = result.data;
            requestCountBadge.textContent = requests.length;
            requestCountBadge.classList.toggle('hidden', requests.length === 0);
            renderRequests(requests);
        }
    }

    function renderRequests(requests) {
        if (requests.length === 0) {
            requestsListContainer.innerHTML = '<p class="text-center text-gray-500 py-8">ไม่มีคำร้องขอที่รออนุมัติ</p>';
            return;
        }
        requestsListContainer.innerHTML = requests.map(req => {
            let detailHtml = '';
            if (req.request_type === 'assign') {
                detailHtml = `<p><strong>ขอเข้าร่วมศูนย์:</strong> ${req.shelter_name || `(ID: ${req.shelter_id})`}</p>`;
            } else {
                detailHtml = `<p><strong>ขอสร้างศูนย์ใหม่:</strong></p>
                              <ul class="list-disc list-inside pl-4 text-sm">
                                <li><strong>ชื่อ:</strong> ${req.new_shelter_data.name}</li>
                                <li><strong>ประเภท:</strong> ${req.new_shelter_data.type}</li>
                              </ul>`;
            }
            return `<div class="bg-gray-50 p-4 rounded-lg border">
                    <div class="flex flex-wrap justify-between items-start gap-4">
                        <div>
                            <p><strong>ผู้ร้องขอ:</strong> ${req.user_name} (${req.user_email})</p>
                            <div class="mt-2 text-sm">${detailHtml}</div>
                            <p class="text-sm text-gray-500 mt-1"><strong>หมายเหตุจากผู้ใช้:</strong> ${req.user_notes || '-'}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button class="approve-request-btn bg-green-500 text-white px-3 py-1 rounded-lg text-sm" data-id="${req.id}">อนุมัติ</button>
                            <button class="reject-request-btn bg-red-500 text-white px-3 py-1 rounded-lg text-sm" data-id="${req.id}">ปฏิเสธ</button>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    async function processRequest(id, action) {
        let admin_notes = '';
        if (action === 'reject') {
            const { value: notes } = await Swal.fire({
                title: 'กรอกเหตุผลที่ปฏิเสธ (ไม่บังคับ)', input: 'textarea', showCancelButton: true,
                confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก'
            });
            if (notes === undefined) return;
            admin_notes = notes;
        }
        const response = await fetch(`${API_URL}&api=process_request`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ request_id: id, action: action, admin_notes: admin_notes })
        });
        const result = await response.json();
        if (result.status === 'success') {
            if (result.verification_code) {
                Swal.fire({
                    icon: 'success',
                    title: 'อนุมัติสำเร็จ!',
                    html: `${result.message}<br><br><strong>รหัสยืนยันสำหรับผู้ใช้คือ:</strong><br><div style="font-size: 1.5rem; font-weight: bold; letter-spacing: 2px; margin-top: 8px; padding: 8px; background-color: #f0f0f0; border-radius: 8px;">${result.verification_code}</div><br>กรุณาส่งรหัสนี้ให้ผู้ใช้เพื่อยืนยันตัวตน`
                });
            } else {
                Swal.fire('สำเร็จ!', result.message, 'success');
            }
            loadRequests(); 
            mainFetch();
        } else { 
            Swal.fire('ผิดพลาด!', result.message, 'error'); 
        }
    }

    addUserBtn.addEventListener('click', () => openUserModal());
    document.getElementById('closeUserModal').addEventListener('click', closeUserModal);
    document.getElementById('cancelUserModal').addEventListener('click', closeUserModal);
    roleSelect.addEventListener('change', () => {
        shelterAssignmentContainer.classList.toggle('hidden', roleSelect.value !== 'Coordinator');
        multiShelterContainer.classList.toggle('hidden', roleSelect.value !== 'HealthStaff');
    });

    userTableBody.addEventListener('click', e => {
        if (e.target.classList.contains('edit-btn')) { openUserModal(JSON.parse(e.target.dataset.user)); }
        if (e.target.classList.contains('delete-btn')) {
            const { id, name } = e.target.dataset;
            if (id == loggedInUserId) { showAlert('error', 'ไม่สามารถลบตัวเองได้'); return; }
            Swal.fire({ title: 'ยืนยันการลบ?', text: `คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ "${name}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6b7280', confirmButtonText: 'ใช่, ลบเลย!', cancelButtonText: 'ยกเลิก'
            }).then(async (result) => { if (result.isConfirmed) { const res = await submitForm(`${API_URL}&api=delete_user`, { id }); showAlert(res.success ? 'success' : 'error', res.message); } });
        }
        if (e.target.classList.contains('approve-btn')) {
            const { id, name } = e.target.dataset;
            Swal.fire({ title: 'ยืนยันการอนุมัติ?', text: `คุณต้องการอนุมัติผู้ใช้ "${name}" และเปลี่ยนบทบาทเป็น Coordinator หรือไม่?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#6b7280', confirmButtonText: 'ใช่, อนุมัติ!', cancelButtonText: 'ยกเลิก'
            }).then(async (result) => { if (result.isConfirmed) { const res = await submitForm(`${API_URL}&api=approve_user`, { id }); showAlert(res.success ? 'success' : 'error', res.message); } });
        }
    });

    userForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target).entries());
        if (data.role === 'HealthStaff') {
            const multiSelect = document.getElementById('multi_shelters');
            if (multiSelect.selectedOptions.length === 0) {
                document.getElementById('multiShelterError').style.display = 'block'; return;
            } else { document.getElementById('multiShelterError').style.display = 'none'; }
        }
        const url = data.id ? `${API_URL}&api=edit_user` : `${API_URL}&api=add_user`;
        const result = await submitForm(url, data);
        if (result.success) { closeUserModal(); showAlert('success', result.message); } 
        else { showAlert('error', 'เกิดข้อผิดพลาด', result.message); }
    });

    manageRequestsBtn.addEventListener('click', () => { requestsModal.classList.remove('hidden'); loadRequests(); });
    closeRequestsModal.addEventListener('click', () => requestsModal.classList.add('hidden'));
    requestsListContainer.addEventListener('click', e => {
        if (e.target.classList.contains('approve-request-btn')) { processRequest(e.target.dataset.id, 'approve'); }
        if (e.target.classList.contains('reject-request-btn')) { processRequest(e.target.dataset.id, 'reject'); }
    });
    
    searchInput.addEventListener('input', renderUsers);
    roleFilter.addEventListener('change', renderUsers);
    statusFilter.addEventListener('change', renderUsers);

    mainFetch();
    loadRequests();
});
</script>

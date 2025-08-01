<?php
// pages/assign_shelter.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- API Logic for this page ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../db_connect.php';

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($_GET['api']) {
        case 'get_shelters':
            $result = $conn->query("SELECT id, name, amphoe, tambon FROM shelters ORDER BY name ASC");
            $shelters = [];
            while($row = $result->fetch_assoc()) {
                $shelters[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $shelters]);
            break;

        case 'submit_request':
            $request_type = $data['request_type'] ?? null;
            
            $conn->begin_transaction();
            try {
                // Check if user already has a pending request
                $check_stmt = $conn->prepare("SELECT id FROM shelter_requests WHERE user_id = ? AND status = 'pending'");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    throw new Exception('คุณมีคำร้องที่รอการอนุมัติอยู่แล้ว');
                }
                $check_stmt->close();

                if ($request_type === 'assign') {
                    $shelter_id = intval($data['shelter_id']);
                    $user_notes = trim($data['user_notes'] ?? '');
                    
                    $stmt = $conn->prepare("INSERT INTO shelter_requests (user_id, request_type, shelter_id, user_notes) VALUES (?, 'assign', ?, ?)");
                    $stmt->bind_param("iis", $user_id, $shelter_id, $user_notes);

                } elseif ($request_type === 'create') {
                    $new_shelter_data = json_encode($data['new_shelter']);
                    $user_notes = trim($data['user_notes'] ?? '');

                    $stmt = $conn->prepare("INSERT INTO shelter_requests (user_id, request_type, new_shelter_data, user_notes) VALUES (?, 'create', ?, ?)");
                    $stmt->bind_param("iss", $user_id, $new_shelter_data, $user_notes);
                } else {
                    throw new Exception('Invalid request type.');
                }

                if (!$stmt->execute()) {
                    throw new Exception('Failed to submit request: ' . $stmt->error);
                }
                $stmt->close();

                // Update user status
                $update_user_stmt = $conn->prepare("UPDATE users SET has_pending_request = 1 WHERE id = ?");
                $update_user_stmt->bind_param("i", $user_id);
                $update_user_stmt->execute();
                $update_user_stmt->close();

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'ส่งคำร้องสำเร็จ! กรุณารอการอนุมัติจากผู้ดูแลระบบ']);

            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;
    }
    $conn->close();
    exit();
}

// --- Page Rendering Logic ---
require_once __DIR__ . '/../db_connect.php';
$user_id = $_SESSION['user_id'];
$pending_request = null;
$stmt = $conn->prepare("SELECT * FROM shelter_requests WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $pending_request = $result->fetch_assoc();
}
$stmt->close();
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <div class="text-center">
            <i data-lucide="building-2" class="mx-auto h-16 w-16 text-indigo-500"></i>
            <h1 class="text-3xl font-bold text-gray-800 mt-4">กำหนดศูนย์ที่รับผิดชอบ</h1>
            <p class="text-gray-500 mt-2">บัญชีของคุณได้รับการอนุมัติแล้ว กรุณาเลือกศูนย์ที่ต้องการเข้าร่วม หรือขอเพิ่มศูนย์ใหม่</p>
        </div>

        <?php if ($pending_request): ?>
            <div class="mt-8 bg-blue-50 border-l-4 border-blue-500 text-blue-800 p-6 rounded-lg">
                <h3 class="font-bold text-lg">สถานะคำร้องของคุณ: <span class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-sm">รอการอนุมัติ</span></h3>
                <p class="mt-2">คุณได้ส่งคำร้องขอเรียบร้อยแล้วเมื่อวันที่ <?= date('d/m/Y H:i', strtotime($pending_request['created_at'])) ?> น.</p>
                <p>กรุณารอการตรวจสอบและอนุมัติจากผู้ดูแลระบบ</p>
            </div>
        <?php else: ?>
            <!-- Tabs -->
            <div class="mt-8">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button id="tab-assign" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">
                            เข้าร่วมศูนย์ที่มีอยู่
                        </button>
                        <button id="tab-create" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                            ขอเพิ่มศูนย์ใหม่
                        </button>
                    </nav>
                </div>

                <!-- Assign Form -->
                <div id="form-assign" class="mt-6">
                    <form id="assignShelterForm" class="space-y-4">
                        <input type="hidden" name="request_type" value="assign">
                        <div>
                            <label for="shelter_id" class="block text-sm font-medium text-gray-700">ค้นหาและเลือกศูนย์</label>
                            <select id="shelter_id" name="shelter_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" required>
                                <option>กำลังโหลด...</option>
                            </select>
                        </div>
                        <div>
                            <label for="assign_notes" class="block text-sm font-medium text-gray-700">หมายเหตุ (ถ้ามี)</label>
                            <textarea id="assign_notes" name="user_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>
                        <div class="pt-2 text-right">
                            <button type="submit" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                ส่งคำขอเข้าร่วม
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Create Form -->
                <div id="form-create" class="mt-6 hidden">
                    <form id="createShelterForm" class="space-y-4">
                         <input type="hidden" name="request_type" value="create">
                         <!-- Fields are similar to admin's add shelter modal -->
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ชื่อศูนย์</label>
                                <input type="text" name="new_shelter[name]" class="mt-1 block w-full rounded-md border-gray-300" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ประเภท</label>
                                <select name="new_shelter[type]" class="mt-1 block w-full rounded-md border-gray-300">
                                   <option>ศูนย์พักพิง</option>
                                   <option>ศูนย์รับบริจาค</option>
                                   <option>รพ.สต.</option>
                                   <option>โรงพยาบาล</option>
                                   <option>โรงครัวพระราชทาน</option>
                                   <option>ศูนย์อพยพสัตว์เลี้ยง</option>
                                </select>
                            </div>
                         </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-700">หมายเหตุถึงผู้ดูแลระบบ</label>
                            <textarea name="user_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>
                         <div class="pt-2 text-right">
                            <button type="submit" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                ส่งคำขอเพิ่มศูนย์
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabAssign = document.getElementById('tab-assign');
    const tabCreate = document.getElementById('tab-create');
    const formAssign = document.getElementById('form-assign');
    const formCreate = document.getElementById('form-create');

    if(tabAssign && tabCreate) {
        tabAssign.addEventListener('click', () => {
            formAssign.classList.remove('hidden');
            formCreate.classList.add('hidden');
            tabAssign.classList.add('border-indigo-500', 'text-indigo-600');
            tabAssign.classList.remove('border-transparent', 'text-gray-500');
            tabCreate.classList.add('border-transparent', 'text-gray-500');
            tabCreate.classList.remove('border-indigo-500', 'text-indigo-600');
        });

        tabCreate.addEventListener('click', () => {
            formCreate.classList.remove('hidden');
            formAssign.classList.add('hidden');
            tabCreate.classList.add('border-indigo-500', 'text-indigo-600');
            tabCreate.classList.remove('border-transparent', 'text-gray-500');
            tabAssign.classList.add('border-transparent', 'text-gray-500');
            tabAssign.classList.remove('border-indigo-500', 'text-indigo-600');
        });
    }

    // Fetch shelters for dropdown
    const shelterSelect = document.getElementById('shelter_id');
    if (shelterSelect) {
        fetch('index.php?page=assign_shelter&api=get_shelters')
            .then(res => res.json())
            .then(result => {
                if (result.status === 'success') {
                    shelterSelect.innerHTML = '<option value="">-- กรุณาเลือกศูนย์ --</option>';
                    result.data.forEach(shelter => {
                        const option = new Option(`${shelter.name} (อ.${shelter.amphoe}, ต.${shelter.tambon})`, shelter.id);
                        shelterSelect.add(option);
                    });
                }
            });
    }

    // Handle form submissions
    const assignForm = document.getElementById('assignShelterForm');
    if (assignForm) {
        assignForm.addEventListener('submit', e => {
            e.preventDefault();
            const formData = new FormData(assignForm);
            const data = Object.fromEntries(formData.entries());
            submitRequest(data);
        });
    }

    const createForm = document.getElementById('createShelterForm');
    if (createForm) {
        createForm.addEventListener('submit', e => {
            e.preventDefault();
            const data = {
                request_type: 'create',
                user_notes: createForm.querySelector('[name="user_notes"]').value,
                new_shelter: {
                    name: createForm.querySelector('[name="new_shelter[name]"]').value,
                    type: createForm.querySelector('[name="new_shelter[type]"]').value,
                }
            };
            submitRequest(data);
        });
    }

    async function submitRequest(data) {
        try {
            const response = await fetch('index.php?page=assign_shelter&api=submit_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.status === 'success') {
                Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: result.message })
                   .then(() => location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message });
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'การเชื่อมต่อล้มเหลว' });
        }
    }
});
</script>

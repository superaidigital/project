<?php // pages/shelters.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- API Logic ---
if (isset($_GET['api'])) {
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
            $shelters = [];
            if ($_SESSION['role'] === 'Coordinator' && isset($_SESSION['assigned_shelter_id'])) {
                // FIXED: Use Prepared Statement for security
                $stmt = $conn->prepare("SELECT * FROM shelters WHERE id = ?");
                $stmt->bind_param("i", $_SESSION['assigned_shelter_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();
            } else {
                $sql = "SELECT * FROM shelters ORDER BY name ASC";
                $result = $conn->query($sql);
            }
            
            while($row = $result->fetch_assoc()) {
                $shelters[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $shelters]);
            break;

        case 'add_shelter':
            if ($_SESSION['role'] !== 'Admin') {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ดำเนินการ']);
                exit();
            }
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
                 echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขศูนย์นี้']);
                 exit();
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
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ดำเนินการ']);
                exit();
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
                 echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์อัปเดตศูนย์นี้']);
                 exit();
             }
            $change_amount = intval($data['change_amount']);
            $log_type = $data['log_type'];
            $item_name = isset($data['item_name']) && !empty(trim($data['item_name'])) ? trim($data['item_name']) : 'ผู้เข้าพัก/ใช้บริการ';
            $item_unit = isset($data['item_unit']) && !empty(trim($data['item_unit'])) ? trim($data['item_unit']) : 'คน';

            $conn->begin_transaction();

            try {
                // FIXED: Use Prepared Statement for maximum security
                $stmt_get = $conn->prepare("SELECT current_occupancy FROM shelters WHERE id = ? FOR UPDATE");
                $stmt_get->bind_param("i", $shelter_id);
                $stmt_get->execute();
                $result = $stmt_get->get_result();
                $shelter = $result->fetch_assoc();
                $stmt_get->close();

                if (!$shelter) {
                    throw new Exception("ไม่พบศูนย์ที่ต้องการอัปเดต");
                }

                $current_total = $shelter['current_occupancy'];
                $new_total = ($log_type == 'add') ? $current_total + $change_amount : $current_total - $change_amount;
                
                $stmt_update = $conn->prepare("UPDATE shelters SET current_occupancy = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $new_total, $shelter_id);
                $stmt_update->execute();
                $stmt_update->close();

                $stmt_log = $conn->prepare("INSERT INTO shelter_logs (shelter_id, item_name, item_unit, change_amount, log_type, new_total) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_log->bind_param("issisi", $shelter_id, $item_name, $item_unit, $change_amount, $log_type, $new_total);
                $stmt_log->execute();
                $stmt_log->close();
                
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'อัปเดตยอดสำเร็จ', 'new_total' => $new_total]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
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

<!-- ======================================================================= -->
<!-- ============================ HTML PART ================================ -->
<!-- ======================================================================= -->

<div class="space-y-6">
    <!-- Page Header -->
    <h1 class="text-3xl font-bold text-gray-800">จัดการข้อมูลศูนย์</h1>

    <!-- Filter and Action Toolbar (Only for Admins) -->
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
                 <button id="exportCsvBtn" class="p-2.5 bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200" title="บันทึกเป็น CSV"><i data-lucide="file-down" class="h-5 w-5"></i></button>
                 <button id="addShelterBtn" class="p-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700" title="เพิ่มศูนย์ใหม่"><i data-lucide="plus" class="h-5 w-5"></i></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Data Display Area -->
    <div id="dataDisplayContainer" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <p class="text-center text-gray-500 py-12 col-span-full">กำลังโหลดข้อมูล...</p>
    </div>
</div>

<!-- ======================================================================= -->
<!-- ============================ MODALS =================================== -->
<!-- ======================================================================= -->

<!-- Shelter Add/Edit Modal -->
<div id="shelterModal" class="fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-8 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
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
                           <option>ศูนย์พักพิง</option><option>ศูนย์รับบริจาค</option><option>รพ.สต.</option><option>โรงพยาบาล</option>
                        </select>
                    </div>
                    <div>
                        <label for="capacity" class="block text-sm font-medium text-gray-700">เป้าหมาย/ความจุ</label>
                        <input type="number" id="capacity" name="capacity" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="500" min="0">
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

<!-- Update Amount Modal -->
<div id="updateAmountModal" class="fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-8 border w-full max-w-lg shadow-lg rounded-2xl bg-white">
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

<!-- ======================================================================= -->
<!-- ============================ SCRIPT =================================== -->
<!-- ======================================================================= -->

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let allShelters = [];
    let currentView = 'grid'; 

    // --- Element References ---
    const dataContainer = document.getElementById('dataDisplayContainer');
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('typeFilter');
    const amphoeFilter = document.getElementById('amphoeFilter');
    const tambonFilter = document.getElementById('tambonFilter');
    const viewGridBtn = document.getElementById('viewGridBtn');
    const viewListBtn = document.getElementById('viewListBtn');
    const addShelterBtn = document.getElementById('addShelterBtn');
    const resetFilterBtn = document.getElementById('resetFilterBtn');
    const exportCsvBtn = document.getElementById('exportCsvBtn');
    
    // Shelter Modal Elements
    const shelterModal = document.getElementById('shelterModal');
    const shelterForm = document.getElementById('shelterForm');
    const shelterModalTitle = document.getElementById('shelterModalTitle');
    const closeShelterModalBtn = document.getElementById('closeShelterModal');
    const cancelShelterModalBtn = document.getElementById('cancelShelterModal');
    const modalAmphoe = document.getElementById('modalAmphoe');
    const modalTambon = document.getElementById('modalTambon');

    // Update Amount Modal Elements
    const updateAmountModal = document.getElementById('updateAmountModal');
    const updateAmountForm = document.getElementById('updateAmountForm');
    const updateAmountModalTitle = document.getElementById('updateAmountModalTitle');
    const updateAmountModalSubtitle = document.getElementById('updateAmountModalSubtitle');
    const occupantUpdateView = document.getElementById('occupantUpdateView');
    const donationUpdateView = document.getElementById('donationUpdateView');
    const closeUpdateAmountModalBtn = document.getElementById('closeUpdateAmountModal');
    const cancelUpdateAmountModalBtn = document.getElementById('cancelUpdateAmountModal');

    // --- Constants and Data ---
    const API_URL = 'pages/shelters.php';
    const sisaketData = {"กันทรลักษ์":["กระแชง","กุดเสลา","ขนุน","ชำ","จานใหญ่","ตระกาจ","น้ำอ้อม","บักดอง","พราน","ภูเงิน","ภูผาหมอก","เมือง","เมืองคง","ละลาย","สังเม็ก","สวนกล้วย","เสาธงชัย","หนองหว้า","หนองหญ้าลาด","เวียงเหนือ"],"กันทรารมย์":["คำเนียม","จาน","ดูน","ทาม","บัวน้อย","ผักแพว","เมืองแคน","เมืองน้อย","ยาง","ละทาย","หนองบัว","หนองหัวช้าง","หนองแวง","หนองแก้ว","หนองไอ้คูน","อีปาด"],"ขุขันธ์":["กฤษณา","กันทรอม","จะกง","ใจดี","ดองกำเม็ด","ตาอุด","นิคมพัฒนา","ปราสาท","ปรือใหญ่","ยางชุมพัฒนา","ศรีตระกูล","สะเดาใหญ่","สำโรงตาเจ็น","โสน","หนองฉลอง","หนองสองห้อง","หัวเสือ","ห้วยเหนือ","ห้วยใต้","ห้วยสำราญ","โคกเพชร","ลมศักดิ์"],"ขุนหาญ":["กันทรอม","กระหวัน","ดินอุดม","บักดอง","พราน","ไพร","ภูฝ้าย","สิ","ห้วยจันทร์","โนนสูง","โพธิ์กระสังข์","โพธิ์วงศ์"],"น้ำเกลี้ยง":["คูบ","เขิน","ตองปิด","น้ำเกลี้ยง","รุ่งระวี","ละเอาะ"],"โนนคูณ":["บก","โพธิ์","เหล่ากวาง","หนองกุง","โนนค้อ"],"บึงบูรพ์":["บึงบูรพ์","เป๊าะ"],"เบญจลักษ์":["ท่าคล้อ","หนองงูเหลือม","หนองหว้า","หนองฮาง","เสียว"],"ปรางค์กู่":["กู่","ดู่","ตูม","พิมาย","พิมายเหนือ","สวาย","สมอ","สำโรงปราสาท","หนองเชียงทูน","โพธิ์ศรี"],"พยุห์":["ตำแย","พยุห์","พรหมสวัสดิ์","หนองค้า","โนนเพ็ก"],"ไพรบึง":["แข้","ดินแดง","ไพรบึง","ปราสาทเยอ","สุขสวัสดิ์","สำโรงพลัน"],"โพธิ์ศรีสุวรรณ":["โดด","ผือใหญ่","หนองม้า","อีเซ","เสียว"],"ภูสิงห์":["โคกตาล","ดงรัก","ตะเคียนราม","ภูสิงห์","ละลม","ห้วยตึ๊กชู","ห้วยตามอญ"],"เมืองจันทร์":["ตาโกน","เมืองจันทร์","หนองใหญ่"],"เมืองศรีสะเกษ":["คูซอด","จาน","ซำ","ตะดอบ","ทุ่ม","น้ำคำ","เมืองเหนือ","เมืองใต้","โพนข่า","โพนค้อ","โพนเขวา","โพนเพ็ค","หนองครก","หนองค้า","หนองไผ่","หนองแก้ว","หญ้าปล้อง"],"ยางชุมน้อย":["กุดเมืองฮาม","คอนกาม","ขี้เหล็ก","โนนคูณ","บึงบอน","ยางชุมน้อย","ยางชุมใหญ่","ลิ้นฟ้า"],"ราษีไศล":["ด่าน","ดู่","บัวหุ่ง","ไผ่","สร้างปี่","เมืองคง","เมืองแคน","ส้มป่อย","หนองแค","หนองหมี","หนองหลวง","หนองอึ่ง","หว้านคำ"],"วังหิน":["ดวนใหญ่","ทุ่งสว่าง","ธาตุ","บ่อแก้ว","บุสูง","วังหิน","ศรีสำราญ","โพนยาง"],"ศรีรัตนะ":["ตูม","พิงพวย","ศรีแก้ว","ศรีโนนงาม","สระเยาว์","สะพุง","เสื่องข้าว"],"ศิลาลาด":["กุง","คลีกลิ้ง","โจดม่วง","หนองบัวดง"],"ห้วยทับทัน":["กล้วยกว้าง","จานแสนไชย","ปราสาท","ผักไหม","เมืองหลวง","ห้วยทับทัน"],"อุทุมพรพิสัย":["กำแพง","แขม","แข้","ขะยูง","โคกจาน","โคกหล่าม","ตาเกษ","แต้","ทุ่งไชย","บก","ปะอาว","โพธิ์ชัย","เมืองจันทร์","ลิ้นฟ้า","สระกำแพงใหญ่","สำโรง","หนองห้าง","หนองไฮ","อุทุมพรพิสัย","อีหล่ำ"]};
    const amphoes = Object.keys(sisaketData).sort((a,b) => a.localeCompare(b, 'th'));
    const TYPE_STYLES = {
        'ศูนย์พักพิง':    { icon: 'home',         color: 'blue' },
        'ศูนย์รับบริจาค': { icon: 'package',      color: 'purple' },
        'รพ.สต.':         { icon: 'plus-square',  color: 'teal' },
        'โรงพยาบาล':     { icon: 'hospital',     color: 'pink' }
    };

    // --- Helper Functions ---
    const showAlert = (icon, title, text = '') => Swal.fire({ icon, title, text, confirmButtonColor: '#2563EB' });
    
    /**
     * FIXED: Escapes HTML special characters in a string to prevent XSS and attribute breaking.
     * This is crucial for safely inserting dynamic data into HTML attributes.
     * @param {string} str The string to escape.
     * @returns {string} The escaped string.
     */
    const escapeHTML = (str) => {
        const p = document.createElement("p");
        p.textContent = str;
        return p.innerHTML.replace(/"/g, '&quot;');
    };

    // --- Rendering Functions ---
    function render() {
        let filteredShelters = allShelters;
        if(searchInput) {
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
        if (currentView === 'grid') { 
            renderGridView(filteredShelters); 
        } else { 
            renderListView(filteredShelters); 
        }
    }

    function renderGridView(shelters) {
        dataContainer.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6';
        const userRole = "<?= $_SESSION['role'] ?? 'User' ?>";
        dataContainer.innerHTML = shelters.map(s => {
            const style = TYPE_STYLES[s.type] || TYPE_STYLES['ศูนย์พักพิง'];
            // FIXED: Use escaped JSON string in a double-quoted attribute for safety.
            const shelterDataAttr = `data-shelter="${escapeHTML(JSON.stringify(s))}"`;

            let actionButtons = `<button class="edit-btn text-gray-400 hover:text-blue-600" ${shelterDataAttr} title="แก้ไขข้อมูล"><i class="h-5 w-5 pointer-events-none" data-lucide="file-pen-line"></i></button>`;
            if (userRole === 'Admin') {
                actionButtons = `<button class="delete-btn text-gray-400 hover:text-red-600" data-id="${s.id}" data-name="${s.name}" title="ลบศูนย์"><i class="h-5 w-5 pointer-events-none" data-lucide="trash-2"></i></button>` + actionButtons;
            }

            return `
            <div class="bg-white rounded-xl shadow-md p-5 flex flex-col hover:shadow-lg transition-shadow">
                <div class="flex-grow">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-${style.color}-100 rounded-lg flex-shrink-0"><i data-lucide="${style.icon}" class="h-6 w-6 text-${style.color}-600"></i></div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">${s.name || 'N/A'}</h3>
                                <p class="text-sm text-gray-500">ต.${s.tambon || '-'}, อ.${s.amphoe || '-'}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                           ${actionButtons}
                        </div>
                    </div>
                    <div class="border-t pt-4 mt-4 space-y-2 text-sm">
                        <p><span class="font-medium text-gray-600">ผู้ประสานงาน:</span> ${s.coordinator || '-'}</p>
                        <p><span class="font-medium text-gray-600">โทร:</span> ${s.phone || '-'}</p>
                    </div>
                </div>
                <div class="border-t mt-4 pt-4 flex justify-between items-center">
                     <div>
                        <p class="text-sm text-gray-500">${s.type === 'ศูนย์รับบริจาค' ? 'ยอดบริจาค' : 'ผู้เข้าพัก'}</p>
                        <p class="text-2xl font-bold">${s.current_occupancy || 0} <span class="text-base font-normal text-gray-600">${s.type === 'ศูนย์รับบริจาค' ? 'ชิ้น' : ('/ ' + (s.capacity || 0) + ' คน')}</span></p>
                    </div>
                    <div class="flex gap-2">
                        <button class="update-amount-btn px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-sm font-semibold" ${shelterDataAttr}>อัปเดตยอด</button>
                    </div>
                </div>
            </div>`;
        }).join('');
        
        // ICON FIX: Re-initialize icons after rendering new HTML.
        lucide.createIcons();
    }

    function renderListView(shelters) {
        dataContainer.className = 'overflow-x-auto bg-white rounded-xl shadow-md';
        const userRole = "<?= $_SESSION['role'] ?? 'User' ?>";
        let actionHeader = userRole === 'Admin' ? '<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">จัดการ</th>' : '';
        
        const tableRows = shelters.map(s => {
            const shelterDataAttr = `data-shelter="${escapeHTML(JSON.stringify(s))}"`;
            let actionCells = `<button class="update-amount-btn text-green-600 hover:text-green-900" ${shelterDataAttr}>อัปเดต</button>
                               <button class="edit-btn text-blue-600 hover:text-blue-900 ml-4" ${shelterDataAttr}>แก้ไข</button>`;
            if (userRole === 'Admin') {
                actionCells += `<button class="delete-btn text-red-600 hover:text-red-900 ml-4" data-id="${s.id}" data-name="${s.name}">ลบ</button>`;
            }
            return `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap"><div class="font-medium">${s.name || ''}</div><div class="text-sm text-gray-500">${s.type || ''}</div></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${s.amphoe || ''} / ${s.tambon || ''}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${s.coordinator || '-'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${s.current_occupancy || 0} / ${s.capacity || 0}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">${actionCells}</td>
            </tr>`;
        }).join('');

        dataContainer.innerHTML = `
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อศูนย์/ประเภท</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">อำเภอ/ตำบล</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ผู้ประสานงาน</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ยอดปัจจุบัน/ความจุ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">${tableRows}</tbody>
        </table>`;
        
        // ICON FIX: Re-initialize icons after rendering new HTML.
        lucide.createIcons();
    }

    // --- Data Fetching and Submission ---
    async function mainFetch() {
        try {
            const response = await fetch(`${API_URL}?api=get_shelters`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.status === 'success') {
                allShelters = result.data;
                render();
            } else { 
                showAlert('error', 'เกิดข้อผิดพลาด', result.message); 
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showAlert('error', 'การเชื่อมต่อล้มเหลว', 'ไม่สามารถดึงข้อมูลจากเซิร์ฟเวอร์ได้');
        }
    }

    async function submitForm(url, data) {
         try {
            const response = await fetch(url, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify(data) 
            });
            const result = await response.json();
            if (result.status === 'success') {
                mainFetch(); // Refresh data on success
                return { success: true, message: result.message };
            } else {
                return { success: false, message: result.message };
            }
        } catch (error) {
            console.error('Submit error:', error);
            return { success: false, message: 'การเชื่อมต่อล้มเหลว ไม่สามารถส่งข้อมูลได้' };
        }
    }

    // --- Modal Handling ---
    function openShelterModal(shelter = null) {
        shelterForm.reset();
        if (shelter) { // Editing existing shelter
            shelterModalTitle.textContent = 'แก้ไขข้อมูลศูนย์';
            Object.keys(shelter).forEach(key => {
                const input = shelterForm.elements[key];
                if (input) input.value = shelter[key] || '';
            });
            populateTambonDropdown(shelter.amphoe, modalTambon);
            modalTambon.value = shelter.tambon || '';
        } else { // Adding new shelter
            shelterModalTitle.textContent = 'เพิ่มศูนย์ช่วยเหลือใหม่';
            populateTambonDropdown('', modalTambon);
        }
        shelterModal.classList.remove('hidden');
        // ICON FIX: Re-initialize icons inside the modal.
        lucide.createIcons();
    }

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
            occupantUpdateView.classList.add('hidden');
            donationUpdateView.classList.remove('hidden');
            occupantAmountInput.required = false;
            itemNameInput.required = true;
            donationAmountInput.required = true;
            itemUnitInput.required = true;
        } else {
            occupantUpdateView.classList.remove('hidden');
            donationUpdateView.classList.add('hidden');
            occupantAmountInput.required = true;
            itemNameInput.required = false;
            donationAmountInput.required = false;
            itemUnitInput.required = false;
            occupantAmountInput.value = 1; // Default to 1 for convenience
        }
        updateAmountModal.classList.remove('hidden');
        // ICON FIX: Re-initialize icons inside the modal.
        lucide.createIcons();
    }

    function populateAmphoeDropdowns() {
        if(amphoeFilter) amphoeFilter.innerHTML = '<option value="">ทุกอำเภอ</option>';
        modalAmphoe.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
        amphoes.forEach(amphoe => {
            if(amphoeFilter) amphoeFilter.add(new Option(amphoe, amphoe));
            modalAmphoe.add(new Option(amphoe, amphoe));
        });
    }

    function populateTambonDropdown(amphoeName, selectElement) {
        selectElement.innerHTML = '';
        selectElement.disabled = true;
        const defaultOption = selectElement.id === 'tambonFilter' ? 'ทุกตำบล' : '-- เลือกตำบล --';
        selectElement.add(new Option(defaultOption, ''));
        if (amphoeName && sisaketData[amphoeName]) {
            selectElement.disabled = false;
            sisaketData[amphoeName].forEach(tambon => selectElement.add(new Option(tambon, tambon)));
        }
    }

    // --- Event Listeners ---
    // Toolbar listeners (for Admins)
    if (searchInput) {
        [searchInput, typeFilter, amphoeFilter, tambonFilter].forEach(el => el.addEventListener('input', render));
        amphoeFilter.addEventListener('change', () => { 
            populateTambonDropdown(amphoeFilter.value, tambonFilter); 
            render(); 
        });
        resetFilterBtn.addEventListener('click', () => {
            searchInput.value = '';
            typeFilter.value = '';
            amphoeFilter.value = '';
            populateTambonDropdown('', tambonFilter);
            render();
        });
        viewGridBtn.addEventListener('click', () => {
            if (currentView === 'list') {
                currentView = 'grid';
                viewGridBtn.classList.add('bg-white', 'shadow');
                viewGridBtn.classList.remove('text-gray-500');
                viewListBtn.classList.remove('bg-white', 'shadow');
                viewListBtn.classList.add('text-gray-500');
                render();
            }
        });
        viewListBtn.addEventListener('click', () => {
            if (currentView === 'grid') {
                currentView = 'list';
                viewListBtn.classList.add('bg-white', 'shadow');
                viewListBtn.classList.remove('text-gray-500');
                viewGridBtn.classList.remove('bg-white', 'shadow');
                viewGridBtn.classList.add('text-gray-500');
                render();
            }
        });
        addShelterBtn.addEventListener('click', () => openShelterModal());
    }

    // Event delegation for dynamically created buttons
    dataContainer.addEventListener('click', e => {
        const editBtn = e.target.closest('.edit-btn');
        const deleteBtn = e.target.closest('.delete-btn');
        const updateAmountBtn = e.target.closest('.update-amount-btn');
        
        if (editBtn) {
            const shelterData = JSON.parse(editBtn.dataset.shelter);
            openShelterModal(shelterData);
        }
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const name = deleteBtn.dataset.name;
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: `คุณแน่ใจหรือไม่ว่าต้องการลบ "${name}"? การกระทำนี้ไม่สามารถย้อนกลับได้`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3B82F6',
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const res = await submitForm(`${API_URL}?api=delete_shelter`, { id });
                    showAlert(res.success ? 'success' : 'error', res.success ? 'ลบข้อมูลสำเร็จ' : res.message);
                }
            });
        }
        if (updateAmountBtn) {
            const shelterData = JSON.parse(updateAmountBtn.dataset.shelter);
            openUpdateModal(shelterData);
        }
    });

    // Shelter Modal Listeners
    modalAmphoe.addEventListener('change', () => populateTambonDropdown(modalAmphoe.value, modalTambon));
    closeShelterModal.addEventListener('click', () => shelterModal.classList.add('hidden'));
    cancelShelterModal.addEventListener('click', () => shelterModal.classList.add('hidden'));
    shelterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target).entries());
        const url = data.id ? `${API_URL}?api=edit_shelter` : `${API_URL}?api=add_shelter`;
        const result = await submitForm(url, data);
        if (result.success) {
            shelterModal.classList.add('hidden');
            showAlert('success', result.message);
        } else {
            showAlert('error', 'เกิดข้อผิดพลาด', result.message);
        }
    });

    // Update Amount Modal Listeners
    closeUpdateAmountModal.addEventListener('click', () => updateAmountModal.classList.add('hidden'));
    cancelUpdateAmountModal.addEventListener('click', () => updateAmountModal.classList.add('hidden'));
    updateAmountForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = {
            shelter_id: formData.get('shelter_id'),
            log_type: formData.get('log_type'),
            change_amount: formData.get('donation_amount') || formData.get('occupant_amount'),
            item_name: formData.get('item_name'),
            item_unit: formData.get('item_unit'),
        };
        const result = await submitForm(`${API_URL}?api=update_amount`, data);
        if (result.success) {
            updateAmountModal.classList.add('hidden');
            showAlert('success', result.message);
        } else {
            showAlert('error', 'เกิดข้อผิดพลาด', result.message);
        }
    });

    // Geolocation Listener
    document.getElementById('getCurrentLocation').addEventListener('click', () => {
        if (!navigator.geolocation) {
            return showAlert('warning', 'Geolocation ไม่รองรับในเบราว์เซอร์นี้');
        }
        navigator.geolocation.getCurrentPosition(
            pos => {
                shelterForm.elements.latitude.value = pos.coords.latitude.toFixed(6);
                shelterForm.elements.longitude.value = pos.coords.longitude.toFixed(6);
            },
            () => showAlert('error', 'ไม่สามารถดึงพิกัดได้', 'กรุณาตรวจสอบการอนุญาตให้เข้าถึงตำแหน่ง')
        );
    });

    // CSV Export Listener
    if(exportCsvBtn) {
        exportCsvBtn.addEventListener('click', () => {
            // This part uses the already filtered data from the 'render' function's logic.
            let filteredData = allShelters;
             if(searchInput) {
                const filters = { search: searchInput.value.toLowerCase(), type: typeFilter.value, amphoe: amphoeFilter.value, tambon: tambonFilter.value };
                filteredData = allShelters.filter(s => {
                    const searchMatch = filters.search === '' || (s.name && s.name.toLowerCase().includes(filters.search)) || (s.coordinator && s.coordinator.toLowerCase().includes(filters.search));
                    const typeMatch = filters.type === '' || s.type === filters.type;
                    const amphoeMatch = filters.amphoe === '' || s.amphoe === filters.amphoe;
                    const tambonMatch = filters.tambon === '' || s.tambon === filters.tambon;
                    return searchMatch && typeMatch && amphoeMatch && tambonMatch;
                });
            }
            if (filteredData.length === 0) {
                return showAlert('warning', 'ไม่มีข้อมูล', 'ไม่พบข้อมูลสำหรับส่งออก');
            }
            const headers = ["ID", "ชื่อศูนย์", "ประเภท", "ความจุ", "ยอดปัจจุบัน", "ผู้ประสานงาน", "เบอร์โทร", "อำเภอ", "ตำบล", "ละติจูด", "ลองจิจูด"];
            const rows = filteredData.map(s => 
                [s.id, s.name, s.type, s.capacity, s.current_occupancy, s.coordinator, s.phone, s.amphoe, s.tambon, s.latitude, s.longitude]
                .map(val => `"${String(val || '').replace(/"/g, '""')}"`)
            );
            
            let csvContent = "\uFEFF" + headers.join(",") + "\n" + rows.map(e => e.join(",")).join("\n");
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", `shelters_export_${new Date().toISOString().slice(0,10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // --- Initial Load ---
    populateAmphoeDropdowns();
    mainFetch();
});
</script>

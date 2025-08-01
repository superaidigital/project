<?php
// File: pages/reports.php (แสดงสีพิกัดแยกประเภท)
// DESCRIPTION: โค้ดสำหรับหน้าแสดงรายงาน (รวม API และส่วนแสดงผล)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- API Logic ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            throw new Exception('Unauthorized', 401);
        }
        
        require_once __DIR__ . '/../db_connect.php';

        if (!$conn || $conn->connect_error) {
            throw new Exception('Database connection failed', 500);
        }

        $response = ['status' => 'error', 'message' => 'Invalid API call'];
        $api_action = $_GET['api'];

        switch ($api_action) {
            
            case 'get_summary':
                $summary_sql = "
                    WITH DailyChanges AS (
                        SELECT 
                            s.amphoe, 
                            SUM(IF(sl.log_type = 'add', sl.change_amount, -sl.change_amount)) as daily_change
                        FROM shelter_logs sl
                        JOIN shelters s ON sl.shelter_id = s.id
                        WHERE DATE(sl.created_at) = CURDATE()
                        GROUP BY s.amphoe
                    )
                    SELECT
                        s.amphoe,
                        COUNT(s.id) AS shelter_count,
                        SUM(s.current_occupancy) AS total_occupancy,
                        COALESCE(dc.daily_change, 0) AS daily_change
                    FROM shelters s
                    LEFT JOIN DailyChanges dc ON s.amphoe = dc.amphoe
                    WHERE s.amphoe IS NOT NULL AND s.amphoe != '' AND s.type NOT IN ('ศูนย์รับบริจาค', 'โรงครัวพระราชทาน')
                    GROUP BY s.amphoe
                    ORDER BY s.amphoe;
                ";
                $result = $conn->query($summary_sql);
                if (!$result) throw new Exception('Query failed: ' . $conn->error);
                
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $response = ['status' => 'success', 'data' => $data];
                break;

            case 'get_all_shelters_for_map':
                $map_sql = "
                    SELECT
                        s.id, s.name, s.type, s.latitude, s.longitude, s.current_occupancy, s.amphoe, s.tambon, s.phone
                    FROM shelters s
                    WHERE s.latitude IS NOT NULL AND s.latitude != '' AND s.longitude IS NOT NULL AND s.longitude != ''
                ";
                $result = $conn->query($map_sql);
                if (!$result) throw new Exception("Query failed: " . $conn->error);

                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $row['latitude'] = floatval($row['latitude']);
                    $row['longitude'] = floatval($row['longitude']);
                    $row['current_occupancy'] = intval($row['current_occupancy']);
                    $data[] = $row;
                }
                $response = ['status' => 'success', 'data' => $data];
                break;

            case 'get_district_detail':
                if (empty($_GET['amphoe'])) {
                    throw new Exception("District not specified", 400);
                }
                $amphoe = trim($_GET['amphoe']);
                $detail_sql = "
                    SELECT
                        s.id, s.name, s.type, s.tambon, s.current_occupancy,
                        (SELECT COALESCE(SUM(IF(sl.log_type = 'add', sl.change_amount, -sl.change_amount)), 0)
                         FROM shelter_logs sl
                         WHERE sl.shelter_id = s.id AND DATE(sl.created_at) = CURDATE()
                        ) AS daily_change
                    FROM shelters s
                    WHERE s.amphoe = ?
                    ORDER BY s.name;
                ";
                $stmt = $conn->prepare($detail_sql);
                $stmt->bind_param("s", $amphoe);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                $response = ['status' => 'success', 'data' => $data];
                break;
                
            case 'get_shelter_details':
                if (empty($_GET['shelter_id'])) {
                    throw new Exception("Shelter ID not specified", 400);
                }
                $shelter_id = intval($_GET['shelter_id']);

                $shelter_info_sql = "SELECT * FROM shelters WHERE id = ?";
                $stmt_info = $conn->prepare($shelter_info_sql);
                $stmt_info->bind_param("i", $shelter_id);
                $stmt_info->execute();
                $shelter_info = $stmt_info->get_result()->fetch_assoc();
                $stmt_info->close();

                if (!$shelter_info) {
                     throw new Exception("Shelter not found", 404);
                }
                
                $daily_change_sql = "
                    SELECT COALESCE(SUM(IF(log_type = 'add', change_amount, -sl.change_amount)), 0) AS daily_change
                    FROM shelter_logs sl
                    WHERE sl.shelter_id = ? AND DATE(sl.created_at) = CURDATE()
                ";
                $stmt_change = $conn->prepare($daily_change_sql);
                $stmt_change->bind_param("i", $shelter_id);
                $stmt_change->execute();
                $daily_change_data = $stmt_change->get_result()->fetch_assoc();
                $stmt_change->close();

                $graph_sql = "SELECT report_date, total_patients FROM hospital_daily_reports WHERE shelter_id = ? AND report_date >= CURDATE() - INTERVAL 7 DAY ORDER BY report_date ASC";
                $stmt_graph = $conn->prepare($graph_sql);
                $stmt_graph->bind_param("i", $shelter_id);
                $stmt_graph->execute();
                $graph_data = $stmt_graph->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_graph->close();

                $details_sql = "SELECT * FROM hospital_daily_reports WHERE shelter_id = ? ORDER BY report_date DESC, updated_at DESC LIMIT 1";
                $stmt_details = $conn->prepare($details_sql);
                $stmt_details->bind_param("i", $shelter_id);
                $stmt_details->execute();
                $current_details = $stmt_details->get_result()->fetch_assoc();
                $stmt_details->close();

                $response = [
                    'status' => 'success', 
                    'data' => [
                        'shelterInfo' => $shelter_info,
                        'dailyChange' => $daily_change_data['daily_change'] ?? 0,
                        'graphData' => $graph_data, 
                        'currentDetails' => $current_details
                    ]
                ];
                break;
            default:
                throw new Exception('Invalid API endpoint.', 404);
        }

        echo json_encode($response);

    } catch (Exception $e) {
        $http_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($http_code);
        error_log("API Error in reports.php: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } finally {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }
    exit();
}

// --- Page Rendering Logic ---
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map-view-container.fullscreen { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 9999; margin: 0 !important; }
    #map-view { height: 65vh; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); z-index: 1; background: #f0f0f0; width: 100%; transition: height 0.3s ease-in-out; }
    #map-view-container.fullscreen #map-view { height: 100%; border-radius: 0; }
    .leaflet-popup-content-wrapper { border-radius: 0.5rem; }
    .leaflet-popup-content { font-family: 'Sarabun', sans-serif; }
    .popup-button { background-color: #3b82f6; color: white; padding: 4px 8px; border-radius: 4px; border: none; cursor: pointer; font-weight: bold; display: inline-block; font-size: 12px; text-decoration: none; }
    .popup-button:hover { background-color: #2563eb; }
    .popup-icon-button { display: inline-flex; align-items: center; justify-content: center; padding: 6px; border-radius: 9999px; background-color: #f3f4f6; color: #4b5563; text-decoration: none; transition: background-color 0.2s; }
    .popup-icon-button:hover { background-color: #e5e7eb; }
    .accordion-toggle { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 1rem; font-weight: 600; color: #374151; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.2s; }
    .accordion-toggle:hover { background-color: #f3f4f6; }
    .accordion-toggle.active { background-color: #eef2ff; border-color: #818cf8; }
    .accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; border-radius: 0 0 0.5rem 0.5rem; }
    .chevron-icon { transition: transform 0.3s; }
    .accordion-toggle.active .chevron-icon { transform: rotate(180deg); }
    .legend { line-height: 18px; color: #555; background: rgba(255,255,255,0.8); padding: 6px 8px; border-radius: 5px; box-shadow: 0 0 15px rgba(0,0,0,0.2); }
    .legend i { width: 18px; height: 18px; float: left; margin-right: 8px; opacity: 0.9; border: 1px solid rgba(0,0,0,0.2); }
</style>

<div class="space-y-6">
    <div id="report-header" class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <!-- Header will be generated by JavaScript -->
    </div>
    
    <div id="card-filter-bar" class="hidden bg-white p-4 rounded-xl shadow-md">
         <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-3">
                 <label for="summarySearchInput" class="text-sm font-medium text-gray-700">ค้นหาอำเภอ</label>
                 <div class="relative mt-1">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400"></i>
                    <input type="text" id="summarySearchInput" placeholder="พิมพ์ชื่ออำเภอ..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg">
                 </div>
            </div>
             <div>
                 <button id="summaryResetFilterBtn" class="w-full p-2.5 bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200 flex items-center justify-center gap-2" title="ล้างค่า">
                    <i data-lucide="rotate-cw" class="h-5 w-5"></i>
                    <span>ล้างค่า</span>
                </button>
            </div>
        </div>
    </div>

    <div id="map-filter-bar" class="hidden bg-white p-4 rounded-xl shadow-md mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                 <label for="mapSearchInput" class="text-sm font-medium text-gray-700">ค้นหาชื่อศูนย์</label>
                 <div class="relative mt-1">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400"></i>
                    <input type="text" id="mapSearchInput" placeholder="พิมพ์ชื่อศูนย์..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg">
                 </div>
            </div>
            <div>
                <label for="mapTypeFilter" class="text-sm font-medium text-gray-700">ประเภท</label>
                <select id="mapTypeFilter" class="w-full mt-1 py-2 border border-gray-300 rounded-lg">
                    <option value="">ทุกประเภท</option>
                </select>
            </div>
             <div>
                <label for="mapAmphoeFilter" class="text-sm font-medium text-gray-700">อำเภอ</label>
                <select id="mapAmphoeFilter" class="w-full mt-1 py-2 border border-gray-300 rounded-lg">
                    <option value="">ทุกอำเภอ</option>
                </select>
            </div>
             <div>
                 <button id="mapResetFilterBtn" class="w-full p-2.5 bg-gray-100 rounded-lg text-gray-600 hover:bg-gray-200 flex items-center justify-center gap-2" title="ล้างค่า">
                    <i data-lucide="rotate-cw" class="h-5 w-5"></i>
                    <span>ล้างค่า</span>
                </button>
            </div>
        </div>
    </div>

    <div id="report-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Report cards will be injected here -->
    </div>
    <div id="map-view-container" class="hidden">
        <div id="map-view"></div>
    </div>
</div>

<!-- Shelter Detail Modal -->
<div id="detailModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full justify-center items-center z-50 hidden">
    <div class="relative mx-auto p-4 md:p-6 border w-full max-w-4xl shadow-2xl rounded-2xl bg-gray-50 my-8">
        <div id="modalHeader" class="flex flex-col mb-4"></div>
        <div id="modalContent" class="space-y-4">
            <div id="modalLoading" class="text-center py-16">
                 <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                 <p class="mt-2 text-gray-600">กำลังโหลดข้อมูล...</p>
            </div>
            <div id="modalDataContainer" class="hidden space-y-2">
                <div class="accordion-item">
                    <button class="accordion-toggle">
                        <span>แนวโน้มผู้เข้าพัก/ผู้ป่วย (7 วันล่าสุด)</span>
                        <i data-lucide="chevron-down" class="chevron-icon"></i>
                    </button>
                    <div class="accordion-content">
                        <div class="bg-white p-4"><canvas id="detailChart"></canvas></div>
                    </div>
                </div>
                <div class="accordion-item">
                     <button class="accordion-toggle">
                        <span>ข้อมูลสรุปปัจจุบัน</span>
                        <i data-lucide="chevron-down" class="chevron-icon"></i>
                    </button>
                     <div class="accordion-content">
                         <div class="bg-white p-4">
                            <div id="detailTableContainer" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4"></div>
                         </div>
                    </div>
                </div>
            </div>
             <div id="modalNoData" class="hidden text-center py-16">
                <i data-lucide="inbox" class="h-12 w-12 mx-auto text-gray-400"></i>
                <p class="mt-2 text-gray-600">ไม่พบข้อมูลรายละเอียดของศูนย์นี้</p>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- 1. STATE & CONSTANTS ---
    const API_URL = 'index.php?page=reports';
    let allSummaryData = [];
    let allMapShelters = [];
    let currentViewMode = 'card';
    let map;
    let mapLegend;
    let mapInitialized = false;
    let mapMarkersLayer = L.layerGroup();
    window.detailChartInstance = null; 

    const typeStyles = {
        'ศูนย์พักพิง': { color: '#3b82f6', fillColor: '#60a5fa' },
        'ศูนย์รับบริจาค': { color: '#8b5cf6', fillColor: '#a78bfa' },
        'รพ.สต.': { color: '#14b8a6', fillColor: '#5eead4' },
        'โรงพยาบาล': { color: '#ec4899', fillColor: '#f9a8d4' },
        'โรงครัวพระราชทาน': { color: '#f97316', fillColor: '#fb923c'},
        'ศูนย์อพยพสัตว์เลี้ยง': { color: '#8d6e63', fillColor: '#bcaaa4'},
        'default': { color: '#6b7280', fillColor: '#9ca3af' }
    };

    // --- 2. DOM ELEMENTS ---
    const reportContainer = document.getElementById('report-container');
    const reportHeader = document.getElementById('report-header');
    const mapViewContainer = document.getElementById('map-view-container');
    const mapFilterBar = document.getElementById('map-filter-bar');
    const cardFilterBar = document.getElementById('card-filter-bar');
    const summarySearchInput = document.getElementById('summarySearchInput');
    const summaryResetFilterBtn = document.getElementById('summaryResetFilterBtn');
    const mapSearchInput = document.getElementById('mapSearchInput');
    const mapTypeFilter = document.getElementById('mapTypeFilter');
    const mapAmphoeFilter = document.getElementById('mapAmphoeFilter');
    const mapResetFilterBtn = document.getElementById('mapResetFilterBtn');
    const detailModal = document.getElementById('detailModal');
    const modalHeader = document.getElementById('modalHeader');
    const modalLoading = document.getElementById('modalLoading');
    const modalDataContainer = document.getElementById('modalDataContainer');
    const modalNoData = document.getElementById('modalNoData');
    const detailTableContainer = document.getElementById('detailTableContainer');

    // --- 3. FUNCTION DEFINITIONS ---
    const showLoading = (message) => {
        reportContainer.innerHTML = `<div class="col-span-full text-center text-gray-500 py-10 flex flex-col items-center"><svg class="animate-spin h-6 w-6 text-indigo-600 mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><p>${message}</p></div>`;
        mapViewContainer.classList.add('hidden');
    };
    
    const renderSummaryCards = (data) => {
        if (!data || data.length === 0) {
            reportContainer.innerHTML = '<p class="col-span-full text-center text-gray-500 py-10">ไม่พบข้อมูลสรุปของอำเภอที่ตรงกับเงื่อนไข</p>';
            return;
        }
        reportContainer.innerHTML = data.map(amphoe => `
            <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow cursor-pointer" 
                 onclick="window.location.href='index.php?page=reports&view=district&amphoe=${encodeURIComponent(amphoe.amphoe)}'">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-indigo-100 rounded-lg flex-shrink-0"><i data-lucide="map" class="h-8 w-8 text-indigo-600"></i></div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">${amphoe.amphoe}</h3>
                        <p class="text-sm text-gray-500">${amphoe.shelter_count} ศูนย์</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-500">ยอดวันนี้</p>
                        <p class="text-2xl font-bold ${((parseInt(amphoe.daily_change) || 0) > 0) ? 'text-green-600' : (((parseInt(amphoe.daily_change) || 0) < 0) ? 'text-red-600' : 'text-gray-600')}">${((parseInt(amphoe.daily_change) || 0) > 0) ? '+' : ''}${parseInt(amphoe.daily_change) || 0}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">ยอดสะสม</p>
                        <p class="text-2xl font-bold text-gray-800">${parseInt(amphoe.total_occupancy) || 0}</p>
                    </div>
                </div>
            </div>`).join('');
        lucide.createIcons();
    };

    const renderSummaryView = (data) => {
        allSummaryData = data;
        reportHeader.innerHTML = `
            <h1 class="text-3xl font-bold text-gray-800">รายงานสรุปภาพรวม</h1>
            <div class="bg-gray-200 p-1 rounded-lg flex self-end">
                <button id="viewCardBtn" class="p-2 rounded-md bg-white shadow flex items-center gap-2" title="มุมมองการ์ด"><i data-lucide="layout-grid" class="h-5 w-5 pointer-events-none"></i><span class="hidden sm:inline">การ์ด</span></button>
                <button id="viewMapBtn" class="p-2 rounded-md text-gray-500 flex items-center gap-2" title="มุมมองแผนที่"><i data-lucide="map-pin" class="h-5 w-5 pointer-events-none"></i><span class="hidden sm:inline">แผนที่</span></button>
            </div>
        `;
        document.getElementById('viewCardBtn').addEventListener('click', () => switchView('card'));
        document.getElementById('viewMapBtn').addEventListener('click', () => switchView('map'));
        
        renderSummaryCards(data);
        switchView(currentViewMode);
        lucide.createIcons();
    };

    const renderDistrictDetailView = (data, amphoeName) => {
        reportHeader.innerHTML = `
            <div class="flex items-center gap-4">
                <a href="index.php?page=reports" class="p-2 bg-gray-200 rounded-lg text-gray-600 hover:bg-gray-300" title="กลับ"><i data-lucide="arrow-left" class="h-6 w-6"></i></a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">ศูนย์ในอำเภอ${amphoeName}</h1>
                    <p class="text-gray-500">ข้อมูลล่าสุด ณ วันที่ ${new Date().toLocaleDateString('th-TH')}</p>
                </div>
            </div>
        `;
        if (!data || data.length === 0) {
            reportContainer.innerHTML = '<p class="col-span-full text-center text-gray-500 py-10">ไม่พบข้อมูลศูนย์ในอำเภอนี้</p>';
            return;
        }
        reportContainer.innerHTML = data.map(shelter => {
            const cumulativeTotal = parseInt(shelter.current_occupancy, 10) || 0;
            const dailyChange = parseInt(shelter.daily_change, 10) || 0;
            const shelterIcon = shelter.type === 'รพ.สต.' ? 'hospital' : (shelter.type === 'ศูนย์รับบริจาค' ? 'package' : 'home');

            return `
            <div class="bg-white rounded-xl shadow-md p-5 hover:shadow-lg transition-shadow cursor-pointer" onclick="openShelterDetailModal(${shelter.id})">
                <div class="flex items-start gap-4 mb-3">
                    <div class="p-3 bg-blue-100 rounded-lg flex-shrink-0">
                        <i data-lucide="${shelterIcon}" class="h-6 w-6 text-blue-600"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800">${shelter.name}</h4>
                        <div class="flex items-center text-xs text-gray-500 mt-1">
                            <i data-lucide="map-pin" class="h-3 w-3 mr-1.5 flex-shrink-0"></i>
                            <span>ต.${shelter.tambon || '-'}, อ.${amphoeName}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-500">ยอดวันนี้</p>
                        <p class="text-xl font-bold ${dailyChange > 0 ? 'text-green-600' : (dailyChange < 0 ? 'text-red-600' : 'text-gray-600')}">${dailyChange > 0 ? '+' : ''}${dailyChange}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">ยอดสะสม</p>
                        <p class="text-xl font-bold text-gray-800">${cumulativeTotal}</p>
                    </div>
                </div>
            </div>`;
        }).join('');
        lucide.createIcons();
    };
    
    const filterSummaryCards = () => {
        const searchTerm = summarySearchInput.value.toLowerCase();
        const filteredData = allSummaryData.filter(item => 
            item.amphoe.toLowerCase().includes(searchTerm)
        );
        renderSummaryCards(filteredData);
    };

    const addMarkersToMap = (shelters) => {
        mapMarkersLayer.clearLayers();
        if (!shelters || shelters.length === 0) return;

        shelters.forEach(shelter => {
            if (!shelter.latitude || !shelter.longitude) return;
            const style = typeStyles[shelter.type] || typeStyles['default'];
            const marker = L.circleMarker([shelter.latitude, shelter.longitude], {
                radius: 8,
                color: style.color,
                fillColor: style.fillColor,
                fillOpacity: 0.8,
                weight: 2
            });

            let phoneHtml = '';
            if (shelter.phone) {
                phoneHtml = `<p class="text-sm text-gray-600 flex items-center gap-2">
                                <strong>โทร:</strong> ${shelter.phone}
                                <a href="tel:${shelter.phone}" class="popup-icon-button" title="โทรออก"><i data-lucide="phone" class="w-4 h-4"></i></a>
                             </p>`;
            }

            const popupContent = `
                <div class="space-y-2" style="min-width: 200px;">
                    <h4 class="font-bold text-base">${shelter.name}</h4>
                    <p class="text-sm text-gray-600"><strong>ประเภท:</strong> ${shelter.type}</p>
                    <p class="text-sm text-gray-600"><strong>ผู้เข้าพัก:</strong> ${shelter.current_occupancy} คน</p>
                    ${phoneHtml}
                    <div class="mt-2 pt-2 border-t flex justify-between items-center">
                        <a href="#" onclick="openShelterDetailModal(${shelter.id}); return false;" class="popup-button">ดูรายละเอียด</a>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=${shelter.latitude},${shelter.longitude}" target="_blank" class="popup-icon-button" title="นำทาง"><i data-lucide="navigation" class="w-4 h-4"></i></a>
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent);
            mapMarkersLayer.addLayer(marker);
        });
        map.on('popupopen', () => lucide.createIcons());
    };

    const populateMapFilters = () => {
        const types = [...new Set(allMapShelters.map(s => s.type))].sort();
        const amphoes = [...new Set(allMapShelters.map(s => s.amphoe))].sort();
        mapTypeFilter.innerHTML = '<option value="">ทุกประเภท</option>' + types.map(t => `<option value="${t}">${t}</option>`).join('');
        mapAmphoeFilter.innerHTML = '<option value="">ทุกอำเภอ</option>' + amphoes.map(a => `<option value="${a}">${a}</option>`).join('');
    };

    const filterMapMarkers = () => {
        const searchVal = mapSearchInput.value.toLowerCase();
        const typeVal = mapTypeFilter.value;
        const amphoeVal = mapAmphoeFilter.value;

        const filtered = allMapShelters.filter(s => {
            const nameMatch = s.name.toLowerCase().includes(searchVal);
            const typeMatch = !typeVal || s.type === typeVal;
            const amphoeMatch = !amphoeVal || s.amphoe === amphoeVal;
            return nameMatch && typeMatch && amphoeMatch;
        });
        addMarkersToMap(filtered);
    };

    const addMapLegend = () => {
        if (mapLegend) map.removeControl(mapLegend);
        mapLegend = L.control({position: 'bottomright'});
        mapLegend.onAdd = function (map) {
            const div = L.DomUtil.create('div', 'info legend');
            const types = Object.keys(typeStyles).filter(t => t !== 'default');
            let labels = ['<strong>คำอธิบายสัญลักษณ์</strong>'];
            for (let i = 0; i < types.length; i++) {
                const style = typeStyles[types[i]];
                labels.push(
                    '<i style="background:' + style.fillColor + '; border: 1px solid ' + style.color + '"></i> ' +
                    types[i]);
            }
            div.innerHTML = labels.join('<br>');
            return div;
        };
        mapLegend.addTo(map);
    };

    const initMap = async () => {
        if (mapInitialized) {
            map.invalidateSize();
            return;
        }
        mapInitialized = true;
        map = L.map('map-view').setView([15.115, 104.320], 9); 
        mapMarkersLayer.addTo(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        addMapLegend();
        
        try {
            const response = await fetch(`${API_URL}&api=get_all_shelters_for_map`);
            const result = await response.json();
            if (result.status === 'success') {
                allMapShelters = result.data;
                populateMapFilters();
                addMarkersToMap(allMapShelters);
            }
        } catch(error) {
            console.error("Failed to load map data:", error);
        }
    };
    
    const switchView = (view) => {
        const viewCardBtn = document.getElementById('viewCardBtn');
        const viewMapBtn = document.getElementById('viewMapBtn');
        if (!viewCardBtn || !viewMapBtn) return;
        currentViewMode = view;
        if (view === 'map') {
            reportContainer.classList.add('hidden');
            cardFilterBar.classList.add('hidden');
            mapViewContainer.classList.remove('hidden');
            mapFilterBar.classList.remove('hidden');
            viewMapBtn.classList.add('bg-white', 'shadow');
            viewMapBtn.classList.remove('text-gray-500');
            viewCardBtn.classList.remove('bg-white', 'shadow');
            viewCardBtn.classList.add('text-gray-500');
            initMap();
        } else {
            mapViewContainer.classList.add('hidden');
            mapFilterBar.classList.add('hidden');
            reportContainer.classList.remove('hidden');
            cardFilterBar.classList.remove('hidden');
            viewCardBtn.classList.add('bg-white', 'shadow');
            viewCardBtn.classList.remove('text-gray-500');
            viewMapBtn.classList.remove('bg-white', 'shadow');
            viewMapBtn.classList.add('text-gray-500');
        }
    };
    
    const setupAccordion = () => {
        const accordions = document.querySelectorAll('.accordion-toggle');
        accordions.forEach(button => {
            button.addEventListener('click', () => {
                const content = button.nextElementSibling;
                button.classList.toggle('active');
                if (content.style.maxHeight) {
                    content.style.maxHeight = null;
                } else {
                    content.style.maxHeight = content.scrollHeight + "px";
                }
            });
        });
    };

    const renderDetailChart = (data) => {
        if (window.detailChartInstance) {
            window.detailChartInstance.destroy();
        }
        const ctx = document.getElementById('detailChart').getContext('2d');
        const labels = data.map(d => new Date(d.report_date).toLocaleDateString('th-TH'));
        const values = data.map(d => d.total_patients);

        window.detailChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'จำนวนผู้ป่วยสะสม',
                    data: values,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    };

    const renderDetailTable = (details) => {
        if (!details) {
            detailTableContainer.innerHTML = '<p class="col-span-full text-center text-gray-500">ไม่มีข้อมูลสรุป</p>';
            return;
        }
        const dataMap = [
            { key: 'total_patients', label: 'รวม' },
            { key: 'male_patients', label: 'ชาย' },
            { key: 'female_patients', label: 'หญิง' },
            { key: 'elderly_patients', label: 'ผู้สูงอายุ' },
            { key: 'child_patients', label: 'เด็ก' },
            { key: 'pregnant_women', label: 'หญิงตั้งครรภ์' },
            { key: 'disabled_patients', label: 'ผู้พิการ' },
            { key: 'bedridden_patients', label: 'ผู้ป่วยติดเตียง' },
            { key: 'chronic_disease_patients', label: 'โรคเรื้อรัง' },
            { key: 'diabetes_patients', label: 'เบาหวาน' },
            { key: 'hypertension_patients', label: 'ความดัน' },
            { key: 'heart_disease_patients', label: 'โรคหัวใจ' },
            { key: 'kidney_disease_patients', label: 'โรคไต' },
            { key: 'mental_health_patients', label: 'สุขภาพจิต' },
        ];
        detailTableContainer.innerHTML = dataMap.map(item => `
            <div class="text-center bg-gray-100 p-3 rounded-lg">
                <p class="text-sm text-gray-600">${item.label}</p>
                <p class="text-xl font-bold text-gray-900">${details[item.key] || 0}</p>
            </div>
        `).join('');
    };

    window.openShelterDetailModal = async (shelterId) => {
        detailModal.classList.remove('hidden');
        modalLoading.classList.remove('hidden');
        modalDataContainer.classList.add('hidden');
        modalNoData.classList.add('hidden');

        try {
            const response = await fetch(`${API_URL}&api=get_shelter_details&shelter_id=${shelterId}`);
            const result = await response.json();

            if (result.status !== 'success') throw new Error(result.message);

            const { shelterInfo, dailyChange, graphData, currentDetails } = result.data;
            
            modalHeader.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">${shelterInfo.name}</h2>
                        <p class="text-gray-500">อ.${shelterInfo.amphoe}, ต.${shelterInfo.tambon}</p>
                    </div>
                    <button onclick="detailModal.classList.add('hidden')" class="p-2 text-gray-500 hover:text-gray-800"><i data-lucide="x" class="h-6 w-6"></i></button>
                </div>
            `;
            
            modalLoading.classList.add('hidden');
            
            if (!graphData.length && !currentDetails) {
                modalNoData.classList.remove('hidden');
            } else {
                modalDataContainer.classList.remove('hidden');
                renderDetailChart(graphData);
                renderDetailTable(currentDetails);
                setupAccordion();
            }
            lucide.createIcons();

        } catch (error) {
            modalLoading.classList.add('hidden');
            modalHeader.innerHTML = '<h2 class="text-2xl font-bold text-red-600">เกิดข้อผิดพลาด</h2>';
            modalNoData.innerHTML = `<p class="text-center text-red-600">${error.message}</p>`;
            modalNoData.classList.remove('hidden');
        }
    };

    // --- 4. MAIN EXECUTION LOGIC ---
    const main = async () => {
        const urlParams = new URLSearchParams(window.location.search);
        const view = urlParams.get('view');
        const amphoe = urlParams.get('amphoe');

        summarySearchInput.addEventListener('keyup', filterSummaryCards);
        summaryResetFilterBtn.addEventListener('click', () => {
            summarySearchInput.value = '';
            filterSummaryCards();
        });
        mapSearchInput.addEventListener('keyup', filterMapMarkers);
        mapTypeFilter.addEventListener('change', filterMapMarkers);
        mapAmphoeFilter.addEventListener('change', filterMapMarkers);
        mapResetFilterBtn.addEventListener('click', () => {
            mapSearchInput.value = '';
            mapTypeFilter.value = '';
            mapAmphoeFilter.value = '';
            filterMapMarkers();
        });

        if (view === 'district' && amphoe) {
            showLoading(`กำลังโหลดข้อมูลอำเภอ ${amphoe}...`);
            try {
                const response = await fetch(`${API_URL}&api=get_district_detail&amphoe=${encodeURIComponent(amphoe)}`);
                const result = await response.json();
                if (result.status === 'success') renderDistrictDetailView(result.data, amphoe);
                else showLoading(`เกิดข้อผิดพลาด: ${result.message}`);
            } catch (error) { 
                console.error("Fetch error:", error);
                showLoading('ไม่สามารถโหลดข้อมูลได้ กรุณาตรวจสอบ Console');
            }
        } else {
            showLoading('กำลังโหลดข้อมูลสรุป...');
             try {
                const response = await fetch(`${API_URL}&api=get_summary`);
                const result = await response.json();
                if (result.status === 'success') {
                    renderSummaryView(result.data);
                }
                else showLoading(`เกิดข้อผิดพลาด: ${result.message}`);
            } catch (error) { 
                console.error("Fetch error:", error);
                showLoading('ไม่สามารถโหลดข้อมูลได้ กรุณาตรวจสอบ Console');
            }
        }
    };

    // --- 5. INITIALIZE ---
    main();
});
</script>

<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/claude.php';

$method = $_SERVER['REQUEST_METHOD'];

// Support both direct path access and ?route= query parameter
$path = '';
if (!empty($_GET['route'])) {
    $path = '/' . trim($_GET['route'], '/');
} else {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/');
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    $path = substr($uri, strlen($basePath));
    $path = '/' . trim($path, '/');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

function jsonResponse($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function requireAuth() {
    $user = getAuthUser();
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    return $user;
}

function handleResourceList(string $table) {
    $items = dbGetAll($table);
    jsonResponse($items);
}

function handleResourceGet(string $table, string $id) {
    $item = dbGetById($table, $id);
    if (!$item) jsonResponse(['error' => 'Not found'], 404);
    jsonResponse($item);
}

function handleResourceCreate(string $table, array $input) {
    if ($table === 'users' && isset($input['password']) && $input['password'] !== '') {
        $input['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }
    $id = $input['id'] ?? (bin2hex(random_bytes(12)));
    $data = array_merge(['id' => $id], $input);
    $item = dbCreate($table, $data);
    jsonResponse($item, 201);
}

function handleResourceUpdate(string $table, string $id, array $input) {
    $existing = dbGetById($table, $id);
    if (!$existing) jsonResponse(['error' => 'Not found'], 404);
    unset($input['id'], $input['createdAt'], $input['created_at']);
    if (isset($input['password']) && $input['password'] === '') {
        unset($input['password']);
    }
    if (isset($input['password'])) {
        $input['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }
    $item = dbUpdate($table, $id, $input);
    jsonResponse($item);
}

function handleResourceDelete(string $table, string $id) {
    $deleted = dbDelete($table, $id);
    if (!$deleted) jsonResponse(['error' => 'Not found'], 404);
    jsonResponse(['ok' => true]);
}

function handleReports() {
    $tables = ['students', 'medicalRecords', 'visits', 'medicine', 'appointments', 'incidents', 'staff', 'programs', 'clearance'];
    $data = [];
    foreach ($tables as $t) {
        $data[$t] = dbGetAll($t);
    }
    $data['medicine'] = array_map(function($m) {
        $m['stock'] = (int)($m['stock'] ?? 0);
        $m['reorderLevel'] = (int)($m['reorderLevel'] ?? 0);
        return $m;
    }, $data['medicine'] ?? []);

    $lowStock = array_filter($data['medicine'], fn($m) => $m['stock'] <= $m['reorderLevel']);
    $openIncidents = array_filter($data['incidents'] ?? [], fn($i) => $i['status'] !== 'Resolved');
    $expiredClearance = array_filter($data['clearance'] ?? [], fn($c) => $c['status'] === 'Expired');
    $pendingAppts = array_filter($data['appointments'] ?? [], fn($a) => $a['status'] === 'Pending');

    jsonResponse([
        'records' => $data,
        'summary' => [
            'lowStock' => count($lowStock),
            'openIncidents' => count($openIncidents),
            'expiredClearance' => count($expiredClearance),
            'pendingAppointments' => count($pendingAppts),
        ],
    ]);
}

function handleDashboard() {
    $tables = ['students', 'medicalRecords', 'visits', 'medicine', 'appointments', 'incidents', 'staff', 'programs', 'clearance'];
    $data = [];
    foreach ($tables as $t) {
        $data[$t] = dbGetAll($t);
    }

    $today = date('Y-m-d');
    $todaysVisits = count(array_filter($data['visits'] ?? [], fn($v) => ($v['date'] ?? '') === $today));
    $pendingAppointments = count(array_filter($data['appointments'] ?? [], fn($a) => ($a['status'] ?? '') === 'Pending'));

    $medicine = array_map(function($m) {
        $m['stock'] = (int)($m['stock'] ?? 0);
        $m['reorderLevel'] = (int)($m['reorderLevel'] ?? 0);
        return $m;
    }, $data['medicine'] ?? []);
    $lowStock = count(array_filter($medicine, fn($m) => $m['stock'] > 0 && $m['stock'] <= $m['reorderLevel']));
    $clearances = count(array_filter($data['clearance'] ?? [], fn($c) => ($c['status'] ?? '') !== 'Expired'));
    $openIncidents = count(array_filter($data['incidents'] ?? [], fn($i) => ($i['status'] ?? 'Open') === 'Open'));

    $chartData = [];
    $dow = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $now = new DateTime();
    $monday = (clone $now)->modify('monday this week');
    for ($i = 0; $i < 7; $i++) {
        $dt = (clone $monday)->modify("+$i days");
        $key = $dt->format('Y-m-d');
        $count = count(array_filter($data['visits'] ?? [], fn($v) => ($v['date'] ?? '') === $key));
        $chartData[] = ['day' => $dow[$i], 'visits' => $count];
    }

    $recent = [];
    foreach ($data['visits'] ?? [] as $v) {
        $recent[] = ['ts' => (int)($v['createdAt'] ?? 0), 'text' => 'Clinic visit logged for ' . ($v['patientname'] ?? 'a patient'), 'type' => 'visit'];
    }
    foreach ($data['medicine'] ?? [] as $m) {
        $recent[] = ['ts' => (int)($m['createdAt'] ?? 0), 'text' => 'Medicine "' . ($m['name'] ?? '') . '" updated in inventory', 'type' => 'medicine'];
    }
    foreach ($data['clearance'] ?? [] as $c) {
        $recent[] = ['ts' => (int)($c['createdAt'] ?? 0), 'text' => 'Health clearance recorded for ' . ($c['name'] ?? ''), 'type' => 'clearance'];
    }
    foreach ($data['incidents'] ?? [] as $inc) {
        $recent[] = ['ts' => (int)($inc['createdAt'] ?? 0), 'text' => 'Incident report ' . ($inc['caseno'] ?? '') . ' submitted', 'type' => 'incident'];
    }
    usort($recent, fn($a, $b) => $b['ts'] - $a['ts']);
    $recent = array_slice($recent, 0, 5);

    $upcomingAppts = array_filter($data['appointments'] ?? [], fn($a) => ($a['date'] ?? '') >= $today && ($a['status'] ?? '') !== 'Cancelled');
    usort($upcomingAppts, fn($a, $b) => strcmp(($a['date'] ?? '') . ($a['time'] ?? ''), ($b['date'] ?? '') . ($b['time'] ?? '')));
    $upcomingAppts = array_slice($upcomingAppts, 0, 5);

    $programUpdates = array_filter($data['programs'] ?? [], fn($p) => ($p['status'] ?? '') !== 'Completed');
    $programUpdates = array_slice($programUpdates, 0, 3);

    jsonResponse([
        'stats' => [
            'todaysVisits' => $todaysVisits,
            'pendingAppointments' => $pendingAppointments,
            'lowStock' => $lowStock,
            'clearances' => $clearances,
            'openIncidents' => $openIncidents,
        ],
        'chartData' => $chartData,
        'recentActivity' => $recent,
        'upcomingAppointments' => $upcomingAppts,
        'programUpdates' => $programUpdates,
        'medicine' => array_map(function($m) {
            $m['stock'] = (int)($m['stock'] ?? 0);
            $m['reorderLevel'] = (int)($m['reorderLevel'] ?? 0);
            return $m;
        }, $data['medicine'] ?? []),
        'appointments' => $data['appointments'] ?? [],
        'incidents' => $data['incidents'] ?? [],
        'visits' => $data['visits'] ?? [],
        'students' => $data['students'] ?? [],
    ]);
}

/* ============================== RECORD FOLDERS ============================== */

function handleRecordFoldersList() {
    $base = dirname(__DIR__) . '/public/records';
    if (!is_dir($base)) {
        jsonResponse([]);
    }
    $entries = scandir($base);
    $dirs = array_values(array_filter($entries, function($d) use ($base) {
        return $d !== '.' && $d !== '..' && is_dir($base . '/' . $d);
    }));
    $result = array_map(function($name) use ($base) {
        $path = $base . '/' . $name;
        $metaFile = $path . '/metadata.json';
        $meta = null;
        if (file_exists($metaFile)) {
            $j = @file_get_contents($metaFile);
            $meta = $j ? @json_decode($j, true) : null;
        }
        return [
            'name' => $name,
            'createdAt' => filectime($path) ?: null,
            'meta' => $meta,
        ];
    }, $dirs);
    jsonResponse($result);
}

function handleRecordFoldersCreate() {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        jsonResponse(['error' => 'name is required'], 400);
    }
    // sanitize folder name to avoid directory traversal and weird chars
    $name = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', $name);
    $base = dirname(__DIR__) . '/public/records';
    if (!is_dir($base)) {
        if (!mkdir($base, 0755, true) && !is_dir($base)) {
            jsonResponse(['error' => 'Failed to create base records directory'], 500);
        }
    }
    $path = $base . '/' . $name;
    if (is_dir($path)) {
        jsonResponse(['error' => 'Folder already exists'], 409);
    }
    $meta = [];
    foreach (['yearLevel','department','type','description'] as $k) {
        if (isset($input[$k]) && $input[$k] !== '') $meta[$k] = $input[$k];
    }
    $meta['name'] = $name;
    $meta['createdAt'] = time();
    if (mkdir($path, 0755, true)) {
        // write metadata
        if (!empty($meta)) {
            @file_put_contents($path . '/metadata.json', json_encode($meta, JSON_PRETTY_PRINT));
        }
        jsonResponse(['ok' => true, 'name' => $name, 'meta' => $meta], 201);
    }
    jsonResponse(['error' => 'Could not create folder'], 500);
}

function handleRecordFoldersUpdate() {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $currentName = trim((string)($input['currentName'] ?? $input['name'] ?? ''));
    $newName = trim((string)($input['newName'] ?? $input['name'] ?? ''));
    if ($currentName === '' || $newName === '') {
        jsonResponse(['error' => 'currentName and newName are required'], 400);
    }
    $currentName = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', $currentName);
    $newName = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', $newName);
    if ($currentName === '' || $newName === '') {
        jsonResponse(['error' => 'Invalid folder name'], 400);
    }
    $base = dirname(__DIR__) . '/public/records';
    $oldPath = $base . '/' . $currentName;
    $newPath = $base . '/' . $newName;
    if (!is_dir($oldPath)) {
        jsonResponse(['error' => 'Source folder not found'], 404);
    }
    if (is_dir($newPath)) {
        jsonResponse(['error' => 'Target folder already exists'], 409);
    }
    if (!rename($oldPath, $newPath)) {
        jsonResponse(['error' => 'Could not rename folder'], 500);
    }
    $metaFile = $newPath . '/metadata.json';
    $meta = null;
    if (file_exists($metaFile)) {
        $j = @file_get_contents($metaFile);
        $meta = $j ? @json_decode($j, true) : null;
        if (is_array($meta)) {
            $meta['name'] = $newName;
            @file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));
        }
    }

    // Update any medical records that referenced the old folder name.
    try {
        $db = getDbConnection();
        $sql = 'UPDATE ' . escapeIdentifier('medicalrecords') . ' SET ' . escapeIdentifier('groupfolder') . ' = :newName WHERE ' . escapeIdentifier('groupfolder') . ' = :currentName';
        $stmt = $db->prepare($sql);
        $stmt->execute([':newName' => $newName, ':currentName' => $currentName]);
    } catch (Exception $e) {
        // Even if DB update fails, still return success for folder rename; log if needed.
    }

    jsonResponse(['ok' => true, 'name' => $newName, 'meta' => $meta]);
}

/* ============================== ROUTING ============================== */

$resourceMap = [
    'students' => 'students',
    'medicalRecords' => 'medicalrecords',
    'medicalrecords' => 'medicalrecords',
    'medicalHistory' => 'medicalhistory',
    'medicalhistory' => 'medicalhistory',
    'visits' => 'visits',
    'medicine' => 'medicine',
    'appointments' => 'appointments',
    'incidents' => 'incidents',
    'staff' => 'staff',
    'programs' => 'programs',
    'clearance' => 'clearance',
    'users' => 'users',
];

function handleAiAnalyze() {
    $user = requirePermission('use_ai_assistant');

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $visitId = trim((string)($input['visitId'] ?? ''));
    if ($visitId === '') {
        jsonResponse(['error' => 'visitId is required'], 400);
    }

    $visit = dbGetById('visits', $visitId);
    if (!$visit) {
        jsonResponse(['error' => 'Visit not found'], 404);
    }

    $diagnosis = trim((string)($visit['diagnosis'] ?? ''));
    if ($diagnosis === '') {
        jsonResponse(['error' => 'This visit has no recorded diagnosis to analyze'], 400);
    }

    $cfg = getConfig();
    $max = max(1, (int)($cfg['ai_rate_limit_max'] ?? 10));
    $windowMin = max(1, (int)($cfg['ai_rate_limit_window_minutes'] ?? 60));
    $since = round(microtime(true) * 1000) - ($windowMin * 60000);
    $count = dbCountAiRequestsSince((string)($user['id'] ?? ''), $since);
    if ($count >= $max) {
        dbLogAudit($user, 'ai.rate_limited', 'visits', $visitId, ['reason' => 'rate limit exceeded']);
        jsonResponse(['error' => 'AI request limit reached. Please try again later.'], 429);
    }

    $student = lookupStudentByVisit($visit);
    $payload = buildMinimizedAiPayload($visit, $student);
    $requestId = dbLogAiRequest($user, $visitId, $diagnosis, $payload, 'pending');

    dbLogAudit($user, 'ai.analyze', 'visits', $visitId, [
        'visitId' => $visitId,
        'diagnosis' => $diagnosis,
        'requestId' => $requestId,
    ]);

    $result = callClaude($payload);

    if ($result['ok']) {
        dbUpdateAiRequest($requestId, 'success', mb_substr($result['analysis'] ?? '', 0, 2000));
    } elseif (($result['status'] ?? '') === 'not_configured') {
        dbUpdateAiRequest($requestId, 'not_configured', '', $result['message'] ?? '');
    } else {
        dbUpdateAiRequest($requestId, 'error', '', $result['message'] ?? '');
    }

    jsonResponse([
        'ok' => $result['ok'],
        'status' => $result['status'] ?? 'error',
        'disclaimer' => AI_DISCLAIMER,
        'analysis' => $result['analysis'] ?? null,
        'message' => $result['message'] ?? null,
        'requestId' => $requestId,
    ]);
}

try {
    if ($path === '/login' || $path === '/auth/login') {
        jsonResponse(handleLogin());
    } elseif ($path === '/register' || $path === '/auth/register') {
        jsonResponse(handleRegister());
    } elseif ($path === '/dashboard') {
        requireAuth();
        handleDashboard();
    } elseif ($path === '/reports') {
        requirePermission('view_reports');
        handleReports();
    } elseif ($path === '/seed') {
        $existing = dbGetAll('users');
        if (count($existing) === 0) {
            $id = bin2hex(random_bytes(12));
            dbCreate('users', [
                'id' => $id,
                'fullname' => 'Nurse Admin',
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'role' => 'Clinic Administrator',
                'status' => 'Active',
            ]);
            jsonResponse(['ok' => true, 'message' => 'Default admin user created']);
        }
        jsonResponse(['ok' => true, 'message' => 'Users already exist']);
    } elseif (preg_match('#^/seed/(\w+)$#', $path, $m)) {
        requirePermission('manage_users');
        $table = $m[1];
        $existing = dbGetAll($table);
        if (count($existing) === 0) {
            $sample = getSampleData($table);
            if ($sample) {
                foreach ($sample as $row) {
                    dbCreate($table, $row);
                }
                jsonResponse(['ok' => true, 'message' => 'Sample data created for ' . $table]);
            }
        }
        jsonResponse(['ok' => true, 'message' => 'Data already exists']);
    } elseif ($path === '/api/ai/analyze') {
        handleAiAnalyze();
    } elseif ($path === '/api/medicalRecords/folders') {
        requireAuth();
        switch ($method) {
            case 'GET':
                handleRecordFoldersList();
                break;
            case 'POST':
                handleRecordFoldersCreate();
                break;
            case 'DELETE':
                handleRecordFoldersDelete();
                break;
            case 'PUT':
                handleRecordFoldersUpdate();
                break;
            default:
                jsonResponse(['error' => 'Method not allowed'], 405);
        }
    } elseif (preg_match('#^/api/(\w+)$#', $path, $m)) {
        $resource = $m[1];
        if (!isset($resourceMap[$resource])) {
            jsonResponse(['error' => 'Unknown resource'], 404);
        }
        if ($resource === 'users') {
            requirePermission('manage_users');
        } else {
            requireAuth();
        }
        $table = $resourceMap[$resource];
        switch ($method) {
            case 'GET':
                handleResourceList($table);
                break;
            case 'POST':
                handleResourceCreate($table, $input);
                break;
            default:
                jsonResponse(['error' => 'Method not allowed'], 405);
        }
    } elseif (preg_match('#^/api/(\w+)/(.+)$#', $path, $m)) {
        $resource = $m[1];
        $id = $m[2];
        if (!isset($resourceMap[$resource])) {
            jsonResponse(['error' => 'Unknown resource'], 404);
        }
        if ($resource === 'users') {
            requirePermission('manage_users');
        } else {
            requireAuth();
        }
        $table = $resourceMap[$resource];
        switch ($method) {
            case 'GET':
                handleResourceGet($table, $id);
                break;
            case 'PUT':
            case 'PATCH':
                handleResourceUpdate($table, $id, $input);
                break;
            case 'DELETE':
                handleResourceDelete($table, $id);
                break;
            default:
                jsonResponse(['error' => 'Method not allowed'], 405);
        }
    } else {
        jsonResponse(['error' => 'Not found', 'path' => $path], 404);
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['error' => $e->getMessage()]);
}

function getSampleData(string $table): ?array {
    $samples = [
        'students' => [
            ['name' => 'Juan Dela Cruz', 'studentId' => 'BCP-2024-0001', 'course' => 'BSIT', 'yearLevel' => '3rd Year', 'bloodType' => 'O+', 'allergies' => 'None', 'conditions' => 'Asthma', 'contactNumber' => '09171234567', 'emergencyContact' => 'Maria Dela Cruz - 09179876543'],
            ['name' => 'Maria Santos', 'studentId' => 'BCP-2024-0002', 'course' => 'BSED', 'yearLevel' => '2nd Year', 'bloodType' => 'A+', 'allergies' => 'Penicillin', 'conditions' => 'None', 'contactNumber' => '09187654321', 'emergencyContact' => 'Pedro Santos - 09171112233'],
        ],
        'medicalRecords' => [
            ['recordId' => 'MR-2026-01', 'status' => 'Active', 'studentId' => 'BCP-2024-0001', 'studentName' => 'Juan Dela Cruz', 'department' => 'College of IT', 'yearLevel' => '3rd Year', 'section' => 'A', 'bloodType' => 'O+', 'allergies' => 'None', 'medicalConditions' => 'Asthma', 'height' => '170', 'weight' => '65', 'vision' => '20/20', 'hearing' => 'Normal', 'immunizationStatus' => 'Complete', 'remarks' => 'Regular checkup required'],
            ['recordId' => 'MR-2026-02', 'status' => 'Active', 'studentId' => 'BCP-2024-0002', 'studentName' => 'Maria Santos', 'department' => 'College of Education', 'yearLevel' => '2nd Year', 'section' => 'B', 'bloodType' => 'A+', 'allergies' => 'Penicillin', 'medicalConditions' => 'None', 'height' => '165', 'weight' => '58', 'vision' => '20/25', 'hearing' => 'Normal', 'immunizationStatus' => 'Incomplete', 'remarks' => 'Needs tetanus booster'],
            ['recordId' => 'MR-2026-03', 'status' => 'Active', 'studentId' => 'BCP-2024-0003', 'studentName' => 'Carlos Garcia', 'department' => 'College of Engineering', 'yearLevel' => '4th Year', 'section' => 'A', 'bloodType' => 'B+', 'allergies' => 'Dust mites', 'medicalConditions' => 'None', 'height' => '175', 'weight' => '70', 'vision' => '20/20', 'hearing' => 'Normal', 'immunizationStatus' => 'Complete', 'remarks' => 'No significant health issues'],
            ['recordId' => 'MR-2026-04', 'status' => 'Active', 'studentId' => 'BCP-2024-0004', 'studentName' => 'Ana Rodriguez', 'department' => 'College of Nursing', 'yearLevel' => '1st Year', 'section' => 'C', 'bloodType' => 'AB-', 'allergies' => 'Latex', 'medicalConditions' => 'None', 'height' => '160', 'weight' => '52', 'vision' => '20/20', 'hearing' => 'Normal', 'immunizationStatus' => 'Pending', 'remarks' => 'Allergy alert for medical equipment'],
            ['recordId' => 'MR-2026-05', 'status' => 'Active', 'studentId' => 'BCP-2024-0005', 'studentName' => 'Miguel Tan', 'department' => 'Senior High School', 'yearLevel' => 'Grade 11', 'section' => 'STEM-A', 'bloodType' => 'O-', 'allergies' => 'None', 'medicalConditions' => 'Mild scoliosis', 'height' => '168', 'weight' => '60', 'vision' => '20/20', 'hearing' => 'Normal', 'immunizationStatus' => 'Complete', 'remarks' => 'Regular monitoring for scoliosis'],
        ],
        'visits' => [
            ['patientName' => 'Juan Dela Cruz', 'patientType' => 'Student', 'date' => date('Y-m-d'), 'time' => '09:30', 'complaint' => 'Headache and dizziness', 'diagnosis' => 'Mild dehydration', 'treatment' => 'Provided water and paracetamol. Advised to rest.', 'nurseOnDuty' => 'Nurse Admin'],
        ],
        'medicine' => [
            ['name' => 'Paracetamol', 'category' => 'Analgesic', 'stock' => 50, 'unit' => 'tablets', 'expiryDate' => '2027-12-31', 'reorderLevel' => 20],
            ['name' => 'Amoxicillin', 'category' => 'Antibiotic', 'stock' => 5, 'unit' => 'tablets', 'expiryDate' => '2027-06-30', 'reorderLevel' => 15],
        ],
        'appointments' => [
            ['patientName' => 'Maria Santos', 'patientType' => 'Student', 'date' => date('Y-m-d'), 'time' => '14:00', 'type' => 'General Check-up', 'status' => 'Confirmed', 'notes' => 'Annual physical exam'],
        ],
        'incidents' => [
            ['caseNo' => 'INC-2026-001', 'date' => date('Y-m-d'), 'personInvolved' => 'Juan Dela Cruz', 'location' => 'Building A - Room 102', 'description' => 'Student fainted during class', 'severity' => 'High', 'status' => 'Open', 'actionTaken' => 'Transferred to clinic, vital signs monitored'],
        ],
        'staff' => [
            ['name' => 'Dr. Reyes', 'department' => 'College of Education', 'position' => 'Professor', 'bloodType' => 'AB+', 'healthNotes' => 'Regular checkup', 'lastCheckup' => '2026-06-15', 'contactNumber' => '09175556677'],
        ],
        'programs' => [
            ['name' => 'Dental Mission 2026', 'category' => 'Dental', 'startDate' => '2026-08-15', 'endDate' => '2026-08-20', 'targetParticipants' => 200, 'status' => 'Upcoming', 'description' => 'Annual dental mission for students'],
        ],
        'clearance' => [
            ['name' => 'Juan Dela Cruz', 'personType' => 'Student', 'clearanceType' => 'Medical Clearance', 'dateIssued' => '2026-06-01', 'expiryDate' => '2026-12-31', 'status' => 'Valid', 'issuedBy' => 'Nurse Admin'],
        ],
        'medicalHistory' => [
            ['historyId' => 'HIST-2026-001', 'studentId' => 'BCP-2024-0001', 'studentName' => 'Juan Dela Cruz', 'department' => 'College of IT', 'yearLevel' => '3rd Year', 'section' => 'A', 'bloodType' => 'O+', 'course' => 'BSIT', 'diagnosis' => 'Mild dehydration', 'treatment' => 'Oral rehydration and rest', 'doctor' => 'Dr. Reyes', 'visitDate' => date('Y-m-d')],
            ['historyId' => 'HIST-2026-002', 'studentId' => 'BCP-2024-0002', 'studentName' => 'Maria Santos', 'department' => 'College of Education', 'yearLevel' => '2nd Year', 'section' => 'B', 'bloodType' => 'A+', 'course' => 'BSED', 'diagnosis' => 'Tetanus booster needed', 'treatment' => 'Administered tetanus vaccine', 'doctor' => 'Nurse Admin', 'visitDate' => date('Y-m-d', strtotime('-7 days'))],
        ],
    ];
    return $samples[$table] ?? null;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return false;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}

function handleRecordFoldersDelete() {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        jsonResponse(['error' => 'name is required'], 400);
    }
    $base = dirname(__DIR__) . '/public/records';
    $path = $base . '/' . $name;
    if (!is_dir($path)) {
        jsonResponse(['error' => 'Folder not found'], 404);
    }
    // attempt to remove directory recursively
    if (!rrmdir($path)) {
        jsonResponse(['error' => 'Could not delete folder'], 500);
    }

    // update DB records to remove references to this folder
    try {
        $db = getDbConnection();
        $sql = 'UPDATE ' . escapeIdentifier('medicalrecords') . ' SET ' . escapeIdentifier('groupfolder') . ' = :empty WHERE ' . escapeIdentifier('groupfolder') . ' = :name';
        $stmt = $db->prepare($sql);
        $stmt->execute([':empty' => '', ':name' => $name]);
    } catch (Exception $e) {
        // ignore DB errors
    }

    jsonResponse(['ok' => true]);
}

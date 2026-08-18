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
require_once __DIR__ . '/gemini.php';

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

// $code left null (rather than defaulting to 200) so callers like handleLogin()/
// handleRegister() that already called http_response_code() internally before
// returning their result array aren't silently overwritten back to 200.
function jsonResponse($data, ?int $code = null) {
    if ($code !== null) {
        http_response_code($code);
    }
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

// Row-level restriction for the self-service Student/Faculty roles: they only get
// generic requireAuth() at the routing layer (same as every other role), so without
// this every authenticated Student could list every other student's records. Admin
// and clinical roles are unaffected (returns null = no filtering).
//
// The match column differs by table because the data model was never given a real
// foreign key for "which patient is this row about" in visits/appointments/clearance
// (they identify the patient by free-text name, not an id) — matching by name is a
// real limitation (duplicate/mistyped names would over- or under-match), not
// something this pass invents a new schema to fix.
function ownerFilterFor(string $table, array $user): ?array {
    $role = $user['role'] ?? '';
    if (!in_array($role, ['Student', 'Faculty and Staff'], true)) {
        return null;
    }

    $linkedId = trim((string)($user['linkedRecordId'] ?? ''));
    $none = ['__column__' => 'id', '__value__' => '__unlinked__'];
    if ($linkedId === '') {
        return $none;
    }

    if ($role === 'Student') {
        $record = dbGetById('students', $linkedId);
        if (!$record) return $none;
        return match ($table) {
            'students' => ['__column__' => 'id', '__value__' => $linkedId],
            'medicalrecords', 'medicalhistory' => ['__column__' => 'studentId', '__value__' => $record['studentId'] ?? '__unlinked__'],
            // Prefer the studentId link (Phase 17) when the row has one; fall back
            // to the pre-existing name match for rows created before that column
            // existed, or where auto-linking at create time couldn't find a unique match.
            'visits', 'appointments' => ['__column__' => 'studentId', '__value__' => $linkedId, '__fallback__' => ['__column__' => 'patientName', '__value__' => $record['name'] ?? '__unlinked__']],
            'clearance' => ['__column__' => 'studentId', '__value__' => $linkedId, '__fallback__' => ['__column__' => 'name', '__value__' => $record['name'] ?? '__unlinked__']],
            default => $none,
        };
    }

    // Faculty and Staff
    $record = dbGetById('staff', $linkedId);
    if (!$record) return $none;
    return match ($table) {
        'staff' => ['__column__' => 'id', '__value__' => $linkedId],
        'employee_medical_record', 'employee_visit', 'employee_medicine' => ['__column__' => 'staffId', '__value__' => $linkedId],
        'visits', 'appointments' => ['__column__' => 'staffId', '__value__' => $linkedId, '__fallback__' => ['__column__' => 'patientName', '__value__' => $record['name'] ?? '__unlinked__']],
        'clearance' => ['__column__' => 'staffId', '__value__' => $linkedId, '__fallback__' => ['__column__' => 'name', '__value__' => $record['name'] ?? '__unlinked__']],
        default => $none,
    };
}

// A row matches an ownerFilterFor() filter if it matches the primary column, or
// (when the row has no value in that column — pre-migration legacy data, or a
// create that couldn't auto-link) it matches the fallback name-based column.
function rowMatchesOwnerFilter(array $row, array $filter): bool {
    $primary = $row[$filter['__column__']] ?? null;
    if ($primary === $filter['__value__'] && $primary !== null && $primary !== '') {
        return true;
    }
    if (isset($filter['__fallback__']) && ($primary === null || $primary === '')) {
        $fb = $filter['__fallback__'];
        return ($row[$fb['__column__']] ?? null) === $fb['__value__'];
    }
    return false;
}

function handleResourceList(string $table, array $user) {
    $items = dbGetAll($table);
    $filter = ownerFilterFor($table, $user);
    if ($filter) {
        $items = array_values(array_filter($items, fn($row) => rowMatchesOwnerFilter($row, $filter)));
    }
    jsonResponse($items);
}

function handleResourceGet(string $table, string $id, array $user) {
    $item = dbGetById($table, $id);
    if (!$item) jsonResponse(['error' => 'Not found'], 404);
    $filter = ownerFilterFor($table, $user);
    if ($filter && !rowMatchesOwnerFilter($item, $filter)) {
        jsonResponse(['error' => 'Not found'], 404);
    }
    jsonResponse($item);
}

// Best-effort auto-link: when a visit/appointment/clearance record is created
// for a Student or Faculty/Staff patient and no explicit studentId/staffId was
// given, look up a uniquely-matching students/staff row by exact name and
// stamp the id — so ownerFilterFor() can match by id instead of by name for
// records created going forward, without requiring every caller to know the
// linked id upfront. Ambiguous (non-unique) or unmatched names are left alone;
// they still work via the existing name-based fallback.
function autoLinkPatientRecord(string $table, array $data): array {
    if (!in_array($table, ['visits', 'appointments', 'clearance'], true)) {
        return $data;
    }
    $nameKey = $table === 'clearance' ? 'name' : 'patientName';
    $typeKey = $table === 'clearance' ? 'personType' : 'patientType';
    $name = trim((string)($data[$nameKey] ?? ''));
    if ($name === '') {
        return $data;
    }
    $type = (string)($data[$typeKey] ?? '');

    if (empty($data['studentId']) && (stripos($type, 'Student') !== false || $type === '')) {
        $matches = array_values(array_filter(dbGetAll('students'), fn($s) => strcasecmp(trim($s['name'] ?? ''), $name) === 0));
        if (count($matches) === 1) {
            $data['studentId'] = $matches[0]['id'];
        }
    }
    if (empty($data['staffId']) && (stripos($type, 'Faculty') !== false || stripos($type, 'Staff') !== false)) {
        $matches = array_values(array_filter(dbGetAll('staff'), fn($s) => strcasecmp(trim($s['name'] ?? ''), $name) === 0));
        if (count($matches) === 1) {
            $data['staffId'] = $matches[0]['id'];
        }
    }
    return $data;
}

function handleResourceCreate(string $table, array $input, array $user) {
    if ($table === 'users' && isset($input['password']) && $input['password'] !== '') {
        $input['password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }
    $input = autoLinkPatientRecord($table, $input);
    $id = $input['id'] ?? (bin2hex(random_bytes(12)));
    $data = array_merge(['id' => $id], $input);
    $item = dbCreate($table, $data);
    dbLogAudit($user, 'create', $table, $item['id'] ?? $id);
    jsonResponse($item, 201);
}

function handleResourceUpdate(string $table, string $id, array $input, array $user) {
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
    dbLogAudit($user, 'update', $table, $id);
    jsonResponse($item);
}

function handleResourceDelete(string $table, string $id, array $user) {
    $deleted = dbDelete($table, $id);
    if (!$deleted) jsonResponse(['error' => 'Not found'], 404);
    dbLogAudit($user, 'delete', $table, $id);
    jsonResponse(['ok' => true]);
}

function handleReports() {
    $tables = [
        'students', 'medicalRecords', 'medicalHistory', 'visits', 'medicine', 'dispensing',
        'appointments', 'doctorSchedule', 'incidents', 'emergencyTreatment', 'staff',
        'employeeMedicalRecord', 'employeeVisit', 'employeeMedicine', 'programs',
        'participants', 'attendance', 'assessment', 'clearance',
    ];
    $reportTableNames = [
        'doctorSchedule' => 'doctor_schedule',
        'emergencyTreatment' => 'emergency_treatment',
        'employeeMedicalRecord' => 'employee_medical_record',
        'employeeVisit' => 'employee_visit',
        'employeeMedicine' => 'employee_medicine',
    ];
    $data = [];
    foreach ($tables as $t) {
        $data[$t] = dbGetAll($reportTableNames[$t] ?? $t);
    }
    $data['medicine'] = array_map(function($m) {
        $m['stock'] = (int)($m['stock'] ?? 0);
        $m['reorderLevel'] = (int)($m['reorderLevel'] ?? 0);
        return $m;
    }, $data['medicine'] ?? []);

    $auditLogs = dbGetAll('audit_logs');
    $loginAttempts = array_values(array_filter($auditLogs, fn($a) => str_starts_with($a['action'] ?? '', 'login.')));
    $data['auditTrail'] = $auditLogs;
    $data['loginAttempts'] = $loginAttempts;

    // Named/aggregated reports from the module design doc, derived from the data
    // already fetched above (no extra queries) rather than raw per-table dumps.
    $today = date('Y-m-d');
    $weekStart = (new DateTime('monday this week'))->format('Y-m-d');
    $monthStart = date('Y-m-01');
    $in30Days = date('Y-m-d', strtotime('+30 days'));

    // Module 2: Clinic Visit & Consultation
    $data['dailyPatients'] = array_values(array_filter($data['visits'], fn($v) => ($v['date'] ?? '') === $today));
    $data['weeklyPatients'] = array_values(array_filter($data['visits'], fn($v) => ($v['date'] ?? '') >= $weekStart));
    $data['monthlyVisits'] = array_values(array_filter($data['visits'], fn($v) => ($v['date'] ?? '') >= $monthStart));
    $illnessCounts = [];
    foreach ($data['visits'] as $v) {
        $dx = trim($v['diagnosis'] ?? '');
        if ($dx === '') continue;
        $illnessCounts[$dx] = ($illnessCounts[$dx] ?? 0) + 1;
    }
    arsort($illnessCounts);
    $data['mostCommonIllnesses'] = array_map(fn($dx, $count) => ['diagnosis' => $dx, 'visitCount' => $count], array_keys($illnessCounts), $illnessCounts);

    // Module 3: Medicine Inventory & Dispensing
    $data['expiredMedicines'] = array_values(array_filter($data['medicine'], fn($m) => !empty($m['expiryDate']) && $m['expiryDate'] < $today));
    $data['nearExpirationMedicines'] = array_values(array_filter($data['medicine'], fn($m) => !empty($m['expiryDate']) && $m['expiryDate'] >= $today && $m['expiryDate'] <= $in30Days));
    $data['lowStockMedicines'] = array_values(array_filter($data['medicine'], fn($m) => $m['stock'] <= $m['reorderLevel']));
    $data['medicineUsage'] = array_merge($data['dispensing'], $data['employeeMedicine']);
    $dispenseCounts = [];
    foreach ($data['medicineUsage'] as $d) {
        $name = $d['medicineName'] ?? 'Unknown';
        $dispenseCounts[$name] = ($dispenseCounts[$name] ?? 0) + (int)($d['quantity'] ?? 0);
    }
    arsort($dispenseCounts);
    $data['mostDispensedMedicine'] = array_map(fn($name, $qty) => ['medicine' => $name, 'totalDispensed' => $qty], array_keys($dispenseCounts), $dispenseCounts);

    // Module 4: Appointment Scheduling
    $data['todaysAppointments'] = array_values(array_filter($data['appointments'], fn($a) => ($a['date'] ?? '') === $today));
    $data['missedAppointments'] = array_values(array_filter($data['appointments'], fn($a) => in_array($a['status'] ?? '', ['No Show', 'Missed'], true)));
    $data['completedAppointments'] = array_values(array_filter($data['appointments'], fn($a) => ($a['status'] ?? '') === 'Completed'));
    $data['cancelledAppointments'] = array_values(array_filter($data['appointments'], fn($a) => ($a['status'] ?? '') === 'Cancelled'));

    // Module 5: Incident & Emergency Case Management
    $data['emergencyCases'] = array_values(array_filter($data['incidents'], fn($i) => in_array(strtolower($i['severity'] ?? ''), ['high', 'critical'], true)));
    $data['incidentReferrals'] = array_values(array_filter($data['incidents'], fn($i) => stripos($i['actionTaken'] ?? '', 'referr') !== false));
    $severityCounts = [];
    foreach ($data['incidents'] as $i) {
        $sev = $i['severity'] ?? 'Unspecified';
        $severityCounts[$sev] = ($severityCounts[$sev] ?? 0) + 1;
    }
    $data['accidentStatistics'] = array_map(fn($sev, $count) => ['severity' => $sev, 'incidentCount' => $count], array_keys($severityCounts), $severityCounts);

    // Module 7: School Health Program Monitoring
    $programsById = [];
    foreach ($data['programs'] as $p) { $programsById[$p['id']] = $p; }
    $data['vaccinationParticipants'] = array_values(array_filter($data['participants'], function($p) use ($programsById) {
        $prog = $programsById[$p['programId']] ?? null;
        return $prog && stripos($prog['category'] ?? '', 'vaccin') !== false;
    }));
    $data['completedPrograms'] = array_values(array_filter($data['programs'], fn($p) => ($p['status'] ?? '') === 'Completed'));

    // Module 8: Health Clearance & Certification
    $data['issuedCertificates'] = array_values(array_filter($data['clearance'], fn($c) => in_array($c['status'] ?? '', ['Valid', 'Issued'], true)));
    $data['pendingCertificates'] = array_values(array_filter($data['clearance'], fn($c) => ($c['status'] ?? '') === 'Pending'));

    $lowStock = array_filter($data['medicine'], fn($m) => $m['stock'] <= $m['reorderLevel']);
    $openIncidents = array_filter($data['incidents'] ?? [], fn($i) => $i['status'] !== 'Resolved');
    $expiredClearance = array_filter($data['clearance'] ?? [], fn($c) => $c['status'] === 'Expired');
    $pendingAppts = array_filter($data['appointments'] ?? [], fn($a) => $a['status'] === 'Pending');
    $failedLogins = array_filter($loginAttempts, fn($a) => ($a['action'] ?? '') !== 'login.success');

    jsonResponse([
        'records' => $data,
        'summary' => [
            'lowStock' => count($lowStock),
            'openIncidents' => count($openIncidents),
            'expiredClearance' => count($expiredClearance),
            'pendingAppointments' => count($pendingAppts),
            'auditEvents' => count($auditLogs),
            'failedLogins' => count($failedLogins),
        ],
    ]);
}

const BACKUP_TABLES = [
    'users', 'students', 'medicalrecords', 'medicalhistory', 'visits', 'medicine', 'appointments',
    'doctor_schedule', 'incidents', 'emergency_treatment', 'staff', 'employee_medical_record',
    'employee_visit', 'employee_medicine', 'programs', 'participants', 'attendance', 'assessment',
    'clearance', 'audit_logs', 'ai_requests', 'permissions', 'role_permissions',
];

function handleBackup(): void {
    $user = requirePermission('manage_users');
    $backup = ['version' => 1, 'exportedAt' => round(microtime(true) * 1000), 'tables' => []];
    foreach (BACKUP_TABLES as $t) {
        $backup['tables'][$t] = dbGetAll($t);
    }
    dbLogAudit($user, 'backup.created', 'system', '', ['tableCount' => count(BACKUP_TABLES)]);
    header('Content-Disposition: attachment; filename="clinic_backup_' . date('Y-m-d_His') . '.json"');
    jsonResponse($backup);
}

// Full replace per table (delete-then-reinsert), not merge — matches standard
// restore-from-backup semantics. Original row IDs are preserved (present in the
// backup's rows) so cross-table references stay intact.
function handleRestore(): void {
    $user = requirePermission('manage_users');
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $tablesData = $input['tables'] ?? null;
    if (!is_array($tablesData)) {
        jsonResponse(['error' => 'Invalid backup file: missing "tables"'], 400);
    }

    $pdo = getDbConnection();
    $restoredCounts = [];
    foreach ($tablesData as $table => $rows) {
        if (!in_array($table, BACKUP_TABLES, true) || !is_array($rows)) {
            continue;
        }
        $pdo->exec('DELETE FROM ' . escapeIdentifier($table));
        foreach ($rows as $row) {
            if (is_array($row)) {
                dbCreate($table, $row);
            }
        }
        $restoredCounts[$table] = count($rows);
    }

    dbLogAudit($user, 'backup.restored', 'system', '', ['restoredCounts' => $restoredCounts]);
    jsonResponse(['ok' => true, 'restoredCounts' => $restoredCounts]);
}

// Student/Faculty are self-service roles — the full clinic-wide dashboard (every
// patient's visits, incidents, upcoming appointments) would otherwise leak through
// here since /dashboard only required requireAuth(), not a role check.
function handleSelfServiceDashboard(array $user): void {
    $role = $user['role'] ?? '';
    $ownRecord = null;
    if ($role === 'Student') {
        $ownRecord = !empty($user['linkedRecordId']) ? dbGetById('students', $user['linkedRecordId']) : null;
    } elseif ($role === 'Faculty and Staff') {
        $ownRecord = !empty($user['linkedRecordId']) ? dbGetById('staff', $user['linkedRecordId']) : null;
    }
    $name = $ownRecord['name'] ?? '';

    $myAppointments = $name === '' ? [] : array_values(array_filter(dbGetAll('appointments'), fn($a) => ($a['patientName'] ?? '') === $name));
    $today = date('Y-m-d');
    $upcoming = array_values(array_filter($myAppointments, fn($a) => ($a['date'] ?? '') >= $today && ($a['status'] ?? '') !== 'Cancelled'));
    usort($upcoming, fn($a, $b) => strcmp(($a['date'] ?? '') . ($a['time'] ?? ''), ($b['date'] ?? '') . ($b['time'] ?? '')));

    jsonResponse([
        'selfService' => true,
        'linked' => $ownRecord !== null,
        'upcomingAppointments' => array_slice($upcoming, 0, 5),
        'appointmentHistory' => $myAppointments,
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
    $folderRows = dbQuery('SELECT name, meta, created_at FROM record_folders');
    $byName = [];
    foreach ($folderRows as $row) {
        $meta = $row['meta'] ? json_decode($row['meta'], true) : null;
        $byName[$row['name']] = [
            'name' => $row['name'],
            'createdAt' => isset($row['created_at']) ? (int)$row['created_at'] : null,
            'meta' => is_array($meta) ? $meta : null,
        ];
    }
    // Include folders that only exist because a medical record references them
    // (e.g. legacy data created before the record_folders table existed).
    $legacyRows = dbQuery('SELECT groupfolder, MIN(created_at) AS created_at FROM medicalrecords WHERE groupfolder IS NOT NULL AND groupfolder != "" GROUP BY groupfolder');
    foreach ($legacyRows as $row) {
        $name = $row['groupfolder'];
        if (!isset($byName[$name])) {
            $byName[$name] = [
                'name' => $name,
                'createdAt' => isset($row['created_at']) ? (int)$row['created_at'] : null,
                'meta' => null,
            ];
        }
    }
    $result = array_values($byName);
    usort($result, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
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
    if ($name === '') {
        jsonResponse(['error' => 'Invalid folder name'], 400);
    }
    $existing = dbQuery('SELECT name FROM record_folders WHERE name = :name', [':name' => $name]);
    if (!empty($existing)) {
        jsonResponse(['error' => 'Folder already exists'], 409);
    }
    $meta = [];
    foreach (['yearLevel','department','type','description'] as $k) {
        if (isset($input[$k]) && $input[$k] !== '') $meta[$k] = $input[$k];
    }
    $meta['name'] = $name;
    $createdAt = time();
    $meta['createdAt'] = $createdAt;
    $stmt = getDbConnection()->prepare('INSERT INTO record_folders (name, meta, created_at) VALUES (:name, :meta, :created_at)');
    $stmt->execute([
        ':name' => $name,
        ':meta' => json_encode($meta),
        ':created_at' => $createdAt,
    ]);
    jsonResponse(['ok' => true, 'name' => $name, 'meta' => $meta], 201);
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
    $db = getDbConnection();
    $existingRow = dbQuery('SELECT name, meta FROM record_folders WHERE name = :name', [':name' => $currentName]);
    if (empty($existingRow)) {
        jsonResponse(['error' => 'Source folder not found'], 404);
    }
    $conflict = dbQuery('SELECT name FROM record_folders WHERE name = :name', [':name' => $newName]);
    if (!empty($conflict)) {
        jsonResponse(['error' => 'Target folder already exists'], 409);
    }
    $meta = $existingRow[0]['meta'] ? json_decode($existingRow[0]['meta'], true) : [];
    if (!is_array($meta)) $meta = [];
    $meta['name'] = $newName;

    $stmt = $db->prepare('UPDATE record_folders SET name = :newName, meta = :meta WHERE name = :currentName');
    $stmt->execute([':newName' => $newName, ':meta' => json_encode($meta), ':currentName' => $currentName]);

    // Update any medical records that referenced the old folder name.
    try {
        $sql = 'UPDATE ' . escapeIdentifier('medicalrecords') . ' SET ' . escapeIdentifier('groupfolder') . ' = :newName WHERE ' . escapeIdentifier('groupfolder') . ' = :currentName';
        $stmt2 = $db->prepare($sql);
        $stmt2->execute([':newName' => $newName, ':currentName' => $currentName]);
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
    'dispensing' => 'dispensing',
    'emergencyTreatment' => 'emergency_treatment',
    'employeeMedicalRecord' => 'employee_medical_record',
    'employeeVisit' => 'employee_visit',
    'employeeMedicine' => 'employee_medicine',
    'participants' => 'participants',
    'attendance' => 'attendance',
    'assessment' => 'assessment',
    'doctorSchedule' => 'doctor_schedule',
    'privacyConsents' => 'privacy_consents',
    'permissions' => 'permissions',
    'roles' => 'roles',
    'rolePermissions' => 'role_permissions',
    'users' => 'users',
];

// Resources whose access is gated by 'manage_users' rather than a per-resource
// ':write' permission — account management and the RBAC catalog itself.
const ADMIN_ONLY_RESOURCES = ['users', 'permissions', 'rolePermissions', 'roles'];

// Rejects double-booking: same doctor, date, and time already Pending/Confirmed.
// If the doctor has declared availability for that date, the requested time must
// match a non-Booked slot; if no schedule exists for that date, skip that check
// (so appointments aren't blocked before any doctor availability has been entered).
function handleAppointmentCreate(array $input, array $user) {
    $role = $user['role'] ?? '';
    if (in_array($role, ['Student', 'Faculty and Staff'], true)) {
        $table = $role === 'Student' ? 'students' : 'staff';
        $ownRecord = !empty($user['linkedRecordId']) ? dbGetById($table, $user['linkedRecordId']) : null;
        if (!$ownRecord) {
            jsonResponse(['error' => 'Your account is not linked to a record. Contact your administrator.'], 403);
        }
        // Force the booking onto their own identity — a self-service user cannot
        // book on someone else's behalf regardless of what the request body says.
        $input['patientName'] = $ownRecord['name'] ?? '';
        $input['patientType'] = $role === 'Student' ? 'Student' : 'Faculty/Staff';
        $input['status'] = 'Pending';
        // Stamp the link id directly from the account's own linkedRecordId — no
        // need for autoLinkPatientRecord()'s name-lookup guess, we already know it.
        if ($role === 'Student') {
            $input['studentId'] = $user['linkedRecordId'];
        } else {
            $input['staffId'] = $user['linkedRecordId'];
        }
    }

    $doctorId = trim((string)($input['doctorId'] ?? ''));
    $date = trim((string)($input['date'] ?? ''));
    $time = trim((string)($input['time'] ?? ''));

    if ($doctorId !== '' && $date !== '' && $time !== '') {
        foreach (dbGetAll('appointments') as $existing) {
            if (($existing['doctorId'] ?? '') === $doctorId
                && ($existing['date'] ?? '') === $date
                && ($existing['time'] ?? '') === $time
                && in_array($existing['status'] ?? '', ['Pending', 'Confirmed'], true)) {
                jsonResponse(['error' => 'This doctor already has an appointment at that date and time'], 409);
            }
        }

        $daySchedule = array_values(array_filter(dbGetAll('doctor_schedule'), function($s) use ($doctorId, $date) {
            return ($s['doctorId'] ?? '') === $doctorId && ($s['date'] ?? '') === $date;
        }));
        if (count($daySchedule) > 0) {
            $matching = null;
            foreach ($daySchedule as $s) {
                if (($s['availableTime'] ?? '') === $time) {
                    $matching = $s;
                    break;
                }
            }
            if (!$matching) {
                jsonResponse(['error' => 'This doctor has no declared availability at that time'], 409);
            }
            if (($matching['status'] ?? 'Available') !== 'Available') {
                jsonResponse(['error' => 'This time slot is already booked'], 409);
            }
        }
    }

    handleResourceCreate('appointments', $input, $user);
}

// Signature covers exactly what the verifier re-checks, so any later edit to type/
// expiry/status naturally invalidates old signatures without needing extra bookkeeping.
function certificateSignature(string $id, array $cert): string {
    $cfg = getConfig();
    $payload = $id . '|' . ($cert['clearanceType'] ?? '') . '|' . ($cert['expiryDate'] ?? '') . '|' . ($cert['status'] ?? '');
    return substr(hash_hmac('sha256', $payload, $cfg['jwt_secret']), 0, 16);
}

function handleClearanceCreate(array $input, array $user) {
    $id = $input['id'] ?? bin2hex(random_bytes(12));
    $input['id'] = $id;
    $input['qrCode'] = $id . '.' . certificateSignature($id, $input);
    handleResourceCreate('clearance', $input, $user);
}

function handleClearanceUpdate(string $id, array $input, array $user) {
    $existing = dbGetById('clearance', $id);
    if (!$existing) {
        jsonResponse(['error' => 'Not found'], 404);
    }
    $merged = array_merge($existing, $input);
    $input['qrCode'] = $id . '.' . certificateSignature($id, $merged);
    handleResourceUpdate('clearance', $id, $input, $user);
}

// Public: no auth required (the point of a QR-verifiable certificate is that
// whoever holds the QR code can prove validity without a system login). Only
// returns non-sensitive fields, not full health data.
function handleVerifyCertificate(): void {
    $code = trim((string)($_GET['code'] ?? ''));
    $parts = explode('.', $code, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        jsonResponse(['valid' => false, 'reason' => 'Malformed verification code']);
    }
    [$id, $sig] = $parts;
    $cert = dbGetById('clearance', $id);
    if (!$cert) {
        jsonResponse(['valid' => false, 'reason' => 'Certificate not found']);
    }
    if (!hash_equals(certificateSignature($id, $cert), $sig)) {
        jsonResponse(['valid' => false, 'reason' => 'Invalid or tampered code']);
    }
    $isExpiredByDate = !empty($cert['expiryDate']) && $cert['expiryDate'] < date('Y-m-d');
    jsonResponse([
        'valid' => !$isExpiredByDate && ($cert['status'] ?? '') !== 'Expired',
        'name' => $cert['name'] ?? '',
        'type' => $cert['clearanceType'] ?? '',
        'status' => $cert['status'] ?? '',
        'issuedDate' => $cert['dateIssued'] ?? '',
        'expiryDate' => $cert['expiryDate'] ?? '',
    ]);
}

// Shared by 'dispensing' (students) and 'employeeMedicine' (staff): checks stock,
// creates the dispense-log row, deducts medicine.stock, logs the audit trail.
function dispenseMedicine(string $table, string $subjectIdKey, string $subjectNameKey, array $input, array $user) {
    $medicineId = trim((string)($input['medicineId'] ?? ''));
    $quantity = (int)($input['quantity'] ?? 0);
    if ($medicineId === '' || $quantity <= 0) {
        jsonResponse(['error' => 'medicineId and a positive quantity are required'], 400);
    }

    $medicine = dbGetById('medicine', $medicineId);
    if (!$medicine) {
        jsonResponse(['error' => 'Medicine not found'], 404);
    }

    $currentStock = (int)($medicine['stock'] ?? 0);
    if ($quantity > $currentStock) {
        jsonResponse(['error' => 'Insufficient stock: only ' . $currentStock . ' available'], 409);
    }

    $data = [
        'id' => bin2hex(random_bytes(12)),
        $subjectIdKey => $input[$subjectIdKey] ?? '',
        $subjectNameKey => $input[$subjectNameKey] ?? '',
        'medicineId' => $medicineId,
        'medicineName' => $medicine['name'] ?? '',
        'quantity' => $quantity,
        'dateReleased' => $input['dateReleased'] ?? date('Y-m-d'),
        'releasedBy' => $user['fullName'] ?? $user['username'] ?? '',
    ];
    $item = dbCreate($table, $data);
    dbUpdate('medicine', $medicineId, ['stock' => $currentStock - $quantity]);
    dbLogAudit($user, 'create', $table, $item['id'] ?? $data['id']);
    jsonResponse($item, 201);
}

function handleDispenseCreate(array $input, array $user) {
    dispenseMedicine('dispensing', 'studentId', 'patientName', $input, $user);
}

function handleEmployeeDispenseCreate(array $input, array $user) {
    dispenseMedicine('employee_medicine', 'staffId', 'staffName', $input, $user);
}

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

    $result = callGemini($payload);

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
    } elseif ($path === '/login/verify-2fa') {
        jsonResponse(handleVerifyTwoFactor());
    } elseif ($path === '/2fa/setup') {
        jsonResponse(handleTwoFactorSetup(requireAuth()));
    } elseif ($path === '/2fa/confirm') {
        jsonResponse(handleTwoFactorConfirm(requireAuth()));
    } elseif ($path === '/2fa/disable') {
        jsonResponse(handleTwoFactorDisable(requireAuth()));
    } elseif ($path === '/verifyCertificate') {
        handleVerifyCertificate();
    } elseif ($path === '/dashboard') {
        $dashUser = requireAuth();
        if (in_array($dashUser['role'] ?? '', ['Student', 'Faculty and Staff'], true)) {
            handleSelfServiceDashboard($dashUser);
        } else {
            handleDashboard();
        }
    } elseif ($path === '/reports') {
        requirePermission('view_reports');
        handleReports();
    } elseif ($path === '/backup') {
        handleBackup();
    } elseif ($path === '/restore') {
        handleRestore();
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
    } elseif ($path === '/seed/permissions') {
        requirePermission('manage_users');
        seedPermissions();
        jsonResponse(['ok' => true, 'message' => 'Permissions and role grants seeded']);
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
        if (in_array($resource, ADMIN_ONLY_RESOURCES, true)) {
            $user = requirePermission('manage_users');
        } elseif ($method !== 'GET') {
            $user = requirePermission($resource . ':write');
        } else {
            $user = requireAuth();
        }
        $table = $resourceMap[$resource];
        switch ($method) {
            case 'GET':
                handleResourceList($table, $user);
                break;
            case 'POST':
                if ($resource === 'dispensing') {
                    handleDispenseCreate($input, $user);
                } elseif ($resource === 'employeeMedicine') {
                    handleEmployeeDispenseCreate($input, $user);
                } elseif ($resource === 'appointments') {
                    handleAppointmentCreate($input, $user);
                } elseif ($resource === 'clearance') {
                    handleClearanceCreate($input, $user);
                } else {
                    handleResourceCreate($table, $input, $user);
                }
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
        if (in_array($resource, ADMIN_ONLY_RESOURCES, true)) {
            $user = requirePermission('manage_users');
        } elseif ($method !== 'GET') {
            $user = requirePermission($resource . ':write');
        } else {
            $user = requireAuth();
        }
        if (in_array($resource, ['dispensing', 'employeeMedicine'], true) && in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            jsonResponse(['error' => 'Dispensing records are an immutable transaction log and cannot be modified'], 405);
        }
        $table = $resourceMap[$resource];
        switch ($method) {
            case 'GET':
                handleResourceGet($table, $id, $user);
                break;
            case 'PUT':
            case 'PATCH':
                if ($resource === 'clearance') {
                    handleClearanceUpdate($id, $input, $user);
                } else {
                    handleResourceUpdate($table, $id, $input, $user);
                }
                break;
            case 'DELETE':
                handleResourceDelete($table, $id, $user);
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

// Idempotent per-row: safe to call again after a new phase adds permissions —
// only missing permissions/grants are inserted, existing ones are left alone.
function seedPermissions(): void {
    $perms = [
        ['id' => 'perm_manage_users', 'module' => 'manage_users', 'action' => '', 'description' => 'Manage user accounts'],
        ['id' => 'perm_view_reports', 'module' => 'view_reports', 'action' => '', 'description' => 'View reports and dashboard analytics'],
        ['id' => 'perm_use_ai_assistant', 'module' => 'use_ai_assistant', 'action' => '', 'description' => 'Use the AI diagnosis assistant'],
        ['id' => 'perm_access_patient_records', 'module' => 'access_patient_records', 'action' => '', 'description' => 'Access patient medical records'],
        ['id' => 'perm_students_write', 'module' => 'students', 'action' => 'write', 'description' => 'Create/update/delete student records'],
        ['id' => 'perm_medicalrecords_write', 'module' => 'medicalRecords', 'action' => 'write', 'description' => 'Create/update/delete medical records'],
        ['id' => 'perm_medicalhistory_write', 'module' => 'medicalHistory', 'action' => 'write', 'description' => 'Create/update/delete medical history'],
        ['id' => 'perm_visits_write', 'module' => 'visits', 'action' => 'write', 'description' => 'Create/update/delete clinic visits'],
        ['id' => 'perm_medicine_write', 'module' => 'medicine', 'action' => 'write', 'description' => 'Create/update/delete medicine inventory'],
        ['id' => 'perm_dispensing_write', 'module' => 'dispensing', 'action' => 'write', 'description' => 'Dispense medicine to a patient'],
        ['id' => 'perm_appointments_write', 'module' => 'appointments', 'action' => 'write', 'description' => 'Create/update/delete appointments'],
        ['id' => 'perm_doctorschedule_write', 'module' => 'doctorSchedule', 'action' => 'write', 'description' => 'Manage doctor availability schedule'],
        ['id' => 'perm_privacyconsents_write', 'module' => 'privacyConsents', 'action' => 'write', 'description' => 'Record and update privacy consent status'],
        ['id' => 'perm_incidents_write', 'module' => 'incidents', 'action' => 'write', 'description' => 'Create/update/delete incidents'],
        ['id' => 'perm_emergencytreatment_write', 'module' => 'emergencyTreatment', 'action' => 'write', 'description' => 'Record emergency treatment given for an incident'],
        ['id' => 'perm_staff_write', 'module' => 'staff', 'action' => 'write', 'description' => 'Create/update/delete staff records'],
        ['id' => 'perm_employeemedicalrecord_write', 'module' => 'employeeMedicalRecord', 'action' => 'write', 'description' => 'Create/update employee medical records'],
        ['id' => 'perm_employeevisit_write', 'module' => 'employeeVisit', 'action' => 'write', 'description' => 'Log employee clinic visits'],
        ['id' => 'perm_employeemedicine_write', 'module' => 'employeeMedicine', 'action' => 'write', 'description' => 'Dispense medicine to an employee'],
        ['id' => 'perm_programs_write', 'module' => 'programs', 'action' => 'write', 'description' => 'Create/update/delete health programs'],
        ['id' => 'perm_participants_write', 'module' => 'participants', 'action' => 'write', 'description' => 'Assign participants to a health program'],
        ['id' => 'perm_attendance_write', 'module' => 'attendance', 'action' => 'write', 'description' => 'Record health program attendance'],
        ['id' => 'perm_assessment_write', 'module' => 'assessment', 'action' => 'write', 'description' => 'Record health program assessment results'],
        ['id' => 'perm_clearance_write', 'module' => 'clearance', 'action' => 'write', 'description' => 'Create/update/delete clearances/certificates'],
    ];

    $existingPerms = dbGetAll('permissions');
    $existingPermIds = array_flip(array_column($existingPerms, 'id'));
    foreach ($perms as $p) {
        if (!isset($existingPermIds[$p['id']])) {
            dbCreate('permissions', $p);
        }
    }

    // Matches the Role and Access Summary in the module design doc.
    $roleGrants = [
        'Clinic Administrator' => ['manage_users', 'view_reports', 'use_ai_assistant', 'access_patient_records', 'students', 'medicalRecords', 'medicalHistory', 'visits', 'medicine', 'dispensing', 'appointments', 'doctorSchedule', 'incidents', 'emergencyTreatment', 'staff', 'employeeMedicalRecord', 'employeeVisit', 'employeeMedicine', 'programs', 'participants', 'attendance', 'assessment', 'clearance', 'privacyConsents'],
        // Module 4 (Appointment Scheduling), Module 5 (Incident/Emergency), Module 6
        // (Faculty & Staff Health), and Module 7 (Health Program Monitoring) all list
        // School Nurse and/or Physician as users per the design doc's Role and Access
        // Summary — grants added here to match (Module 4 lists Doctor, not Nurse).
        'School Nurse' => ['view_reports', 'use_ai_assistant', 'access_patient_records', 'visits', 'medicalRecords', 'medicalHistory', 'dispensing', 'incidents', 'emergencyTreatment', 'staff', 'employeeMedicalRecord', 'employeeVisit', 'employeeMedicine', 'programs', 'participants', 'attendance', 'assessment', 'clearance', 'privacyConsents'],
        'Physician' => ['view_reports', 'use_ai_assistant', 'access_patient_records', 'visits', 'medicalRecords', 'medicalHistory', 'dispensing', 'appointments', 'doctorSchedule', 'incidents', 'emergencyTreatment', 'staff', 'employeeMedicalRecord', 'employeeVisit', 'employeeMedicine', 'programs', 'participants', 'attendance', 'assessment', 'clearance'],
        'Staff Encoder' => ['view_reports', 'access_patient_records', 'students', 'visits', 'medicine', 'dispensing', 'appointments', 'doctorSchedule', 'incidents', 'emergencyTreatment', 'staff', 'employeeMedicalRecord', 'employeeVisit', 'employeeMedicine', 'programs', 'participants', 'attendance', 'assessment', 'privacyConsents'],
        // New self-service roles (Module 4/6/8's "Student"/"Faculty and Staff" users).
        // Read access to their own records is enforced by row-level filtering in
        // handleResourceList/handleResourceGet (see ownerFilterFor()), not by a
        // write permission — these roles only need a WRITE grant for the one thing
        // the spec has them doing: booking their own appointment.
        'Student' => ['appointments'],
        'Faculty and Staff' => ['appointments'],
    ];
    $permIdByModule = [];
    foreach ($perms as $p) {
        $permIdByModule[$p['module']] = $p['id'];
    }

    $existingGrants = [];
    foreach (dbGetAll('role_permissions') as $g) {
        $existingGrants[($g['role'] ?? '') . '|' . ($g['permissionId'] ?? '')] = true;
    }
    foreach ($roleGrants as $role => $modules) {
        foreach ($modules as $module) {
            $permId = $permIdByModule[$module];
            $key = $role . '|' . $permId;
            if (!isset($existingGrants[$key])) {
                dbCreate('role_permissions', ['role' => $role, 'permission_id' => $permId]);
            }
        }
    }
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

function handleRecordFoldersDelete() {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        jsonResponse(['error' => 'name is required'], 400);
    }
    $db = getDbConnection();
    $stmt = $db->prepare('DELETE FROM record_folders WHERE name = :name');
    $stmt->execute([':name' => $name]);

    // Records inside remain, but no longer belong to a tracked folder entry;
    // leave their groupfolder value intact so they keep showing under that name
    // if it still exists in medicalrecords (matches the confirm-dialog wording).

    jsonResponse(['ok' => true]);
}

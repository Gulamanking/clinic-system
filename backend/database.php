<?php

require_once __DIR__ . '/config.php';

// Maps frontend camelCase keys to database lowercase keys (and vice versa)
function dbKeyMap(): array {
    return [
        // users
        'fullName' => 'fullname',
        // students
        'studentId' => 'studentid',
        'staffId' => 'staffid',
        'yearLevel' => 'yearlevel',
        'bloodType' => 'bloodtype',
        'contactNumber' => 'contactnumber',
        'emergencyContact' => 'emergencycontact',
        // visits
        'patientName' => 'patientname',
        'patientType' => 'patienttype',
        'nurseOnDuty' => 'nurseonduty',
        // medicine
        'reorderLevel' => 'reorderlevel',
        'expiryDate' => 'expirydate',
        // medical records
        'recordId' => 'recordid',
        'studentName' => 'studentname',
        'groupFolder' => 'groupfolder',
        'medicalConditions' => 'medicalconditions',
        'immunizationStatus' => 'immunizationstatus',
        // medical history
        'historyId' => 'historyid',
        'studentName' => 'studentname',
        'department' => 'department',
        'yearLevel' => 'yearlevel',
        'section' => 'section',
        'bloodType' => 'bloodtype',
        'course' => 'course',
        // incidents
        'caseNo' => 'caseno',
        'personInvolved' => 'personinvolved',
        'actionTaken' => 'actiontaken',
        // staff
        'healthNotes' => 'healthnotes',
        'lastCheckup' => 'lastcheckup',
        // programs
        'startDate' => 'startdate',
        'endDate' => 'enddate',
        'targetParticipants' => 'targetparticipants',
        // clearance
        'personType' => 'persontype',
        'clearanceType' => 'clearancetype',
        'dateIssued' => 'dateissued',
        'issuedBy' => 'issuedby',
        // visits (vitals)
        'bloodPressure' => 'bloodpressure',
        'pulseRate' => 'pulserate',
        'medicineDispensed' => 'medicinedispensed',
        // employee medical record / visit / dispensing
        'lastPhysicalExam' => 'lastphysicalexam',
        'staffName' => 'staffname',
        // clearance
        'qrCode' => 'qrcode',
        // roles
        'isSelfService' => 'is_self_service',
        // dispensing (medicineId/medicineName/dateReleased/releasedBy already
        // auto-convert correctly via the generic camelCase->snake_case fallback;
        // studentId/patientName reuse the students/visits mappings above)
        // appointments
        // (patientName, patientType already above)
    ];
}

function normalizeTableName(string $table): string {
    $map = [
        'medicalRecords' => 'medicalrecords',
        'medicalHistory' => 'medicalhistory',
    ];
    return $map[$table] ?? $table;
}

function toDbKeys(array $data): array {
    $map = dbKeyMap();
    $out = [];
    foreach ($data as $k => $v) {
        $dbKey = $map[$k] ?? strtolower(preg_replace('/([A-Z])/', '_$1', $k));
        $out[$dbKey] = $v;
    }
    $out['created_at'] = $data['created_at'] ?? $out['created_at'] ?? round(microtime(true) * 1000);
    return $out;
}

function toCamelKeys(array $data): array {
    $map = array_flip(dbKeyMap());
    $out = [];
    foreach ($data as $k => $v) {
        $camel = $map[$k] ?? lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $k))));
        $out[$camel] = $v;
    }
    if (isset($out['createdAt'])) {
        $out['createdAt'] = (int)$out['createdAt'];
    }
    return $out;
}

function getDbDriver(): string {
    $cfg = getConfig();
    $driver = trim(strtolower($cfg['db_driver'] ?? ''));
    $available = PDO::getAvailableDrivers();

    if ($driver !== '') {
        if (in_array($driver, ['pgsql', 'postgres'], true)) {
            if (!in_array('pgsql', $available, true)) {
                throw new RuntimeException('Configured database driver "pgsql" is not installed. Enable the pdo_pgsql extension.');
            }
            return 'pgsql';
        }

        if (in_array($driver, ['mysql', 'pdo_mysql'], true)) {
            if (!in_array('mysql', $available, true)) {
                throw new RuntimeException('Configured database driver "mysql" is not installed. Enable the pdo_mysql extension.');
            }
            return 'mysql';
        }

        if (in_array($driver, ['sqlite', 'pdo_sqlite'], true)) {
            if (!in_array('sqlite', $available, true)) {
                throw new RuntimeException('Configured database driver "sqlite" is not installed. Enable the pdo_sqlite extension.');
            }
            return 'sqlite';
        }

        throw new RuntimeException('Unsupported configured database driver: ' . $driver);
    }

    if (in_array('mysql', $available, true)) {
        return 'mysql';
    }

    if (in_array('pgsql', $available, true)) {
        return 'pgsql';
    }

    throw new RuntimeException('No supported PDO database driver is installed. Enable pdo_mysql or pdo_pgsql.');
}

function createMysqlDatabaseIfMissing(string $host, string $port, string $dbname, string $user, string $password): void {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . escapeIdentifier($dbname) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}

function executeSqlFile(PDO $pdo, string $filePath): void {
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new RuntimeException('Unable to read schema file: ' . $filePath);
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

function mysqlColumnExists(PDO $pdo, string $table, string $column): bool {
    $sql = 'SHOW COLUMNS FROM ' . escapeIdentifier($table) . ' LIKE ' . $pdo->quote($column);
    $stmt = $pdo->query($sql);
    return (bool)$stmt && (bool)$stmt->fetch();
}

function sqliteColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->query('PRAGMA table_info(' . escapeIdentifier($table) . ')');
    foreach ($stmt->fetchAll() as $row) {
        if (strcasecmp($row['name'], $column) === 0) {
            return true;
        }
    }
    return false;
}

function columnExists(PDO $pdo, string $driver, string $table, string $column): bool {
    if ($driver === 'mysql') return mysqlColumnExists($pdo, $table, $column);
    if ($driver === 'sqlite') return sqliteColumnExists($pdo, $table, $column);
    return true; // pgsql: not covered by this migration step, matches prior behavior
}

// CREATE TABLE IF NOT EXISTS in schema.sql only creates missing tables — it never
// adds columns to a table that already exists. New columns on existing tables go here.
function applyMigrations(PDO $pdo, string $driver): void {
    if (!in_array($driver, ['mysql', 'sqlite'], true)) {
        return;
    }

    $migrations = [
        'students' => [
            'status' => "TEXT NOT NULL DEFAULT 'Active'",
        ],
        'visits' => [
            'temperature' => "TEXT NOT NULL DEFAULT ''",
            'bloodpressure' => "TEXT NOT NULL DEFAULT ''",
            'pulserate' => "TEXT NOT NULL DEFAULT ''",
            'assessment' => "TEXT NOT NULL DEFAULT ''",
            'medicinedispensed' => "TEXT NOT NULL DEFAULT ''",
            'disposition' => "TEXT NOT NULL DEFAULT ''",
            'studentid' => "TEXT NOT NULL DEFAULT ''",
            'staffid' => "TEXT NOT NULL DEFAULT ''",
        ],
        'appointments' => [
            'doctor_id' => "TEXT NOT NULL DEFAULT ''",
            'studentid' => "TEXT NOT NULL DEFAULT ''",
            'staffid' => "TEXT NOT NULL DEFAULT ''",
        ],
        'users' => [
            'failed_login_count' => "INTEGER NOT NULL DEFAULT 0",
            'locked_until' => "BIGINT NOT NULL DEFAULT 0",
            'last_login' => "BIGINT NOT NULL DEFAULT 0",
            'two_factor_secret' => "TEXT NOT NULL DEFAULT ''",
            'two_factor_enabled' => "INTEGER NOT NULL DEFAULT 0",
            'linked_record_id' => "TEXT NOT NULL DEFAULT ''",
        ],
        'clearance' => [
            'qrcode' => "TEXT NOT NULL DEFAULT ''",
            'studentid' => "TEXT NOT NULL DEFAULT ''",
            'staffid' => "TEXT NOT NULL DEFAULT ''",
        ],
    ];

    // Track which of the new studentid/staffid columns didn't exist before this
    // call, so we only backfill them once (right after they're added) rather
    // than re-running the name-match backfill on every request.
    $justAdded = [];
    foreach ($migrations as $table => $columns) {
        foreach ($columns as $column => $definition) {
            if (!columnExists($pdo, $driver, $table, $column)) {
                $pdo->exec('ALTER TABLE ' . escapeIdentifier($table) . ' ADD COLUMN ' . escapeIdentifier($column) . ' ' . $definition);
                if (in_array($column, ['studentid', 'staffid'], true) && in_array($table, ['visits', 'appointments', 'clearance'], true)) {
                    $justAdded[$table][] = $column;
                }
            }
        }
    }

    if (!empty($justAdded)) {
        backfillPatientLinkIds($pdo, $justAdded);
    }
}

// One-time best-effort backfill: for pre-existing visits/appointments/clearance
// rows that predate the studentid/staffid columns, link them to a students/staff
// row by exact (case-insensitive) name match — but only when the name is unique,
// since a mistaken link is worse than leaving the row unlinked (it'd fall back to
// the existing name-based ownerFilterFor() match anyway).
function backfillPatientLinkIds(PDO $pdo, array $justAdded): void {
    $studentsByName = [];
    foreach ($pdo->query('SELECT id, name FROM ' . escapeIdentifier('students'))->fetchAll() as $row) {
        $key = strtolower(trim($row['name'] ?? ''));
        if ($key === '') continue;
        $studentsByName[$key] = ($studentsByName[$key] ?? null) === null ? $row['id'] : false;
    }
    $staffByName = [];
    foreach ($pdo->query('SELECT id, name FROM ' . escapeIdentifier('staff'))->fetchAll() as $row) {
        $key = strtolower(trim($row['name'] ?? ''));
        if ($key === '') continue;
        $staffByName[$key] = ($staffByName[$key] ?? null) === null ? $row['id'] : false;
    }

    foreach ($justAdded as $table => $columns) {
        $nameCol = $table === 'clearance' ? 'name' : 'patientname';
        $rows = $pdo->query('SELECT id, ' . escapeIdentifier($nameCol) . ' AS pname FROM ' . escapeIdentifier($table))->fetchAll();
        foreach ($rows as $row) {
            $key = strtolower(trim($row['pname'] ?? ''));
            if ($key === '') continue;
            $sets = [];
            $params = [':id' => $row['id']];
            if (in_array('studentid', $columns, true) && isset($studentsByName[$key]) && $studentsByName[$key] !== false) {
                $sets[] = escapeIdentifier('studentid') . ' = :studentid';
                $params[':studentid'] = $studentsByName[$key];
            }
            if (in_array('staffid', $columns, true) && isset($staffByName[$key]) && $staffByName[$key] !== false) {
                $sets[] = escapeIdentifier('staffid') . ' = :staffid';
                $params[':staffid'] = $staffByName[$key];
            }
            if ($sets) {
                $stmt = $pdo->prepare('UPDATE ' . escapeIdentifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $stmt->execute($params);
            }
        }
    }
}

function initializeDatabaseSchema(PDO $pdo, string $driver): void {
    $schemaFile = $driver === 'mysql'
        ? __DIR__ . '/schema_mysql.sql'
        : __DIR__ . '/schema.sql';

    if (!file_exists($schemaFile)) {
        return;
    }
    executeSqlFile($pdo, $schemaFile);
    applyMigrations($pdo, $driver);
    seedRoles($pdo);
}

// Roles used to be four hardcoded arrays scattered across the frontend and
// backend. This is the single authoritative list now — everything else
// (Users form dropdown, Roles & Permissions matrix) reads from /api/roles.
// Idempotent: only inserts a role name that isn't already present, so an
// admin renaming or adding a role through the UI later is never overwritten.
function seedRoles(PDO $pdo): void {
    $defaults = [
        ['name' => 'Clinic Administrator', 'description' => 'Full system access, manages users and roles', 'is_self_service' => 0],
        ['name' => 'School Nurse', 'description' => 'Clinical staff role', 'is_self_service' => 0],
        ['name' => 'Physician', 'description' => 'Clinical staff role', 'is_self_service' => 0],
        ['name' => 'Staff Encoder', 'description' => 'Data-entry clinical staff role', 'is_self_service' => 0],
        ['name' => 'Student', 'description' => 'Self-service portal account linked to a student record', 'is_self_service' => 1],
        ['name' => 'Faculty and Staff', 'description' => 'Self-service portal account linked to a staff record', 'is_self_service' => 1],
    ];
    $existing = array_column($pdo->query('SELECT name FROM ' . escapeIdentifier('roles'))->fetchAll(), 'name');
    $stmt = $pdo->prepare('INSERT INTO ' . escapeIdentifier('roles') . ' (id, name, description, is_self_service, created_at) VALUES (:id, :name, :description, :is_self_service, :created_at)');
    foreach ($defaults as $role) {
        if (in_array($role['name'], $existing, true)) continue;
        $stmt->execute([
            ':id' => bin2hex(random_bytes(12)),
            ':name' => $role['name'],
            ':description' => $role['description'],
            ':is_self_service' => $role['is_self_service'],
            ':created_at' => time() * 1000,
        ]);
    }
}

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }

    $cfg = getConfig();
    $driver = getDbDriver();

    if ($driver === 'sqlite') {
        $path = $cfg['db_name'] ?: (__DIR__ . '/clinic_system.sqlite');
        if (!preg_match('#^([a-zA-Z]:)?[\\\\/]#', $path)) {
            $path = __DIR__ . '/' . $path;
        }
        $dsn = 'sqlite:' . $path;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        // WAL lets readers and a writer proceed concurrently instead of the
        // default rollback-journal mode's whole-database write lock — every
        // request opens its own fresh PDO connection to this same file (PHP
        // tears down all state between requests), so without this, two
        // requests landing close together block each other far more than
        // they need to. busy_timeout makes any lock that still occurs wait
        // and retry briefly instead of failing immediately as SQLITE_BUSY.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        initializeDatabaseSchema($pdo, $driver);
        return $pdo;
    }

    $host = $cfg['db_host'] ?: '127.0.0.1';
    $port = $cfg['db_port'] ?: ($driver === 'mysql' ? '3306' : '5432');
    $dbname = $cfg['db_name'] ?: ($driver === 'mysql' ? 'clinic_system' : 'postgres');
    $user = $cfg['db_user'] ?: ($driver === 'mysql' ? 'root' : 'postgres');
    $password = $cfg['db_password'] ?: '';

    $dsn = $driver === 'mysql'
        ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname)
        : sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        if ($driver === 'mysql' && intval($e->getCode()) === 1049) {
            createMysqlDatabaseIfMissing($host, $port, $dbname, $user, $password);
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    initializeDatabaseSchema($pdo, $driver);

    return $pdo;
}

function escapeIdentifier(string $identifier): string {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
        throw new RuntimeException('Invalid identifier: ' . $identifier);
    }

    if (getDbDriver() === 'mysql') {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    return '"' . $identifier . '"';
}

// Column-level encryption at rest: applied only at the DB read/write boundary in
// dbCreate/dbUpdate/dbGetAll/dbGetById, so everything above this layer (filters,
// search, CSV export, reports) works on already-decrypted plaintext exactly like
// before — only what's physically stored on disk is encrypted. Only free-text
// health-detail fields are covered, deliberately excluding anything used in a raw
// SQL WHERE clause elsewhere (ids, usernames, statuses, dates, group names) since
// AES-GCM ciphertext isn't equality- or LIKE-searchable at the SQL level.
const ENCRYPTED_FIELDS = [
    'medicalrecords' => ['allergies', 'medicalconditions', 'remarks'],
    'medicalhistory' => ['diagnosis', 'treatment'],
    'visits' => ['complaint', 'diagnosis', 'treatment', 'assessment'],
    'staff' => ['healthnotes'],
    'employee_medical_record' => ['allergies', 'medicalconditions', 'remarks'],
    'incidents' => ['description', 'actiontaken'],
    'emergency_treatment' => ['treatment', 'remarks'],
];

function getEncryptionKey(): string {
    $cfg = getConfig();
    return hash('sha256', (string)($cfg['encryption_key'] ?? ''), true);
}

function encryptValue(string $plaintext): string {
    if ($plaintext === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', getEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'enc:' . base64_encode($iv . $tag . $ciphertext);
}

// Rows written before encryption was added have no 'enc:' prefix — returned as-is
// rather than treated as an error, so existing plaintext data stays readable.
function decryptValue(?string $stored): string {
    if ($stored === null || $stored === '' || strpos($stored, 'enc:') !== 0) {
        return $stored ?? '';
    }
    $raw = base64_decode(substr($stored, 4));
    if ($raw === false || strlen($raw) < 28) {
        return '';
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', getEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function encryptRowFields(string $table, array $dbData): array {
    foreach (ENCRYPTED_FIELDS[$table] ?? [] as $field) {
        if (isset($dbData[$field]) && is_string($dbData[$field])) {
            $dbData[$field] = encryptValue($dbData[$field]);
        }
    }
    return $dbData;
}

function decryptRowFields(string $table, array $row): array {
    foreach (ENCRYPTED_FIELDS[$table] ?? [] as $field) {
        if (isset($row[$field])) {
            $row[$field] = decryptValue($row[$field]);
        }
    }
    return $row;
}

function dbGetAll(string $table, string $orderBy = 'created_at.desc') {
    $table = normalizeTableName($table);
    $parts = explode('.', $orderBy);
    $column = escapeIdentifier($parts[0] ?? 'created_at');
    $direction = strtoupper($parts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $sql = 'SELECT * FROM ' . escapeIdentifier($table) . ' ORDER BY ' . $column . ' ' . $direction;
    $stmt = getDbConnection()->query($sql);
    $rows = $stmt->fetchAll();
    $rows = array_map(fn($r) => decryptRowFields($table, $r), $rows);
    return array_map('toCamelKeys', $rows);
}

function dbGetById(string $table, string $id) {
    $table = normalizeTableName($table);
    $sql = 'SELECT * FROM ' . escapeIdentifier($table) . ' WHERE ' . escapeIdentifier('id') . ' = :id LIMIT 1';
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row) {
        $row = decryptRowFields($table, $row);
    }
    return $row ? toCamelKeys($row) : null;
}

function getAllowedColumns(string $table): array {
    $columns = [
        'users' => ['id', 'fullname', 'username', 'password', 'role', 'status', 'failed_login_count', 'locked_until', 'last_login', 'two_factor_secret', 'two_factor_enabled', 'linked_record_id', 'created_at'],
        'students' => ['id', 'name', 'studentid', 'status', 'course', 'yearlevel', 'bloodtype', 'allergies', 'conditions', 'contactnumber', 'emergencycontact', 'created_at'],
        'medicalrecords' => ['id', 'recordid', 'status', 'studentid', 'studentname', 'department', 'yearlevel', 'section', 'bloodtype', 'allergies', 'medicalconditions', 'height', 'weight', 'vision', 'hearing', 'immunizationstatus', 'remarks', 'groupfolder', 'created_at'],
        'medicalhistory' => ['id', 'historyid', 'studentid', 'studentname', 'department', 'yearlevel', 'section', 'bloodtype', 'course', 'diagnosis', 'treatment', 'doctor', 'visitdate', 'created_at'],
        'visits' => ['id', 'patientname', 'patienttype', 'studentid', 'staffid', 'date', 'time', 'complaint', 'diagnosis', 'treatment', 'temperature', 'bloodpressure', 'pulserate', 'assessment', 'medicinedispensed', 'nurseonduty', 'disposition', 'created_at'],
        'medicine' => ['id', 'name', 'category', 'stock', 'unit', 'expirydate', 'reorderlevel', 'created_at'],
        'appointments' => ['id', 'patientname', 'patienttype', 'studentid', 'staffid', 'doctor_id', 'date', 'time', 'type', 'status', 'notes', 'created_at'],
        'incidents' => ['id', 'caseno', 'date', 'personinvolved', 'location', 'description', 'severity', 'status', 'actiontaken', 'created_at'],
        'staff' => ['id', 'name', 'department', 'position', 'bloodtype', 'healthnotes', 'lastcheckup', 'contactnumber', 'created_at'],
        'programs' => ['id', 'name', 'category', 'startdate', 'enddate', 'targetparticipants', 'status', 'description', 'created_at'],
        'clearance' => ['id', 'name', 'persontype', 'studentid', 'staffid', 'clearancetype', 'dateissued', 'expirydate', 'status', 'issuedby', 'qrcode', 'created_at'],
        'audit_logs' => ['id', 'user_id', 'username', 'action', 'resource', 'resource_id', 'details', 'ip_address', 'created_at'],
        'ai_requests' => ['id', 'user_id', 'username', 'visit_id', 'diagnosis', 'request_payload', 'response_summary', 'status', 'error', 'ip_address', 'created_at'],
        'permissions' => ['id', 'module', 'action', 'description', 'created_at'],
        'role_permissions' => ['id', 'role', 'permission_id', 'created_at'],
        'dispensing' => ['id', 'studentid', 'patientname', 'medicine_id', 'medicine_name', 'quantity', 'date_released', 'released_by', 'created_at'],
        'emergency_treatment' => ['id', 'incident_id', 'treatment', 'medicine', 'nurse', 'date', 'remarks', 'created_at'],
        'employee_medical_record' => ['id', 'staff_id', 'bloodtype', 'allergies', 'medicalconditions', 'immunizationstatus', 'lastphysicalexam', 'remarks', 'created_at'],
        'employee_visit' => ['id', 'staff_id', 'staffname', 'date', 'time', 'complaint', 'diagnosis', 'treatment', 'nurseonduty', 'created_at'],
        'employee_medicine' => ['id', 'staff_id', 'staffname', 'medicine_id', 'medicine_name', 'quantity', 'date_released', 'released_by', 'created_at'],
        'participants' => ['id', 'program_id', 'studentid', 'studentname', 'eligibility', 'status', 'created_at'],
        'attendance' => ['id', 'program_id', 'participant_id', 'attendance_date', 'status', 'created_at'],
        'assessment' => ['id', 'program_id', 'participant_id', 'result', 'remarks', 'assessed_by', 'created_at'],
        'doctor_schedule' => ['id', 'doctor_id', 'date', 'available_time', 'status', 'created_at'],
        'privacy_consents' => ['id', 'subject_id', 'subject_name', 'subject_type', 'consent_type', 'status', 'granted_at', 'notes', 'created_at'],
        'roles' => ['id', 'name', 'description', 'is_self_service', 'created_at'],
    ];
    return $columns[$table] ?? [];
}

function dbCreate(string $table, array $data) {
    $table = normalizeTableName($table);
    if (!isset($data['id'])) {
        $data['id'] = bin2hex(random_bytes(12));
    }
    $dbData = toDbKeys($data);
    $allowed = array_flip(getAllowedColumns($table));
    $dbData = array_intersect_key($dbData, $allowed);
    $dbData = encryptRowFields($table, $dbData);

    $columns = array_keys($dbData);
    $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
    $sql = 'INSERT INTO ' . escapeIdentifier($table) . ' (' . implode(', ', array_map('escapeIdentifier', $columns)) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($dbData);

    return dbGetById($table, $dbData['id']);
}

function dbUpdate(string $table, string $id, array $data) {
    $table = normalizeTableName($table);
    $dbData = toDbKeys($data);
    unset($dbData['id'], $dbData['created_at']);
    $allowed = array_flip(getAllowedColumns($table));
    $dbData = array_intersect_key($dbData, $allowed);
    if (empty($dbData)) {
        return dbGetById($table, $id);
    }
    $dbData = encryptRowFields($table, $dbData);

    $columns = array_keys($dbData);
    $assignments = array_map(function($col) { return escapeIdentifier($col) . ' = :' . $col; }, $columns);
    $sql = 'UPDATE ' . escapeIdentifier($table) . ' SET ' . implode(', ', $assignments) . ' WHERE ' . escapeIdentifier('id') . ' = :id';
    $dbData['id'] = $id;
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($dbData);
    return dbGetById($table, $id);
}

function dbDelete(string $table, string $id) {
    $table = normalizeTableName($table);
    $sql = 'DELETE FROM ' . escapeIdentifier($table) . ' WHERE ' . escapeIdentifier('id') . ' = :id';
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

function dbQuery(string $sql, array $params = []) {
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbCountAiRequestsSince(string $userId, int $sinceMs): int {
    $sql = 'SELECT COUNT(*) AS total FROM ' . escapeIdentifier('ai_requests') . ' WHERE ' . escapeIdentifier('user_id') . ' = :user_id AND ' . escapeIdentifier('created_at') . ' >= :sinceMs';
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute([':user_id' => $userId, ':sinceMs' => $sinceMs]);
    $row = $stmt->fetch();
    return (int)($row['total'] ?? 0);
}

/* ============================== AUDIT & AI LOGGING ============================== */

function dbLogAudit(array $user, string $action, string $resource, string $resourceId = '', array $details = []): void {
    try {
        dbCreate('audit_logs', [
            'user_id' => $user['id'] ?? '',
            'username' => $user['username'] ?? '',
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('audit log failed: ' . $e->getMessage());
    }
}

function dbLogAiRequest(array $user, string $visitId, string $diagnosis, array $payload, string $status, string $responseSummary = '', string $error = ''): string {
    $id = bin2hex(random_bytes(12));
    try {
        dbCreate('ai_requests', [
            'id' => $id,
            'user_id' => $user['id'] ?? '',
            'username' => $user['username'] ?? '',
            'visit_id' => $visitId,
            'diagnosis' => $diagnosis,
            'request_payload' => json_encode($payload),
            'response_summary' => $responseSummary,
            'status' => $status,
            'error' => $error,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('ai request log failed: ' . $e->getMessage());
    }
    return $id;
}

function dbUpdateAiRequest(string $id, string $status, string $responseSummary = '', string $error = ''): void {
    try {
        dbUpdate('ai_requests', $id, [
            'status' => $status,
            'response_summary' => $responseSummary,
            'error' => $error,
        ]);
    } catch (Throwable $e) {
        error_log('ai request update failed: ' . $e->getMessage());
    }
}



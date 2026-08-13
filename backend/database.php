<?php

require_once __DIR__ . '/config.php';

// Maps frontend camelCase keys to database lowercase keys (and vice versa)
function dbKeyMap(): array {
    return [
        // users
        'fullName' => 'fullname',
        // students
        'studentId' => 'studentid',
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

function applyMysqlMigrations(PDO $pdo): void {
    $migrations = [
        'students' => [
            'status' => "TEXT NOT NULL DEFAULT 'Active'",
        ],
    ];

    foreach ($migrations as $table => $columns) {
        foreach ($columns as $column => $definition) {
            if (!mysqlColumnExists($pdo, $table, $column)) {
                $pdo->exec('ALTER TABLE ' . escapeIdentifier($table) . ' ADD COLUMN ' . escapeIdentifier($column) . ' ' . $definition);
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

    if ($driver === 'mysql') {
        applyMysqlMigrations($pdo);
    }
}

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }

    $cfg = getConfig();
    $driver = getDbDriver();
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

function dbGetAll(string $table, string $orderBy = 'created_at.desc') {
    $table = normalizeTableName($table);
    $parts = explode('.', $orderBy);
    $column = escapeIdentifier($parts[0] ?? 'created_at');
    $direction = strtoupper($parts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $sql = 'SELECT * FROM ' . escapeIdentifier($table) . ' ORDER BY ' . $column . ' ' . $direction;
    $stmt = getDbConnection()->query($sql);
    $rows = $stmt->fetchAll();
    return array_map('toCamelKeys', $rows);
}

function dbGetById(string $table, string $id) {
    $table = normalizeTableName($table);
    $sql = 'SELECT * FROM ' . escapeIdentifier($table) . ' WHERE ' . escapeIdentifier('id') . ' = :id LIMIT 1';
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ? toCamelKeys($row) : null;
}

function getAllowedColumns(string $table): array {
    $columns = [
        'users' => ['id', 'fullname', 'username', 'password', 'role', 'status', 'created_at'],
        'students' => ['id', 'name', 'studentid', 'status', 'course', 'yearlevel', 'bloodtype', 'allergies', 'conditions', 'contactnumber', 'emergencycontact', 'created_at'],
        'medicalrecords' => ['id', 'recordid', 'status', 'studentid', 'studentname', 'department', 'yearlevel', 'section', 'bloodtype', 'allergies', 'medicalconditions', 'height', 'weight', 'vision', 'hearing', 'immunizationstatus', 'remarks', 'groupfolder', 'created_at'],
        'medicalhistory' => ['id', 'historyid', 'studentid', 'studentname', 'department', 'yearlevel', 'section', 'bloodtype', 'course', 'diagnosis', 'treatment', 'doctor', 'visitdate', 'created_at'],
        'visits' => ['id', 'patientname', 'patienttype', 'date', 'time', 'complaint', 'diagnosis', 'treatment', 'nurseonduty', 'created_at'],
        'medicine' => ['id', 'name', 'category', 'stock', 'unit', 'expirydate', 'reorderlevel', 'created_at'],
        'appointments' => ['id', 'patientname', 'patienttype', 'date', 'time', 'type', 'status', 'notes', 'created_at'],
        'incidents' => ['id', 'caseno', 'date', 'personinvolved', 'location', 'description', 'severity', 'status', 'actiontaken', 'created_at'],
        'staff' => ['id', 'name', 'department', 'position', 'bloodtype', 'healthnotes', 'lastcheckup', 'contactnumber', 'created_at'],
        'programs' => ['id', 'name', 'category', 'startdate', 'enddate', 'targetparticipants', 'status', 'description', 'created_at'],
        'clearance' => ['id', 'name', 'persontype', 'clearancetype', 'dateissued', 'expirydate', 'status', 'issuedby', 'created_at'],
        'audit_logs' => ['id', 'user_id', 'username', 'action', 'resource', 'resource_id', 'details', 'ip_address', 'created_at'],
        'ai_requests' => ['id', 'user_id', 'username', 'visit_id', 'diagnosis', 'request_payload', 'response_summary', 'status', 'error', 'ip_address', 'created_at'],
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



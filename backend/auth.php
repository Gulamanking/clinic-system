<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateJWT(array $payload): string {
    $cfg = getConfig();
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload['iat'] = time();
    $payload['exp'] = time() + 86400 * 7;
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payloadEncoded", $cfg['jwt_secret'], true)
    );
    return "$header.$payloadEncoded.$signature";
}

function verifyJWT(string $token): ?array {
    $cfg = getConfig();
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payloadEncoded, $signature] = $parts;
    $expected = base64url_encode(
        hash_hmac('sha256', "$header.$payloadEncoded", $cfg['jwt_secret'], true)
    );
    if (!hash_equals($expected, $signature)) return null;
    $payload = json_decode(base64url_decode($payloadEncoded), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return null;
    return $payload;
}

function handleLogin(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        http_response_code(400);
        return ['ok' => false, 'message' => 'Please enter your username and password.'];
    }

    $users = dbGetAll('users');
    $found = null;
    foreach ($users as $u) {
        if (strtolower($u['username']) === strtolower($username)) {
            $found = $u;
            break;
        }
    }

    if (!$found || !password_verify($password, $found['password'])) {
        http_response_code(401);
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

    if (($found['status'] ?? 'Active') === 'Inactive') {
        http_response_code(403);
        return ['ok' => false, 'message' => 'This account has been deactivated. Contact your administrator.'];
    }

    $token = generateJWT([
        'sub' => $found['id'],
        'username' => $found['username'],
    ]);

    return [
        'ok' => true,
        'token' => $token,
        'user' => [
            'id' => $found['id'],
            'fullName' => $found['fullName'] ?? $found['fullname'] ?? '',
            'username' => $found['username'],
            'role' => $found['role'],
            'status' => $found['status'],
            'createdAt' => isset($found['createdAt']) ? (int)$found['createdAt'] : 0,
        ],
    ];
}

function handleRegister(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    $fullName = trim($input['fullName'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'Staff Encoder';

    if (!$fullName || !$username || !$password) {
        http_response_code(400);
        return ['ok' => false, 'message' => 'All fields are required.'];
    }

    $existing = dbQuery("SELECT id FROM \"users\" WHERE LOWER(username) = LOWER(?)", [$username]);
    if ($existing) {
        http_response_code(409);
        return ['ok' => false, 'message' => 'That username is already taken.'];
    }

    $id = bin2hex(random_bytes(12));
    $hashed = password_hash($password, PASSWORD_BCRYPT);

    $user = [
        'id' => $id,
        'fullname' => $fullName,
        'username' => $username,
        'password' => $hashed,
        'role' => $role,
        'status' => 'Active',
    ];

    dbCreate('users', $user);

    return ['ok' => true, 'user' => [
        'id' => $id,
        'fullName' => $fullName,
        'username' => $username,
        'role' => $role,
        'status' => 'Active',
    ]];
}

function getAuthUser(): ?array {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) return null;
    $payload = verifyJWT($m[1]);
    if (!$payload) return null;
    return dbGetById('users', $payload['sub']);
}

/* ============================== RBAC ============================== */

function getRolePermissions(): array {
    return [
        'Clinic Administrator' => ['manage_users', 'view_reports', 'use_ai_assistant', 'access_patient_records'],
        'School Nurse' => ['view_reports', 'use_ai_assistant', 'access_patient_records'],
        'Physician' => ['view_reports', 'use_ai_assistant', 'access_patient_records'],
        'Staff Encoder' => ['view_reports', 'access_patient_records'],
    ];
}

function hasPermission(array $user, string $permission): bool {
    $role = $user['role'] ?? '';
    $permissions = getRolePermissions();
    if (!isset($permissions[$role])) return false;
    return in_array($permission, $permissions[$role], true);
}

function requirePermission(string $permission): array {
    $user = requireAuth();
    if (!hasPermission($user, $permission)) {
        jsonResponse(['error' => 'Forbidden: insufficient permissions'], 403);
    }
    return $user;
}

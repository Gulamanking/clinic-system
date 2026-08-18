<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateJWT(array $payload, int $expirySeconds = 604800): string {
    $cfg = getConfig();
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload['iat'] = time();
    $payload['exp'] = time() + $expirySeconds;
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payloadEncoded", $cfg['jwt_secret'], true)
    );
    return "$header.$payloadEncoded.$signature";
}

/* ============================== TOTP (RFC 6238) ============================== */

function base32Encode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binaryString = '';
    foreach (str_split($data) as $char) {
        $binaryString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    foreach (str_split($binaryString, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function base32Decode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $data = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $data));
    $binaryString = '';
    foreach (str_split($data) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($binaryString, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes .= chr(bindec($byte));
        }
    }
    return $bytes;
}

function generateTotpSecret(): string {
    return base32Encode(random_bytes(20));
}

function getTotpCode(string $base32Secret, ?int $timestamp = null): string {
    $key = base32Decode($base32Secret);
    $counter = intdiv($timestamp ?? time(), 30);
    $counterBytes = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $counterBytes, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    return str_pad((string)($truncated % 1000000), 6, '0', STR_PAD_LEFT);
}

// Allows +/-1 time step (30s) either side for clock drift between server and phone.
function verifyTotpCode(string $base32Secret, string $code): bool {
    $code = trim($code);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(getTotpCode($base32Secret, time() + ($i * 30)), $code)) {
            return true;
        }
    }
    return false;
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

const LOGIN_LOCKOUT_THRESHOLD = 5;
const LOGIN_LOCKOUT_DURATION_MS = 15 * 60 * 1000;

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

    $nowMs = round(microtime(true) * 1000);

    if ($found && (int)($found['lockedUntil'] ?? 0) > $nowMs) {
        $minutesLeft = (int)ceil(((int)$found['lockedUntil'] - $nowMs) / 60000);
        dbLogAudit(['id' => $found['id'], 'username' => $found['username']], 'login.blocked', 'auth', $found['id'], ['reason' => 'account locked']);
        http_response_code(403);
        return ['ok' => false, 'message' => "Too many failed attempts. Try again in $minutesLeft minute(s)."];
    }

    if (!$found || !password_verify($password, $found['password'])) {
        if ($found) {
            $newCount = (int)($found['failedLoginCount'] ?? 0) + 1;
            $update = ['failedLoginCount' => $newCount];
            if ($newCount >= LOGIN_LOCKOUT_THRESHOLD) {
                $update['lockedUntil'] = $nowMs + LOGIN_LOCKOUT_DURATION_MS;
                $update['failedLoginCount'] = 0;
            }
            dbUpdate('users', $found['id'], $update);
        }
        dbLogAudit(['id' => $found['id'] ?? '', 'username' => $username], 'login.failed', 'auth', $found['id'] ?? '');
        http_response_code(401);
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

    if (($found['status'] ?? 'Active') === 'Inactive') {
        dbLogAudit(['id' => $found['id'], 'username' => $found['username']], 'login.blocked', 'auth', $found['id'], ['reason' => 'account inactive']);
        http_response_code(403);
        return ['ok' => false, 'message' => 'This account has been deactivated. Contact your administrator.'];
    }

    dbUpdate('users', $found['id'], ['failedLoginCount' => 0, 'lockedUntil' => 0]);

    if ((int)($found['twoFactorEnabled'] ?? 0) === 1) {
        $tempToken = generateJWT(['sub' => $found['id'], 'pending2fa' => true], 300);
        dbLogAudit(['id' => $found['id'], 'username' => $found['username']], 'login.pending2fa', 'auth', $found['id']);
        return ['ok' => true, 'needsTwoFactor' => true, 'tempToken' => $tempToken];
    }

    return finishLogin($found, $nowMs);
}

function finishLogin(array $user, ?float $nowMs = null): array {
    $nowMs = $nowMs ?? round(microtime(true) * 1000);
    dbUpdate('users', $user['id'], ['lastLogin' => $nowMs]);
    dbLogAudit(['id' => $user['id'], 'username' => $user['username']], 'login.success', 'auth', $user['id']);

    $token = generateJWT([
        'sub' => $user['id'],
        'username' => $user['username'],
    ]);

    return [
        'ok' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'fullName' => $user['fullName'] ?? $user['fullname'] ?? '',
            'username' => $user['username'],
            'role' => $user['role'],
            'status' => $user['status'],
            'twoFactorEnabled' => (int)($user['twoFactorEnabled'] ?? 0) === 1,
            'createdAt' => isset($user['createdAt']) ? (int)$user['createdAt'] : 0,
        ],
    ];
}

function handleVerifyTwoFactor(): array {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $tempToken = trim((string)($input['tempToken'] ?? ''));
    $code = trim((string)($input['code'] ?? ''));

    $payload = $tempToken !== '' ? verifyJWT($tempToken) : null;
    if (!$payload || empty($payload['pending2fa'])) {
        http_response_code(401);
        return ['ok' => false, 'message' => 'Your session expired. Please log in again.'];
    }

    $user = dbGetById('users', $payload['sub']);
    if (!$user || (int)($user['twoFactorEnabled'] ?? 0) !== 1) {
        http_response_code(401);
        return ['ok' => false, 'message' => 'Two-factor authentication is not set up for this account.'];
    }

    if (!verifyTotpCode($user['twoFactorSecret'], $code)) {
        dbLogAudit(['id' => $user['id'], 'username' => $user['username']], 'login.failed', 'auth', $user['id'], ['reason' => 'invalid 2FA code']);
        http_response_code(401);
        return ['ok' => false, 'message' => 'Invalid authentication code.'];
    }

    return finishLogin($user);
}

// Called by an already-authenticated user (via requireAuth()) to begin enrolling
// their own account in 2FA. Secret isn't marked enabled until confirmed with a
// real code from the app, so a half-finished setup can't lock anyone out.
function handleTwoFactorSetup(array $currentUser): array {
    $secret = generateTotpSecret();
    dbUpdate('users', $currentUser['id'], ['twoFactorSecret' => $secret, 'twoFactorEnabled' => 0]);
    $label = rawurlencode('ClinicSystem:' . $currentUser['username']);
    $otpauthUrl = "otpauth://totp/$label?secret=$secret&issuer=ClinicSystem&algorithm=SHA1&digits=6&period=30";
    return ['ok' => true, 'secret' => $secret, 'otpauthUrl' => $otpauthUrl];
}

function handleTwoFactorConfirm(array $currentUser): array {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $code = trim((string)($input['code'] ?? ''));
    $user = dbGetById('users', $currentUser['id']);
    if (!$user || empty($user['twoFactorSecret'])) {
        return ['ok' => false, 'message' => 'Start 2FA setup first.'];
    }
    if (!verifyTotpCode($user['twoFactorSecret'], $code)) {
        return ['ok' => false, 'message' => 'Invalid code. Check your authenticator app and try again.'];
    }
    dbUpdate('users', $currentUser['id'], ['twoFactorEnabled' => 1]);
    dbLogAudit($currentUser, '2fa.enabled', 'users', $currentUser['id']);
    return ['ok' => true];
}

function handleTwoFactorDisable(array $currentUser): array {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $code = trim((string)($input['code'] ?? ''));
    $user = dbGetById('users', $currentUser['id']);
    if (!$user || (int)($user['twoFactorEnabled'] ?? 0) !== 1) {
        return ['ok' => false, 'message' => 'Two-factor authentication is not currently enabled.'];
    }
    if (!verifyTotpCode($user['twoFactorSecret'], $code)) {
        return ['ok' => false, 'message' => 'Invalid code.'];
    }
    dbUpdate('users', $currentUser['id'], ['twoFactorEnabled' => 0, 'twoFactorSecret' => '']);
    dbLogAudit($currentUser, '2fa.disabled', 'users', $currentUser['id']);
    return ['ok' => true];
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
    if (!$payload || !empty($payload['pending2fa'])) return null;
    return dbGetById('users', $payload['sub']);
}

/* ============================== RBAC ============================== */

// Fallback used only before /seed/permissions has populated the DB tables.
function getDefaultRolePermissions(): array {
    return [
        'Clinic Administrator' => ['manage_users', 'view_reports', 'use_ai_assistant', 'access_patient_records'],
        'School Nurse' => ['view_reports', 'use_ai_assistant', 'access_patient_records'],
        'Physician' => ['view_reports', 'use_ai_assistant', 'access_patient_records'],
        'Staff Encoder' => ['view_reports', 'access_patient_records'],
    ];
}

// Permission strings are either legacy flags ('manage_users') or 'module:write'
// (e.g. 'students:write') for per-resource CRUD gating.
function hasPermission(array $user, string $permission): bool {
    $role = $user['role'] ?? '';
    $catalog = dbGetAll('permissions');

    if (count($catalog) === 0) {
        // Per-resource write checks (e.g. 'students:write') didn't exist before this
        // RBAC upgrade — fail open on them pre-seed so existing installs aren't locked
        // out until an admin runs /seed/permissions. Legacy flags still enforce.
        if (strpos($permission, ':') !== false) return true;
        $defaults = getDefaultRolePermissions();
        if (!isset($defaults[$role])) return false;
        return in_array($permission, $defaults[$role], true);
    }

    $permsById = [];
    foreach ($catalog as $p) {
        $permsById[$p['id']] = $p;
    }
    foreach (dbGetAll('role_permissions') as $grant) {
        if (($grant['role'] ?? '') !== $role) continue;
        $p = $permsById[$grant['permissionId']] ?? null;
        if (!$p) continue;
        $granted = ($p['action'] ?? '') !== '' ? $p['module'] . ':' . $p['action'] : $p['module'];
        if ($granted === $permission) return true;
    }
    return false;
}

function requirePermission(string $permission): array {
    $user = requireAuth();
    if (!hasPermission($user, $permission)) {
        jsonResponse(['error' => 'Forbidden: insufficient permissions'], 403);
    }
    return $user;
}

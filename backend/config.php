<?php

// Config precedence, lowest to highest:
//   1. the dev defaults below (safe XAMPP values; insecure on purpose)
//   2. backend/config.local.php   (tracked in git — see its own comment)
//   3. backend/config.secret.php  (gitignored; real API keys go here)
//   4. real OS environment variables
//
// Environment variables win over everything. That is deliberate: in
// production (HostForge and any other host that injects config through the
// environment) the deployed checkout still contains config.local.php with
// its XAMPP defaults, and if that file could override the environment the
// app would try to reach MySQL on 127.0.0.1 as root no matter what the
// dashboard said.
function getConfig() {
    $config = [
        'app_env' => 'development',
        'db_driver' => '',
        'db_host' => '',
        'db_port' => '3306',
        'db_name' => 'clinic_system',
        'db_user' => 'root',
        'db_password' => '',
        'jwt_secret' => 'clinic-system-jwt-secret-change-in-production',
        'encryption_key' => 'clinic-system-encryption-key-change-in-production',
        // Password for the bootstrap admin account created by /seed. The dev
        // default is fine locally; production must set ADMIN_PASSWORD.
        'admin_password' => 'admin123',
        'gemini_api_key' => '',
        'gemini_model' => 'gemini-3.6-flash',
        'ai_rate_limit_max' => 10,
        'ai_rate_limit_window_minutes' => 60,
        // Comma-separated list of origins allowed to call the API, or '*'.
        // '*' is fine for local development; set a real origin in production.
        'allowed_origins' => '*',
    ];

    $localFile = __DIR__ . '/config.local.php';
    if (file_exists($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $config = array_merge($config, $local);
        }
    }

    $secretFile = __DIR__ . '/config.secret.php';
    if (file_exists($secretFile)) {
        $secret = require $secretFile;
        if (is_array($secret)) {
            $config = array_merge($config, $secret);
        }
    }

    $envMap = [
        'app_env' => 'APP_ENV',
        'db_driver' => 'DB_DRIVER',
        'db_host' => 'DB_HOST',
        'db_port' => 'DB_PORT',
        'db_name' => 'DB_NAME',
        'db_user' => 'DB_USER',
        'db_password' => 'DB_PASSWORD',
        'jwt_secret' => 'JWT_SECRET',
        'encryption_key' => 'ENCRYPTION_KEY',
        'admin_password' => 'ADMIN_PASSWORD',
        'gemini_api_key' => 'GEMINI_API_KEY',
        'gemini_model' => 'GEMINI_MODEL',
        'ai_rate_limit_max' => 'AI_RATE_LIMIT_MAX',
        'ai_rate_limit_window_minutes' => 'AI_RATE_LIMIT_WINDOW_MINUTES',
        'allowed_origins' => 'ALLOWED_ORIGINS',
    ];
    foreach ($envMap as $key => $var) {
        $value = getenv($var);
        // An unset variable is false; an empty one means "not configured"
        // rather than "override with empty" — DB_PASSWORD is the one case
        // where empty is a legitimate value, so it is handled separately.
        if ($value !== false && ($value !== '' || $key === 'db_password')) {
            $config[$key] = $value;
        }
    }

    $config['ai_rate_limit_max'] = (int)$config['ai_rate_limit_max'];
    $config['ai_rate_limit_window_minutes'] = (int)$config['ai_rate_limit_window_minutes'];

    assertProductionConfig($config);

    return $config;
}

// Refuse to serve a production deployment that is still using the shared
// development secrets. They are committed to a public repository, so anyone
// who can read the repo could otherwise forge an admin token against the
// live site. Fail closed and loudly rather than silently running insecure.
function assertProductionConfig(array $config) {
    if ($config['app_env'] !== 'production') {
        return;
    }

    $insecure = [];
    if ($config['jwt_secret'] === '' || strpos($config['jwt_secret'], 'change-in-production') !== false) {
        $insecure[] = 'JWT_SECRET';
    }
    if ($config['encryption_key'] === '' || strpos($config['encryption_key'], 'change-in-production') !== false) {
        $insecure[] = 'ENCRYPTION_KEY';
    }
    if ($config['admin_password'] === '' || $config['admin_password'] === 'admin123') {
        $insecure[] = 'ADMIN_PASSWORD';
    }
    if (!$insecure) {
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Insecure production configuration',
        'detail' => 'These environment variables must be set to unique secret '
            . 'values before the app will run with APP_ENV=production: '
            . implode(', ', $insecure),
    ]);
    exit;
}

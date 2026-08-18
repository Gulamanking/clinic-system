<?php

// Config precedence: the defaults below (from real OS environment variables,
// if any are set — there is no .env file or dotenv loader here) are merged
// with, then overridden by, backend/config.local.php (tracked in git — see
// its own comment), then overridden again by backend/config.secret.php
// (gitignored — real API keys go there, never in config.local.php).
function getConfig() {
    $config = [
        'db_driver' => getenv('DB_DRIVER') ?: '',
        'db_host' => getenv('DB_HOST') ?: '',
        'db_port' => getenv('DB_PORT') ?: '3306',
        'db_name' => getenv('DB_NAME') ?: 'clinic_system',
        'db_user' => getenv('DB_USER') ?: 'root',
        'db_password' => getenv('DB_PASSWORD') ?: '',
        'jwt_secret' => getenv('JWT_SECRET') ?: 'clinic-system-jwt-secret-change-in-production',
        'encryption_key' => getenv('ENCRYPTION_KEY') ?: 'clinic-system-encryption-key-change-in-production',
        'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
        'gemini_model' => getenv('GEMINI_MODEL') ?: 'gemini-3.6-flash',
        'ai_rate_limit_max' => (int)(getenv('AI_RATE_LIMIT_MAX') ?: 10),
        'ai_rate_limit_window_minutes' => (int)(getenv('AI_RATE_LIMIT_WINDOW_MINUTES') ?: 60),
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

    return $config;
}

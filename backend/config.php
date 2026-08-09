<?php

function getConfig() {
    $config = [
        'db_driver' => getenv('DB_DRIVER') ?: '',
        'db_host' => getenv('DB_HOST') ?: '',
        'db_port' => getenv('DB_PORT') ?: '3306',
        'db_name' => getenv('DB_NAME') ?: 'clinic_system',
        'db_user' => getenv('DB_USER') ?: 'root',
        'db_password' => getenv('DB_PASSWORD') ?: '',
        'jwt_secret' => getenv('JWT_SECRET') ?: 'clinic-system-jwt-secret-change-in-production',
        'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
        'anthropic_model' => getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-4-5',
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

    return $config;
}

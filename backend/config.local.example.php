<?php
// Local development overrides. Copy to config.local.php (one is already
// committed with working XAMPP defaults, so you usually do not need this).
//
// Do NOT put production values here — in production the environment
// overrides this file entirely. See DEPLOYMENT.md.
return [
    'db_driver' => 'mysql',
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'clinic_system',
    'db_user' => 'root',
    'db_password' => 'your-database-password',
];

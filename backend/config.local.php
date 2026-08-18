<?php

// This is the project's local config file — tracked in git (unusual for a
// config file) with safe XAMPP defaults on purpose, so a collaborator can
// `git pull` and run the app immediately with zero setup. If you change
// these values for your own machine, keep in mind anyone else who pulls
// your commit inherits your local values too — only commit changes here
// that should apply to everyone (e.g. a schema-affecting default), not
// personal/experimental overrides.
//
// Real secrets (API keys) do NOT belong here — put those in
// backend/config.secret.php instead (gitignored; copy config.secret.example.php
// to create it). Anything set in this file overrides config.php's defaults;
// config.secret.php overrides both.
return[
    'db_driver' => 'mysql',
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'clinic_system',
    'db_user' => 'root',
    'db_password' => '',
    'jwt_secret' => 'clinic-system-jwt-secret-change-in-production',
];
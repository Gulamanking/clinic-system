<?php

// Router for PHP's built-in server (`php -S 0.0.0.0:8000 -t . router.php`),
// which is how this app runs in production on HostForge.
//
// The built-in server does NOT read .htaccess, so the Apache rules in
// backend/.htaccess do nothing there. Without this router it happily serves
// the whole repository as static files — including backend/clinic_system
// (a SQLite database), the SQL seed scripts, the CSV templates and the
// design PDF. This is an allow-list: anything not named here is a 404.
//
// Keep it in sync when adding frontend assets.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = '/' . ltrim(rawurldecode($path), '/');

if ($path === '/') {
    $path = '/index.html';
}

$allowed = [
    '/index.html',
    '/enrollment.html',
    '/assets/favicon.svg',
    '/backend/index.php',
];

$isAllowed = in_array($path, $allowed, true)
    // Frontend scripts only — no traversal, no nested directories.
    || (bool)preg_match('#^/js/[A-Za-z0-9_.-]+\.js$#', $path);

if (!$isAllowed) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
    return true;
}

// Let the built-in server serve the file (or execute backend/index.php).
return false;

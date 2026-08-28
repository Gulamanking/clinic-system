<?php

// Dependency-free automated test suite. Run from the project root:
//   php backend/tests/run_tests.php
//
// Spins up its own throwaway SQLite database and its own PHP built-in
// server on a scratch port, runs every file in backend/tests/cases/,
// then tears everything down — the real dev database and config.local.php
// are never touched. Exits 1 if any assertion failed (safe to wire into CI).

require_once __DIR__ . '/TestClient.php';

$projectRoot = dirname(__DIR__, 2);
$backendDir = $projectRoot . '/backend';
$configLocalPath = $backendDir . '/config.local.php';
$configLocalBackup = $backendDir . '/config.local.php.testbak';
$configSecretPath = $backendDir . '/config.secret.php';
$configSecretBackup = $backendDir . '/config.secret.php.testbak';
$testDbPath = sys_get_temp_dir() . '/clinic_system_test_' . uniqid() . '.sqlite';
$testPort = 8971;

$movedConfig = false;
$movedSecret = false;
$serverProcess = null;
$serverLogPath = null;

function killServer($proc) {
    if ($proc === null) return;
    if (PHP_OS_FAMILY === 'Windows') {
        $status = proc_get_status($proc);
        if (!empty($status['pid'])) {
            exec('taskkill /F /T /PID ' . (int)$status['pid'] . ' 2>NUL');
        }
    }
    proc_terminate($proc);
    proc_close($proc);
}

register_shutdown_function(function() use (&$movedConfig, $configLocalPath, $configLocalBackup, &$movedSecret, $configSecretPath, $configSecretBackup, $testDbPath, &$serverProcess, &$serverLogPath) {
    killServer($serverProcess);
    if ($movedConfig && file_exists($configLocalBackup)) {
        @unlink($configLocalPath);
        rename($configLocalBackup, $configLocalPath);
    }
    if ($movedSecret && file_exists($configSecretBackup)) {
        @unlink($configSecretPath);
        rename($configSecretBackup, $configSecretPath);
    }
    @unlink($testDbPath);
    if (!empty($serverLogPath)) { @unlink($serverLogPath); }
});

try {
    // Isolate: point config.local.php at the throwaway DB for the duration
    // of the test run, restoring the real one (via the shutdown function
    // above) no matter how the run ends.
    if (file_exists($configLocalPath)) {
        rename($configLocalPath, $configLocalBackup);
        $movedConfig = true;
    }
    file_put_contents($configLocalPath, "<?php\nreturn [\n" .
        "    'db_driver' => 'sqlite',\n" .
        "    'db_name' => '" . addslashes($testDbPath) . "',\n" .
        "    'jwt_secret' => 'test-suite-jwt-secret',\n" .
        "    'encryption_key' => 'test-suite-encryption-key-0123456',\n" .
        "];\n");

    // Also hide any real config.secret.php — otherwise a real Gemini key
    // leaks into the test run, breaking the "not configured" assertion in
    // 07_ai_assistant.php and burning real API quota on every test run.
    if (file_exists($configSecretPath)) {
        rename($configSecretPath, $configSecretBackup);
        $movedSecret = true;
    }

    // Redirect the server's stdout/stderr to a file, not pipes: php -S logs
    // one line per request, and an unread pipe fills its OS buffer after
    // enough requests, which blocks the child process on its next write —
    // silently hanging every request after that point. A file never blocks.
    // stderr is merged into stdout at the shell level (2>&1) rather than
    // given its own separate file descriptor — two distinct handles writing
    // the same path concurrently is a real sharing-violation hazard on
    // Windows and reproduced this exact hang once already.
    $serverLogPath = sys_get_temp_dir() . '/clinic_test_server_' . uniqid() . '.log';
    $serverProcess = proc_open(
        'php -S 127.0.0.1:' . $testPort . ' -t ' . escapeshellarg($projectRoot) . ' 2>&1',
        [1 => ['file', $serverLogPath, 'w']],
        $pipes,
        $projectRoot
    );
    if (!is_resource($serverProcess)) {
        fwrite(STDERR, "Failed to start test server.\n");
        exit(1);
    }

    // Wait for the server to accept connections.
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $conn = @fsockopen('127.0.0.1', $testPort, $errno, $errstr, 0.2);
        if ($conn) { fclose($conn); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) {
        fwrite(STDERR, "Test server never became ready.\n");
        exit(1);
    }

    $client = new TestClient('http://127.0.0.1:' . $testPort);
    // First hit triggers initializeDatabaseSchema() against the fresh file.
    $client->post('/seed');

    $allResults = [];
    $caseFiles = glob(__DIR__ . '/cases/*.php');
    sort($caseFiles);
    foreach ($caseFiles as $file) {
        $run = require $file;
        if (is_callable($run)) {
            $results = $run($client, $testDbPath);
            foreach ($results as $r) { $allResults[] = $r; }
        }
    }

    $failed = array_filter($allResults, function($r) { return !$r->passed; });
    $passedCount = count($allResults) - count($failed);

    echo "\n=== Test Results ===\n";
    foreach ($allResults as $r) {
        echo ($r->passed ? '[PASS] ' : '[FAIL] ') . $r->name . ($r->passed ? '' : ' -- ' . $r->message) . "\n";
    }
    echo "\n$passedCount / " . count($allResults) . " passed.\n";

    exit(count($failed) > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Test runner crashed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    if ($serverLogPath && file_exists($serverLogPath)) {
        fwrite(STDERR, "\n--- test server log ---\n" . file_get_contents($serverLogPath) . "\n");
    }
    exit(1);
}

<?php

// Fires genuinely concurrent requests (via curl_multi, not sequential calls)
// against the app's own race-condition-sensitive endpoints: medicine stock
// deduction and double-booking prevention. Run from the project root:
//   php backend/tests/concurrency_test.php
//
// Uses the same throwaway-database isolation as run_tests.php — the real
// dev database and config.local.php are never touched.

require_once __DIR__ . '/TestClient.php';

$projectRoot = dirname(__DIR__, 2);
$backendDir = $projectRoot . '/backend';
$configLocalPath = $backendDir . '/config.local.php';
$configLocalBackup = $backendDir . '/config.local.php.testbak';
$testDbPath = sys_get_temp_dir() . '/clinic_system_concurrency_' . uniqid() . '.sqlite';
$testPort = 8974;

$movedConfig = false;
$serverProcess = null;
$serverLogPath = null;

function killServerC($proc) {
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

register_shutdown_function(function() use (&$movedConfig, $configLocalPath, $configLocalBackup, $testDbPath, &$serverProcess, &$serverLogPath) {
    killServerC($serverProcess);
    if ($movedConfig && file_exists($configLocalBackup)) {
        @unlink($configLocalPath);
        rename($configLocalBackup, $configLocalPath);
    }
    @unlink($testDbPath);
    if (!empty($serverLogPath)) { @unlink($serverLogPath); }
});

// Fires $requests[] (each ['method','route','body','token']) truly
// concurrently via curl_multi and returns the matching array of
// ['status' => int, 'body' => mixed] results, same order as input.
function fireConcurrent(string $baseUrl, array $requests): array {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($requests as $i => $req) {
        $ch = curl_init();
        $headers = ['Content-Type: application/json'];
        if (!empty($req['token'])) $headers[] = 'Authorization: Bearer ' . $req['token'];
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/backend/index.php?route=' . urlencode($req['route']),
            CURLOPT_CUSTOMREQUEST => $req['method'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        if (isset($req['body'])) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req['body']));
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 1.0);
    } while ($running > 0);
    $results = [];
    foreach ($handles as $i => $ch) {
        $raw = curl_multi_getcontent($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $json = json_decode($raw, true);
        $results[$i] = ['status' => $status, 'body' => $json === null ? $raw : $json];
    }
    curl_multi_close($mh);
    return $results;
}

try {
    if (file_exists($configLocalPath)) {
        rename($configLocalPath, $configLocalBackup);
        $movedConfig = true;
    }
    file_put_contents($configLocalPath, "<?php\nreturn [\n" .
        "    'db_driver' => 'sqlite',\n" .
        "    'db_name' => '" . addslashes($testDbPath) . "',\n" .
        "    'jwt_secret' => 'concurrency-test-jwt-secret',\n" .
        "    'encryption_key' => 'concurrency-test-encryption-key-01',\n" .
        "];\n");

    $serverLogPath = sys_get_temp_dir() . '/clinic_concurrency_server_' . uniqid() . '.log';
    $serverProcess = proc_open(
        'php -S 127.0.0.1:' . $testPort . ' -t ' . escapeshellarg($projectRoot) . ' 2>&1',
        [1 => ['file', $serverLogPath, 'w']],
        $pipes,
        $projectRoot
    );
    if (!is_resource($serverProcess)) { fwrite(STDERR, "Failed to start test server.\n"); exit(1); }

    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $conn = @fsockopen('127.0.0.1', $testPort, $errno, $errstr, 0.2);
        if ($conn) { fclose($conn); $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) { fwrite(STDERR, "Test server never became ready.\n"); exit(1); }

    $baseUrl = 'http://127.0.0.1:' . $testPort;
    $client = new TestClient($baseUrl);
    $client->post('/seed');
    $client->login('admin', 'admin123');
    $adminToken = null;
    $login = $client->login('admin', 'admin123');
    $adminToken = $login['body']['token'] ?? null;

    $results = [];
    $fail = function(string $name, string $msg) use (&$results) { $results[] = ['name' => $name, 'passed' => false, 'msg' => $msg]; };
    $pass = function(string $name) use (&$results) { $results[] = ['name' => $name, 'passed' => true, 'msg' => '']; };

    // --- Race 1: concurrent dispensing must never push stock negative ---
    $medicine = $client->post('/api/medicine', ['name' => 'Concurrency Test Med', 'category' => 'Other', 'stock' => 10, 'unit' => 'tablets']);
    $medId = $medicine['body']['id'] ?? null;
    if (!$medId) {
        $fail('dispense race: setup', 'failed to create test medicine');
    } else {
        // 20 concurrent requests each trying to dispense 1 unit against a stock of 10.
        $requests = [];
        for ($i = 0; $i < 20; $i++) {
            $requests[] = ['method' => 'POST', 'route' => '/api/dispensing', 'token' => $adminToken,
                'body' => ['medicineId' => $medId, 'patientName' => 'Race Test Patient', 'quantity' => 1]];
        }
        $dispenseResults = fireConcurrent($baseUrl, $requests);
        $succeeded = count(array_filter($dispenseResults, function($r) { return $r['status'] === 201; }));
        $finalMed = $client->get('/api/medicine/' . $medId);
        $finalStock = $finalMed['body']['stock'] ?? null;

        if ($finalStock === null) {
            $fail('dispense race: final stock readable', 'could not read final stock');
        } elseif ($finalStock < 0) {
            $fail('dispense race: stock never goes negative', "stock went negative: $finalStock (this is a real race condition bug)");
        } else {
            $pass('dispense race: stock never goes negative (stock=' . $finalStock . ')');
        }
        // Exactly as many dispenses should have succeeded as stock actually allows (10), the rest correctly rejected as insufficient stock.
        if ($succeeded === 10) {
            $pass('dispense race: exactly 10 of 20 concurrent requests succeeded (matches starting stock)');
        } else {
            $fail('dispense race: correct number of requests succeeded', "expected 10 succeeded, got $succeeded (stock=$finalStock) — possible race condition allowing overselling or under-selling");
        }
        $client->delete('/api/medicine/' . $medId);
    }

    // --- Race 2: double-booking prevention under concurrent requests ---
    $requests = [];
    for ($i = 0; $i < 10; $i++) {
        $requests[] = ['method' => 'POST', 'route' => '/api/appointments', 'token' => $adminToken,
            'body' => ['patientName' => 'Race Booking Patient ' . $i, 'patientType' => 'Student',
                'doctorId' => 'race-test-doctor', 'date' => '2026-06-01', 'time' => '09:00', 'type' => 'General Check-up']];
    }
    $bookingResults = fireConcurrent($baseUrl, $requests);
    $bookingSucceeded = array_filter($bookingResults, function($r) { return $r['status'] === 201; });
    if (count($bookingSucceeded) === 1) {
        $pass('double-booking race: exactly 1 of 10 concurrent bookings for the same doctor/date/time succeeded');
    } else {
        $fail('double-booking race: exactly 1 concurrent booking succeeds', 'expected exactly 1 success, got ' . count($bookingSucceeded) . ' — possible race condition allowing a double-booking');
    }
    // Cleanup whatever got created.
    $allAppts = $client->get('/api/appointments');
    foreach (($allAppts['body'] ?? []) as $a) {
        if (($a['doctorId'] ?? '') === 'race-test-doctor') $client->delete('/api/appointments/' . $a['id']);
    }

    echo "\n=== Concurrency Test Results ===\n";
    foreach ($results as $r) {
        echo ($r['passed'] ? '[PASS] ' : '[FAIL] ') . $r['name'] . ($r['passed'] ? '' : ' -- ' . $r['msg']) . "\n";
    }
    $failedCount = count(array_filter($results, function($r) { return !$r['passed']; }));
    echo "\n" . (count($results) - $failedCount) . " / " . count($results) . " passed.\n";
    exit($failedCount > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Concurrency test crashed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    if ($serverLogPath && file_exists($serverLogPath)) {
        fwrite(STDERR, "\n--- test server log ---\n" . file_get_contents($serverLogPath) . "\n");
    }
    exit(1);
}

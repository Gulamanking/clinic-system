<?php

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Patient Link (studentId/staffId)');
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->login('admin', 'admin123');

    // --- Explicit id (what the new "Link to Existing Record" selector sends) always wins ---
    $unique = $admin->post('/api/students', [
        'name' => 'Explicit Link Test', 'studentId' => 'LINK-1', 'course' => 'BSIT',
        'yearLevel' => '1st Year', 'status' => 'Active',
    ]);
    $uniqueId = $unique['body']['id'] ?? null;
    $visitExplicit = $admin->post('/api/visits', [
        'patientName' => 'Explicit Link Test', 'patientType' => 'Student', 'date' => '2026-01-01',
        'complaint' => 'test', 'studentId' => $uniqueId,
    ]);
    $t->assertEqual('explicit studentId is stored exactly as given', $uniqueId, $visitExplicit['body']['studentId'] ?? null);

    // --- No explicit id, but the name uniquely matches one student: auto-link fills it in ---
    $soloName = 'Unique Auto Link Name ' . uniqid();
    $solo = $admin->post('/api/students', [
        'name' => $soloName, 'studentId' => 'LINK-2', 'course' => 'BSN',
        'yearLevel' => '2nd Year', 'status' => 'Active',
    ]);
    $soloId = $solo['body']['id'] ?? null;
    $visitAuto = $admin->post('/api/visits', [
        'patientName' => $soloName, 'patientType' => 'Student', 'date' => '2026-01-01', 'complaint' => 'test',
    ]);
    $t->assertEqual('auto-link resolves a uniquely-named student with no explicit id', $soloId, $visitAuto['body']['studentId'] ?? null);

    // --- Two students share a name, no explicit id: auto-link correctly refuses to guess ---
    $dupName = 'Ambiguous Duplicate Name ' . uniqid();
    $dupA = $admin->post('/api/students', [
        'name' => $dupName, 'studentId' => 'LINK-3A', 'course' => 'BSIT',
        'yearLevel' => '1st Year', 'status' => 'Active',
    ]);
    $dupB = $admin->post('/api/students', [
        'name' => $dupName, 'studentId' => 'LINK-3B', 'course' => 'BSN',
        'yearLevel' => '1st Year', 'status' => 'Active',
    ]);
    $visitAmbiguous = $admin->post('/api/visits', [
        'patientName' => $dupName, 'patientType' => 'Student', 'date' => '2026-01-01', 'complaint' => 'test',
    ]);
    $t->assert('auto-link leaves studentId blank when the name is ambiguous', empty($visitAmbiguous['body']['studentId']), 'expected blank, got ' . ($visitAmbiguous['body']['studentId'] ?? 'null'));

    // --- Explicitly picking one of the two duplicates (what the UI selector does) still works ---
    $visitDisambiguated = $admin->post('/api/visits', [
        'patientName' => $dupName, 'patientType' => 'Student', 'date' => '2026-01-01', 'complaint' => 'test',
        'studentId' => $dupB['body']['id'] ?? null,
    ]);
    $t->assertEqual('explicitly selecting one of two duplicates links the exact one chosen', $dupB['body']['id'] ?? null, $visitDisambiguated['body']['studentId'] ?? null);

    // Cleanup
    foreach ([$visitExplicit, $visitAuto, $visitAmbiguous, $visitDisambiguated] as $v) {
        $admin->delete('/api/visits/' . ($v['body']['id'] ?? ''));
    }
    foreach ([$uniqueId, $soloId, $dupA['body']['id'] ?? null, $dupB['body']['id'] ?? null] as $id) {
        if ($id) $admin->delete('/api/students/' . $id);
    }

    return $t->results();
};

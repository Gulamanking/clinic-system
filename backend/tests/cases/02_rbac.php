<?php

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('RBAC');
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->login('admin', 'admin123');

    // --- Admin-only resources block non-admin roles ---
    $physician = $admin->post('/api/users', [
        'fullName' => 'Test Physician', 'username' => 'test.physician', 'password' => 'Physician123!',
        'role' => 'Physician', 'status' => 'Active',
    ]);
    $t->assertEqual('create Physician user succeeds', 201, $physician['status']);

    $physicianClient = new TestClient('http://127.0.0.1:8971');
    $physicianClient->login('test.physician', 'Physician123!');
    $res = $physicianClient->get('/api/users');
    $t->assertEqual('Physician cannot list /api/users', 403, $res['status']);
    $res = $physicianClient->get('/api/roles');
    $t->assertEqual('Physician cannot list /api/roles', 403, $res['status']);

    // --- Self-service Student: row-level isolation ---
    $studentA = $admin->post('/api/students', [
        'name' => 'RBAC Test Student A', 'studentId' => 'RBAC-A', 'course' => 'BSIT',
        'yearLevel' => '1st Year', 'status' => 'Active',
    ]);
    $studentB = $admin->post('/api/students', [
        'name' => 'RBAC Test Student B', 'studentId' => 'RBAC-B', 'course' => 'BSN',
        'yearLevel' => '1st Year', 'status' => 'Active',
    ]);
    $studentAId = $studentA['body']['id'] ?? null;
    $studentBId = $studentB['body']['id'] ?? null;
    $t->assert('two distinct test students created', $studentAId && $studentBId && $studentAId !== $studentBId, 'student creation failed');

    $visitA = $admin->post('/api/visits', [
        'patientName' => 'RBAC Test Student A', 'patientType' => 'Student', 'date' => '2026-01-01',
        'complaint' => 'test', 'studentId' => $studentAId,
    ]);
    $t->assertEqual('visit for Student A created', 201, $visitA['status']);

    $studentUser = $admin->post('/api/users', [
        'fullName' => 'RBAC Test Student A', 'username' => 'rbac.studenta', 'password' => 'Student123!',
        'role' => 'Student', 'status' => 'Active', 'linkedRecordId' => $studentAId,
    ]);
    $t->assertEqual('create Student self-service account succeeds', 201, $studentUser['status']);

    $studentClient = new TestClient('http://127.0.0.1:8971');
    $studentClient->login('rbac.studenta', 'Student123!');

    $visits = $studentClient->get('/api/visits');
    $names = array_map(function($v) { return $v['patientName'] ?? null; }, $visits['body'] ?? []);
    $t->assertEqual('Student A sees exactly 1 visit (her own)', 1, count($visits['body'] ?? []));
    $t->assert('Student A\'s visit list contains only her own name', in_array('RBAC Test Student A', $names, true), 'unexpected names: ' . implode(',', $names));

    $students = $studentClient->get('/api/students');
    $t->assertEqual('Student A sees exactly 1 student record (her own)', 1, count($students['body'] ?? []));
    $seenIds = array_map(function($s) { return $s['id'] ?? null; }, $students['body'] ?? []);
    $t->assert('Student A does not see Student B\'s record', !in_array($studentBId, $seenIds, true), 'Student B leaked into Student A\'s student list');

    // --- Default-deny: tables with no explicit ownership mapping return empty, not everything ---
    $incidents = $studentClient->get('/api/incidents');
    $t->assertEqual('Student sees 0 incidents (no ownership mapping = default deny, not open access)', 0, count($incidents['body'] ?? []));

    // --- Identity forcing: a self-service Student cannot book under someone else's name ---
    $spoofAttempt = $studentClient->post('/api/appointments', [
        'patientName' => 'Someone Completely Different', 'patientType' => 'Student',
        'date' => '2026-02-01', 'time' => '09:00', 'type' => 'General Check-up',
    ]);
    $t->assertEqual('self-service appointment booking succeeds', 201, $spoofAttempt['status']);
    $t->assertEqual('backend forces the booking onto the account\'s real linked identity', 'RBAC Test Student A', $spoofAttempt['body']['patientName'] ?? null);
    $t->assertEqual('backend stamps the real studentId, not a guess', $studentAId, $spoofAttempt['body']['studentId'] ?? null);

    // Cleanup
    $admin->delete('/api/visits/' . ($visitA['body']['id'] ?? ''));
    $admin->delete('/api/appointments/' . ($spoofAttempt['body']['id'] ?? ''));
    $admin->delete('/api/students/' . $studentAId);
    $admin->delete('/api/students/' . $studentBId);
    $admin->delete('/api/users/' . ($studentUser['body']['id'] ?? ''));
    $admin->delete('/api/users/' . ($physician['body']['id'] ?? ''));

    return $t->results();
};

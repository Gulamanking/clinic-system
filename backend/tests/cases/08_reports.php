<?php

// Covers the reports added to close the gaps between the module design
// document and the implementation. Each one is derived in handleReports()
// rather than stored, so the characteristic failure is a filter that quietly
// matches nothing. These assertions seed known data, then check the derived
// set contains exactly what it should and excludes what it should not.

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Spec Reports');

    $res = $client->login('admin', 'admin123');
    $client->setToken($res['body']['token'] ?? null);

    // --- Seed students with known health details -------------------------
    $created = $client->post('/api/students', [
        'name' => 'Report Allergy Student', 'studentId' => 'RPT-ALLERGY-1',
        'course' => 'STEM', 'yearLevel' => 'Grade 11', 'status' => 'Active',
        'bloodType' => 'O+', 'allergies' => 'Peanuts', 'conditions' => '',
    ]);
    $t->assertEqual('allergy student created', 201, $created['status']);

    $created = $client->post('/api/students', [
        'name' => 'Report Asthma Student', 'studentId' => 'RPT-ASTHMA-1',
        'course' => 'ABM', 'yearLevel' => 'Grade 12', 'status' => 'Active',
        'bloodType' => 'A+', 'allergies' => 'None', 'conditions' => 'Mild asthma since childhood',
    ]);
    $t->assertEqual('asthma student created', 201, $created['status']);

    // "None" must not count as an allergy, and this student has no condition
    // and no medical record, so they are the control for every filter below.
    $created = $client->post('/api/students', [
        'name' => 'Report Healthy Student', 'studentId' => 'RPT-HEALTHY-1',
        'course' => 'GAS', 'yearLevel' => 'Grade 11', 'status' => 'Active',
        'bloodType' => 'B+', 'allergies' => 'None', 'conditions' => '',
    ]);
    $t->assertEqual('control student created', 201, $created['status']);

    $reports = $client->get('/reports');
    $t->assertEqual('reports endpoint responds', 200, $reports['status']);
    $rec = $reports['body']['records'] ?? [];

    $names = function(array $rows): array {
        return array_map(fn($r) => $r['name'] ?? '', $rows);
    };

    // --- Module 1: Students with Allergies -------------------------------
    $allergies = $rec['studentsWithAllergies'] ?? null;
    $t->assert('studentsWithAllergies present', is_array($allergies), 'key missing from reports payload');
    $t->assert('allergy student is listed', in_array('Report Allergy Student', $names($allergies ?? []), true));
    $t->assert('None is not treated as an allergy', !in_array('Report Asthma Student', $names($allergies ?? []), true));
    $t->assert('control student excluded from allergies', !in_array('Report Healthy Student', $names($allergies ?? []), true));

    // --- Module 1: Students with Asthma ----------------------------------
    $asthma = $rec['studentsWithAsthma'] ?? null;
    $t->assert('studentsWithAsthma present', is_array($asthma), 'key missing from reports payload');
    $t->assert('asthma student is listed', in_array('Report Asthma Student', $names($asthma ?? []), true));
    $t->assert('non-asthmatic excluded', !in_array('Report Allergy Student', $names($asthma ?? []), true));

    // --- Module 1: Immunization Status -----------------------------------
    // A status report lists everyone, so the control student must appear with
    // an explicit value rather than being filtered out for having no record.
    $immun = $rec['immunizationStatus'] ?? null;
    $t->assert('immunizationStatus present', is_array($immun), 'key missing from reports payload');
    $t->assert('every student appears in the status report', in_array('Report Healthy Student', $names($immun ?? []), true));
    $blank = array_values(array_filter($immun ?? [], fn($r) => ($r['name'] ?? '') === 'Report Healthy Student'));
    $t->assertEqual('missing immunisation reads as No record', 'No record', $blank[0]['immunizationStatus'] ?? '');

    // --- Module 9: Annual Report -----------------------------------------
    $annual = $rec['annualReport'] ?? null;
    $t->assert('annualReport present', is_array($annual), 'key missing from reports payload');
    $t->assertEqual('annual report covers five metrics', 5, count($annual ?? []));
    $metrics = array_map(fn($r) => $r['metric'] ?? '', $annual ?? []);
    $t->assert('annual report counts clinic visits', in_array('Clinic visits', $metrics, true));

    // --- Module 10: Role and Permission Matrix ---------------------------
    $matrix = $rec['rolePermissionMatrix'] ?? null;
    $t->assert('rolePermissionMatrix present', is_array($matrix), 'key missing from reports payload');
    $t->assert('matrix is not empty', count($matrix ?? []) > 0, 'no role/permission pairs resolved');
    $first = ($matrix ?? [])[0] ?? [];
    $t->assert('matrix rows carry role, module and action',
        isset($first['role'], $first['module'], $first['action']),
        'row shape is wrong: ' . json_encode($first));

    // --- Module 10: Privacy Consent Status -------------------------------
    $t->assert('privacyConsentStatus present', is_array($rec['privacyConsentStatus'] ?? null), 'key missing');

    // --- Module 6: staff medical certificates ----------------------------
    $t->assert('staffMedicalCertificates present', is_array($rec['staffMedicalCertificates'] ?? null), 'key missing');

    // --- Module 10: Backup Status ----------------------------------------
    // Derived from the audit trail, so take a backup and confirm it shows up.
    $t->assert('backupStatus present before any backup', is_array($rec['backupStatus'] ?? null), 'key missing');
    $before = count($rec['backupStatus'] ?? []);
    $backup = $client->get('/backup');
    $t->assertEqual('backup runs', 200, $backup['status']);
    $after = $client->get('/reports');
    $t->assert('backup is recorded in backup status',
        count($after['body']['records']['backupStatus'] ?? []) > $before,
        'backup.created did not appear in the derived report');

    // --- Module 10: User Activity Log ------------------------------------
    $activity = $after['body']['records']['userActivityLog'] ?? null;
    $t->assert('userActivityLog present', is_array($activity), 'key missing');
    $adminRows = array_values(array_filter($activity ?? [], fn($r) => ($r['user'] ?? '') === 'admin'));
    $t->assert('admin appears in the activity summary', count($adminRows) > 0, 'admin missing from per-user rollup');
    $t->assert('activity summary counts actions', ($adminRows[0]['actions'] ?? 0) > 0);

    return $t->results();
};

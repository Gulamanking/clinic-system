<?php

// Covers the schema additions that closed the remaining field-level gaps
// against the module design document: student demographics, the three missing
// vital signs, medicine supply details, the generated queue number, and the
// demographic analytics those demographics unblock.
//
// The characteristic failure here is silent: a column missing from
// getAllowedColumns() is stripped on write with no error, so the request
// succeeds and the value simply never arrives. Every field is therefore
// written and read back rather than just posted.

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Spec Fields');

    $res = $client->login('admin', 'admin123');
    $client->setToken($res['body']['token'] ?? null);

    // --- Student demographics round-trip ---------------------------------
    $created = $client->post('/api/students', [
        'name' => 'Fields Demo Student', 'studentId' => 'FLD-001',
        'course' => 'STEM', 'yearLevel' => 'Grade 11', 'status' => 'Active',
        'section' => 'Rizal', 'gender' => 'Female', 'birthDate' => '2009-04-15',
    ]);
    $t->assertEqual('student with demographics created', 201, $created['status']);
    $sid = $created['body']['id'] ?? '';
    $back = $client->get('/api/students/' . $sid);
    $t->assertEqual('section persists', 'Rizal', $back['body']['section'] ?? '');
    $t->assertEqual('gender persists', 'Female', $back['body']['gender'] ?? '');
    $t->assertEqual('birth date persists', '2009-04-15', $back['body']['birthDate'] ?? '');

    // --- Medicine supply details round-trip ------------------------------
    $med = $client->post('/api/medicine', [
        'name' => 'Fields Demo Syrup', 'category' => 'Analgesic', 'stock' => 20,
        'unit' => 'bottles', 'expiryDate' => '2027-01-31', 'reorderLevel' => 5,
        'supplier' => 'Acme Pharma', 'batchNumber' => 'BATCH-77',
    ]);
    $t->assertEqual('medicine created', 201, $med['status']);
    $medBack = $client->get('/api/medicine/' . ($med['body']['id'] ?? ''));
    $t->assertEqual('supplier persists', 'Acme Pharma', $medBack['body']['supplier'] ?? '');
    $t->assertEqual('batch number persists', 'BATCH-77', $medBack['body']['batchNumber'] ?? '');

    // --- Vital signs round-trip ------------------------------------------
    $v1 = $client->post('/api/visits', [
        'patientName' => 'Fields Demo Student', 'patientType' => 'Student',
        'date' => '2026-03-02', 'time' => '08:00', 'complaint' => 'Cough',
        'temperature' => '37.1', 'bloodPressure' => '110/70', 'pulseRate' => '80',
        'respiration' => '18', 'height' => '162', 'weight' => '54',
    ]);
    $t->assertEqual('visit created', 201, $v1['status']);
    $v1Back = $client->get('/api/visits/' . ($v1['body']['id'] ?? ''));
    $t->assertEqual('respiration persists', '18', $v1Back['body']['respiration'] ?? '');
    $t->assertEqual('height persists', '162', $v1Back['body']['height'] ?? '');
    $t->assertEqual('weight persists', '54', $v1Back['body']['weight'] ?? '');

    // --- Queue numbers ----------------------------------------------------
    // Spec Module 2 step 3. Numbered per day, so the first visit on a fresh
    // date starts again at 001.
    $t->assertEqual('first visit of the day is queue 001', '001', $v1['body']['queueNo'] ?? '');
    $t->assertEqual('visit status defaults to Open', 'Open', $v1['body']['status'] ?? '');

    $v2 = $client->post('/api/visits', [
        'patientName' => 'Second Patient', 'patientType' => 'Student', 'date' => '2026-03-02', 'time' => '08:30',
    ]);
    $t->assertEqual('second visit same day is queue 002', '002', $v2['body']['queueNo'] ?? '');

    $other = $client->post('/api/visits', [
        'patientName' => 'Next Day Patient', 'patientType' => 'Student', 'date' => '2026-03-03', 'time' => '09:00',
    ]);
    $t->assertEqual('a different day restarts at 001', '001', $other['body']['queueNo'] ?? '');

    // Deleting the highest number frees it for reuse, which is intended: the
    // queue number is a call order, not an identifier, and a permanent gap
    // would leave staff calling 003 when 002 was never seen.
    $del = $client->delete('/api/visits/' . ($v2['body']['id'] ?? ''));
    $t->assert('second visit deleted', in_array($del['status'], [200, 204], true), 'delete returned ' . $del['status']);
    $v3 = $client->post('/api/visits', [
        'patientName' => 'Third Patient', 'patientType' => 'Student', 'date' => '2026-03-02', 'time' => '09:15',
    ]);
    $t->assertEqual('a freed queue number is reissued, keeping the call order gapless', '002', $v3['body']['queueNo'] ?? '');

    // An explicitly supplied queue number is respected rather than overwritten.
    $manual = $client->post('/api/visits', [
        'patientName' => 'Manual Queue', 'patientType' => 'Student', 'date' => '2026-03-02', 'queueNo' => '042',
    ]);
    $t->assertEqual('an explicit queue number is kept', '042', $manual['body']['queueNo'] ?? '');

    // --- Module 9 demographic analytics -----------------------------------
    $reports = $client->get('/reports');
    $rec = $reports['body']['records'] ?? [];

    $age = $rec['ageDistribution'] ?? null;
    $t->assert('ageDistribution present', is_array($age), 'key missing');
    $t->assertEqual('age report uses six bands', 6, count($age ?? []));
    $bands = [];
    foreach ($age ?? [] as $row) { $bands[$row['ageBand']] = $row['students']; }
    // Born 2009-04-15, so 16 or 17 depending on the day the suite runs.
    $t->assert('the student lands in the 15-17 band', ($bands['15-17'] ?? 0) >= 1,
        'bands were ' . json_encode($bands));

    $gender = $rec['genderDistribution'] ?? null;
    $t->assert('genderDistribution present', is_array($gender), 'key missing');
    $female = array_values(array_filter($gender ?? [], fn($g) => ($g['gender'] ?? '') === 'Female'));
    $t->assert('female student counted', count($female) > 0 && $female[0]['students'] >= 1);

    $course = $rec['courseDistribution'] ?? null;
    $t->assert('courseDistribution present', is_array($course), 'key missing');
    $stem = array_values(array_filter($course ?? [], fn($c) => ($c['strand'] ?? '') === 'STEM'));
    $t->assert('STEM strand counted', count($stem) > 0 && $stem[0]['students'] >= 1);

    // Students with no birth date must not be silently dropped from the age
    // report — they belong in Unknown so the totals still reconcile.
    $client->post('/api/students', [
        'name' => 'No Birthday Student', 'studentId' => 'FLD-002',
        'course' => 'ICT', 'yearLevel' => 'Grade 12', 'status' => 'Active',
    ]);
    $reports2 = $client->get('/reports');
    $age2 = $reports2['body']['records']['ageDistribution'] ?? [];
    $unknown = array_values(array_filter($age2, fn($r) => ($r['ageBand'] ?? '') === 'Unknown'));
    $t->assert('a student with no birth date counts as Unknown',
        count($unknown) > 0 && $unknown[0]['students'] >= 1,
        'Unknown band was ' . json_encode($unknown));

    return $t->results();
};

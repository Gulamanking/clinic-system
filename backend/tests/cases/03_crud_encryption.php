<?php

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('CRUD & Encryption');
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->login('admin', 'admin123');

    $secretDiagnosis = 'CONFIDENTIAL_DIAGNOSIS_TEXT_' . uniqid();
    $created = $admin->post('/api/visits', [
        'patientName' => 'Encryption Test Patient', 'patientType' => 'Student',
        'date' => '2026-01-01', 'complaint' => 'test', 'diagnosis' => $secretDiagnosis,
    ]);
    $id = $created['body']['id'] ?? null;
    $t->assertEqual('create visit succeeds', 201, $created['status']);
    $t->assertEqual('API returns the plaintext diagnosis on create', $secretDiagnosis, $created['body']['diagnosis'] ?? null);

    $fetched = $admin->get('/api/visits/' . $id);
    $t->assertEqual('API returns the plaintext diagnosis on read-back', $secretDiagnosis, $fetched['body']['diagnosis'] ?? null);

    // Read the raw SQLite file directly, bypassing the app entirely.
    try {
        $pdo = new PDO('sqlite:' . $testDbPath);
        $stmt = $pdo->prepare('SELECT diagnosis FROM visits WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $raw = $stmt->fetchColumn();
        $t->assert('raw stored value is ciphertext, not plaintext', $raw !== $secretDiagnosis, 'diagnosis was stored in plaintext');
        $t->assert('raw stored value has the enc: prefix', strpos((string)$raw, 'enc:') === 0, 'got: ' . substr((string)$raw, 0, 20));
    } catch (Exception $e) {
        $t->assert('could open raw sqlite file to verify encryption', false, $e->getMessage());
    }

    $updated = $admin->put('/api/visits/' . $id, ['diagnosis' => $secretDiagnosis . '_UPDATED']);
    $t->assertEqual('update visit succeeds', 200, $updated['status']);
    $t->assertEqual('update returns new plaintext value', $secretDiagnosis . '_UPDATED', $updated['body']['diagnosis'] ?? null);

    $deleted = $admin->delete('/api/visits/' . $id);
    $t->assertEqual('delete visit succeeds', 200, $deleted['status']);
    $afterDelete = $admin->get('/api/visits/' . $id);
    $t->assertEqual('deleted visit is gone', 404, $afterDelete['status']);

    return $t->results();
};

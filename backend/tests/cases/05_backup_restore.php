<?php

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Backup & Restore');
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->login('admin', 'admin123');

    $before = $admin->get('/backup');
    $t->assertEqual('backup endpoint succeeds', 200, $before['status']);
    $t->assert('backup contains a tables payload', isset($before['body']['tables']), 'no tables key in backup response');

    $created = $admin->post('/api/students', [
        'name' => 'Restore Test Delete Me', 'studentId' => 'RESTORE-1', 'course' => 'BSIT',
        'yearLevel' => '1st Year', 'status' => 'Active',
    ]);
    $createdId = $created['body']['id'] ?? null;
    $t->assertEqual('mutation before restore succeeds', 201, $created['status']);

    $duringList = $admin->get('/api/students');
    $names = array_map(function($s) { return $s['name'] ?? null; }, $duringList['body'] ?? []);
    $t->assert('mutation is visible before restore', in_array('Restore Test Delete Me', $names, true), 'mutated row not found before restore');

    $restore = $admin->post('/restore', $before['body']);
    $t->assertEqual('restore endpoint succeeds', 200, $restore['status']);

    $afterList = $admin->get('/api/students');
    $namesAfter = array_map(function($s) { return $s['name'] ?? null; }, $afterList['body'] ?? []);
    $t->assert('mutation created after the backup is gone post-restore', !in_array('Restore Test Delete Me', $namesAfter, true), 'mutated row survived the restore');

    return $t->results();
};

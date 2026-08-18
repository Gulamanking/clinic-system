<?php

// Simulates a collaborator's pre-existing "original schema" database (the
// exact scenario raised when discussing the MySQL upgrade path) by directly
// stripping columns/tables this project's phases have added, then confirms
// the app heals itself on the very next request — no manual migration step.
return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Migration Self-Heal (legacy schema simulation)');
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->login('admin', 'admin123');

    $existingStudent = $admin->post('/api/students', [
        'name' => 'Backfill Match Test', 'studentId' => 'BACKFILL-1', 'course' => 'BSIT',
        'yearLevel' => '1st Year', 'status' => 'Active',
    ]);
    $existingVisit = $admin->post('/api/visits', [
        'patientName' => 'Backfill Match Test', 'patientType' => 'Student', 'date' => '2026-01-01',
        'complaint' => 'test', 'studentId' => $existingStudent['body']['id'] ?? null,
    ]);

    try {
        $pdo = new PDO('sqlite:' . $testDbPath);
        $pdo->exec('ALTER TABLE visits DROP COLUMN studentid');
        $pdo->exec('ALTER TABLE visits DROP COLUMN staffid');
        $pdo->exec('DROP TABLE roles');
    } catch (Exception $e) {
        $t->assert('could strip schema to simulate a legacy database', false, $e->getMessage());
    }

    // Any request re-triggers initializeDatabaseSchema() + applyMigrations() + seedRoles().
    $healRequest = $admin->get('/api/students');
    $t->assertEqual('app still responds after schema was stripped', 200, $healRequest['status']);

    $pdo = new PDO('sqlite:' . $testDbPath);
    $cols = $pdo->query('PRAGMA table_info(visits)')->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map(function($c) { return $c['name']; }, $cols);
    $t->assert('studentid column was restored on visits', in_array('studentid', $colNames, true), 'columns: ' . implode(',', $colNames));
    $t->assert('staffid column was restored on visits', in_array('staffid', $colNames, true), 'columns: ' . implode(',', $colNames));

    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $t->assert('roles table was recreated', in_array('roles', $tables, true), 'tables: ' . implode(',', $tables));

    $roles = $admin->get('/api/roles');
    $roleNames = array_map(function($r) { return $r['name'] ?? null; }, $roles['body'] ?? []);
    $t->assertEqual('all 6 roles were reseeded', 6, count($roleNames));
    $t->assert('Clinic Administrator role present after reseed', in_array('Clinic Administrator', $roleNames, true), 'roles: ' . implode(',', $roleNames));

    // The pre-existing visit's studentId column was dropped and recreated
    // empty by the ALTER TABLE round-trip (SQLite has no column-preserving
    // DROP COLUMN restore) — confirm the one-time backfill re-linked it by
    // exact name match, same as it would for a real collaborator's legacy data.
    $healedVisit = $admin->get('/api/visits/' . ($existingVisit['body']['id'] ?? ''));
    $t->assertEqual('backfill re-linked the pre-existing visit to its student by name', $existingStudent['body']['id'] ?? null, $healedVisit['body']['studentId'] ?? null);

    $admin->delete('/api/visits/' . ($existingVisit['body']['id'] ?? ''));
    $admin->delete('/api/students/' . ($existingStudent['body']['id'] ?? ''));

    return $t->results();
};

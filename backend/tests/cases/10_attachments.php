<?php

// Spec Module 1 names "PDF/image attachments" as a key feature. Files are
// stored in the database rather than on disk because the application
// filesystem does not survive a redeploy.
//
// The risks worth pinning down are the ones that fail quietly or dangerously:
// a list endpoint that ships whole files, an upload that accepts anything, and
// a download that hands the browser a file it will execute.

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Attachments');

    $res = $client->login('admin', 'admin123');
    $client->setToken($res['body']['token'] ?? null);

    $student = $client->post('/api/students', [
        'name' => 'Attachment Owner', 'studentId' => 'ATT-001',
        'course' => 'STEM', 'yearLevel' => 'Grade 11', 'status' => 'Active',
    ]);
    $studentId = $student['body']['id'] ?? '';
    $t->assertEqual('owner student created', 201, $student['status']);

    // A real (tiny) PNG, so the stored bytes are a genuine image.
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $png = base64_encode($pngBytes);

    // --- Upload -----------------------------------------------------------
    $up = $client->post('/api/attachments', [
        'recordType' => 'student', 'recordId' => $studentId,
        'fileName' => 'referral.png', 'mimeType' => 'image/png', 'data' => $png,
    ]);
    $t->assertEqual('upload succeeds', 201, $up['status']);
    $attId = $up['body']['id'] ?? '';
    $t->assertEqual('size is recorded from the decoded bytes', strlen($pngBytes), (int)($up['body']['sizeBytes'] ?? 0));
    $t->assert('the create response does not echo the file back', !isset($up['body']['content']),
        'content was returned in the create response');

    // A data: URL is what FileReader gives the browser, so it must work too.
    $up2 = $client->post('/api/attachments', [
        'recordType' => 'student', 'recordId' => $studentId,
        'fileName' => 'scan.png', 'mimeType' => 'image/png', 'data' => 'data:image/png;base64,' . $png,
    ]);
    $t->assertEqual('a data: URL is accepted', 201, $up2['status']);

    // --- Path traversal in the filename ------------------------------------
    $evil = $client->post('/api/attachments', [
        'recordType' => 'student', 'recordId' => $studentId,
        'fileName' => '../../etc/passwd.png', 'mimeType' => 'image/png', 'data' => $png,
    ]);
    $t->assertEqual('traversal filename still uploads', 201, $evil['status']);
    $t->assertEqual('the path is stripped to a leaf name', 'passwd.png', $evil['body']['fileName'] ?? '');

    // --- Rejections --------------------------------------------------------
    $badType = $client->post('/api/attachments', [
        'recordType' => 'student', 'recordId' => $studentId,
        'fileName' => 'payload.html', 'mimeType' => 'text/html', 'data' => base64_encode('<script>alert(1)</script>'),
    ]);
    $t->assertEqual('an executable content type is refused', 415, $badType['status']);

    $noRecord = $client->post('/api/attachments', [
        'recordType' => '', 'recordId' => '',
        'fileName' => 'orphan.png', 'mimeType' => 'image/png', 'data' => $png,
    ]);
    $t->assertEqual('an attachment with no owning record is refused', 400, $noRecord['status']);

    $notBase64 = $client->post('/api/attachments', [
        'recordType' => 'student', 'recordId' => $studentId,
        'fileName' => 'broken.png', 'mimeType' => 'image/png', 'data' => '!!!! not base64 !!!!',
    ]);
    $t->assertEqual('non-base64 content is refused', 400, $notBase64['status']);

    $tooBig = $client->post('/api/attachments', [
        'recordType' => 'student', 'recordId' => $studentId,
        'fileName' => 'huge.pdf', 'mimeType' => 'application/pdf',
        'data' => base64_encode(str_repeat('A', 5 * 1024 * 1024 + 10)),
    ]);
    $t->assertEqual('a file over the size limit is refused', 413, $tooBig['status']);

    // --- Listing ------------------------------------------------------------
    $list = $client->get('/attachments/record/' . rawurlencode($studentId));
    $t->assertEqual('list responds', 200, $list['status']);
    $t->assertEqual('three attachments stored for this student', 3, count($list['body'] ?? []));
    $carriesContent = false;
    foreach ($list['body'] ?? [] as $row) {
        if (isset($row['content'])) $carriesContent = true;
    }
    $t->assert('the list never ships file contents', !$carriesContent,
        'listing whole files would send every attachment on one request');

    $other = $client->get('/attachments/record/no-such-record');
    $t->assertEqual('filtering by record id excludes other records', 0, count($other['body'] ?? []));

    // --- Delete --------------------------------------------------------------
    $del = $client->delete('/api/attachments/' . $attId);
    $t->assert('attachment deleted', in_array($del['status'], [200, 204], true), 'delete returned ' . $del['status']);
    $after = $client->get('/attachments/record/' . rawurlencode($studentId));
    $t->assertEqual('deleted attachment is gone', 2, count($after['body'] ?? []));

    // --- Audit trail ----------------------------------------------------------
    $reports = $client->get('/reports');
    $audit = $reports['body']['records']['auditTrail'] ?? [];
    $uploads = array_filter($audit, fn($a) => ($a['action'] ?? '') === 'attachment.uploaded');
    $t->assert('uploads are audit logged', count($uploads) > 0, 'no attachment.uploaded entries found');

    // --- Backup coverage --------------------------------------------------------
    // Attachments were not in BACKUP_TABLES before this feature; a backup that
    // omits scanned clinical documents is not a backup.
    $backup = $client->get('/backup');
    $t->assert('attachments are included in the backup', isset($backup['body']['tables']['attachments']),
        'backup tables were: ' . implode(', ', array_keys($backup['body']['tables'] ?? [])));
    $t->assert('privacy consents are included in the backup', isset($backup['body']['tables']['privacy_consents']));

    return $t->results();
};

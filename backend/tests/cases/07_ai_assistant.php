<?php

// No real Gemini API key exists in any test environment — this verifies the
// app degrades gracefully (a clean "not configured" response) rather than
// crashing when the key is absent, which is the one part of the AI feature
// that's actually testable without a real key.
return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('AI Assistant (no key configured)');
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->login('admin', 'admin123');

    $visit = $admin->post('/api/visits', [
        'patientName' => 'AI Test Patient', 'patientType' => 'Student', 'date' => '2026-01-01',
        'complaint' => 'headache', 'diagnosis' => 'tension headache',
    ]);
    $visitId = $visit['body']['id'] ?? null;

    $res = $admin->post('/api/ai/analyze', ['visitId' => $visitId]);
    $t->assertEqual('AI analyze request does not crash (200 with a graceful failure)', 200, $res['status']);
    $t->assertEqual('response reports not_configured, not a raw error', 'not_configured', $res['body']['status'] ?? null);
    $t->assert('response includes the disclaimer regardless of outcome', !empty($res['body']['disclaimer']), 'no disclaimer in response');

    $admin->delete('/api/visits/' . $visitId);

    return $t->results();
};

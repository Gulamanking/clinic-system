<?php

// Spec Module 4 lists email/SMS notification as a key feature and step 8 of the
// booking workflow. Delivery itself needs a real mail server, so what is pinned
// down here is the behaviour that must hold whether or not one is configured:
// booking never fails because of mail, the outcome is always recorded, and a
// notification is not fired for an edit that changed nothing.

require_once dirname(__DIR__, 2) . '/mailer.php';

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Notifications');

    $res = $client->login('admin', 'admin123');
    $client->setToken($res['body']['token'] ?? null);

    // No MAIL_HOST is set for the test run, so this is the unconfigured path.
    $t->assert('mailer reports itself unconfigured without MAIL_HOST', !mailerIsConfigured());

    [$ok, $detail] = sendMail('someone@example.com', 'Subject', 'Body');
    $t->assert('sending without configuration does not throw', true);
    $t->assertEqual('and reports why rather than failing', 'not_configured', $detail);
    $t->assert('nothing is reported as sent', !$ok);

    // An address that is not an address is rejected before any socket is opened.
    [$ok2, $detail2] = sendMail('not-an-address', 'Subject', 'Body');
    $t->assertEqual('an invalid recipient is refused', 'invalid_recipient', $detail2);
    $t->assert('invalid recipient is not reported sent', !$ok2);

    $student = $client->post('/api/students', [
        'name' => 'Notify Student', 'studentId' => 'NTF-001',
        'course' => 'STEM', 'yearLevel' => 'Grade 11', 'status' => 'Active',
        'email' => 'guardian@example.com', 'contactNumber' => '09171234567',
    ]);
    $t->assertEqual('student with an email created', 201, $student['status']);
    $t->assertEqual('email persists', 'guardian@example.com', $student['body']['email'] ?? '');
    $studentRowId = $student['body']['id'] ?? '';

    // --- Booking still succeeds with no mail server ------------------------
    $appt = $client->post('/api/appointments', [
        'patientName' => 'Notify Student', 'patientType' => 'Student',
        'studentId' => $studentRowId, 'date' => '2026-11-04', 'time' => '10:00',
        'type' => 'Consultation', 'status' => 'Pending',
    ]);
    $t->assertEqual('booking succeeds even though mail is unconfigured', 201, $appt['status']);
    $apptId = $appt['body']['id'] ?? '';

    $audit = $client->get('/reports')['body']['records']['auditTrail'] ?? [];
    $booked = array_values(array_filter($audit, fn($a) => ($a['action'] ?? '') === 'notification.booked'));
    $t->assert('the booking notification attempt is recorded', count($booked) > 0,
        'no notification.booked entry in the audit trail');

    // --- Recipient resolution ----------------------------------------------
    $recipients = notificationRecipientsFor([
        'studentId' => $studentRowId, 'patientName' => 'Notify Student',
    ]);
    $t->assert('the student email is resolved as a recipient',
        in_array('guardian@example.com', $recipients, true),
        'resolved: ' . json_encode($recipients));
    $t->assert('no SMS recipient without a configured gateway', count($recipients) === 1,
        'resolved: ' . json_encode($recipients));

    $none = notificationRecipientsFor(['studentId' => 'does-not-exist']);
    $t->assertEqual('an unknown patient resolves to no recipients', 0, count($none));

    // --- Status transitions --------------------------------------------------
    $before = count(array_filter($audit, fn($a) => ($a['action'] ?? '') === 'notification.confirmed'));
    $client->put('/api/appointments/' . $apptId, ['status' => 'Confirmed']);
    $audit2 = $client->get('/reports')['body']['records']['auditTrail'] ?? [];
    $confirmed = count(array_filter($audit2, fn($a) => ($a['action'] ?? '') === 'notification.confirmed'));
    $t->assert('confirming an appointment notifies', $confirmed > $before);

    // Editing something else must not re-notify — a patient should not get a
    // second confirmation because a note was corrected.
    $client->put('/api/appointments/' . $apptId, ['status' => 'Confirmed', 'notes' => 'Bring records']);
    $audit3 = $client->get('/reports')['body']['records']['auditTrail'] ?? [];
    $confirmedAgain = count(array_filter($audit3, fn($a) => ($a['action'] ?? '') === 'notification.confirmed'));
    $t->assertEqual('an edit that does not change status does not re-notify', $confirmed, $confirmedAgain);

    $client->put('/api/appointments/' . $apptId, ['status' => 'Cancelled']);
    $audit4 = $client->get('/reports')['body']['records']['auditTrail'] ?? [];
    $cancelled = count(array_filter($audit4, fn($a) => ($a['action'] ?? '') === 'notification.cancelled'));
    $t->assert('cancelling an appointment notifies', $cancelled > 0);

    return $t->results();
};

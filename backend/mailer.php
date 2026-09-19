<?php

// Appointment notifications (spec Module 4: "email/SMS notification").
//
// Talks SMTP over a socket rather than using PHP's mail(), which needs a local
// MTA that does not exist in a container. Credentials come from the
// environment; when none are configured the app carries on and records that
// the notification was skipped, exactly as the AI assistant does without an
// API key. A clinic must never fail to book an appointment because the mail
// server is down.
//
// SMS is covered through an email-to-SMS gateway rather than a second
// provider: set SMS_GATEWAY_DOMAIN and the patient's contact number is also
// messaged as <digits>@<gateway>, which is what most carriers offer schools.

require_once __DIR__ . '/config.php';

function mailerIsConfigured(): bool {
    $cfg = getConfig();
    return trim((string)($cfg['mail_host'] ?? '')) !== ''
        && trim((string)($cfg['mail_from'] ?? '')) !== '';
}

/**
 * Returns [sent(bool), detail(string)]. Never throws: a failed notification
 * must not fail the operation that triggered it.
 */
function sendMail(string $to, string $subject, string $body): array {
    $cfg = getConfig();
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, 'invalid_recipient'];
    }
    if (!mailerIsConfigured()) {
        return [false, 'not_configured'];
    }

    $host = (string)$cfg['mail_host'];
    $port = (int)($cfg['mail_port'] ?: 587);
    $user = (string)($cfg['mail_username'] ?? '');
    $pass = (string)($cfg['mail_password'] ?? '');
    $from = (string)$cfg['mail_from'];
    $fromName = (string)($cfg['mail_from_name'] ?? 'School Clinic');
    $encryption = strtolower((string)($cfg['mail_encryption'] ?? 'tls'));
    $timeout = 10;

    $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $errno = 0; $errstr = '';
    $sock = @stream_socket_client("$transport:$port", $errno, $errstr, $timeout);
    if (!$sock) {
        return [false, 'connect_failed: ' . $errstr];
    }
    stream_set_timeout($sock, $timeout);

    $read = function () use ($sock): string {
        $out = '';
        while (($line = fgets($sock, 515)) !== false) {
            $out .= $line;
            // A multi-line reply keeps a hyphen in the fourth column.
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        return $out;
    };
    $cmd = function (string $line) use ($sock, $read): string {
        fwrite($sock, $line . "\r\n");
        return $read();
    };
    $code = fn(string $reply): int => (int)substr(trim($reply), 0, 3);

    $greeting = $read();
    if ($code($greeting) !== 220) { fclose($sock); return [false, 'bad_greeting: ' . trim($greeting)]; }

    $ehlo = $cmd('EHLO clinic-system');
    if ($code($ehlo) !== 250) { fclose($sock); return [false, 'ehlo_rejected: ' . trim($ehlo)]; }

    if ($encryption === 'tls') {
        $start = $cmd('STARTTLS');
        if ($code($start) !== 220) { fclose($sock); return [false, 'starttls_refused: ' . trim($start)]; }
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock);
            return [false, 'tls_handshake_failed'];
        }
        // The server must be greeted again on the encrypted channel.
        $ehlo = $cmd('EHLO clinic-system');
        if ($code($ehlo) !== 250) { fclose($sock); return [false, 'ehlo_rejected_after_tls']; }
    }

    if ($user !== '') {
        $auth = $cmd('AUTH LOGIN');
        if ($code($auth) === 334) {
            $u = $cmd(base64_encode($user));
            if ($code($u) !== 334) { fclose($sock); return [false, 'auth_username_rejected']; }
            $p = $cmd(base64_encode($pass));
            if ($code($p) !== 235) { fclose($sock); return [false, 'auth_failed']; }
        } else {
            fclose($sock);
            return [false, 'auth_unsupported: ' . trim($auth)];
        }
    }

    $mailFrom = $cmd('MAIL FROM:<' . $from . '>');
    if ($code($mailFrom) !== 250) { fclose($sock); return [false, 'sender_rejected: ' . trim($mailFrom)]; }
    $rcpt = $cmd('RCPT TO:<' . $to . '>');
    if (!in_array($code($rcpt), [250, 251], true)) { fclose($sock); return [false, 'recipient_rejected: ' . trim($rcpt)]; }

    $dataCmd = $cmd('DATA');
    if ($code($dataCmd) !== 354) { fclose($sock); return [false, 'data_refused']; }

    // Strip CR/LF out of header values so a crafted name cannot inject headers.
    $safeSubject = str_replace(["\r", "\n"], ' ', $subject);
    $headers = [
        'From: ' . str_replace(["\r", "\n"], ' ', $fromName) . ' <' . $from . '>',
        'To: ' . $to,
        'Subject: ' . $safeSubject,
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    // Leading dots must be doubled or they terminate the message early.
    $safeBody = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $body));
    $safeBody = str_replace("\n", "\r\n", $safeBody);
    fwrite($sock, implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.\r\n");
    $sent = $read();
    $cmd('QUIT');
    fclose($sock);

    if ($code($sent) !== 250) {
        return [false, 'send_rejected: ' . trim($sent)];
    }
    return [true, 'sent'];
}

// Resolves where an appointment notification should go. Students and staff
// each carry their own address; the email-to-SMS gateway, if configured, adds
// the contact number as a second recipient.
function notificationRecipientsFor(array $appointment): array {
    $cfg = getConfig();
    $recipients = [];
    $person = null;

    $studentId = trim((string)($appointment['studentId'] ?? ''));
    $staffId = trim((string)($appointment['staffId'] ?? ''));
    if ($studentId !== '') {
        foreach (dbGetAll('students') as $row) {
            if (($row['id'] ?? '') === $studentId || ($row['studentId'] ?? '') === $studentId) { $person = $row; break; }
        }
    } elseif ($staffId !== '') {
        foreach (dbGetAll('staff') as $row) {
            if (($row['id'] ?? '') === $staffId) { $person = $row; break; }
        }
    }
    if (!$person) {
        return [];
    }

    $email = trim((string)($person['email'] ?? ''));
    if ($email !== '') {
        $recipients[] = $email;
    }

    $gateway = trim((string)($cfg['sms_gateway_domain'] ?? ''));
    $number = preg_replace('/\D+/', '', (string)($person['contactNumber'] ?? ''));
    if ($gateway !== '' && $number !== '') {
        $recipients[] = $number . '@' . ltrim($gateway, '@');
    }
    return $recipients;
}

// Sends and records the outcome. Returns the per-recipient results so the
// caller can surface them; never throws.
function notifyAppointment(array $appointment, string $event, ?array $actor = null): array {
    $results = [];
    $recipients = notificationRecipientsFor($appointment);
    $when = trim(($appointment['date'] ?? '') . ' ' . ($appointment['time'] ?? ''));
    $name = (string)($appointment['patientName'] ?? 'Patient');

    $subjects = [
        'booked' => 'Clinic appointment requested',
        'confirmed' => 'Clinic appointment confirmed',
        'cancelled' => 'Clinic appointment cancelled',
        'reminder' => 'Clinic appointment reminder',
    ];
    $subject = $subjects[$event] ?? 'Clinic appointment update';
    $body = "Hello $name,\n\n"
        . match ($event) {
            'confirmed' => "Your clinic appointment on $when has been confirmed.",
            'cancelled' => "Your clinic appointment on $when has been cancelled.",
            'reminder' => "This is a reminder of your clinic appointment on $when.",
            default => "Your clinic appointment request for $when has been received and is awaiting approval.",
        }
        . "\n\nSchool Clinic";

    if (!$recipients) {
        $results[] = ['to' => '', 'sent' => false, 'detail' => 'no_recipient_on_file'];
    }
    foreach ($recipients as $to) {
        [$ok, $detail] = sendMail($to, $subject, $body);
        $results[] = ['to' => $to, 'sent' => $ok, 'detail' => $detail];
    }

    if (function_exists('dbLogAudit')) {
        dbLogAudit($actor ?? ['username' => 'system'], 'notification.' . $event, 'appointments',
            (string)($appointment['id'] ?? ''), ['results' => $results]);
    }
    return $results;
}

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

// Branded HTML wrapper matching the application's own palette: maroon #7B1028,
// gold #C9A24E, blush #FDF6F8 on #E8D4DB borders, Playfair Display headings.
//
// Written as tables with inline styles on purpose. Mail clients strip <style>
// blocks and understand almost no modern layout, so flexbox and classes — what
// the app itself uses — would collapse into unstyled text in Outlook and
// Gmail. Playfair is named first with Georgia behind it, because a webfont
// will not load in most clients either.
function renderEmailHtml(string $heading, string $introHtml, string $panelHtml = '', string $footNote = ''): string {
    $year = date('Y');
    $panel = $panelHtml === '' ? '' : <<<PANEL
        <tr><td style="padding:0 32px 8px 32px;">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                 style="background:#FDF6F8;border:1px solid #E8D4DB;border-radius:12px;">
            <tr><td style="padding:20px 24px;font-family:Helvetica,Arial,sans-serif;">$panelHtml</td></tr>
          </table>
        </td></tr>
PANEL;
    $foot = $footNote === '' ? '' : '<p style="margin:16px 0 0 0;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:18px;color:#7A7A7A;">' . $footNote . '</p>';

    return <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F0ECF2;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F0ECF2;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
             style="max-width:560px;background:#FFFFFF;border:1px solid #E8D4DB;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#7B1028;padding:24px 32px;">
          <p style="margin:0;font-family:'Playfair Display',Georgia,serif;font-size:20px;font-weight:700;color:#FFFFFF;">School Clinic</p>
          <p style="margin:2px 0 0 0;font-family:Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#C9A24E;">Management System</p>
        </td></tr>
        <tr><td style="padding:28px 32px 8px 32px;">
          <h1 style="margin:0 0 12px 0;font-family:'Playfair Display',Georgia,serif;font-size:22px;font-weight:700;color:#2B2B2B;">$heading</h1>
          <div style="font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:22px;color:#5A4A62;">$introHtml</div>
        </td></tr>
        $panel
        <tr><td style="padding:16px 32px 28px 32px;">
          $foot
        </td></tr>
        <tr><td style="background:#FDF6F8;border-top:1px solid #E8D4DB;padding:16px 32px;">
          <p style="margin:0;font-family:Helvetica,Arial,sans-serif;font-size:11px;line-height:17px;color:#7A7A7A;">
            This is an automated message from the School Clinic Management System.<br>
            &copy; $year School Clinic Management System. All rights reserved.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
}
function mailerIsConfigured(): bool {
    $cfg = getConfig();
    return trim((string)($cfg['mail_host'] ?? '')) !== ''
        && trim((string)($cfg['mail_from'] ?? '')) !== '';
}

/**
 * Returns [sent(bool), detail(string)]. Never throws: a failed notification
 * must not fail the operation that triggered it.
 */
function sendMail(string $to, string $subject, string $body, ?string $htmlBody = null): array {
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
    ];

    // Leading dots must be doubled or they terminate the message early.
    $escape = function (string $text): string {
        $text = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $text));
        return str_replace("\n", "\r\n", $text);
    };

    if ($htmlBody === null || trim($htmlBody) === '') {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $escape($body);
    } else {
        // multipart/alternative, plain part first: a client that cannot render
        // HTML shows the text version rather than raw markup, and an HTML-only
        // message is treated less kindly by spam filters.
        $boundary = 'bnd_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $payload = implode("\r\n", $headers) . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $escape($body) . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $escape($htmlBody) . "\r\n"
            . '--' . $boundary . "--";
    }
    fwrite($sock, $payload . "\r\n.\r\n");
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
    $line = match ($event) {
        'confirmed' => "Your clinic appointment has been confirmed.",
        'cancelled' => "Your clinic appointment has been cancelled.",
        'reminder' => "This is a reminder of your upcoming clinic appointment.",
        default => "Your clinic appointment request has been received and is awaiting approval.",
    };
    $body = "Hello $name,\n\n$line\n\nWhen: $when\n\nSchool Clinic";

    // The HTML part carries the same words inside the app's own styling; the
    // plain part above stays the fallback. An email-to-SMS gateway only ever
    // sees the plain part, which is why it has to read sensibly on its own.
    $panel = '<p style="margin:0 0 4px 0;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#7A7A7A;">Appointment</p>'
        . '<p style="margin:0;font-size:16px;font-weight:700;color:#2B2B2B;">' . htmlspecialchars($when, ENT_QUOTES) . '</p>'
        . '<p style="margin:6px 0 0 0;font-size:13px;color:#5A4A62;">Patient: ' . htmlspecialchars($name, ENT_QUOTES) . '</p>';
    $html = renderEmailHtml(
        $subjects[$event] ?? 'Appointment update',
        '<p style="margin:0;">Hello ' . htmlspecialchars($name, ENT_QUOTES) . ',</p><p style="margin:12px 0 0 0;">' . htmlspecialchars($line, ENT_QUOTES) . '</p>',
        $panel,
        'If you did not expect this message, please contact the school clinic.'
    );

    if (!$recipients) {
        $results[] = ['to' => '', 'sent' => false, 'detail' => 'no_recipient_on_file'];
    }
    foreach ($recipients as $to) {
        // An SMS gateway address gets the plain text only — HTML down an SMS
        // channel arrives as a wall of markup.
        $isGatewayAddress = $to !== '' && preg_match('/^\d+@/', $to) === 1;
        [$ok, $detail] = sendMail($to, $subject, $body, $isGatewayAddress ? null : $html);
        $results[] = ['to' => $to, 'sent' => $ok, 'detail' => $detail];
    }

    if (function_exists('dbLogAudit')) {
        dbLogAudit($actor ?? ['username' => 'system'], 'notification.' . $event, 'appointments',
            (string)($appointment['id'] ?? ''), ['results' => $results]);
    }
    return $results;
}

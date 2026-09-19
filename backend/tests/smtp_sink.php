<?php

// A throwaway SMTP server for local verification. It speaks just enough of the
// protocol to accept one message and write it to a file, so the hand-written
// client in mailer.php can be exercised for real — EHLO, MAIL FROM, RCPT TO,
// DATA and the terminating dot — without sending anything to the internet or
// needing credentials.
//
//   php backend/tests/smtp_sink.php <port> <output-file>
//
// Not part of the test suite and never reachable from the application; it is a
// development aid, kept here so the verification is repeatable.

$port = (int)($argv[1] ?? 2525);
$out = $argv[2] ?? (__DIR__ . '/captured-mail.txt');

$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "cannot listen on $port: $errstr\n");
    exit(1);
}
echo "sink listening on 127.0.0.1:$port\n";

while (true) {
    $conn = @stream_socket_accept($server, 60);
    if (!$conn) continue;
    stream_set_timeout($conn, 10);

    fwrite($conn, "220 sink.local ESMTP ready\r\n");
    $message = '';
    $inData = false;

    while (($line = fgets($conn, 2048)) !== false) {
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') {
                $inData = false;
                fwrite($conn, "250 2.0.0 Ok: queued\r\n");
                file_put_contents($out, $message);
                echo "captured " . strlen($message) . " bytes -> $out\n";
                continue;
            }
            $message .= $line;
            continue;
        }

        $verb = strtoupper(substr(trim($line), 0, 4));
        if ($verb === 'EHLO' || $verb === 'HELO') {
            // Advertise AUTH so the client exercises its login path.
            fwrite($conn, "250-sink.local\r\n250 AUTH LOGIN PLAIN\r\n");
        } elseif ($verb === 'AUTH') {
            fwrite($conn, "334 VXNlcm5hbWU6\r\n");
        } elseif ($verb === 'DATA') {
            $inData = true;
            fwrite($conn, "354 End data with <CR><LF>.<CR><LF>\r\n");
        } elseif ($verb === 'QUIT') {
            fwrite($conn, "221 2.0.0 Bye\r\n");
            break;
        } elseif (preg_match('/^[A-Za-z0-9+\/=]+$/', trim($line)) && trim($line) !== '') {
            // Base64 credential line: accept the username prompt, then the
            // password, then declare the session authenticated.
            static $credLines = 0;
            $credLines++;
            fwrite($conn, $credLines >= 2 ? "235 2.7.0 Authentication successful\r\n" : "334 UGFzc3dvcmQ6\r\n");
        } else {
            fwrite($conn, "250 2.0.0 Ok\r\n");
        }
    }
    fclose($conn);
}

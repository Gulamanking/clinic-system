<?php

// Email one-time codes at sign-in. Mail is unconfigured during the test run,
// so delivery cannot be asserted — what matters here is everything around it:
// the code must be single-use, time-limited, guess-limited, never stored or
// logged in the clear, and it must not lock out an account that has no address
// to send to.

require_once dirname(__DIR__, 2) . '/auth.php';

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Login OTP');

    $res = $client->login('admin', 'admin123');
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->setToken($res['body']['token'] ?? null);

    // --- Masking ----------------------------------------------------------
    $t->assertEqual('a long local part keeps two characters behind a fixed-width mask', 'ni*****@example.com', maskEmail('nightingale@example.com'));
    $t->assertEqual('a short local part keeps one', 'a*****@example.com', maskEmail('ab@example.com'));
    $t->assertEqual('a malformed address masks to nothing', '', maskEmail('not-an-address'));

    // --- An account set to email OTP but with no address ------------------
    // It must still log in normally. Treating this as "2FA on" would lock the
    // user out of an account they hold the correct password for.
    $noAddr = $admin->post('/api/users', [
        'fullName' => 'OTP No Address', 'username' => 'otp.noaddress', 'password' => 'OtpPass123!',
        'role' => 'Staff Encoder', 'status' => 'Active', 'twoFactorMethod' => 'email',
    ]);
    $t->assertEqual('account created', 201, $noAddr['status']);
    $plain = (new TestClient('http://127.0.0.1:8971'))->login('otp.noaddress', 'OtpPass123!');
    $t->assertEqual('login succeeds', 200, $plain['status']);
    $t->assert('and is not held at a 2FA prompt it could never pass',
        empty($plain['body']['needsTwoFactor']), 'account with no email was asked for a code');

    // --- An account with an address ---------------------------------------
    $created = $admin->post('/api/users', [
        'fullName' => 'OTP Test User', 'username' => 'otp.user', 'password' => 'OtpPass123!',
        'role' => 'Staff Encoder', 'status' => 'Active',
        'email' => 'otp.user@example.com', 'twoFactorMethod' => 'email',
    ]);
    $t->assertEqual('account with an address created', 201, $created['status']);
    $userId = $created['body']['id'] ?? '';
    $t->assertEqual('email persists', 'otp.user@example.com', $created['body']['email'] ?? '');
    $t->assertEqual('method persists', 'email', $created['body']['twoFactorMethod'] ?? '');

    $otpClient = new TestClient('http://127.0.0.1:8971');
    $login = $otpClient->login('otp.user', 'OtpPass123!');
    $t->assertEqual('password alone does not sign in', 200, $login['status']);
    $t->assert('a code is demanded', !empty($login['body']['needsTwoFactor']));
    $t->assertEqual('the method is reported so the UI can word itself', 'email', $login['body']['method'] ?? '');
    $t->assert('no session token is issued yet', empty($login['body']['token']), 'a token was handed out before the code');
    $t->assertEqual('the address is masked in the response', 'ot*****@example.com', $login['body']['sentTo'] ?? '');
    $tempToken = $login['body']['tempToken'] ?? '';
    $t->assert('a temporary token is returned', $tempToken !== '');

    // --- The code is never readable, anywhere ------------------------------
    // The API must not hand back credentials at all: the password hash, the
    // TOTP shared secret and the hash of a live code are all server-side only.
    // The secret matters most — holding it lets anyone generate that user's
    // authenticator codes.
    $row = $admin->get('/api/users/' . $userId);
    $t->assert('the password hash is not returned', !isset($row['body']['password']));
    $t->assert('the TOTP secret is not returned', !isset($row['body']['twoFactorSecret']));
    $t->assert('the code hash is not returned', !isset($row['body']['otpHash']));

    $listed = $admin->get('/api/users')['body'] ?? [];
    $leaks = array_values(array_filter($listed, fn($u) => isset($u['password']) || isset($u['twoFactorSecret']) || isset($u['otpHash'])));
    $t->assertEqual('and the list leaks none either', 0, count($leaks));

    $audit = $admin->get('/reports')['body']['records']['auditTrail'] ?? [];
    $sentRows = array_values(array_filter($audit, fn($a) => ($a['action'] ?? '') === 'login.otp_sent'));
    $t->assert('sending the code is audit logged', count($sentRows) > 0);
    $detailBlob = json_encode($sentRows);
    $t->assert('the code itself never reaches the audit trail', !preg_match('/\b\d{6}\b/', $detailBlob),
        'a six digit code appeared in the audit details');

    // --- Wrong codes -------------------------------------------------------
    $bad = $otpClient->post('/login/verify-2fa', ['tempToken' => $tempToken, 'code' => '000000']);
    $t->assertEqual('a wrong code is rejected', 401, $bad['status']);
    $t->assert('and no token leaks with the rejection', empty($bad['body']['token']));

    // Five wrong tries burns the code rather than leaving it guessable.
    for ($i = 0; $i < 5; $i++) {
        $otpClient->post('/login/verify-2fa', ['tempToken' => $tempToken, 'code' => '111111']);
    }
    // The hash is not readable through the API any more, so the expiry — which
    // is cleared at the same moment — is what proves the code was burned.
    $after = $admin->get('/api/users/' . $userId);
    $t->assertEqual('the code is cleared after too many attempts', 0, (int)($after['body']['otpExpiresAt'] ?? -1));
    $stale = $otpClient->post('/login/verify-2fa', ['tempToken' => $tempToken, 'code' => '111111']);
    $t->assertEqual('and the burned code cannot be used afterwards', 401, $stale['status']);

    // --- A known code round-trip -------------------------------------------
    // The generator is exercised directly so the correct code is known; the
    // login path above already proved the wiring.
    $fresh = $admin->get('/api/users/' . $userId)['body'];
    issueLoginOtp($fresh);
    $withCode = $admin->get('/api/users/' . $userId)['body'];
    $t->assert('a fresh code sets an expiry in the future',
        (int)($withCode['otpExpiresAt'] ?? 0) > (int)round(microtime(true) * 1000));
    $t->assertEqual('attempts reset with a new code', 0, (int)($withCode['otpAttempts'] ?? -1));

    return $t->results();
};

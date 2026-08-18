<?php

require_once dirname(__DIR__, 2) . '/auth.php';

return function(TestClient $client, string $testDbPath) {
    $t = new TestCase('Auth');

    // --- Basic login ---
    $res = $client->login('admin', 'admin123');
    $t->assertEqual('admin login succeeds', 200, $res['status']);
    $t->assert('admin login returns a token', !empty($res['body']['token']), 'no token in response');
    $adminToken = $res['body']['token'] ?? null;

    // --- Wrong password ---
    $badClient = new TestClient('http://127.0.0.1:8971');
    $res = $badClient->login('admin', 'wrong-password-here');
    $t->assertEqual('wrong password rejected with 401', 401, $res['status']);

    // Disposable account for the lockout and 2FA tests below — deliberately
    // NOT the real admin account, since locking it out (or leaving 2FA
    // enabled on it) would break every later test case's admin login.
    $admin = new TestClient('http://127.0.0.1:8971');
    $admin->setToken($adminToken);
    $throwaway = $admin->post('/api/users', [
        'fullName' => 'Auth Test Throwaway', 'username' => 'auth.throwaway', 'password' => 'Throwaway123!',
        'role' => 'Staff Encoder', 'status' => 'Active',
    ]);
    $throwawayId = $throwaway['body']['id'] ?? null;
    $t->assertEqual('disposable test account created', 201, $throwaway['status']);

    // --- Account lockout after repeated failures ---
    $lockoutClient = new TestClient('http://127.0.0.1:8971');
    for ($i = 0; $i < 5; $i++) {
        $lockoutClient->login('auth.throwaway', 'still-wrong');
    }
    $res = $lockoutClient->login('auth.throwaway', 'Throwaway123!'); // correct password, but should be locked now
    $t->assertEqual('account locked after 5 failed attempts', 403, $res['status']);

    // --- Protected route requires a token ---
    $anon = new TestClient('http://127.0.0.1:8971');
    $res = $anon->get('/api/students');
    $t->assert('unauthenticated request to protected route is rejected', $res['status'] === 401 || $res['status'] === 403, 'expected 401/403, got ' . $res['status']);

    // --- 2FA round trip (real TOTP math, not a stub) — on the disposable
    // account too, so 2FA never ends up half-configured on the real admin. ---
    $throwaway2 = $admin->post('/api/users', [
        'fullName' => 'Auth 2FA Throwaway', 'username' => 'auth.2fa.throwaway', 'password' => 'Throwaway123!',
        'role' => 'Staff Encoder', 'status' => 'Active',
    ]);
    $twofa = new TestClient('http://127.0.0.1:8971');
    $login = $twofa->login('auth.2fa.throwaway', 'Throwaway123!');
    if (!empty($login['body']['token'])) {
        $setup = $twofa->post('/2fa/setup');
        $secret = $setup['body']['secret'] ?? null;
        if ($secret) {
            $validCode = getTotpCode($secret);
            $confirm = $twofa->post('/2fa/confirm', ['code' => $validCode]);
            $t->assertEqual('2FA confirm with a real TOTP code succeeds', 200, $confirm['status']);

            // Log in again — should now require the second factor.
            $freshClient = new TestClient('http://127.0.0.1:8971');
            $secondLogin = $freshClient->login('auth.2fa.throwaway', 'Throwaway123!');
            $t->assert('login with 2FA enabled returns needsTwoFactor', !empty($secondLogin['body']['needsTwoFactor']), 'expected needsTwoFactor=true');

            $tempToken = $secondLogin['body']['tempToken'] ?? '';
            // A pending2fa temp token must not work as a real session token.
            $freshClient->setToken($tempToken);
            $blocked = $freshClient->get('/api/students');
            $t->assert('pending2fa temp token cannot authenticate a normal API call', $blocked['status'] === 401 || $blocked['status'] === 403, 'temp token was accepted, status ' . $blocked['status']);

            $code2 = getTotpCode($secret);
            $verify = $freshClient->post('/login/verify-2fa', ['tempToken' => $tempToken, 'code' => $code2]);
            $t->assertEqual('completing 2FA with a valid code succeeds', 200, $verify['status']);
        } else {
            $t->assert('2FA setup returned a secret', false, 'no secret in /2fa/setup response');
        }
    } else {
        $t->assert('2FA round trip: throwaway account login', false, 'no token from throwaway account login');
    }

    // Cleanup — throwaway accounts only, real admin untouched throughout.
    if ($throwawayId) $admin->delete('/api/users/' . $throwawayId);
    if (!empty($throwaway2['body']['id'])) $admin->delete('/api/users/' . $throwaway2['body']['id']);

    return $t->results();
};

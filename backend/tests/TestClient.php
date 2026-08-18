<?php

// Minimal dependency-free HTTP client + assertion helpers for the test suite.
// No PHPUnit/composer — this project has zero dependencies by design and
// runs on plain XAMPP, so the test harness matches that constraint.

class TestClient {
    private $baseUrl;
    private $token;

    public function __construct(string $baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setToken(?string $token): void {
        $this->token = $token;
    }

    // php -S is a single-threaded dev server that can stall under rapid
    // back-to-back requests (well-documented quirk, not an app bug) — one
    // retry with a short backoff absorbs that instead of failing the whole
    // suite on a transient hang.
    public function request(string $method, string $route, $body = null): array {
        $lastError = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($attempt > 0) { usleep(300000); }
            try {
                return $this->doRequest($method, $route, $body);
            } catch (RuntimeException $e) {
                $lastError = $e;
            }
        }
        throw $lastError;
    }

    private function doRequest(string $method, string $route, $body = null): array {
        $ch = curl_init();
        $url = $this->baseUrl . '/backend/index.php?route=' . urlencode($route);
        $headers = ['Content-Type: application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('cURL error calling ' . $route . ': ' . $err);
        }
        // A tiny pause between requests keeps the single-threaded dev server
        // from getting overwhelmed by the suite's rapid sequential calls.
        usleep(15000);
        $json = json_decode($raw, true);
        return ['status' => $status, 'body' => $json === null ? $raw : $json];
    }

    public function get(string $route): array { return $this->request('GET', $route); }
    public function post(string $route, $body = null): array { return $this->request('POST', $route, $body ?? []); }
    public function put(string $route, $body = null): array { return $this->request('PUT', $route, $body ?? []); }
    public function delete(string $route): array { return $this->request('DELETE', $route); }

    public function login(string $username, string $password): array {
        $res = $this->post('/login', ['username' => $username, 'password' => $password]);
        if ($res['status'] === 200 && isset($res['body']['token'])) {
            $this->setToken($res['body']['token']);
        }
        return $res;
    }
}

class TestResult {
    public $name;
    public $passed;
    public $message;
    public function __construct(string $name, bool $passed, string $message = '') {
        $this->name = $name;
        $this->passed = $passed;
        $this->message = $message;
    }
}

class TestCase {
    private $results = [];
    private $groupName;

    public function __construct(string $groupName) {
        $this->groupName = $groupName;
    }

    public function assert(string $name, bool $condition, string $failMessage = ''): void {
        $this->results[] = new TestResult($this->groupName . ': ' . $name, $condition, $condition ? '' : $failMessage);
    }

    public function assertEqual(string $name, $expected, $actual): void {
        $pass = $expected === $actual;
        $msg = $pass ? '' : ('expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        $this->assert($name, $pass, $msg);
    }

    public function results(): array {
        return $this->results;
    }
}

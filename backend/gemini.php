<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

const AI_DISCLAIMER = 'AI-generated information is for medical support and reference only. It does not replace professional medical judgment. Final assessment and diagnosis must be performed by authorized healthcare personnel.';

const AI_SYSTEM_PROMPT = 'You are a clinical decision-support assistant for a school clinic. ' .
    'A licensed healthcare professional has already identified and recorded the patient diagnosis. ' .
    'Do NOT diagnose patients, do NOT determine an illness from symptoms, do NOT prescribe or recommend treatment. ' .
    'Based ONLY on the diagnosis and medical context provided, list possible causes or contributing factors, ' .
    'general medical information about why the condition may occur, and factors that may be worth checking further. ' .
    'Keep the response concise, factual, and general. Start each section clearly.';

function getClientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function buildMinimizedAiPayload(array $visit, array $student = null): array {
    $payload = [
        'diagnosis' => (string)($visit['diagnosis'] ?? ''),
        'complaint' => (string)($visit['complaint'] ?? ''),
        'treatment' => (string)($visit['treatment'] ?? ''),
        'visitDate' => (string)($visit['date'] ?? ''),
        'patientType' => (string)($visit['patientType'] ?? ''),
    ];

    if ($student) {
        $payload['medicalHistory'] = [
            'allergies' => (string)($student['allergies'] ?? ''),
            'conditions' => (string)($student['conditions'] ?? ''),
            'bloodType' => (string)($student['bloodType'] ?? ''),
        ];
    }

    return $payload;
}

function lookupStudentByVisit(array $visit): ?array {
    $name = trim((string)($visit['patientName'] ?? ''));
    if ($name === '') return null;
    try {
        $students = dbGetAll('students');
        foreach ($students as $s) {
            if (strtolower(trim((string)($s['name'] ?? ''))) === strtolower($name)) {
                return $s;
            }
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

// Google Gemini API (Generative Language API) — key-based auth via a query
// param, not a header, and a different request/response shape than
// Anthropic's Messages API. Swapped in because Anthropic API usage isn't
// free; Gemini has a usable free tier for a capstone-scale project like this.
function callGemini(array $payload): array {
    $cfg = getConfig();
    $apiKey = $cfg['gemini_api_key'] ?? '';
    if ($apiKey === '') {
        return [
            'ok' => false,
            'configured' => false,
            'status' => 'not_configured',
            'message' => 'Gemini AI is not configured. Add GEMINI_API_KEY to the backend environment (or gemini_api_key in config.local.php) to enable the AI assistant.',
        ];
    }

    // Configurable, not hardcoded to one snapshot — Google renames/retires
    // model versions over time, and whichever one is current on the free
    // tier when you read this may not match what was current when this was
    // written. Check https://ai.google.dev/gemini-api/docs/models for the
    // current name if this default ever starts returning a 404.
    $model = $cfg['gemini_model'] ?? 'gemini-3.6-flash';
    $userContent = "Patient context (medical only):\n" . json_encode($payload) . "\n\n" .
        "Provide possible causes or contributing factors, general medical information, and factors worth checking further for the recorded diagnosis.";

    $body = json_encode([
        'system_instruction' => ['parts' => [['text' => AI_SYSTEM_PROMPT]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $userContent]]]],
        'generationConfig' => ['maxOutputTokens' => 1000],
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        // Must be comfortably under PHP's own max_execution_time (30s by
        // default): if curl and PHP's script timer raced at the same value,
        // a slow Gemini response could hit PHP's limit first and kill the
        // whole request with an uncaught fatal error instead of the
        // graceful error response below.
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        // Plain XAMPP/Windows PHP installs frequently ship without a
        // configured curl.cainfo/openssl.cafile, which makes any HTTPS
        // curl call fail with "unable to get local issuer certificate" —
        // bundling a CA file here means every collaborator's request
        // works out of the box, without editing their php.ini.
        CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($result, true);
        $parts = $decoded['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            $text .= $part['text'] ?? '';
        }
        if ($text === '') {
            // Empty candidates usually means the prompt or response was
            // blocked by Gemini's safety filters, not a transport error.
            $blockReason = $decoded['promptFeedback']['blockReason'] ?? null;
            return [
                'ok' => false,
                'configured' => true,
                'status' => 'error',
                'message' => $blockReason
                    ? ('Gemini blocked this request (' . $blockReason . ').')
                    : 'Gemini returned an empty response.',
            ];
        }
        return [
            'ok' => true,
            'configured' => true,
            'status' => 'success',
            'analysis' => trim($text),
        ];
    }

    $decoded = json_decode($result, true);
    $apiMessage = $decoded['error']['message'] ?? null;
    return [
        'ok' => false,
        'configured' => true,
        'status' => 'error',
        'message' => $apiMessage ?: ($error ?: ('Gemini API returned HTTP ' . $httpCode)),
    ];
}

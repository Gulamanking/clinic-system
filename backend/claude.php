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

function callClaude(array $payload): array {
    $cfg = getConfig();
    $apiKey = $cfg['anthropic_api_key'] ?? '';
    if ($apiKey === '') {
        return [
            'ok' => false,
            'configured' => false,
            'status' => 'not_configured',
            'message' => 'Claude AI is not configured. Add ANTHROPIC_API_KEY to the backend environment to enable the AI assistant.',
        ];
    }

    $model = $cfg['anthropic_model'] ?? 'claude-sonnet-4-5';
    $userContent = "Patient context (medical only):\n" . json_encode($payload) . "\n\n" .
        "Provide possible causes or contributing factors, general medical information, and factors worth checking further for the recorded diagnosis.";

    $body = json_encode([
        'model' => $model,
        'max_tokens' => 1000,
        'system' => AI_SYSTEM_PROMPT,
        'messages' => [['role' => 'user', 'content' => $userContent]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($result, true);
        $text = '';
        foreach (($decoded['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        return [
            'ok' => true,
            'configured' => true,
            'status' => 'success',
            'analysis' => trim($text),
        ];
    }

    return [
        'ok' => false,
        'configured' => true,
        'status' => 'error',
        'message' => $error ?: ('Claude API returned HTTP ' . $httpCode),
    ];
}

<?php

require_once __DIR__ . '/database.php';

// Deterministic, non-LLM pattern detection over a patient's own visit +
// medical history records. Deliberately NOT sent to Gemini: this only
// counts and dates records already in our own database, so there is no
// reason to pay for or wait on an external call to get the answer.
// Kept separate from gemini.php's AI Insights feature (which reasons about
// a single diagnosis) — this one reasons across a patient's whole history.

const RECURRENCE_WINDOW_DAYS = 365;
const RECURRENCE_ALERT_THRESHOLD = 2;   // same diagnosis seen this many times+
const CATEGORY_ALERT_THRESHOLD = 3;     // related-symptom category seen this many times+
const TREND_ALERT_THRESHOLD = 3;        // clinic-wide: this many students, same window

const SYMPTOM_CATEGORIES = [
    'respiratory' => ['cough', 'cold', 'flu', 'influenza', 'sore throat', 'asthma', 'bronchitis', 'sinus', 'respiratory', 'pneumonia', 'colds'],
    'gastrointestinal' => ['stomach', 'diarrhea', 'vomit', 'nausea', 'gastric', 'abdominal', 'lbm'],
    'dermatological' => ['rash', 'skin', 'allergy', 'allergic', 'dermat', 'itch'],
    'headache/neurological' => ['headache', 'migraine', 'dizziness', 'vertigo'],
    'fever/infection' => ['fever', 'infection', 'viral', 'bacterial'],
    'injury' => ['injury', 'sprain', 'wound', 'fracture', 'cut', 'bruise', 'trauma'],
    'mental health' => ['anxiety', 'stress', 'mental', 'depress', 'panic'],
];

function normalizeText(string $s): string {
    return strtolower(trim($s));
}

function matchedCategories(string $text): array {
    $text = normalizeText($text);
    $hits = [];
    foreach (SYMPTOM_CATEGORIES as $category => $keywords) {
        foreach ($keywords as $kw) {
            if ($text !== '' && strpos($text, $kw) !== false) {
                $hits[] = $category;
                break;
            }
        }
    }
    return $hits;
}

function daysBetween(int $tsMs, int $nowMs): float {
    return ($nowMs - $tsMs) / 86400000;
}

function parseRecordTimeMs(array $record, string $dateField): ?int {
    $raw = trim((string)($record[$dateField] ?? ''));
    if ($raw === '') return null;
    $t = strtotime($raw);
    return $t === false ? null : $t * 1000;
}

// Gathers every visit + medical history entry belonging to one patient,
// matched by studentId when available, falling back to an exact
// case-insensitive name match (mirrors gemini.php's lookupStudentByVisit).
function gatherPatientRecords(string $studentId, string $patientName): array {
    $studentId = trim($studentId);
    $nameKey = normalizeText($patientName);
    $records = [];

    foreach (dbGetAll('visits') as $v) {
        $matches = ($studentId !== '' && trim((string)($v['studentId'] ?? '')) === $studentId)
            || ($nameKey !== '' && normalizeText((string)($v['patientName'] ?? '')) === $nameKey);
        if (!$matches) continue;
        $records[] = [
            'source' => 'visit',
            'diagnosis' => (string)($v['diagnosis'] ?? ''),
            'complaint' => (string)($v['complaint'] ?? ''),
            'date' => (string)($v['date'] ?? ''),
            'timeMs' => parseRecordTimeMs($v, 'date'),
        ];
    }
    foreach (dbGetAll('medicalHistory') as $h) {
        $matches = $studentId !== '' && trim((string)($h['studentId'] ?? '')) === $studentId;
        if (!$matches) continue;
        $records[] = [
            'source' => 'history',
            'diagnosis' => (string)($h['diagnosis'] ?? ''),
            'complaint' => '',
            'date' => (string)($h['visitDate'] ?? ''),
            'timeMs' => parseRecordTimeMs($h, 'visitDate'),
        ];
    }
    return $records;
}

function computePatientInsights(string $studentId, string $patientName): array {
    $nowMs = round(microtime(true) * 1000);
    $windowMs = RECURRENCE_WINDOW_DAYS * 86400000;
    $records = gatherPatientRecords($studentId, $patientName);

    $inWindow = array_values(array_filter($records, function ($r) use ($nowMs, $windowMs) {
        return $r['timeMs'] !== null && ($nowMs - $r['timeMs']) <= $windowMs;
    }));

    // Exact-diagnosis recurrence
    $byDiagnosis = [];
    foreach ($inWindow as $r) {
        $key = normalizeText($r['diagnosis']);
        if ($key === '') continue;
        $byDiagnosis[$key]['label'] = trim($r['diagnosis']);
        $byDiagnosis[$key]['dates'][] = $r['date'];
        $byDiagnosis[$key]['count'] = ($byDiagnosis[$key]['count'] ?? 0) + 1;
    }

    $recurringDiagnoses = [];
    $maxExactCount = 0;
    foreach ($byDiagnosis as $entry) {
        if ($entry['count'] >= RECURRENCE_ALERT_THRESHOLD) {
            sort($entry['dates']);
            $recurringDiagnoses[] = [
                'diagnosis' => $entry['label'],
                'count' => $entry['count'],
                'dates' => $entry['dates'],
            ];
        }
        $maxExactCount = max($maxExactCount, $entry['count']);
    }

    // Related-symptom category recurrence (catches "sore throat" + "cough"
    // both being respiratory, even though the diagnosis text differs)
    $byCategory = [];
    foreach ($inWindow as $r) {
        $cats = array_unique(array_merge(matchedCategories($r['diagnosis']), matchedCategories($r['complaint'])));
        foreach ($cats as $cat) {
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;
        }
    }
    $flaggedCategories = [];
    foreach ($byCategory as $cat => $count) {
        if ($count >= CATEGORY_ALERT_THRESHOLD) {
            $flaggedCategories[] = ['category' => $cat, 'count' => $count];
        }
    }

    // Plain-language alerts — deliberately phrased as review prompts, never
    // as a diagnosis, per the review's requirement that this stay decision
    // support and not a diagnostic claim.
    $alerts = [];
    foreach ($recurringDiagnoses as $rd) {
        $alerts[] = sprintf(
            'Patient has had %d consultations for "%s" within the past %s. Consider reviewing the student\'s previous consultations.',
            $rd['count'], $rd['diagnosis'], RECURRENCE_WINDOW_DAYS >= 365 ? 'year' : (RECURRENCE_WINDOW_DAYS . ' days')
        );
    }
    foreach ($flaggedCategories as $fc) {
        $alerts[] = sprintf(
            'Repeated %s-related symptoms detected — %d related consultations within the past year. Consider reviewing the student\'s previous consultations.',
            $fc['category'], $fc['count']
        );
    }

    // Visit frequency (last 90 days) feeds the risk score's "how often are
    // they showing up lately" factor, independent of whether it's the same
    // complaint each time.
    $recentCount = count(array_filter($records, function ($r) use ($nowMs) {
        return $r['timeMs'] !== null && daysBetween($r['timeMs'], $nowMs) <= 90;
    }));

    // Chronic-condition flag from the student's own record, when we can
    // find one by studentId.
    $hasChronicFlag = false;
    if ($studentId !== '') {
        foreach (dbGetAll('students') as $s) {
            if (trim((string)($s['id'] ?? '')) === $studentId || trim((string)($s['studentId'] ?? '')) === $studentId) {
                $hasChronicFlag = trim((string)($s['allergies'] ?? '')) !== '' || trim((string)($s['medicalConditions'] ?? '')) !== '';
                break;
            }
        }
    }

    $breakdown = [];
    $score = 0;

    $exactPoints = min($maxExactCount, 5) * 8;
    if ($exactPoints > 0) { $score += $exactPoints; $breakdown[] = ['factor' => 'Recurring exact diagnosis', 'detail' => 'Most-repeated diagnosis seen ' . $maxExactCount . 'x', 'points' => $exactPoints]; }

    $categoryPoints = min(count($flaggedCategories), 3) * 10;
    if ($categoryPoints > 0) { $score += $categoryPoints; $breakdown[] = ['factor' => 'Recurring symptom category', 'detail' => count($flaggedCategories) . ' flagged category/categories', 'points' => $categoryPoints]; }

    $frequencyPoints = min($recentCount, 5) * 4;
    if ($frequencyPoints > 0) { $score += $frequencyPoints; $breakdown[] = ['factor' => 'Recent visit frequency', 'detail' => $recentCount . ' visit(s) in the last 90 days', 'points' => $frequencyPoints]; }

    if ($hasChronicFlag) { $score += 10; $breakdown[] = ['factor' => 'Chronic condition / allergy on file', 'detail' => 'Flagged in student medical record', 'points' => 10]; }

    $score = min(100, $score);
    $level = $score >= 60 ? 'High' : ($score >= 30 ? 'Medium' : 'Low');

    return [
        'recordCount' => count($records),
        'recurringDiagnoses' => $recurringDiagnoses,
        'flaggedCategories' => $flaggedCategories,
        'alerts' => $alerts,
        'riskScore' => $score,
        'riskLevel' => $level,
        'riskBreakdown' => $breakdown,
    ];
}

// Clinic-wide: is any single diagnosis/category suddenly showing up across
// many different patients in a short window? (distinct from the per-patient
// function above, which only looks at one person's repeat visits.)
function computeClinicTrends(int $windowDays = 14): array {
    $nowMs = round(microtime(true) * 1000);
    $windowMs = $windowDays * 86400000;

    $byDiagnosis = []; // diagnosis => set of patient keys
    $byCategory = [];  // category => set of patient keys

    foreach (dbGetAll('visits') as $v) {
        $timeMs = parseRecordTimeMs($v, 'date');
        if ($timeMs === null || ($nowMs - $timeMs) > $windowMs) continue;
        $patientKey = trim((string)($v['studentId'] ?? '')) ?: normalizeText((string)($v['patientName'] ?? ''));
        if ($patientKey === '') continue;

        $diagKey = normalizeText((string)($v['diagnosis'] ?? ''));
        if ($diagKey !== '') {
            $byDiagnosis[$diagKey]['label'] = trim((string)$v['diagnosis']);
            $byDiagnosis[$diagKey]['patients'][$patientKey] = true;
        }
        foreach (array_unique(array_merge(matchedCategories((string)($v['diagnosis'] ?? '')), matchedCategories((string)($v['complaint'] ?? '')))) as $cat) {
            $byCategory[$cat]['patients'][$patientKey] = true;
        }
    }

    $diagnosisTrends = [];
    foreach ($byDiagnosis as $entry) {
        $n = count($entry['patients']);
        if ($n >= TREND_ALERT_THRESHOLD) {
            $diagnosisTrends[] = ['diagnosis' => $entry['label'], 'studentCount' => $n];
        }
    }
    usort($diagnosisTrends, fn($a, $b) => $b['studentCount'] <=> $a['studentCount']);

    $categoryTrends = [];
    foreach ($byCategory as $cat => $entry) {
        $n = count($entry['patients']);
        if ($n >= TREND_ALERT_THRESHOLD) {
            $categoryTrends[] = ['category' => $cat, 'studentCount' => $n];
        }
    }
    usort($categoryTrends, fn($a, $b) => $b['studentCount'] <=> $a['studentCount']);

    return [
        'windowDays' => $windowDays,
        'diagnosisTrends' => $diagnosisTrends,
        'categoryTrends' => $categoryTrends,
    ];
}

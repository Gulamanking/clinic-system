<?php

// Re-encrypts every encrypted field from one encryption key to another.
//
// Needed because the deployed app ran with the development encryption key
// that is committed to a public repository. Simply setting a new
// ENCRYPTION_KEY would not re-encrypt anything — decryptValue() returns ''
// on failure rather than erroring, so existing records would silently read
// back blank instead of raising.
//
// Usage (from the repository root):
//
//   OLD_ENCRYPTION_KEY='...' NEW_ENCRYPTION_KEY='...' php backend/tools/reencrypt.php
//   OLD_ENCRYPTION_KEY='...' NEW_ENCRYPTION_KEY='...' php backend/tools/reencrypt.php --apply
//
// Without --apply it is a dry run: it decrypts and re-encrypts in memory and
// reports what it would change, touching nothing. Database credentials come
// from the usual environment variables (see DEPLOYMENT.md).
//
// Take a backup first, and stop the application while this runs — a write
// that lands mid-migration would be encrypted with the old key and then
// missed.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

$apply = in_array('--apply', $argv, true);
$oldKey = getenv('OLD_ENCRYPTION_KEY');
$newKey = getenv('NEW_ENCRYPTION_KEY');

if ($oldKey === false || $oldKey === '' || $newKey === false || $newKey === '') {
    fwrite(STDERR, "OLD_ENCRYPTION_KEY and NEW_ENCRYPTION_KEY must both be set.\n");
    exit(1);
}
if ($oldKey === $newKey) {
    fwrite(STDERR, "The two keys are identical — nothing to do.\n");
    exit(1);
}

// Deliberately independent of getConfig(): this script has to hold two keys
// at once, which the application's own helpers cannot express.
function keyBytes(string $key): string {
    return hash('sha256', $key, true);
}

function decryptWith(string $stored, string $key) {
    $raw = base64_decode(substr($stored, 4));
    if ($raw === false || strlen($raw) < 28) {
        return false;
    }
    return openssl_decrypt(
        substr($raw, 28), 'aes-256-gcm', keyBytes($key),
        OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)
    );
}

function encryptWith(string $plaintext, string $key): string {
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', keyBytes($key), OPENSSL_RAW_DATA, $iv, $tag);
    return 'enc:' . base64_encode($iv . $tag . $ciphertext);
}

$pdo = getDbConnection();
$totalRows = 0;
$totalFields = 0;
$skipped = 0;
$failures = [];
$updates = [];

foreach (ENCRYPTED_FIELDS as $table => $fields) {
    try {
        $rows = $pdo->query('SELECT id, ' . implode(', ', $fields) . " FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo "  $table: skipped (" . $e->getMessage() . ")\n";
        continue;
    }

    $tableFields = 0;
    foreach ($rows as $row) {
        $changes = [];
        foreach ($fields as $field) {
            $stored = $row[$field] ?? null;
            // Legacy plaintext (no prefix) is left exactly as the app treats
            // it: readable as-is, not an error, and not something to encrypt
            // here — that would change behaviour beyond a key rotation.
            if (!is_string($stored) || strpos($stored, 'enc:') !== 0) {
                $skipped++;
                continue;
            }
            $plain = decryptWith($stored, $oldKey);
            if ($plain === false) {
                $failures[] = "$table.$field id={$row['id']}";
                continue;
            }
            $changes[$field] = encryptWith($plain, $newKey);
            $tableFields++;
        }
        if ($changes) {
            $updates[] = [$table, $row['id'], $changes];
            $totalRows++;
        }
    }
    $totalFields += $tableFields;
    printf("  %-26s %4d rows, %4d encrypted fields\n", $table, count($rows), $tableFields);
}

echo "\n";
echo "Rows to update:     $totalRows\n";
echo "Fields to re-encrypt: $totalFields\n";
echo "Left as-is (plaintext/empty): $skipped\n";

if ($failures) {
    echo "\nFAILED to decrypt with OLD_ENCRYPTION_KEY:\n";
    foreach (array_slice($failures, 0, 20) as $f) {
        echo "  $f\n";
    }
    if (count($failures) > 20) {
        echo "  ... and " . (count($failures) - 20) . " more\n";
    }
    fwrite(STDERR, "\nAborting: the old key does not decrypt every value, so this is the "
        . "wrong key or the data is already partly migrated. Nothing was written.\n");
    exit(1);
}

if (!$apply) {
    echo "\nDry run — nothing written. Re-run with --apply to commit.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    foreach ($updates as [$table, $id, $changes]) {
        $set = implode(', ', array_map(fn($f) => "$f = :$f", array_keys($changes)));
        $stmt = $pdo->prepare("UPDATE $table SET $set WHERE id = :id");
        foreach ($changes as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }
        $stmt->bindValue(':id', $id);
        $stmt->execute();
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Rolled back: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nDone. Set ENCRYPTION_KEY to the new value and redeploy.\n";

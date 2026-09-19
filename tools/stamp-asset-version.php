<?php

// Keeps the ?v= cache buster on the frontend script tags in step with the
// JavaScript it points at.
//
// The host serves js/*.js with "Cache-Control: public, max-age=2592000,
// immutable", so a browser that has already fetched a given URL will not
// revalidate it for thirty days — not even on a normal refresh. The only
// thing that makes it fetch again is the URL changing. That number sat at 38
// from the first commit until September 2026, which meant every JavaScript
// change only reached people who happened to hard-reload.
//
// Rather than a counter someone has to remember to increment, the version is
// a hash of the scripts' own contents: it changes exactly when they change,
// and never otherwise, so re-running this is idempotent.
//
// Run from the repository root:
//   php tools/stamp-asset-version.php          # rewrite and report
//   php tools/stamp-asset-version.php --check  # exit 1 if out of date, write nothing

$root = dirname(__DIR__);
$scripts = ['js/api.js', 'js/app.js'];
$pages = ['index.html', 'enrollment.html'];
$check = in_array('--check', $argv, true);

$material = '';
foreach ($scripts as $s) {
    $path = "$root/$s";
    if (!is_file($path)) {
        fwrite(STDERR, "missing $s\n");
        exit(1);
    }
    // Normalise line endings so a CRLF checkout and an LF checkout agree.
    $material .= str_replace("\r\n", "\n", file_get_contents($path));
}
$version = substr(sha1($material), 0, 10);

$stale = [];
foreach ($pages as $page) {
    $path = "$root/$page";
    if (!is_file($path)) {
        continue;
    }
    $before = file_get_contents($path);
    // Matches both an existing ?v=... and a bare src with no query at all.
    $after = preg_replace(
        '#(src="js/(?:api|app)\.js)(?:\?v=[^"]*)?"#',
        '$1?v=' . $version . '"',
        $before
    );
    if ($after === $before) {
        continue;
    }
    $stale[] = $page;
    if (!$check) {
        file_put_contents($path, $after);
    }
}

if (!$stale) {
    echo "asset version already current (v=$version)\n";
    exit(0);
}

if ($check) {
    fwrite(STDERR, "asset version is stale in: " . implode(', ', $stale) . "\n");
    fwrite(STDERR, "run: php tools/stamp-asset-version.php\n");
    exit(1);
}

echo "stamped v=$version into " . implode(', ', $stale) . "\n";

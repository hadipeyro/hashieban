<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = array();
$warnings = array();

function hbAuditFiles(string $base, string $extension): array
{
    $files = array();

    if (! is_dir($base)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === strtolower($extension)) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);
    return $files;
}

function hbAuditHasLocalizedIdentifier(string $source): bool
{
    foreach (token_get_all($source) as $token) {
        if (! is_array($token)) {
            continue;
        }

        $identifierTokens = array(T_STRING);

        if (defined('T_NAME_QUALIFIED')) {
            $identifierTokens[] = T_NAME_QUALIFIED;
        }
        if (defined('T_NAME_FULLY_QUALIFIED')) {
            $identifierTokens[] = T_NAME_FULLY_QUALIFIED;
        }
        if (defined('T_NAME_RELATIVE')) {
            $identifierTokens[] = T_NAME_RELATIVE;
        }

        if (
            in_array($token[0], $identifierTokens, true)
            && preg_match('/[^\x00-\x7F]/u', $token[1]) === 1
        ) {
            return true;
        }
    }

    return false;
}

echo "Hashieban release audit\n";
echo str_repeat('=', 30) . "\n";

$mainFile = $root . '/hashieban.php';
$main = file_get_contents($mainFile);

if (! is_string($main)) {
    $failures[] = 'Unable to read hashieban.php.';
} else {
    preg_match('/^ \* Version:\s+([^\r\n]+)/m', $main, $headerVersion);
    preg_match("/define\('HASHIEBAN_VERSION',\s*'([^']+)'\);/", $main, $constantVersion);

    if (! isset($headerVersion[1], $constantVersion[1])) {
        $failures[] = 'Version metadata is missing.';
    } elseif (trim((string) $headerVersion[1]) !== trim((string) $constantVersion[1])) {
        $failures[] = 'Plugin header version does not match HASHIEBAN_VERSION.';
    } else {
        echo 'Version: ' . trim((string) $constantVersion[1]) . PHP_EOL;
    }

    if (strpos($main, 'Requires PHP:      7.4') === false) {
        $warnings[] = 'Review Requires PHP metadata before final 1.0 build.';
    }
}

$required = array(
    '/hashieban.php',
    '/composer.json',
    '/src/Plugin.php',
    '/assets/admin/maps/iran-provinces.svg',
    '/assets/admin/js/hashieban-dashboard.js',
    '/assets/admin/css/hashieban-dashboard.css',
    '/src/Security/Capabilities.php',
    '/src/Licensing/LicenseManager.php',
    '/CHANGELOG.md',
    '/readme.txt',
    '/docs/QA-CHECKLIST-FA.md',
);

foreach ($required as $relative) {
    if (! is_readable($root . $relative)) {
        $failures[] = 'Missing required file: ' . $relative;
    }
}

foreach (hbAuditFiles($root . '/src', 'php') as $file) {
    $source = file_get_contents($file);

    if (! is_string($source)) {
        $failures[] = 'Unable to read: ' . $file;
        continue;
    }

    if (hbAuditHasLocalizedIdentifier($source)) {
        $failures[] = 'Localized PHP identifier found: ' . $file;
    }

    if (preg_match('/\b(var_dump|print_r|error_log)\s*\(/', $source) === 1) {
        $failures[] = 'Debug statement found: ' . $file;
    }

    if (preg_match('/http:\/\//i', $source) === 1) {
        $failures[] = 'Insecure HTTP URL found in runtime PHP: ' . $file;
    }

    if (preg_match('/\?->|\bmatch\s*\(|\#\[|\breadonly\s+|\benum\s+[A-Za-z_]/', $source) === 1) {
        $failures[] = 'Possible PHP >7.4 syntax found: ' . $file;
    }
}

$forbiddenReleaseEntries = array(
    '/node_modules',
    '/.git',
    '/dist',
);

foreach ($forbiddenReleaseEntries as $relative) {
    if (is_dir($root . $relative)) {
        $warnings[] = 'Development directory exists and will be excluded from release build: ' . $relative;
    }
}

if (! is_readable($root . '/vendor/autoload.php')) {
    $warnings[] = 'vendor/autoload.php is not present in this source copy. Final marketplace ZIP must include Composer vendor files.';
}

if ($warnings !== array()) {
    echo PHP_EOL . "Warnings:\n";
    foreach ($warnings as $warning) {
        echo '- ' . $warning . PHP_EOL;
    }
}

if ($failures !== array()) {
    echo PHP_EOL . "RELEASE AUDIT FAILED\n";
    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo PHP_EOL . "RELEASE AUDIT PASSED\n";

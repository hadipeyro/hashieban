<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = array();
$phpCount = 0;
$jsCount = 0;

function qaRun(string $command, ?array &$output = null): int
{
    $lines = array();
    $status = 0;
    exec($command . ' 2>&1', $lines, $status);
    $output = $lines;

    return $status;
}

function qaFiles(string $base, string $extension): array
{
    $files = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) === strtolower($extension)) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);
    return $files;
}

echo "Hashieban QA\n";
echo str_repeat('=', 28) . "\n";

foreach (qaFiles($root . '/src', 'php') as $file) {
    $phpCount++;
    $output = array();
    $status = qaRun('php -l ' . escapeshellarg($file), $output);

    if ($status !== 0) {
        $failures[] = 'PHP lint: ' . $file . ' => ' . implode(' ', $output);
    }
}

foreach (array($root . '/hashieban.php', $root . '/tests/run.php') as $file) {
    $phpCount++;
    $output = array();
    $status = qaRun('php -l ' . escapeshellarg($file), $output);

    if ($status !== 0) {
        $failures[] = 'PHP lint: ' . $file . ' => ' . implode(' ', $output);
    }
}

$nodePath = trim((string) shell_exec('command -v node 2>/dev/null'));

if ($nodePath !== '') {
    foreach (qaFiles($root . '/assets/admin/js', 'js') as $file) {
        $jsCount++;
        $output = array();
        $status = qaRun(escapeshellarg($nodePath) . ' --check ' . escapeshellarg($file), $output);

        if ($status !== 0) {
            $failures[] = 'JS syntax: ' . $file . ' => ' . implode(' ', $output);
        }
    }
} else {
    echo "NOTE: node not found; JavaScript syntax check skipped.\n";
}

$requiredRuntimeFiles = array(
    '/assets/admin/maps/iran-provinces.svg',
    '/assets/admin/js/hashieban-dashboard.js',
    '/assets/admin/js/hashieban-settings.js',
    '/assets/admin/css/hashieban-dashboard.css',
);

foreach ($requiredRuntimeFiles as $relative) {
    if (! is_readable($root . $relative)) {
        $failures[] = 'Missing runtime asset: ' . $relative;
    }
}

$output = array();
$status = qaRun('php ' . escapeshellarg($root . '/tests/run.php'), $output);

foreach ($output as $line) {
    echo $line . PHP_EOL;
}

if ($status !== 0) {
    $failures[] = 'Automated core tests failed.';
}

echo PHP_EOL;
echo 'PHP files linted: ' . $phpCount . PHP_EOL;
echo 'JS files checked:  ' . $jsCount . PHP_EOL;

if ($failures !== array()) {
    echo PHP_EOL . "QA FAILED\n";

    foreach ($failures as $failure) {
        echo '- ' . $failure . PHP_EOL;
    }

    exit(1);
}

echo PHP_EOL . "QA PASSED\n";

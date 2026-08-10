<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$main = file_get_contents($root . '/hashieban.php');

if (! is_string($main)) {
    fwrite(STDERR, "Unable to read hashieban.php.\n");
    exit(1);
}

preg_match("/define\('HASHIEBAN_VERSION',\s*'([^']+)'\);/", $main, $match);

if (! isset($match[1])) {
    fwrite(STDERR, "Unable to detect Hashieban version.\n");
    exit(1);
}

$version = trim((string) $match[1]);

if (! class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP ZipArchive extension is required for release build.\n");
    exit(1);
}

if (! is_readable($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php is missing. Run Composer install before building the marketplace ZIP.\n");
    exit(1);
}

$auditOutput = array();
$auditStatus = 0;
exec('php ' . escapeshellarg($root . '/tools/release-audit.php') . ' 2>&1', $auditOutput, $auditStatus);

foreach ($auditOutput as $line) {
    echo $line . PHP_EOL;
}

if ($auditStatus !== 0) {
    fwrite(STDERR, "Release build stopped because audit failed.\n");
    exit(1);
}

$dist = $root . '/dist';

if (! is_dir($dist) && ! mkdir($dist, 0775, true) && ! is_dir($dist)) {
    fwrite(STDERR, "Unable to create dist directory.\n");
    exit(1);
}

$zipPath = $dist . '/hashieban-' . $version . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create release ZIP.\n");
    exit(1);
}

$topFiles = array(
    'hashieban.php',
    'composer.json',
    'composer.lock',
    'README.md',
    'CHANGELOG.md',
    'readme.txt',
);

foreach ($topFiles as $relative) {
    $path = $root . '/' . $relative;
    if (is_readable($path)) {
        $zip->addFile($path, 'hashieban/' . $relative);
    }
}

$runtimeDirectories = array('src', 'assets', 'vendor');

foreach ($runtimeDirectories as $directory) {
    $base = $root . '/' . $directory;
    if (! is_dir($base)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relative = ltrim(str_replace($root, '', $path), '/\\');
        $zip->addFile($path, 'hashieban/' . str_replace('\\', '/', $relative));
    }
}

$zip->close();

echo PHP_EOL;
echo 'Release ZIP created: ' . $zipPath . PHP_EOL;
echo "This is a build artifact only; publish it after final manual QA approval.\n";

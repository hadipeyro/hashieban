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

if (preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version) !== 1) {
    fwrite(STDERR, "Invalid Hashieban version.\n");
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
$topFiles = array(
    'hashieban.php',
    'composer.json',
    'composer.lock',
    'README.md',
    'CHANGELOG.md',
    'readme.txt',
);
$runtimeDirectories = array('src', 'assets');

function hashiebanAddTreeToZip(ZipArchive $zip, string $root, string $base): void
{
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

function hashiebanCopyTree(string $source, string $destination): void
{
    if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
        throw new RuntimeException('Unable to create staging directory.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = ltrim(str_replace($source, '', $item->getPathname()), '/\\');
        $target = $destination . '/' . str_replace('\\', '/', $relative);

        if ($item->isDir()) {
            if (! is_dir($target) && ! mkdir($target, 0775, true) && ! is_dir($target)) {
                throw new RuntimeException('Unable to create staging directory.');
            }
            continue;
        }

        if (! copy($item->getPathname(), $target)) {
            throw new RuntimeException('Unable to copy release file: ' . $relative);
        }
    }
}

function hashiebanRemoveTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($path);
}

@unlink($zipPath);

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Unable to create release ZIP.\n");
        exit(1);
    }

    foreach ($topFiles as $relative) {
        $path = $root . '/' . $relative;
        if (is_readable($path)) {
            $zip->addFile($path, 'hashieban/' . $relative);
        }
    }

    foreach ($runtimeDirectories as $directory) {
        $base = $root . '/' . $directory;
        if (is_dir($base)) {
            hashiebanAddTreeToZip($zip, $root, $base);
        }
    }

    $zip->close();
} else {
    $zipBinary = trim((string) shell_exec('command -v zip 2>/dev/null'));

    if ($zipBinary === '') {
        fwrite(STDERR, "Release build requires PHP ZipArchive or the system zip command.\n");
        exit(1);
    }

    $staging = sys_get_temp_dir() . '/hashieban-release-' . bin2hex(random_bytes(6));
    $pluginRoot = $staging . '/hashieban';

    try {
        if (! mkdir($pluginRoot, 0775, true) && ! is_dir($pluginRoot)) {
            throw new RuntimeException('Unable to create release staging directory.');
        }

        foreach ($topFiles as $relative) {
            $source = $root . '/' . $relative;
            if (is_readable($source) && ! copy($source, $pluginRoot . '/' . $relative)) {
                throw new RuntimeException('Unable to copy release file: ' . $relative);
            }
        }

        foreach ($runtimeDirectories as $directory) {
            $source = $root . '/' . $directory;
            if (is_dir($source)) {
                hashiebanCopyTree($source, $pluginRoot . '/' . $directory);
            }
        }

        $command = 'cd ' . escapeshellarg($staging)
            . ' && ' . escapeshellcmd($zipBinary)
            . ' -q -r ' . escapeshellarg($zipPath) . ' hashieban';
        $output = array();
        $status = 0;
        exec($command . ' 2>&1', $output, $status);

        if ($status !== 0 || ! is_file($zipPath)) {
            throw new RuntimeException('Unable to create release ZIP with system zip. ' . implode(' ', $output));
        }
    } catch (Throwable $error) {
        hashiebanRemoveTree($staging);
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }

    hashiebanRemoveTree($staging);
}

if (! is_file($zipPath) || filesize($zipPath) <= 0) {
    fwrite(STDERR, "Release ZIP was not created correctly.\n");
    exit(1);
}

echo PHP_EOL;
echo 'Release ZIP created: ' . $zipPath . PHP_EOL;
echo "This is a build artifact only; publish it after final manual QA approval.\n";

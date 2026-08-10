<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

$testFiles = glob(__DIR__ . '/Unit/*Test.php');

if (! is_array($testFiles)) {
    $testFiles = array();
}

sort($testFiles);

$classesBefore = get_declared_classes();

foreach ($testFiles as $testFile) {
    require_once $testFile;
}

$classes = array_values(array_diff(get_declared_classes(), $classesBefore));
$totalTests = 0;
$totalPassed = 0;
$totalFailed = 0;
$totalAssertions = 0;
$failures = array();

foreach ($classes as $className) {
    if (! is_subclass_of($className, \Hashieban\Tests\TestCase::class)) {
        continue;
    }

    $test = new $className();
    $result = $test->run();

    $totalTests += $result['tests'];
    $totalPassed += $result['passed'];
    $totalFailed += $result['failed'];
    $totalAssertions += $result['assertions'];
    $failures = array_merge($failures, $result['failures']);
}

echo PHP_EOL;
echo 'Hashieban automated tests' . PHP_EOL;
echo str_repeat('=', 28) . PHP_EOL;
echo 'Tests:      ' . $totalTests . PHP_EOL;
echo 'Passed:     ' . $totalPassed . PHP_EOL;
echo 'Failed:     ' . $totalFailed . PHP_EOL;
echo 'Assertions: ' . $totalAssertions . PHP_EOL;

if ($failures !== array()) {
    echo PHP_EOL . 'Failures:' . PHP_EOL;

    foreach ($failures as $failure) {
        echo '- ' . $failure['test'] . PHP_EOL;
        echo '  ' . $failure['message'] . PHP_EOL;
    }

    exit(1);
}

echo PHP_EOL . "OK - core financial tests passed.\n";

<?php
declare(strict_types=1);

use Babanoell\Auth\Tests\TestCase;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Auth-service tests are CLI-only.\n");
    exit(1);
}

$serviceRoot = dirname(__DIR__);
$testRoot = __DIR__;

spl_autoload_register(static function (string $class) use ($serviceRoot, $testRoot): void {
    $prefixes = [
        'Babanoell\\Auth\\Tests\\' => $testRoot . DIRECTORY_SEPARATOR,
        'Babanoell\\Auth\\' => $serviceRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
    ];

    foreach ($prefixes as $prefix => $basePath) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $basePath . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

$testFiles = array_merge(
    glob($testRoot . '/unit/*Test.php') ?: [],
    glob($testRoot . '/integration/*Test.php') ?: []
);

sort($testFiles, SORT_STRING);

$total = 0;
$failed = 0;

foreach ($testFiles as $file) {
    $test = require $file;

    if (!$test instanceof TestCase) {
        fwrite(STDERR, "Test file must return a TestCase instance: {$file}\n");
        exit(1);
    }

    $test->setSuiteName(pathinfo($file, PATHINFO_FILENAME));

    foreach ($test->run() as $result) {
        $total++;

        if ($result['passed']) {
            echo '[PASS] ' . $result['name'] . PHP_EOL;
            continue;
        }

        $failed++;
        echo '[FAIL] ' . $result['name'] . ' - ' . $result['error'] . PHP_EOL;
    }
}

echo sprintf("Auth-service tests: %d run, %d failed.\n", $total, $failed);
exit($failed === 0 ? 0 : 1);

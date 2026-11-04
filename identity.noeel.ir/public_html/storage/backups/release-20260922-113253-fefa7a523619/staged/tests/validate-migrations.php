<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Migration validation is CLI-only.\n");
    exit(1);
}

$serviceRoot = dirname(__DIR__);

require $serviceRoot . '/src/Database/Migration.php';

$migrationFiles = glob($serviceRoot . '/database/migrations/*.php') ?: [];
sort($migrationFiles, SORT_STRING);

$versions = [];

foreach ($migrationFiles as $file) {
    $migration = require $file;

    if (!$migration instanceof Migration) {
        fwrite(STDERR, "Invalid migration return value: {$file}\n");
        exit(1);
    }

    $version = $migration->version();
    $basename = basename($file);

    if (!preg_match('/^\d{12}$/', $version)) {
        fwrite(STDERR, "Invalid migration version: {$basename}\n");
        exit(1);
    }

    if (!str_starts_with($basename, $version . '_')) {
        fwrite(STDERR, "Migration filename must start with its version: {$basename}\n");
        exit(1);
    }

    if (isset($versions[$version])) {
        fwrite(STDERR, "Duplicate migration version: {$version}\n");
        exit(1);
    }

    $versions[$version] = true;
}

echo sprintf("Validated %d auth-service migration files.\n", count($migrationFiles));

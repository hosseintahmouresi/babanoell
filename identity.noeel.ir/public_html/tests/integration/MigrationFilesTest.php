<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testMigrationFilesExposeUniqueVersions(): void
    {
        $serviceRoot = dirname(__DIR__, 2);
        require_once $serviceRoot . '/src/Database/Migration.php';

        $files = glob($serviceRoot . '/database/migrations/*.php') ?: [];
        sort($files, SORT_STRING);

        $versions = [];

        foreach ($files as $file) {
            $migration = require $file;

            Assertions::true($migration instanceof Migration);
            Assertions::matches('/^\d{12}$/', $migration->version());
            Assertions::false(isset($versions[$migration->version()]), 'Duplicate migration version.');

            $versions[$migration->version()] = true;
        }

        Assertions::same(22, count($versions));
    }
};

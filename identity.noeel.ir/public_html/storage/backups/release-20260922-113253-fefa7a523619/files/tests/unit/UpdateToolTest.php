<?php
declare(strict_types=1);

use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\Update\UpdateTool;

return new class extends TestCase
{
    public function testValidEntriesAreMappedToServiceTargets(): void
    {
        Assertions::same('src/App/App.php', UpdateTool::entryTarget('identity/src/App/App.php'));
        Assertions::same('.htaccess', UpdateTool::entryTarget('identity/.htaccess'));
        Assertions::same('public/.htaccess', UpdateTool::entryTarget('identity/public/.htaccess'));
        Assertions::same('config/.env.example', UpdateTool::entryTarget('identity/config/.env.example'));
        Assertions::same('config/local.env.example.php', UpdateTool::entryTarget('identity/config/local.env.example.php'));
        Assertions::same(
            'database/migrations/202608250001_add_access_token_hash_to_sessions_table.php',
            UpdateTool::entryTarget('identity/database/migrations/202608250001_add_access_token_hash_to_sessions_table.php'),
        );
        Assertions::same('router.php', UpdateTool::entryTarget('identity/router.php'));
        Assertions::same('src/Update/UpdateTool.php', UpdateTool::entryTarget('identity/src/Update/UpdateTool.php'));
    }

    public function testUnsafeEntriesAreRejected(): void
    {
        $unsafe = [
            '/etc/passwd',
            '../identity/src/App/App.php',
            'identity/../../etc/passwd',
            'identity/.git/HEAD',
            'identity/.git/config',
            'identity/.git/index',
            'identity//src/App/App.php',
            'identity/src\\App\\App.php',
            'identity/..hidden.php',
            'identity/.env',
            'identity/.env.production',
            'identity/foo.exe',
            'identity/foo.sh',
            'identity/foo',
            'other/src/App/App.php',
            'storage/update.zip',
            'C:/identity/src/App/App.php',
            "identity/src/App/\0App.php",
            '',
        ];

        foreach ($unsafe as $name) {
            Assertions::same(null, UpdateTool::entryTarget($name), 'Entry should be rejected: ' . $name);
        }
    }

    public function testProtectedServiceFilesAreNeverOverwritten(): void
    {
        Assertions::same(null, UpdateTool::entryTarget('identity/config/local.env.php'));
        Assertions::same(null, UpdateTool::entryTarget('identity/storage/update.zip'));
        Assertions::same(null, UpdateTool::entryTarget('identity/public/install.php'));
        Assertions::same(null, UpdateTool::entryTarget('identity/public/install.php.locked'));

        Assertions::true(UpdateTool::isProtectedTarget('config/local.env.php'));
        Assertions::true(UpdateTool::isProtectedTarget('storage/backups/manifest.json'));
        Assertions::true(UpdateTool::isProtectedTarget('public/install.php'));
        Assertions::false(UpdateTool::isProtectedTarget('src/App/App.php'));
    }

    public function testPlanBuildsForAValidPackage(): void
    {
        $plan = UpdateTool::buildPlan([
            ['name' => 'identity/public/index.php', 'size' => 100],
            ['name' => 'identity/src/App/App.php', 'size' => 200],
            ['name' => 'identity/database/migrations/202608250001_test.php', 'size' => 300],
            ['name' => 'identity/.htaccess', 'size' => 50],
        ]);

        Assertions::same(4, $plan['entry_count']);
        Assertions::same(4, count($plan['files']));
        Assertions::same(650, $plan['total_size']);
        Assertions::true($plan['has_front_controller']);
        Assertions::same(1, $plan['migration_count']);
    }

    public function testPlanRejectsMissingFrontController(): void
    {
        Assertions::throws(RuntimeException::class, static function (): void {
            UpdateTool::buildPlan([
                ['name' => 'identity/src/App/App.php', 'size' => 100],
                ['name' => 'identity/database/migrations/202608250001_test.php', 'size' => 100],
            ]);
        });
    }

    public function testPlanRejectsMissingMigrations(): void
    {
        Assertions::throws(RuntimeException::class, static function (): void {
            UpdateTool::buildPlan([
                ['name' => 'identity/public/index.php', 'size' => 100],
                ['name' => 'identity/src/App/App.php', 'size' => 100],
                ['name' => 'identity/README.md', 'size' => 100],
            ]);
        });
    }

    public function testPlanRejectsTraversalEntries(): void
    {
        Assertions::throws(RuntimeException::class, static function (): void {
            UpdateTool::buildPlan([
                ['name' => 'identity/public/index.php', 'size' => 100],
                ['name' => 'identity/../../secret.php', 'size' => 100],
            ]);
        });
    }

    public function testPlanRejectsTooManyEntries(): void
    {
        $entries = [['name' => 'identity/public/index.php', 'size' => 1]];

        for ($index = 0; $index < UpdateTool::MAX_ENTRIES + 1; $index++) {
            $entries[] = ['name' => 'identity/src/x' . $index . '.php', 'size' => 1];
        }

        Assertions::throws(RuntimeException::class, static function () use ($entries): void {
            UpdateTool::buildPlan($entries);
        });
    }

    public function testPlanRejectsOversizedArchive(): void
    {
        $large = UpdateTool::MAX_TOTAL_UNCOMPRESSED_BYTES + 1;

        Assertions::throws(RuntimeException::class, static function () use ($large): void {
            UpdateTool::buildPlan([
                ['name' => 'identity/public/index.php', 'size' => $large],
                ['name' => 'identity/src/App/App.php', 'size' => 1],
                ['name' => 'identity/database/migrations/202608250001_test.php', 'size' => 1],
            ]);
        });
    }

    public function testPlanCountsProtectedEntriesWithoutCopyingThem(): void
    {
        $plan = UpdateTool::buildPlan([
            ['name' => 'identity/public/index.php', 'size' => 100],
            ['name' => 'identity/config/local.env.php', 'size' => 100],
            ['name' => 'identity/storage/update.zip', 'size' => 100],
            ['name' => 'identity/src/App/App.php', 'size' => 100],
            ['name' => 'identity/database/migrations/202608250001_test.php', 'size' => 100],
        ]);

        Assertions::same(3, count($plan['files']));
        Assertions::same(2, count($plan['protected_entries']));

        foreach ($plan['files'] as $file) {
            Assertions::false(UpdateTool::isProtectedTarget($file['target']));
        }
    }

    public function testCollectExtractedPreservesFirstCharacterWithTrailingSlash(): void
    {
        $root = sys_get_temp_dir() . '/babanoell-update-tool-' . bin2hex(random_bytes(6));
        $packageRoot = $root . '/identity';
        mkdir($packageRoot . '/public', 0755, true);
        file_put_contents($packageRoot . '/.htaccess', "Require all denied\n");
        file_put_contents($packageRoot . '/public/index.php', "<?php echo 'ok';\n");
        file_put_contents($packageRoot . '/public/install.php', "<?php echo 'protected';\n");

        try {
            $withoutSlash = UpdateTool::collectExtracted($packageRoot);
            $withSlash = UpdateTool::collectExtracted($packageRoot . DIRECTORY_SEPARATOR);

            $targetsWithoutSlash = array_column($withoutSlash, 'target');
            $targetsWithSlash = array_column($withSlash, 'target');
            sort($targetsWithoutSlash);
            sort($targetsWithSlash);

            Assertions::same(['.htaccess', 'public/index.php'], $targetsWithoutSlash);
            Assertions::same($targetsWithoutSlash, $targetsWithSlash);
        } finally {
            UpdateTool::removePath($root);
        }
    }

    public function testRealServicePackageIsAcceptedByTheUpdater(): void
    {
        $serviceRoot = dirname(__DIR__, 2);
        $entries = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($serviceRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($serviceRoot) + 1));

            if (str_starts_with($relative, 'tests/')) {
                continue;
            }

            $entries[] = [
                'name' => UpdateTool::PACKAGE_PREFIX . $relative,
                'size' => (int) $item->getSize(),
            ];
        }

        $plan = UpdateTool::buildPlan($entries);

        Assertions::true($plan['has_front_controller']);
        Assertions::true($plan['migration_count'] >= 10);
        Assertions::true(count($plan['files']) > 120);
    }
};

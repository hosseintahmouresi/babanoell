<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests\Integration;

use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

final class DocumentsPermissionsMigrationTest extends TestCase
{
    public function testDocumentsPermissionsAndSuperAdminAssignmentAreSeeded(): void
    {
        $root = dirname(__DIR__, 2);
        $file = $root . '/database/migrations/202608270001_seed_documents_permissions.php';
        Assertions::true(is_file($file));
        $source = (string) file_get_contents($file);
        foreach ([
            'documents.view','documents.upload','documents.download','documents.review',
            'documents.replace','documents.archive','documents.audit','documents.manage',
        ] as $permission) {
            Assertions::contains("'{$permission}'", $source);
        }
        Assertions::contains("'super_admin'", $source);
        Assertions::contains('INSERT IGNORE INTO role_permissions', $source);
    }
}

return new DocumentsPermissionsMigrationTest();

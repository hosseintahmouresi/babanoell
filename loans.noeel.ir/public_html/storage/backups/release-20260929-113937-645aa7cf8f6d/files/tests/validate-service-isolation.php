<?php
declare(strict_types=1);
$runner=file_get_contents(dirname(__DIR__).'/src/Database/MigrationRunner.php');
$installer=file_get_contents(dirname(__DIR__).'/public/install.php');
$guard=file_get_contents(dirname(__DIR__).'/src/Database/DatabaseIsolationGuard.php');
if(!is_string($runner)||!str_contains($runner,"loans_schema_migrations"))throw new RuntimeException('Loans migration tracking table is not namespaced.');
if(str_contains($runner,"INSERT INTO schema_migrations"))throw new RuntimeException('Generic schema_migrations write remains.');
if(!is_string($installer)||!str_contains($installer,'DatabaseIsolationGuard::assertLoansDatabase'))throw new RuntimeException('Installer isolation guard is missing.');
foreach(['users','roles','permissions','sessions','auth_audit_logs'] as $marker)if(!str_contains((string)$guard,"'{$marker}'"))throw new RuntimeException('Identity marker missing: '.$marker);
echo "Validated Loans database isolation safeguards.\n";

<?php
declare(strict_types=1);
namespace Babanoell\Auth\Tests\Integration;
use Babanoell\Auth\Tests\Assertions;use Babanoell\Auth\Tests\TestCase;
final class PartnersPermissionsMigrationTest extends TestCase
{
 public function testPartnersPermissionsAndCooperationRoleAreSeeded():void
 {
  $root=dirname(__DIR__,2);$file=$root.'/database/migrations/202609290005_seed_partners_permissions.php';Assertions::true(is_file($file));$src=(string)file_get_contents($file);
  foreach(['partners.merchants.read','partners.merchants.manage','partners.memberships.manage','partners.plan_access.manage','partners.directory.read','partners.cooperation.read','partners.cooperation.create','partners.cooperation.manage','partners.marketplace.read','partners.marketplace.claim','partners.marketplace.manage','partners.audit']as$p)Assertions::contains("'{$p}'",$src);
  foreach(['cooperation_operator','acceptor','applicant','operations_expert']as$r)Assertions::contains("'{$r}'",$src);
 }
}
return new PartnersPermissionsMigrationTest();

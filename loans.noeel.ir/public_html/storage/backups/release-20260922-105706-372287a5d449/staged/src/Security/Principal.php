<?php
declare(strict_types=1);
namespace Babanoell\Loans\Security;
final class Principal
{
    public function __construct(public readonly string $userId,public readonly string $displayName,public readonly array $roles,public readonly array $permissions){}
    public function isSuperAdmin(): bool{return in_array('super_admin',$this->roles,true);} public function can(string $permission): bool{return$this->isSuperAdmin()||in_array($permission,$this->permissions,true);}
}

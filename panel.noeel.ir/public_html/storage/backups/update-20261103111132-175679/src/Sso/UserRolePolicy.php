<?php
declare(strict_types=1);
namespace Babanoell\Panel\Sso;

/** Portal visibility only. All business APIs must continue authorizing via Identity. */
final class UserRolePolicy
{
    private const TARGETS = ['identity', 'loans', 'documents', 'partners', 'finance', 'integrations'];
    private const ROLE_ROUTES = [
        'identity' => ['super_admin', 'admin', 'bank_expert', 'applicant', 'acceptor', 'intermediary', 'partner', 'expert'],
        'loans' => ['super_admin', 'admin', 'bank_expert', 'applicant', 'acceptor', 'intermediary', 'expert'],
        'documents' => ['super_admin', 'admin', 'bank_expert', 'applicant', 'acceptor', 'intermediary', 'expert'],
        'partners' => ['super_admin', 'admin', 'acceptor', 'intermediary', 'partner'],
        'finance' => ['super_admin', 'finance_admin', 'finance_expert'],
        'integrations' => ['super_admin'],
    ];
    private const PERMISSION_PREFIXES = [
        'loans' => ['loans.'], 'documents' => ['documents.'], 'partners' => ['partners.'],
        'finance' => ['finance.'], 'integrations' => ['integrations.'],
    ];
    public static function target(string $service): bool { return in_array($service, self::TARGETS, true); }
    public static function allowed(string $service, array $roles, array $permissions): bool
    {
        if (!self::target($service)) return false;
        $roles = array_values(array_filter($roles, 'is_string'));
        if ($service==='identity') return $roles!==[] || $permissions!==[];
        if (in_array('super_admin', $roles, true)) return true;
        if (in_array($service, ['integrations'], true)) return false; // integration credentials are privileged
        foreach (self::ROLE_ROUTES[$service] as $role) if (in_array($role, $roles, true)) return true;
        foreach (self::PERMISSION_PREFIXES[$service] ?? [] as $prefix) {
            foreach ($permissions as $permission) {
                if (is_string($permission) && str_starts_with($permission, $prefix)) return true;
            }
        }
        return false;
    }
}

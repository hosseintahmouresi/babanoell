<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Me;

use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use Babanoell\Auth\Authorization\ScopeRepositoryInterface;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\TokenHasherInterface;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\SystemClock;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use Throwable;

final class ActiveMeUseCase implements MeUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly UserRepositoryInterface $users,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly bool $meEnabled = false,
        private readonly ?ScopeRepositoryInterface $scopes = null,
    ) {
    }

    public function handle(MeRequest $request): MeResult
    {
        if (!$this->meEnabled) {
            return MeResult::inactive();
        }

        if (!$request->credentialSubmitted()) {
            return MeResult::authenticationRequired();
        }

        try {
            $session = $this->sessions->findByAccessTokenHash(
                $this->tokenHasher->hash($request->submittedCredential()),
            );
        } catch (Throwable) {
            return MeResult::unavailable();
        }

        if ($session === null || $session->isRevoked()) {
            return MeResult::invalidCredentials();
        }

        if ($session->accessExpiresAt <= $this->clock->now()) {
            return MeResult::invalidCredentials();
        }

        $user = $this->users->findById($session->userId);

        if (!$this->userMayReadIdentity($user)) {
            return MeResult::invalidCredentials();
        }

        try {
            $roleNames = $this->roleNames($user->id);
            $permissionNames = $this->permissions->findPermissionNamesByUserId($user->id);
            $scopes = $this->scopes?->forUser($user->id) ?? [];
        } catch (Throwable) {
            return MeResult::unavailable();
        }

        return MeResult::authenticated([
            'user' => [
                'id' => $user->id,
                'display_name' => $user->displayName,
                'status' => $user->status,
            ],
            'roles' => $roleNames,
            'permissions' => $permissionNames,
            'scopes' => $scopes,
            'authorization' => ['model'=>'rbac_abac','version'=>'1.9.0'],
            'session' => [
                'access_expires_at' => $session->accessExpiresAt->format(DATE_ATOM),
            ],
        ]);
    }

    private function userMayReadIdentity(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->status !== 'active' || $user->disabledAt !== null) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int,string>
     */
    private function roleNames(string $userId): array
    {
        $names = [];

        foreach ($this->roles->findByUserId($userId) as $role) {
            $names[] = $role->name;
        }

        return $names;
    }
}

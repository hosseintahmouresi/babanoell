<?php
declare(strict_types=1);

namespace Babanoell\Auth\Administration;

use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\TokenHasherInterface;
use DateTimeImmutable;
use Throwable;

final class AdminAuthorization
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly TokenHasherInterface $tokenHasher,
    ) {}

    /** @return array{ok:bool,status:int,code:string,message:string,user_id?:string} */
    public function requireSuperAdmin(Request $request): array
    {
        $token = $this->bearer($request);
        if ($token === null) {
            return ['ok'=>false,'status'=>401,'code'=>'auth_required','message'=>'احراز هویت الزامی است.'];
        }

        try {
            $session = $this->sessions->findByAccessTokenHash($this->tokenHasher->hash($token));
            if ($session === null || $session->isRevoked() || $session->accessExpiresAt <= new DateTimeImmutable('now')) {
                return ['ok'=>false,'status'=>401,'code'=>'invalid_credentials','message'=>'نشست کاربری معتبر نیست.'];
            }
            $user = $this->users->findById($session->userId);
            if ($user === null || $user->status !== 'active' || $user->disabledAt !== null) {
                return ['ok'=>false,'status'=>401,'code'=>'invalid_credentials','message'=>'حساب کاربری فعال نیست.'];
            }
            $roleNames = array_map(static fn($role): string => $role->name, $this->roles->findByUserId($user->id));
            if (!in_array('super_admin', $roleNames, true)) {
                return ['ok'=>false,'status'=>403,'code'=>'permission_denied','message'=>'این عملیات فقط برای مدیر ارشد سامانه مجاز است.'];
            }
            return ['ok'=>true,'status'=>200,'code'=>'','message'=>'','user_id'=>$user->id];
        } catch (Throwable) {
            return ['ok'=>false,'status'=>503,'code'=>'auth_unavailable','message'=>'احراز هویت مدیریتی موقتاً در دسترس نیست.'];
        }
    }

    private function bearer(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header === null || preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) !== 1) return null;
        return $m[1];
    }
}

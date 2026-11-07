<?php
declare(strict_types=1);

namespace Babanoell\Auth\Administration;

use Babanoell\Auth\Security\PasswordHasherInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class UserDirectory
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PasswordHasherInterface $hasher,
        private readonly IdGeneratorInterface $ids,
    ) {}

    /** @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int} */
    public function listUsers(int $page = 1, int $perPage = 25, string $query = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $query = trim($query);
        $where = '';
        $params = [];
        if ($query !== '') {
            $where = ' WHERE u.display_name LIKE :q_name OR u.mobile LIKE :q_mobile OR u.email LIKE :q_email';
            $like = '%' . $query . '%';
            $params = [':q_name' => $like, ':q_mobile' => $like, ':q_email' => $like];
        }

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM users u' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = <<<'SQL'
SELECT
    u.id,u.mobile,u.email,u.display_name,u.status,u.disabled_at,u.last_login_at,u.created_at,
    (SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',')
       FROM user_roles ur JOIN roles r ON r.id=ur.role_id
      WHERE ur.user_id=u.id AND (ur.expires_at IS NULL OR ur.expires_at>UTC_TIMESTAMP(6))) AS role_names,
    (SELECT GROUP_CONCAT(r.label ORDER BY r.name SEPARATOR '، ')
       FROM user_roles ur JOIN roles r ON r.id=ur.role_id
      WHERE ur.user_id=u.id AND (ur.expires_at IS NULL OR ur.expires_at>UTC_TIMESTAMP(6))) AS role_labels
FROM users u
SQL;
        $sql .= $where . ' ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $items[] = $this->normalizeUserRow($row);
        return ['items' => $items, 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function roles(): array
    {
        $sql = <<<'SQL'
SELECT
    r.id,r.name,r.label,r.description,r.is_system,
    (SELECT GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ',')
       FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
      WHERE rp.role_id=r.id) AS permission_names
FROM roles r
ORDER BY r.is_system DESC,r.label ASC
SQL;
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $row['permissions'] = !empty($row['permission_names']) ? explode(',', (string) $row['permission_names']) : [];
            unset($row['permission_names']);
            $out[] = $row;
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function permissions(): array
    {
        return $this->pdo->query('SELECT id,name,description FROM permissions ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(array $input, string $actor): array
    {
        $display = trim((string) ($input['display_name'] ?? ''));
        $mobile = $this->normalizeMobile((string) ($input['mobile'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $roles = $this->normalizeRoles($input['roles'] ?? []);

        if ($display === '') throw new RuntimeException('نام نمایشی کاربر الزامی است.');
        if ($mobile === '' && $email === '') throw new RuntimeException('حداقل موبایل یا ایمیل باید وارد شود.');
        if ($mobile !== '' && preg_match('/^09\d{9}$/', $mobile) !== 1) throw new RuntimeException('شماره موبایل باید با فرمت 09xxxxxxxxx وارد شود.');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new RuntimeException('ایمیل واردشده معتبر نیست.');
        if (strlen($password) < 10) throw new RuntimeException('رمز عبور باید حداقل ۱۰ نویسه باشد.');
        if ($roles === []) throw new RuntimeException('حداقل یک نقش باید برای کاربر انتخاب شود.');
        $this->assertRoleAssignmentAllowed($actor, $roles);
        $roleMap = $this->roleMap($roles);

        $id = $this->ids->generate();
        $hash = $this->hasher->hash($password);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (id,mobile,email,display_name,status,password_hash,password_changed_at) VALUES (:id,:mobile,:email,:display_name,'active',:password_hash,UTC_TIMESTAMP(6))");
            $stmt->execute([
                ':id' => $id,
                ':mobile' => $mobile !== '' ? $mobile : null,
                ':email' => $email !== '' ? $email : null,
                ':display_name' => $display,
                ':password_hash' => $hash,
            ]);
            $this->replaceRolesInternal($id, $roles, $roleMap, $actor);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ((string) $e->getCode() === '23000') throw new RuntimeException('کاربری با این موبایل یا ایمیل قبلاً ثبت شده است.');
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return $this->get($id) ?? throw new RuntimeException('کاربر ایجاد شد اما قابل بازیابی نیست.');
    }

    /** @param array<int,string> $roles @return array<string,mixed> */
    public function replaceRoles(string $userId, array $roles, string $actor): array
    {
        $roles = $this->normalizeRoles($roles);
        if ($roles === []) throw new RuntimeException('حداقل یک نقش باید برای کاربر انتخاب شود.');
        $this->assertRoleAssignmentAllowed($actor, $roles);
        $this->assertTargetManageable($actor, $userId);
        $roleMap = $this->roleMap($roles);
        $current = $this->get($userId);
        if ($current === null) throw new RuntimeException('کاربر پیدا نشد.');
        if ($userId === $actor && in_array('super_admin', $current['roles'], true) && !in_array('super_admin', $roles, true)) {
            throw new RuntimeException('مدیر ارشد نمی‌تواند نقش مدیر ارشد را از حساب فعلی خودش حذف کند.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->replaceRolesInternal($userId, $roles, $roleMap, $actor);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return $this->get($userId) ?? throw new RuntimeException('کاربر قابل بازیابی نیست.');
    }

    /** @return array<string,mixed> */
    public function setStatus(string $userId, string $status, string $actor): array
    {
        if (!in_array($status, ['active','disabled'], true)) throw new RuntimeException('وضعیت کاربر معتبر نیست.');
        if ($userId === $actor && $status !== 'active') throw new RuntimeException('نمی‌توانید حساب فعلی خودتان را غیرفعال کنید.');
        $this->assertTargetManageable($actor, $userId);
        $stmt = $this->pdo->prepare("UPDATE users SET status=:status,disabled_at=CASE WHEN :status_case='disabled' THEN UTC_TIMESTAMP(6) ELSE NULL END WHERE id=:id");
        $stmt->execute([':status' => $status, ':status_case' => $status, ':id' => $userId]);
        if ($stmt->rowCount() === 0 && $this->get($userId) === null) throw new RuntimeException('کاربر پیدا نشد.');
        return $this->get($userId) ?? throw new RuntimeException('کاربر قابل بازیابی نیست.');
    }

    /** @return array<string,mixed>|null */
    public function get(string $id): ?array
    {
        $sql = <<<'SQL'
SELECT
    u.id,u.mobile,u.email,u.display_name,u.status,u.disabled_at,u.last_login_at,u.created_at,
    (SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',')
       FROM user_roles ur JOIN roles r ON r.id=ur.role_id
      WHERE ur.user_id=u.id AND (ur.expires_at IS NULL OR ur.expires_at>UTC_TIMESTAMP(6))) AS role_names,
    (SELECT GROUP_CONCAT(r.label ORDER BY r.name SEPARATOR '، ')
       FROM user_roles ur JOIN roles r ON r.id=ur.role_id
      WHERE ur.user_id=u.id AND (ur.expires_at IS NULL OR ur.expires_at>UTC_TIMESTAMP(6))) AS role_labels
FROM users u WHERE u.id=:id LIMIT 1
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->normalizeUserRow($row);
    }

    /** An idempotent, narrow grant for the canonical Partners activation workflow. */
    public function activatePartner(array $input,string $actor):array
    {
        foreach(['cooperation_id','merchant_id','user_id']as$key)if(!is_string($input[$key]??null)||!preg_match('/^[a-f0-9]{26}$/D',$input[$key]))throw new RuntimeException('شناسه فعال‌سازی پذیرنده معتبر نیست.');
        $userId=$input['user_id'];$this->assertTargetManageable($actor,$userId);
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('SELECT status,disabled_at FROM users WHERE id=:id FOR UPDATE');$s->execute(['id'=>$userId]);$user=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($user)||$user['status']!=='active'||$user['disabled_at']!==null)throw new RuntimeException('partner_account_inactive');
            $role=$this->roleMap(['acceptor'])['acceptor'];
            $s=$this->pdo->prepare('INSERT INTO partner_role_activations(cooperation_id,merchant_id,user_id,actor_user_id) VALUES(:cooperation,:merchant,:user,:actor) ON DUPLICATE KEY UPDATE cooperation_id=VALUES(cooperation_id)');
            $s->execute(['cooperation'=>$input['cooperation_id'],'merchant'=>$input['merchant_id'],'user'=>$userId,'actor'=>$actor]);
            $s=$this->pdo->prepare('SELECT merchant_id,user_id FROM partner_role_activations WHERE cooperation_id=:id FOR UPDATE');$s->execute(['id'=>$input['cooperation_id']]);$previous=$s->fetch(PDO::FETCH_ASSOC);
            if($previous['merchant_id']!==$input['merchant_id']||$previous['user_id']!==$userId)throw new RuntimeException('partner_activation_conflict');
            // Add only the acceptor role. Preserve every existing role and scope grant.
            $s=$this->pdo->prepare('INSERT INTO user_roles(user_id,role_id,created_by_user_id) VALUES(:user,:role,:actor) ON DUPLICATE KEY UPDATE expires_at=NULL');
            $s->execute(['user'=>$userId,'role'=>$role,'actor'=>$actor]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return ['cooperation_id'=>$input['cooperation_id'],'merchant_id'=>$input['merchant_id'],'user_id'=>$userId,'role'=>'acceptor'];
    }

    private function roleMap(array $roles): array
    {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->pdo->prepare('SELECT name,id FROM roles WHERE name IN (' . $placeholders . ')');
        $stmt->execute($roles);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $map[(string) $row['name']] = (string) $row['id'];
        $missing = array_values(array_diff($roles, array_keys($map)));
        if ($missing !== []) throw new RuntimeException('نقش ناشناخته: ' . implode('، ', $missing));
        return $map;
    }

    /** @param array<int,string> $roles @param array<string,string> $roleMap */
    private function replaceRolesInternal(string $userId, array $roles, array $roleMap, string $actor): void
    {
        $delete = $this->pdo->prepare('DELETE FROM user_roles WHERE user_id=:user_id');
        $delete->execute([':user_id' => $userId]);
        $insert = $this->pdo->prepare('INSERT INTO user_roles (user_id,role_id,created_by_user_id) VALUES (:user_id,:role_id,:actor)');
        foreach ($roles as $name) $insert->execute([':user_id' => $userId, ':role_id' => $roleMap[$name], ':actor' => $actor]);
    }

    /** @return array<int,string> */
    private function normalizeRoles(mixed $value): array
    {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $role) {
            if (!is_string($role)) continue;
            $role = strtolower(trim($role));
            if ($role !== '' && preg_match('/^[a-z0-9_.-]{2,128}$/', $role) === 1) $out[] = $role;
        }
        return array_values(array_unique($out));
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeUserRow(array $row): array
    {
        $row['roles'] = !empty($row['role_names']) ? explode(',', (string) $row['role_names']) : [];
        unset($row['role_names']);
        return $row;
    }


    private function isSuperAdmin(string $userId): bool
    {
        $s=$this->pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=:u AND r.name='super_admin' AND (ur.expires_at IS NULL OR ur.expires_at>UTC_TIMESTAMP(6)) LIMIT 1");$s->execute([':u'=>$userId]);return(bool)$s->fetchColumn();
    }
    /** @param array<int,string> $roles */
    private function assertRoleAssignmentAllowed(string $actor,array $roles): void
    {
        if($this->isSuperAdmin($actor))return;
        foreach(['super_admin','admin'] as$protected)if(in_array($protected,$roles,true))throw new RuntimeException('تخصیص نقش مدیریتی سطح بالا فقط برای مدیر ارشد مجاز است.');
    }
    private function hasProtectedAdminRole(string $userId): bool
    {
        $s=$this->pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=:u AND r.name IN ('super_admin','admin') AND (ur.expires_at IS NULL OR ur.expires_at>UTC_TIMESTAMP(6)) LIMIT 1");$s->execute([':u'=>$userId]);return(bool)$s->fetchColumn();
    }
    private function assertTargetManageable(string $actor,string $target): void
    {
        if($actor===$target||$this->isSuperAdmin($actor))return;
        if($this->hasProtectedAdminRole($target))throw new RuntimeException('مدیریت حساب مدیر سطح بالا فقط توسط مدیر ارشد مجاز است.');
    }

    private function normalizeMobile(string $value): string
    {
        $value = strtr(trim($value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        $value = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($value, '98') && strlen($value) === 12) $value = '0' . substr($value, 2);
        return $value;
    }
}

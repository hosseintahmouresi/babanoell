<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202608260001'; }
    public function name(): string { return 'seed_loans_permissions'; }

    public function up(PDO $pdo): void
    {
        $permissions = [
            'loans.plans.read' => 'مشاهده طرح‌های تسهیلات',
            'loans.plans.manage' => 'ایجاد، ویرایش و نسخه‌بندی طرح‌های تسهیلات',
            'loans.calculate' => 'اجرای محاسبات تسهیلات',
            'loans.requests.read' => 'مشاهده پرونده‌های تسهیلات',
            'loans.requests.create' => 'ایجاد پرونده تسهیلات',
            'loans.requests.update' => 'ویرایش اطلاعات پرونده تسهیلات',
            'loans.requests.transition' => 'تغییر وضعیت پرونده تسهیلات',
            'loans.requests.audit' => 'مشاهده تاریخچه و رویدادهای پرونده تسهیلات',
        ];

        // Fresh installs run migrations before installer seeds the first admin.
        // Create the system role here when missing; the installer later reuses it.
        $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $roleQuery->execute([':name' => 'super_admin']);
        $roleId = $roleQuery->fetchColumn();
        if (!is_string($roleId) || $roleId === '') {
            $roleId = bin2hex(random_bytes(13));
            $insertRole = $pdo->prepare(
                'INSERT INTO roles (id, name, label, description, is_system) VALUES (:id, :name, :label, :description, 1)'
            );
            $insertRole->execute([
                ':id' => $roleId,
                ':name' => 'super_admin',
                ':label' => 'مدیر ارشد سامانه',
                ':description' => 'نقش سیستمی با دسترسی کامل مدیریت.',
            ]);
        }

        $findPermission = $pdo->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');
        $insertPermission = $pdo->prepare(
            'INSERT INTO permissions (id, name, description) VALUES (:id, :name, :description)'
        );
        $assign = $pdo->prepare(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id, created_by_user_id) VALUES (:role_id, :permission_id, NULL)'
        );

        foreach ($permissions as $name => $description) {
            $findPermission->execute([':name' => $name]);
            $permissionId = $findPermission->fetchColumn();
            if (!is_string($permissionId) || $permissionId === '') {
                $permissionId = bin2hex(random_bytes(13));
                $insertPermission->execute([
                    ':id' => $permissionId,
                    ':name' => $name,
                    ':description' => $description,
                ]);
            }
            $assign->execute([':role_id' => $roleId, ':permission_id' => $permissionId]);
        }
    }
};

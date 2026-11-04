<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609220002'; }
    public function name(): string { return 'seed_acceptor_role'; }

    public function up(PDO $pdo): void
    {
        $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $roleQuery->execute([':name' => 'acceptor']);
        $roleId = $roleQuery->fetchColumn();

        if (!is_string($roleId) || $roleId === '') {
            $roleId = bin2hex(random_bytes(13));
            $insertRole = $pdo->prepare(
                'INSERT INTO roles (id, name, label, description, is_system) VALUES (:id, :name, :label, :description, 1)'
            );
            $insertRole->execute([
                ':id' => $roleId,
                ':name' => 'acceptor',
                ':label' => 'پذیرنده',
                ':description' => 'پذیرنده مجاز به محاسبه تسهیلات، ثبت و پیگیری پرونده‌های خود و مدیریت مدارک همان پرونده‌ها است.',
            ]);
        }

        $permissionNames = [
            'loans.plans.read',
            'loans.calculate',
            'loans.requests.read',
            'loans.requests.create',
            'loans.requests.update',
            'loans.requests.audit',
            'documents.view',
            'documents.upload',
            'documents.download',
            'documents.replace',
        ];

        $findPermission = $pdo->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');
        $assign = $pdo->prepare(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id, created_by_user_id) VALUES (:role_id, :permission_id, NULL)'
        );

        foreach ($permissionNames as $permissionName) {
            $findPermission->execute([':name' => $permissionName]);
            $permissionId = $findPermission->fetchColumn();
            if (!is_string($permissionId) || $permissionId === '') {
                throw new RuntimeException('Required permission is missing before acceptor role seed: ' . $permissionName);
            }
            $assign->execute([':role_id' => $roleId, ':permission_id' => $permissionId]);
        }
    }
};

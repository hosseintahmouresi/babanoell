<?php
declare(strict_types=1);

use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609220003'; }
    public function name(): string { return 'seed_business_roles'; }

    public function up(PDO $pdo): void
    {
        $permissions = [
            'loans.requests.global' => 'مشاهده سراسری پرونده‌های تسهیلات بدون محدودیت مالک/ارجاع',
            'loans.requests.assign_bank' => 'تخصیص یا تغییر کارشناس بانک پرونده تسهیلات',
        ];

        $findPermission = $pdo->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');
        $insertPermission = $pdo->prepare('INSERT INTO permissions (id, name, description) VALUES (:id, :name, :description)');
        foreach ($permissions as $name => $description) {
            $findPermission->execute([':name' => $name]);
            $id = $findPermission->fetchColumn();
            if (!is_string($id) || $id === '') {
                $insertPermission->execute([
                    ':id' => bin2hex(random_bytes(13)),
                    ':name' => $name,
                    ':description' => $description,
                ]);
            }
        }

        $roles = [
            'admin' => [
                'label' => 'مدیر سامانه',
                'description' => 'معادل مدیر سامانه در افزونه قبلی؛ دسترسی کامل عملیاتی به تسهیلات و مدارک، بدون دسترسی مالک سامانه به مدیریت هویت.',
                'permissions' => [
                    'loans.plans.read','loans.plans.manage','loans.calculate',
                    'loans.requests.read','loans.requests.create','loans.requests.update','loans.requests.transition','loans.requests.audit',
                    'loans.requests.global','loans.requests.assign_bank',
                    'documents.view','documents.upload','documents.download','documents.review','documents.replace','documents.archive','documents.audit','documents.manage',
                ],
            ],
            'acceptor' => [
                'label' => 'پذیرنده',
                'description' => 'معادل نقش pazirande/acceptor افزونه قبلی؛ محاسبه، ثبت و پیگیری پرونده‌های خود و مدیریت مدارک همان پرونده‌ها.',
                'permissions' => [
                    'loans.plans.read','loans.calculate','loans.requests.read','loans.requests.create','loans.requests.update','loans.requests.audit',
                    'documents.view','documents.upload','documents.download','documents.replace',
                ],
            ],
            'applicant' => [
                'label' => 'متقاضی تسهیلات',
                'description' => 'معادل نقش mlr_applicant افزونه قبلی؛ محاسبه، ثبت و پیگیری پرونده شخصی و مدارک همان پرونده.',
                'permissions' => [
                    'loans.plans.read','loans.calculate','loans.requests.read','loans.requests.create','loans.requests.update',
                    'documents.view','documents.upload','documents.download','documents.replace',
                ],
            ],
            'bank_expert' => [
                'label' => 'کارشناس بانک',
                'description' => 'معادل نقش bank افزونه قبلی؛ دسترسی فقط به پرونده‌های ارجاع‌شده، مشاهده مدارک، بازبینی و گردش تخصصی بانکی.',
                'permissions' => [
                    'loans.plans.read','loans.requests.read','loans.requests.update','loans.requests.transition','loans.requests.audit',
                    'documents.view','documents.download','documents.review','documents.audit',
                ],
            ],
            'operations_expert' => [
                'label' => 'کارشناس عملیات بابانوئل',
                'description' => 'معادل نقش amaliat افزونه قبلی؛ مشاهده سراسری پرونده‌ها، پیگیری گردش عملیاتی، ارجاع بانکی و بازبینی مدارک.',
                'permissions' => [
                    'loans.plans.read','loans.requests.read','loans.requests.transition','loans.requests.audit','loans.requests.global','loans.requests.assign_bank',
                    'documents.view','documents.download','documents.review','documents.audit',
                ],
            ],
            'finance_expert' => [
                'label' => 'کارشناس مالی بابانوئل',
                'description' => 'معادل نقش mali افزونه قبلی؛ مشاهده سراسری پرونده‌ها و اطلاعات/مدارک موردنیاز فرآیند مالی. دسترسی‌های تسویه در ماژول مالی اعمال خواهد شد.',
                'permissions' => [
                    'loans.plans.read','loans.requests.read','loans.requests.audit','loans.requests.global',
                    'documents.view','documents.download','documents.audit',
                ],
            ],
            'contract_expert' => [
                'label' => 'کارشناس قرارداد بابانوئل',
                'description' => 'معادل نقش gharardad افزونه قبلی؛ مشاهده سراسری پرونده و مدارک برای فرآیند قرارداد، بدون دسترسی مدیریتی غیرضروری وردپرس.',
                'permissions' => [
                    'loans.plans.read','loans.requests.read','loans.requests.audit','loans.requests.global',
                    'documents.view','documents.download','documents.audit',
                ],
            ],
        ];

        $findRole = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $insertRole = $pdo->prepare('INSERT INTO roles (id, name, label, description, is_system) VALUES (:id, :name, :label, :description, 1)');
        $updateRole = $pdo->prepare('UPDATE roles SET label=:label, description=:description, is_system=1 WHERE id=:id');
        $permissionId = $pdo->prepare('SELECT id FROM permissions WHERE name = :name LIMIT 1');
        $assign = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id, created_by_user_id) VALUES (:role_id, :permission_id, NULL)');
        $clearPlatformPermissions = $pdo->prepare("DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=:role_id AND (p.name LIKE 'loans.%' OR p.name LIKE 'documents.%')");

        foreach ($roles as $name => $definition) {
            $findRole->execute([':name' => $name]);
            $roleId = $findRole->fetchColumn();
            if (!is_string($roleId) || $roleId === '') {
                $roleId = bin2hex(random_bytes(13));
                $insertRole->execute([
                    ':id' => $roleId,
                    ':name' => $name,
                    ':label' => $definition['label'],
                    ':description' => $definition['description'],
                ]);
            } else {
                $updateRole->execute([
                    ':id' => $roleId,
                    ':label' => $definition['label'],
                    ':description' => $definition['description'],
                ]);
            }

            // System roles are deterministic: normalize only platform-owned permissions.
            $clearPlatformPermissions->execute([':role_id' => $roleId]);
            foreach ($definition['permissions'] as $permissionName) {
                $permissionId->execute([':name' => $permissionName]);
                $pid = $permissionId->fetchColumn();
                if (!is_string($pid) || $pid === '') {
                    throw new RuntimeException('Required permission is missing for role '.$name.': '.$permissionName);
                }
                $assign->execute([':role_id' => $roleId, ':permission_id' => $pid]);
            }
        }

        // Keep the platform owner exhaustive for the new scope/assignment permissions.
        $findRole->execute([':name' => 'super_admin']);
        $superAdminId = $findRole->fetchColumn();
        if (is_string($superAdminId) && $superAdminId !== '') {
            foreach (array_keys($permissions) as $permissionName) {
                $permissionId->execute([':name' => $permissionName]);
                $pid = $permissionId->fetchColumn();
                if (is_string($pid) && $pid !== '') {
                    $assign->execute([':role_id' => $superAdminId, ':permission_id' => $pid]);
                }
            }
        }
    }
};

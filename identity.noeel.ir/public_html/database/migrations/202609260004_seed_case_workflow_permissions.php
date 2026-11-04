<?php
declare(strict_types=1);
use Babanoell\Auth\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609260004'; }
    public function name(): string { return 'seed_case_workflow_permissions'; }

    public function up(PDO $pdo): void
    {
        $permissions = [
            'loans.requests.message' => 'ارسال و مشاهده پیام‌های پرونده',
            'loans.requests.internal_notes' => 'ثبت و مشاهده یادداشت داخلی پرونده',
            'loans.requests.document_receipt' => 'مدیریت وضعیت دریافت فیزیکی مدارک',
            'loans.requests.grant' => 'ثبت نهایی اعطای اعتبار',
            'loans.requests.workflow_admin' => 'مدیریت ارجاع و عملیات حساس گردش پرونده',
        ];

        $findPermission = $pdo->prepare('SELECT id FROM permissions WHERE name=:name LIMIT 1');
        $insertPermission = $pdo->prepare('INSERT INTO permissions (id,name,description) VALUES (:id,:name,:description)');
        $updatePermission = $pdo->prepare('UPDATE permissions SET description=:description WHERE id=:id');
        foreach ($permissions as $name => $description) {
            $findPermission->execute([':name'=>$name]);
            $permissionId = $findPermission->fetchColumn();
            if (!is_string($permissionId) || $permissionId === '') {
                $permissionId = bin2hex(random_bytes(13));
                $insertPermission->execute([':id'=>$permissionId, ':name'=>$name, ':description'=>$description]);
            } else {
                $updatePermission->execute([':id'=>$permissionId, ':description'=>$description]);
            }
        }

        $roleMap = [
            'super_admin' => array_keys($permissions),
            'admin' => array_keys($permissions),
            'operations_expert' => ['loans.requests.message','loans.requests.internal_notes','loans.requests.document_receipt','loans.requests.grant','loans.requests.workflow_admin'],
            'finance_expert' => ['loans.requests.message','loans.requests.internal_notes','loans.requests.document_receipt','loans.requests.workflow_admin'],
            'contract_expert' => ['loans.requests.message','loans.requests.internal_notes'],
            'bank_expert' => ['loans.requests.message','loans.requests.internal_notes','loans.requests.grant'],
            'acceptor' => ['loans.requests.message'],
            'applicant' => ['loans.requests.message'],
        ];
        $findRole = $pdo->prepare('SELECT id FROM roles WHERE name=:name LIMIT 1');
        $findPermission->closeCursor();
        $assign = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id,permission_id,created_by_user_id) VALUES (:role_id,:permission_id,NULL)');
        foreach ($roleMap as $roleName => $rolePermissions) {
            $findRole->execute([':name'=>$roleName]);
            $roleId = $findRole->fetchColumn();
            if (!is_string($roleId) || $roleId === '') continue;
            foreach ($rolePermissions as $permissionName) {
                $findPermission->execute([':name'=>$permissionName]);
                $permissionId = $findPermission->fetchColumn();
                if (!is_string($permissionId) || $permissionId === '') throw new RuntimeException('Missing permission '.$permissionName);
                $assign->execute([':role_id'=>$roleId, ':permission_id'=>$permissionId]);
            }
        }
    }
};

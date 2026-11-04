<?php
declare(strict_types=1);

namespace Babanoell\Loans\Database;

use PDO;
use RuntimeException;

final class DatabaseIsolationGuard
{
    /** @var list<string> */
    private const IDENTITY_MARKERS = [
        'users',
        'roles',
        'permissions',
        'sessions',
        'auth_audit_logs',
    ];

    /** @return array{database:string,identity_markers:list<string>,existing_tables:list<string>} */
    public static function inspect(PDO $pdo): array
    {
        $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database==='') {
            throw new RuntimeException('نام دیتابیس فعال قابل تشخیص نیست.');
        }

        $stmt=$pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=:schema ORDER BY table_name');
        $stmt->execute([':schema'=>$database]);
        $tables=[];
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $name){
            if(is_string($name)&&$name!=='')$tables[]=$name;
        }

        $markers=[];
        foreach(self::IDENTITY_MARKERS as $marker){
            if(in_array($marker,$tables,true))$markers[]=$marker;
        }

        return ['database'=>$database,'identity_markers'=>$markers,'existing_tables'=>$tables];
    }

    /**
     * Loans must never share Identity's database. This guard runs before any
     * Loans migration so a wrong DB selection fails without modifying schema.
     */
    public static function assertLoansDatabase(PDO $pdo): void
    {
        $info=self::inspect($pdo);
        if($info['identity_markers']!==[]){
            throw new RuntimeException(
                'دیتابیس انتخاب‌شده متعلق به سرویس Identity است و برای Loans قابل استفاده نیست. '
                .'برای سرویس تسهیلات یک دیتابیس مستقل و خالی بسازید.'
            );
        }
    }
}

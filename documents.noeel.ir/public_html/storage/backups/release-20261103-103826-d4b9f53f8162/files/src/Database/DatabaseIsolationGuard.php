<?php
declare(strict_types=1);
namespace Babanoell\Documents\Database;
use PDO;
use RuntimeException;

final class DatabaseIsolationGuard
{
    private const FOREIGN_MARKERS=['users','roles','permissions','sessions','auth_audit_logs','installment_plans','loan_requests'];
    private const OWN_MARKERS=['documents','document_versions','document_reviews','document_audit_logs'];
    public static function inspect(PDO $pdo): array
    {
        $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if($database==='')throw new RuntimeException('نام دیتابیس فعال قابل تشخیص نیست.');
        $stmt=$pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=:schema ORDER BY table_name');$stmt->execute([':schema'=>$database]);
        $tables=array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN),'is_string'));
        $foreign=array_values(array_intersect(self::FOREIGN_MARKERS,$tables));$own=array_values(array_intersect(self::OWN_MARKERS,$tables));
        return ['database'=>$database,'foreign_markers'=>$foreign,'own_markers'=>$own,'existing_tables'=>$tables];
    }
    public static function assertDocumentsDatabase(PDO $pdo): void
    {
        $info=self::inspect($pdo);
        if($info['foreign_markers']!==[]){
            throw new RuntimeException('دیتابیس انتخاب‌شده شامل ساختارهای سرویس دیگری است و برای Documents قابل استفاده نیست. برای سرویس مدارک یک دیتابیس مستقل انتخاب کنید.');
        }
    }
}

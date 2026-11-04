<?php
declare(strict_types=1);
namespace Babanoell\Finance\Database;
use PDO;use RuntimeException;
final class DatabaseIsolationGuard
{
 private const FOREIGN_MARKERS=['users','roles','permissions','sessions','installment_plans','loan_requests','documents','document_versions','merchants','cooperation_requests'];
 public static function inspect(PDO$pdo):array{$db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();if($db==='')throw new RuntimeException('نام دیتابیس فعال قابل تشخیص نیست.');$s=$pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=:schema ORDER BY table_name');$s->execute([':schema'=>$db]);$tables=array_values(array_filter($s->fetchAll(PDO::FETCH_COLUMN),'is_string'));$markers=[];foreach(self::FOREIGN_MARKERS as$m)if(in_array($m,$tables,true))$markers[]=$m;return['database'=>$db,'foreign_markers'=>$markers,'existing_tables'=>$tables];}
 public static function assertFinanceDatabase(PDO$pdo):void{$i=self::inspect($pdo);if($i['foreign_markers']!==[])throw new RuntimeException('دیتابیس انتخاب‌شده متعلق به سرویس دیگری است. برای Finance یک دیتابیس مستقل بسازید.');}
}

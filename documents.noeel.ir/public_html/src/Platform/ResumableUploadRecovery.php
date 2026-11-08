<?php
declare(strict_types=1);
namespace Babanoell\Documents\Platform;

use Babanoell\Documents\Database\MigrationRunner;
use PDO;
use RuntimeException;
use Throwable;

/** Forward completion for the single known, additive Documents 1.23.0 migration failure. */
final class ResumableUploadRecovery
{
    private const SOURCE='763f23d5f6ab14170f6de631a7e2bfa12a06f1c0';
    private const REPAIR='documents-resumable-collation-1.23.1';
    private const MIGRATION='202611080001';
    private const OVERLAY=['public/update.php','database/migrations/202611080001_resumable_uploads.php'];

    private const FIXED_FILES=[
        'public/update.php'=>'e1a60394f980dd80200bb2570b06781211b4148edb9e4fdbd508febb22c76809',
        'database/migrations/202611080001_resumable_uploads.php'=>'fd78b6dff89c597cc83736eda0b8d23424a6b2e65161e91ca79df6995388b5f9',
    ];

    public static function available(PDO $pdo,string $root):bool
    {
        try{self::assertKnownFailure($pdo,$root);return true;}catch(Throwable){return false;}
    }

    private static function assertKnownFailure(PDO $pdo,string $root):void
    {
        $state=ReleaseTransaction::state($root.'/storage');
        $continuing=($state['forward_repair']??'')===self::REPAIR;
        if(($state['phase']??'')!=='database_recovery_required'&&!(($state['phase']??'')==='migrating'&&$continuing))throw new RuntimeException('این اصلاح فقط برای مهاجرت نیمه‌تمام آپلود نسخهٔ 1.23.0 است.');
        $version=require $root.'/config/version.php';
        if(($version['service']??'')!=='documents'||($version['version']??'')!=='1.23.0'||($version['ui']??'')!=='2.18.0')throw new RuntimeException('نسخهٔ نصب‌شده با اصلاح اختصاصی سازگار نیست.');
        $manifest=json_decode((string)file_get_contents($root.'/release-manifest.json'),true,512,JSON_THROW_ON_ERROR);
        if(($manifest['service']??'')!=='documents'||($manifest['version']??'')!=='1.23.0'||($manifest['source_commit']??'')!==self::SOURCE||!is_array($manifest['files']??null)||count($manifest['files'])<50)throw new RuntimeException('مانیفست نسخهٔ نیمه‌تمام تأیید نشد.');
        $backup=$state['backup']??'';
        if(!is_string($backup)||!preg_match('/^release-[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/D',$backup))throw new RuntimeException('سابقهٔ بازیابی نسخه معتبر نیست.');
        $snapshot=$root.'/storage/backups/'.$backup.'/package.zip';
        if(!is_file($snapshot)||!hash_equals((string)($state['package_sha256']??''),(string)hash_file('sha256',$snapshot)))throw new RuntimeException('بستهٔ اصلیِ ثبت‌شده در سابقه تأیید نشد.');
        foreach($manifest['files'] as $name=>$hash){
            if(!is_string($name)||preg_match('#(^/|\\\\|:|[\x00-\x1f]|//|(^|/)\.{1,2}(/|$))#',$name)||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new RuntimeException('مسیر مانیفست معتبر نیست.');
            $path=$root;
            foreach(explode('/',$name) as $part){$path.='/'.$part;if(is_link($path))throw new RuntimeException('مسیر فایل نسخه معتبر نیست.');}
            if(in_array($name,self::OVERLAY,true)){if(!is_file($path)||!hash_equals(self::FIXED_FILES[$name],(string)hash_file('sha256',$path)))throw new RuntimeException('فایل اصلاحی با نسخهٔ تأییدشده برابر نیست.');continue;}
            if(!is_file($path)||!hash_equals($hash,(string)hash_file('sha256',$path)))throw new RuntimeException('فایل‌های نسخهٔ نیمه‌تمام با مانیفست برابر نیستند؛ تکمیل خودکار متوقف شد.');
        }
        $rows=(new MigrationRunner($pdo,$root.'/database/migrations'))->status()['migrations'];
        if(count($rows)!==9)throw new RuntimeException('فهرست مهاجرت‌های Documents با این اصلاح برابر نیست.');
        $pending=array_values(array_filter($rows,static fn(array $r):bool=>$r['status']==='pending'));
        if(!($pending!==[]&&count($pending)===1&&$pending[0]['version']===self::MIGRATION)&&!($pending===[]&&$continuing))throw new RuntimeException('مهاجرت در انتظار، همان اصلاح شناخته‌شده نیست.');
        $q=$pdo->prepare("SELECT t.ENGINE,c.DATA_TYPE,c.CHARACTER_MAXIMUM_LENGTH,c.COLLATION_NAME FROM information_schema.TABLES t JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME='chunk_uploads' AND c.COLUMN_NAME='id'");$q->execute();$parent=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($parent)||strtoupper((string)$parent['ENGINE'])!=='INNODB'||$parent['DATA_TYPE']!=='char'||(int)$parent['CHARACTER_MAXIMUM_LENGTH']!==26||$parent['COLLATION_NAME']!=='utf8mb4_unicode_ci')throw new RuntimeException('ساختار جدول اصلی آپلود با خطای شناخته‌شده تطبیق ندارد.');
        if(!$continuing&&(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='chunk_upload_parts'")->fetchColumn()!==0)throw new RuntimeException('وضعیت جدول قطعات با خطای شناخته‌شده تطبیق ندارد.');
        // No upload could legitimately start while this first migration was blocked.
        if((int)$pdo->query('SELECT COUNT(*) FROM chunk_uploads')->fetchColumn()!==0||(int)$pdo->query('SELECT COUNT(*) FROM chunk_upload_owners')->fetchColumn()!==0)throw new RuntimeException('جدول آپلود حاوی داده است؛ اصلاح اختصاصی متوقف شد.');
    }

    private static function save(string $file,array $state):void
    {
        $temp=$file.'.'.bin2hex(random_bytes(6)).'.tmp';
        try{
            $data=json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
            if(file_put_contents($temp,$data,LOCK_EX)!==strlen($data)||!chmod($temp,0600)||!rename($temp,$file))throw new RuntimeException('ثبت وضعیت تکمیل مهاجرت انجام نشد.');
        }finally{if(is_file($temp))@unlink($temp);}
    }

    public static function complete(PDO $pdo,string $root):array
    {
        $storage=$root.'/storage';$lockPath=$storage.'/update.lock';
        if(is_link($lockPath)||is_link($storage.'/update-state.json'))throw new RuntimeException('مسیر قفل بازیابی معتبر نیست.');
        $lock=@fopen($lockPath,'c+b');
        if($lock===false)throw new RuntimeException('باز کردن قفل به‌روزرسانی ممکن نیست.');
        if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);throw new RuntimeException('یک به‌روزرسانی دیگر در حال اجراست.');}
        $state=null;
        try{
            self::assertKnownFailure($pdo,$root);
            $state=ReleaseTransaction::state($storage);$state['forward_repair']=self::REPAIR;$state['phase']='migrating';
            self::save($storage.'/update-state.json',$state);
            $done=(new MigrationRunner($pdo,$root.'/database/migrations'))->up();
            $q=$pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='chunk_upload_parts' AND REFERENCED_TABLE_NAME='chunk_uploads' AND DELETE_RULE='CASCADE'");
            if((int)$q->fetchColumn()!==1)throw new RuntimeException('قید ارتباط قطعات آپلود تأیید نشد.');
            $state['phase']='complete';$state['completed_at']=gmdate('c');unset($state['error']);
            self::save($storage.'/update-state.json',$state);ReleaseTransaction::syncVersion($root);
            return $done;
        }catch(Throwable $error){
            if(is_array($state)){$state['phase']='database_recovery_required';$state['error']='The dedicated resumable-upload completion did not finish.';self::save($storage.'/update-state.json',$state);}
            throw $error;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}

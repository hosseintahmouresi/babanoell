<?php
declare(strict_types=1);
namespace Babanoell\Finance\Platform;

use RuntimeException;
use Throwable;
use ZipArchive;

/** File deployment journal; SQL recovery is deliberately explicit for MySQL DDL. */
final class ReleaseTransaction
{
    private static function json(string $path, array $data): void
    {
        $temp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
        $bytes=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if(file_put_contents($temp,$bytes,LOCK_EX)!==strlen($bytes)) throw new RuntimeException('Cannot write deployment journal.');
        @chmod($temp,0600);
        if(!rename($temp,$path)){@unlink($temp);throw new RuntimeException('Cannot commit deployment journal.');}
    }

    public static function state(string $storage): array
    {
        $file=$storage.'/update-state.json';
        if(!is_file($file))return [];
        try{$data=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);return is_array($data)?$data:['phase'=>'unreadable'];}
        catch(Throwable){return ['phase'=>'unreadable'];}
    }

    public static function pending(string $storage): bool
    {
        $phase=self::state($storage)['phase']??'';
        return !in_array($phase,['','complete','rolled_back'],true);
    }

    public static function guard(string $root): void
    {
        if(!self::pending($root.'/storage'))return;
        http_response_code(503);header('Retry-After: 60');header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>به‌روزرسانی سامانه</title><link rel="stylesheet" href="/ui-asset.php?file=platform.css&amp;v=1.6.0"><main class="bn-shell bn-shell--narrow"><section class="bn-card"><div class="bn-eyebrow">پلتفرم بابانوئل</div><h1 class="bn-title">سامانه در حال به‌روزرسانی است</h1><p>عملیات نسخه در حال اجرا یا نیازمند بازیابی مدیر سامانه است. کمی بعد دوباره تلاش کنید.</p><a class="bn-btn bn-btn--secondary" href="/update.php">مرکز به‌روزرسانی</a></section></main></html>';
        exit;
    }

    public static function syncVersion(string $root): void
    {
        if(self::pending($root.'/storage'))return;
        $lock=$root.'/storage/installed.lock';
        if(!is_file($lock))return;
        $meta=json_decode((string)file_get_contents($lock),true);
        if(!is_array($meta))return; // Preserve unknown historical lock formats.
        $version=require $root.'/config/version.php';
        if(($meta['version']??null)===($version['version']??null)&&($meta['ui']??null)===($version['ui']??null))return;
        foreach(['service','version','ui'] as $key)$meta[$key]=$version[$key];
        if(array_key_exists('ui_version',$meta))$meta['ui_version']=$version['ui'];
        if(array_key_exists('service_version',$meta))$meta['service_version']=$version['version'];
        $meta['updated_at']=gmdate('c');self::json($lock,$meta);
    }

    private static function safe(string $relative): void
    {
        if($relative==='' || preg_match('#(^/|\\\\|:|[\x00-\x1f]|//|(^|/)\.{1,2}(/|$))#',$relative))throw new RuntimeException('Unsafe deployment path.');
        if(str_starts_with($relative,'storage/') || $relative==='storage' || in_array($relative,['config/local.env.php','config/service-auth.local.php','public/install.php','public/install.php.locked'],true))throw new RuntimeException('Protected deployment path.');
    }

    private static function destination(string $root,string $relative): string
    {
        self::safe($relative);$path=$root;
        foreach(explode('/',$relative) as $segment){$path.='/'.$segment;if(is_link($path))throw new RuntimeException('Symlink in deployment target.');}
        return $path;
    }

    private static function replace(string $source,string $target): void
    {
        $dir=dirname($target);if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Cannot create target directory.');
        $temp=$dir.'/.release-'.bin2hex(random_bytes(6)).'.tmp';
        try{
            if(!copy($source,$temp)||hash_file('sha256',$source)!==hash_file('sha256',$temp))throw new RuntimeException('Staged file copy failed.');
            @chmod($temp,0644);
            if(!rename($temp,$target))throw new RuntimeException('Cannot replace target file.');
            if(function_exists('opcache_invalidate'))@opcache_invalidate($target,true);
        }finally{if(is_file($temp))@unlink($temp);}
    }

    private static function lock(string $storage)
    {
        $lock=@fopen($storage.'/update.lock','c+b');
        if($lock===false)throw new RuntimeException('Cannot open deployment lock.');
        if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);throw new RuntimeException('Another update is running.');}
        return $lock;
    }

    public static function apply(string $zipPath,string $root,string $storage,string $expectedHash,?callable $migrate=null): array
    {
        $lock=self::lock($storage);$state=[];$backup='';$migrationStarted=false;
        try{
            if(self::pending($storage))throw new RuntimeException('Previous update requires recovery.');
            if(!preg_match('/^[a-f0-9]{64}$/',$expectedHash))throw new RuntimeException('Validate the package first.');
            $id=gmdate('Ymd-His').'-'.bin2hex(random_bytes(6));
            $backup=$storage.'/backups/release-'.$id;
            if(!mkdir($backup.'/files',0700,true))throw new RuntimeException('Cannot create private backup.');
            $snapshot=$backup.'/package.zip';
            if(!copy($zipPath,$snapshot)||!hash_equals($expectedHash,(string)hash_file('sha256',$snapshot)))throw new RuntimeException('Package changed after validation.');
            @chmod($snapshot,0600);
            $service=(require $root.'/config/version.php')['service'];
            $engine='Babanoell\Finance\\Update\\UpdateTool';
            $plan=$service==='identity'?$engine::readArchive($snapshot):(new $engine())->plan($snapshot);
            $archive=new ZipArchive();if($archive->open($snapshot,ZipArchive::CHECKCONS)!==true)throw new RuntimeException('Invalid archive.');
            $files=[];
            try{
                foreach($plan['files'] as $item){
                    $relative=$item['target'];$target=self::destination($root,$relative);
                    $entry=$item['entry']??$item['zip'];$data=$archive->getFromName($entry);
                    if($data===false)throw new RuntimeException('Cannot read package file.');
                    $staged=$backup.'/staged/'.$relative;$dir=dirname($staged);if(!is_dir($dir))mkdir($dir,0700,true);
                    if(file_put_contents($staged,$data)!==strlen($data))throw new RuntimeException('Cannot stage package file.');
                    $existed=is_file($target);
                    if(file_exists($target)&&!$existed)throw new RuntimeException('Target is not a regular file.');
                    if($existed){$saved=$backup.'/files/'.$relative;if(!is_dir(dirname($saved)))mkdir(dirname($saved),0700,true);if(!copy($target,$saved)||hash_file('sha256',$saved)!==hash_file('sha256',$target))throw new RuntimeException('Cannot back up original file.');}
                    $files[]=['target'=>$relative,'existed'=>$existed];
                }
            }finally{$archive->close();}
            $manifest=['files'=>$files,'installed_lock'=>is_file($storage.'/installed.lock')?file_get_contents($storage.'/installed.lock'):null];
            self::json($backup.'/manifest.json',$manifest);
            $state=['phase'=>'copying','backup'=>basename($backup),'started_at'=>gmdate('c'),'package_sha256'=>$expectedHash];self::json($storage.'/update-state.json',$state);
            foreach($files as $file)self::replace($backup.'/staged/'.$file['target'],self::destination($root,$file['target']));
            $migrations=[];
            if($migrate!==null){$migrationStarted=true;$state['phase']='migrating';self::json($storage.'/update-state.json',$state);$migrations=$migrate();}
            $state['phase']='complete';$state['completed_at']=gmdate('c');self::json($storage.'/update-state.json',$state);
            self::syncVersion($root);
            if(is_file($zipPath)&&hash_equals($expectedHash,(string)hash_file('sha256',$zipPath)))@unlink($zipPath);
            return ['copied'=>count($files),'backup'=>$backup,'migrations'=>$migrations];
        }catch(Throwable $failure){
            if($state!==[]){
                if($migrationStarted){$state['phase']='database_recovery_required';$state['error']='Migration or finalization failed. Restore the database backup before file recovery.';self::json($storage.'/update-state.json',$state);}
                else{
                    try{self::restore($root,$storage,$state);$state['phase']='rolled_back';self::json($storage.'/update-state.json',$state);}
                    catch(Throwable){$state['phase']='file_recovery_required';self::json($storage.'/update-state.json',$state);}
                }
            }
            throw $failure;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    private static function restore(string $root,string $storage,array $state): void
    {
        $name=$state['backup']??'';if(!is_string($name)||!preg_match('/^release-[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/',$name))throw new RuntimeException('Invalid recovery journal.');
        $backup=$storage.'/backups/'.$name;
        $manifest=json_decode((string)file_get_contents($backup.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($manifest['files'] as $file){
            $target=self::destination($root,$file['target']);
            if($file['existed'])self::replace($backup.'/files/'.$file['target'],$target);
            elseif(is_file($target)&&!unlink($target))throw new RuntimeException('Cannot remove newly deployed file.');
        }
        if(is_string($manifest['installed_lock']??null)){
            $temp=$backup.'/previous-installed.lock';file_put_contents($temp,$manifest['installed_lock']);self::replace($temp,$storage.'/installed.lock');
        }
    }

    public static function recover(string $root,string $storage,bool $databaseRestored): void
    {
        $lock=self::lock($storage);
        try{
            $state=self::state($storage);
            if(!self::pending($storage))throw new RuntimeException('There is no incomplete update.');
            if(in_array($state['phase']??'',['migrating','database_recovery_required'],true)&&!$databaseRestored)throw new RuntimeException('Confirm database restoration first.');
            self::restore($root,$storage,$state);$state['phase']='rolled_back';$state['recovered_at']=gmdate('c');self::json($storage.'/update-state.json',$state);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}

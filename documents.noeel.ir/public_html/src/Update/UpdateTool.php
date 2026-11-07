<?php
declare(strict_types=1);
namespace Babanoell\Documents\Update;

use RuntimeException;
use ZipArchive;

final class UpdateTool
{
    private const MAX_ENTRIES = 5000;
    private const MAX_UNCOMPRESSED = 52428800;
    private const PROTECTED = ['config/local.env.php','config/service-auth.local.php','public/install.php','public/install.php.locked'];

    /** @return array{files:array<int,array{entry:string,target:string,size:int}>,protected:int,total:int} */
    public function plan(string $zipPath): array
    {
        if (!is_file($zipPath)) throw new RuntimeException('فایل بسته به‌روزرسانی پیدا نشد.');
        $zip=new ZipArchive(); if($zip->open($zipPath)!==true) throw new RuntimeException('فایل ZIP قابل خواندن نیست.');
        try {
            if($zip->numFiles<1 || $zip->numFiles>self::MAX_ENTRIES) throw new RuntimeException('تعداد فایل‌های بسته معتبر نیست.');
            $files=[];$total=0;$protected=0;$seen=[];
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i); if(!is_array($stat)) throw new RuntimeException('ساختار ZIP قابل بررسی نیست.');
                $entry=(string)$stat['name'];
                if (str_contains($entry, '\\')) throw new RuntimeException('Backslash in archive path.');
                $opsys=0; $attributes=0;
                if ($zip->getExternalAttributesIndex($i,$opsys,$attributes) && (($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('Symlink in archive.');
                if(str_ends_with($entry,'/')) continue;
                if(!str_starts_with($entry,'documents/')) throw new RuntimeException('تمام فایل‌های بسته باید داخل پوشه documents/ باشند.');
                $relative=substr($entry,10);
                if (preg_match('#(^|/)(\\.|\\.\\.)(/|$)|//|[:\\x00-\\x1f]#', $relative)) throw new RuntimeException('Unsafe archive path.');
                $this->assertSafe($relative);
                $size=(int)($stat['size']??0);$total+=$size;if($total>self::MAX_UNCOMPRESSED) throw new RuntimeException('حجم استخراج‌شده بسته بیش از حد مجاز است.');
                $canonical=strtolower($relative);
                if(isset($seen[$canonical])) throw new RuntimeException('مسیر تکراری در بسته وجود دارد.'); $seen[$canonical]=true;
                if($this->protected($relative)){ $protected++; continue; }
                $files[]=['entry'=>$entry,'target'=>$relative,'size'=>$size];
            }
            foreach(['public/index.php','public/ui-asset.php','public/assets/babanoell-ui.css','src/App/Config.php','config/version.php'] as $required) if(!isset($seen[strtolower($required)])) throw new RuntimeException('بسته ناقص است: '.$required);
            PackageIntegrity::verifyZip($zip,'documents',(string)(require dirname(__DIR__,2).'/config/version.php')['version']);
            return ['files'=>$files,'protected'=>$protected,'total'=>$total];
        } finally { $zip->close(); }
    }

    /** @return array{copied:int,backup:string} */
    public function apply(string $zipPath, string $serviceRoot, string $storageDir): array
    {
        $plan=$this->plan($zipPath);
        $work=$storageDir.'/tmp/update-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3));
        $backup=$storageDir.'/backups/update-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3));
        if(!mkdir($work,0770,true)&&!is_dir($work)) throw new RuntimeException('پوشه موقت ساخته نشد.');
        if(!mkdir($backup,0770,true)&&!is_dir($backup)) throw new RuntimeException('پوشه بکاپ ساخته نشد.');
        $zip=new ZipArchive(); if($zip->open($zipPath)!==true) throw new RuntimeException('ZIP باز نشد.');
        try { if(!$zip->extractTo($work)) throw new RuntimeException('استخراج بسته انجام نشد.'); } finally { $zip->close(); }
        $copied=[];
        try {
            foreach($plan['files'] as $file){
                $source=$work.'/'.$file['entry']; $target=$serviceRoot.'/'.$file['target'];
                if(!is_file($source)) throw new RuntimeException('فایل استخراج‌شده پیدا نشد: '.$file['target']);
                if(is_file($target)){
                    $backupFile=$backup.'/'.$file['target'];$dir=dirname($backupFile);if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)) throw new RuntimeException('ساخت مسیر بکاپ شکست خورد.');
                    if(!copy($target,$backupFile)) throw new RuntimeException('بکاپ فایل موجود شکست خورد.');
                }
                $dir=dirname($target);if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)) throw new RuntimeException('ساخت مسیر مقصد شکست خورد.');
                $copied[]=$file['target'];
                if(!copy($source,$target)) throw new RuntimeException('کپی فایل جدید شکست خورد: '.$file['target']);
            }
        } catch (\Throwable $e) {
            foreach(array_reverse($copied) as $relative){$b=$backup.'/'.$relative;$t=$serviceRoot.'/'.$relative;if(is_file($b))@copy($b,$t);else@unlink($t);}
            $this->removeTree($work); throw $e;
        }
        $this->removeTree($work);
        @unlink($zipPath);
        $this->pruneBackups($storageDir.'/backups',3);
        return ['copied'=>count($copied),'backup'=>$backup];
    }

    private function assertSafe(string $relative): void
    {
        if($relative===''||str_starts_with($relative,'/')||str_contains($relative,"\0")||preg_match('#(^|/)\.\.(/|$)#',$relative)) throw new RuntimeException('مسیر ناامن در بسته شناسایی شد.');
        if(str_starts_with($relative,'storage/')||$relative==='storage'||$relative==='config/local.env.php') return;
        $base=basename($relative);if($base==='.htaccess'||$relative==='composer.lock') return;
        $ext=strtolower(pathinfo($relative,PATHINFO_EXTENSION));
        if(!in_array($ext,['php','css','js','png','jpg','jpeg','svg','woff','woff2','ttf','md','txt','html','json'],true)) throw new RuntimeException('نوع فایل مجاز نیست: '.$relative);
    }
    private function protected(string $relative): bool { return str_starts_with($relative,'storage/')||$relative==='storage'||in_array($relative,self::PROTECTED,true); }
    private function removeTree(string $dir): void { if(!is_dir($dir))return;foreach(scandir($dir)?:[] as $i){if($i==='.'||$i==='..')continue;$p=$dir.'/'.$i;is_dir($p)?$this->removeTree($p):@unlink($p);}@rmdir($dir); }
    private function pruneBackups(string $dir,int $keep): void { if(!is_dir($dir))return;$items=[];foreach(scandir($dir)?:[] as $i){if($i==='.'||$i==='..')continue;$p=$dir.'/'.$i;if(is_dir($p))$items[$p]=filemtime($p)?:0;}arsort($items);foreach(array_slice(array_keys($items),$keep) as $p)$this->removeTree($p); }
}

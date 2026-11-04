<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Update;

use RuntimeException;
use ZipArchive;

/** Archive-validation and deployment plan, shared conventions with Noeel updater. */
final class UpdateTool
{
    private const PROTECTED=['config/local.env.php','public/install.php','public/install.php.locked'];
    private const EXTENSIONS=['php','css','js','png','jpg','jpeg','svg','md','txt','html','json','sql'];

    /** @return array{files:array<int,array{entry:string,target:string,size:int}>,protected:int,total:int,version:string} */
    public function plan(string $archivePath):array
    {
        $zip=new ZipArchive();
        if(!is_file($archivePath)||$zip->open($archivePath,ZipArchive::CHECKCONS)!==true)
            throw new RuntimeException('فایل ZIP معتبر نیست.');
        try{
            if($zip->numFiles<1||$zip->numFiles>5000)throw new RuntimeException('تعداد فایل‌های بسته معتبر نیست.');
            $manifestBytes=$zip->getFromName('integrations/release-manifest.json');
            if(!is_string($manifestBytes))throw new RuntimeException('مانیفست بسته پیدا نشد.');
            $manifest=json_decode($manifestBytes,true);
            if(!is_array($manifest)||($manifest['service']??null)!=='integrations'
                ||!is_string($manifest['version']??null)||!preg_match('/^\d+\.\d+\.\d+$/',$manifest['version'])
                ||!is_array($manifest['files']??null))throw new RuntimeException('مانیفست نسخه معتبر نیست.');
            $current=require dirname(__DIR__,2).'/config/version.php';
            if(version_compare($manifest['version'],(string)$current['version'],'<'))throw new RuntimeException('بسته قدیمی‌تر از نسخه نصب‌شده است.');
            $seen=[];$files=[];$total=0;$protected=0;$checked=[];
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i);if(!is_array($stat))throw new RuntimeException('ساختار ZIP قابل بررسی نیست.');
                $entry=str_replace('\\','/',(string)$stat['name']);
                if(str_ends_with($entry,'/'))continue;
                if(!str_starts_with($entry,'integrations/'))throw new RuntimeException('بسته باید در پوشه integrations/ باشد.');
                $relative=substr($entry,13);
                if($relative===''||str_starts_with($relative,'/')||str_contains($relative,'\\')||str_contains($relative,"\0")
                    ||preg_match('~(^|/)\.{1,2}(/|$)|//|:|[\x00-\x1f]~',$relative))throw new RuntimeException('مسیر ناامن در بسته شناسایی شد.');
                if(isset($seen[$relative]))throw new RuntimeException('مسیر تکراری در بسته وجود دارد.');
                $seen[$relative]=true;
                $raw=$zip->getExternalAttributesIndex($i,$opsys,$attrs);
                if($raw&&$opsys===ZipArchive::OPSYS_UNIX&&(((($attrs>>16)&0170000)===0120000)))throw new RuntimeException('لینک نمادین در بسته مجاز نیست.');
                $size=(int)($stat['size']??0);$total+=$size;
                if($total>52428800)throw new RuntimeException('حجم فایل‌های بسته بیش از حد مجاز است.');
                $isProtected=str_starts_with($relative,'storage/')||$relative==='storage'||in_array($relative,self::PROTECTED,true);
                if($isProtected){$protected++;continue;}
                if(basename($relative)!=='.htaccess'&&!in_array(strtolower(pathinfo($relative,PATHINFO_EXTENSION)),self::EXTENSIONS,true))
                    throw new RuntimeException('نوع فایل مجاز نیست: '.$relative);
                if($relative==='release-manifest.json'){$files[]=['entry'=>$entry,'target'=>$relative,'size'=>$size];continue;}
                $expected=$manifest['files'][$relative]??null;
                if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/',$expected))throw new RuntimeException('هش فایل موجود نیست: '.$relative);
                $data=$zip->getFromIndex($i);
                if(!is_string($data)||!hash_equals($expected,hash('sha256',$data)))throw new RuntimeException('صحت فایل تأیید نشد: '.$relative);
                $checked[$relative]=true;
                $files[]=['entry'=>$entry,'target'=>$relative,'size'=>$size];
            }
            foreach(['public/index.php','public/update.php','public/ui-asset.php','public/assets/babanoell-ui.css',
                'src/Update/UpdateTool.php','src/Platform/ReleaseTransaction.php','config/version.php'] as $required)
                if(!isset($checked[$required]))throw new RuntimeException('فایل ضروری بسته وجود ندارد: '.$required);
            foreach($manifest['files'] as $file=>$hash)if(!isset($checked[$file]))throw new RuntimeException('مانیفست دارای فایل حذف‌شده یا محافظت‌شده است: '.$file);
            return ['files'=>$files,'protected'=>$protected,'total'=>$total,'version'=>$manifest['version']];
        }finally{$zip->close();}
    }
}

<?php
declare(strict_types=1);
namespace Babanoell\Auth\Update;

use RuntimeException;
use ZipArchive;

/**
 * Common release manifest checks for Noel services.
 * Manifest hashes detect accidental corruption or changes after packing;
 * they do NOT authenticate a publisher. Distribution remains restricted to admins.
 */
final class PackageIntegrity
{
    private const MAX_MANIFEST_BYTES = 3145728;
    private const MAX_ITEMS = 5000;
    private const MAX_TOTAL_BYTES = 52428800;

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,array{sha256:string,bytes:int}> $entries
     */
    public static function verifySnapshot(array $manifest, string $service, string $currentVersion,
        array $entries, string $versionFromPackage): string
    {
        if (($manifest['service'] ?? null) !== $service || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('شناسه سرویس یا فهرست فایل‌های مانیفست نامعتبر است.');
        }
        $version=$manifest['version']??null;
        if(!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/D',$version) ||
           !preg_match('/^\d+\.\d+\.\d+$/D',$currentVersion)) {
            throw new RuntimeException('شماره نسخه بسته معتبر نیست.');
        }
        if(version_compare($version,$currentVersion,'<')){
            throw new RuntimeException('نصب نسخه قدیمی‌تر بدون عملیات بازیابی مجاز نیست.');
        }
        if(!hash_equals($version,$versionFromPackage)){
            throw new RuntimeException('نسخه فایل config/version.php با مانیفست یکسان نیست.');
        }
        $expected=$manifest['files'];
        if(count($expected)!==count($entries)||count($expected)<2||count($expected)>self::MAX_ITEMS){
            throw new RuntimeException('تعداد فایل‌ها با مانیفست مطابقت ندارد.');
        }
        $total=0;
        foreach($entries as $name=>$actual){
            self::validatePath($name);
            $meta=$expected[$name]??null;
            $hash=is_string($meta)?$meta:(is_array($meta)?($meta['sha256']??null):null);
            $bytes=is_array($meta)?($meta['bytes']??null):null;
            if(!is_string($hash)||!preg_match('/^[0-9a-f]{64}$/D',$hash)||
               !hash_equals($hash,$actual['sha256'])){
                throw new RuntimeException('هش فایل بسته نامعتبر است: '.$name);
            }
            if($bytes!==null&&(!is_int($bytes)||$bytes!==$actual['bytes'])){
                throw new RuntimeException('اندازه فایل بسته تغییر کرده است: '.$name);
            }
            $total+=$actual['bytes'];
            if($total>self::MAX_TOTAL_BYTES){throw new RuntimeException('حجم استخراج‌شده بسته بیش از حد مجاز است.');}
        }
        foreach($expected as $name=>$_)if(!array_key_exists($name,$entries)){
            throw new RuntimeException('فایل درج‌شده در مانیفست داخل بسته نیست.');
        }
        foreach(['public/index.php','public/update.php','config/version.php','src/Update/PackageIntegrity.php'] as $required)
            if(!isset($entries[$required]))throw new RuntimeException('فایل ضروری بسته وجود ندارد: '.$required);
        return $version;
    }

    public static function verifyZip(ZipArchive $zip,string $service,string $currentVersion):string
    {
        if($zip->numFiles<2||$zip->numFiles>self::MAX_ITEMS)throw new RuntimeException('تعداد ورودی‌های ZIP معتبر نیست.');
        $manifestFile=$service.'/release-manifest.json';
        $raw=$zip->getFromName($manifestFile);
        if(!is_string($raw)||strlen($raw)>self::MAX_MANIFEST_BYTES)throw new RuntimeException('مانیفست معتبر پیدا نشد.');
        try{$manifest=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new RuntimeException('فرمت مانیفست نامعتبر است.');}
        if(!is_array($manifest))throw new RuntimeException('مانیفست معتبر نیست.');
        $entries=[];$caseNames=[];$versionCode='';
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);
            if(!is_array($stat))throw new RuntimeException('ورودی ZIP قابل بررسی نیست.');
            $zipName=(string)($stat['name']??'');
            if($zipName===''||str_contains($zipName,'\\')||str_contains($zipName,"\0")||
                !str_starts_with($zipName,$service.'/'))throw new RuntimeException('مسیر ZIP نامعتبر است.');
            $relative=substr($zipName,strlen($service)+1);
            if($relative==='')continue;
            if(str_ends_with($relative,'/')){$relative=rtrim($relative,'/');self::validatePath($relative);continue;}
            self::validatePath($relative);
            $key=strtolower($relative);
            if(isset($caseNames[$key]))throw new RuntimeException('مسیرهای هم‌نام در بسته مجاز نیستند.');
            $caseNames[$key]=true;
            if($zip->getExternalAttributesIndex($i,$opsys,$attributes)&&
                $opsys===ZipArchive::OPSYS_UNIX && (($attributes>>16)&0170000)===0120000)
                throw new RuntimeException('لینک نمادین در بسته مجاز نیست.');
            if($relative==='release-manifest.json')continue;
            $content=$zip->getFromIndex($i);
            if(!is_string($content))throw new RuntimeException('خواندن فایل ZIP ممکن نیست.');
            if($relative==='config/version.php')$versionCode=$content;
            $entries[$relative]=['sha256'=>hash('sha256',$content),'bytes'=>strlen($content)];
        }
        if($versionCode===''||!preg_match('/[\x27\"]version[\x27\"]\s*=>\s*[\x27\"](\d+\.\d+\.\d+)[\x27\"]/',$versionCode,$match)){
            throw new RuntimeException('نسخه بسته از فایل تنظیمات قابل تشخیص نیست.');
        }
        return self::verifySnapshot($manifest,$service,$currentVersion,$entries,$match[1]);
    }

    private static function validatePath(string $relative):void
    {
        if($relative===''||str_starts_with($relative,'/')||str_contains($relative,'\\')||
            preg_match('~(^|/)\.{1,2}(/|$)|//|:|[\x00-\x1f]~',$relative))
            throw new RuntimeException('مسیر ناامن در بسته: '.$relative);
        if($relative==='config/local.env.php'||$relative==='public/install.php'||
            $relative==='public/install.php.locked'||$relative==='storage'||
            str_starts_with($relative,'storage/')||$relative==='vendor'||str_starts_with($relative,'vendor/'))
            throw new RuntimeException('فایل تنظیمات خصوصی یا محافظت‌شده داخل بسته مجاز نیست.');
    }
}

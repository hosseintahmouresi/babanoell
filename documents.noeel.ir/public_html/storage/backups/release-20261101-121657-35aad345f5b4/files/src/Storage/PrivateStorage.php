<?php
declare(strict_types=1);
namespace Babanoell\Documents\Storage;
use Babanoell\Documents\Domain\ValidatedUpload;
use RuntimeException;

final class PrivateStorage
{
    private string $base;
    public function __construct(string$base)
    {
        $this->base=rtrim($base,'/');
        if(!is_dir($this->base)&&!mkdir($this->base,0770,true)&&!is_dir($this->base))throw new RuntimeException('فضای ذخیره‌سازی خصوصی ساخته نشد.');
        self::ensureDenyFile($this->base);
    }
    /** Apache defense-in-depth; Nginx must independently deny the storage path. */
    private static function ensureDenyFile(string $directory): void
    {
        $path=$directory.'/.htaccess';
        if(is_file($path))return;
        $fp=@fopen($path,'xb');
        if($fp===false){
            if(is_file($path))return; // concurrently created by another worker
            throw new RuntimeException('محافظت مسیر خصوصی فایل‌ها امکان‌پذیر نیست.');
        }
        $policy="<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
        try {
            if(fwrite($fp,$policy)!==strlen($policy))throw new RuntimeException('نوشتن سیاست امنیت فایل‌ها ناموفق بود.');
        } finally {fclose($fp);}
        @chmod($path,0640);
    }
    public function store(ValidatedUpload$file):string
    {
        $prefix=gmdate('Y/m');$dir=$this->base.'/'.$prefix;if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('مسیر ذخیره‌سازی فایل ساخته نشد.');self::ensureDenyFile($dir);
        for($i=0;$i<5;$i++){$name=bin2hex(random_bytes(18)).'.'.$file->extension;$key=$prefix.'/'.$name;$target=$this->base.'/'.$key;if(file_exists($target))continue;$in=fopen($file->tmpPath,'rb');$out=fopen($target,'xb');if($in===false||$out===false){if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);throw new RuntimeException('ایجاد فایل خصوصی انجام نشد.');}$ok=stream_copy_to_stream($in,$out);fclose($in);fclose($out);@chmod($target,0640);if($ok===false||!hash_equals($file->sha256,(string)hash_file('sha256',$target))){@unlink($target);throw new RuntimeException('بررسی صحت فایل ذخیره‌شده ناموفق بود.');}return$key;}
        throw new RuntimeException('شناسه امن برای فایل ساخته نشد.');
    }
    public function path(string$key):string
    {
        if($key===''||str_starts_with($key,'/')||str_contains($key,"\0")||preg_match('#(^|/)\.\.(/|$)#',$key))throw new RuntimeException('مسیر فایل معتبر نیست.');
        $candidate=$this->base.'/'.$key;$realBase=realpath($this->base);$real=realpath($candidate);
        if($realBase===false||$real===false||!is_file($real)||!str_starts_with($real,$realBase.DIRECTORY_SEPARATOR))throw new RuntimeException('فایل خصوصی پیدا نشد.');
        return$real;
    }
    public function remove(string$key):void{try{$p=$this->path($key);@unlink($p);}catch(\Throwable){}}
}

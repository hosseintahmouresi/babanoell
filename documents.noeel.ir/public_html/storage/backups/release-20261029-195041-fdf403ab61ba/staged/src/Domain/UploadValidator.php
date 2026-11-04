<?php
declare(strict_types=1);
namespace Babanoell\Documents\Domain;
use InvalidArgumentException;

final class UploadValidator
{
    private const MIME_EXTENSIONS=[
        'image/jpeg'=>['jpg','jpeg','jpe'],
        'image/png'=>['png'],
        'image/webp'=>['webp'],
        'application/pdf'=>['pdf'],
    ];
    public function __construct(private readonly int $maxBytes=10485760){}
    public function validate(array$file,bool$requireUploadedFile=true):ValidatedUpload
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException($this->uploadError($error));
        $tmp=(string)($file['tmp_name']??'');$name=$this->safeName((string)($file['name']??''));$declared=(int)($file['size']??0);
        if($tmp===''||!is_file($tmp)||!is_readable($tmp))throw new InvalidArgumentException('فایل بارگذاری‌شده قابل خواندن نیست.');
        if($requireUploadedFile&&!is_uploaded_file($tmp))throw new InvalidArgumentException('منبع فایل بارگذاری‌شده معتبر نیست.');
        $size=filesize($tmp);if($size===false)$size=$declared;$size=(int)$size;
        if($size<=0)throw new InvalidArgumentException('فایل خالی قابل ثبت نیست.');
        if($size>$this->maxBytes)throw new InvalidArgumentException('حجم هر فایل نباید بیشتر از '.self::humanBytes($this->maxBytes).' باشد.');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if($ext===''||!in_array($ext,array_merge(...array_values(self::MIME_EXTENSIONS)),true))throw new InvalidArgumentException('پسوند فایل مجاز نیست. فقط JPG، PNG، WEBP و PDF پذیرفته می‌شود.');
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);
        if(!isset(self::MIME_EXTENSIONS[$mime])||!in_array($ext,self::MIME_EXTENSIONS[$mime],true))throw new InvalidArgumentException('نوع واقعی فایل با پسوند آن هم‌خوانی ندارد یا مجاز نیست.');
        if(str_starts_with($mime,'image/')){$image=@getimagesize($tmp);if(!is_array($image)||($image['mime']??'')!==$mime)throw new InvalidArgumentException('ساختار تصویر معتبر نیست.');}
        if($mime==='application/pdf'){$h=fopen($tmp,'rb');$magic=$h!==false?fread($h,5):false;if(is_resource($h))fclose($h);if($magic!=='%PDF-')throw new InvalidArgumentException('ساختار فایل PDF معتبر نیست.');}
        $hash=hash_file('sha256',$tmp);if(!is_string($hash)||strlen($hash)!==64)throw new InvalidArgumentException('محاسبه صحت فایل انجام نشد.');
        $canonical=self::MIME_EXTENSIONS[$mime][0];
        return new ValidatedUpload($tmp,$name,$mime,$canonical,$size,$hash);
    }
    public static function humanBytes(int$bytes):string{return number_format($bytes/1048576,0,'.','').' MB';}
    private function safeName(string$name):string
    {
        $name=basename(str_replace('\\','/',$name));$name=preg_replace('/[\x00-\x1F\x7F]+/u','',$name)??'';$name=trim($name);
        if($name===''||$name==='.'||$name==='..')throw new InvalidArgumentException('نام فایل معتبر نیست.');
        if(function_exists('mb_substr'))$name=mb_substr($name,0,220,'UTF-8');else$name=substr($name,0,220);
        return$name;
    }
    private function uploadError(int$error):string{return match($error){UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'حجم فایل از محدودیت سرور بیشتر است.',UPLOAD_ERR_PARTIAL=>'بارگذاری فایل ناقص انجام شده است.',UPLOAD_ERR_NO_FILE=>'فایلی انتخاب نشده است.',UPLOAD_ERR_NO_TMP_DIR=>'پوشه موقت بارگذاری روی سرور در دسترس نیست.',UPLOAD_ERR_CANT_WRITE=>'نوشتن فایل روی دیسک انجام نشد.',UPLOAD_ERR_EXTENSION=>'بارگذاری فایل توسط تنظیمات سرور متوقف شد.',default=>'بارگذاری فایل کامل نشد.'};}
}

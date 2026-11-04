<?php
declare(strict_types=1);
$root=dirname(__DIR__);require $root.'/bootstrap.php';
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Database\Migrator;
header('Cache-Control: no-store');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
$storage=$root.'/storage';if(!is_file($storage.'/installed.lock')){http_response_code(403);exit('Not installed');}
if(strlen(Config::get('INTEGRATIONS_INSTALL_KEY'))<32){http_response_code(503);exit('Installer key not configured');}
if(Config::production()&&empty($_SERVER['HTTPS'])&&($_SERVER['HTTP_X_FORWARDED_PROTO']??'')!=='https'){http_response_code(403);exit('HTTPS required');}
if(!is_dir($storage.'/tmp'))mkdir($storage.'/tmp',0700,true);session_save_path($storage.'/tmp');session_name('noeel_integrations_update');session_set_cookie_params(['httponly'=>true,'secure'=>Config::production(),'samesite'=>'Strict']);session_start();
if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));$message='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 if(!hash_equals((string)$_SESSION['csrf'],(string)($_POST['csrf']??'')))$message='توکن فرم معتبر نیست.';
 elseif(!hash_equals(Config::get('INTEGRATIONS_INSTALL_KEY'),(string)($_POST['install_key']??'')))$message='کلید به‌روزرسانی نامعتبر است.';
 elseif(!isset($_FILES['bundle'])||($_FILES['bundle']['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file((string)$_FILES['bundle']['tmp_name']))$message='فایل ZIP معتبر نیست.';
 else {try {
  $zip=new ZipArchive();if($zip->open((string)$_FILES['bundle']['tmp_name'])!==true)throw new RuntimeException('zip_open_failed');
  $files=[];$total=0;$seen=[];
  for($i=0;$i<$zip->numFiles;$i++){$st=$zip->statIndex($i);$entry=(string)($st['name']??'');if(str_ends_with($entry,'/'))continue;
   if(!str_starts_with($entry,'integrations/'))throw new RuntimeException('invalid_root');$rel=substr($entry,13);
   if(str_starts_with($rel,'storage/')||$rel==='config/local.env.php')continue;
   if($rel===''||str_starts_with($rel,'/')||preg_match('~(^|/)(\.\.?|config/local\.env\.php|storage)(/|$)~',$rel)||str_contains($rel,'\\')||isset($seen[$rel])||!preg_match('~\.(php|sql|htaccess|md|txt|css|js|json)$~i',$rel))throw new RuntimeException('unsafe_entry');
   $seen[$rel]=true;$total+=(int)($st['size']??0);if($total>15728640||count($seen)>500)throw new RuntimeException('oversized_bundle');$files[$rel]=$entry;
  }
  foreach(['public/index.php','bootstrap.php','config/version.php']as$must)if(!isset($files[$must]))throw new RuntimeException('incomplete_bundle');
  $backup=$storage.'/backups/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5));mkdir($backup,0700,true);
  foreach($files as$relative=>$entry){$data=$zip->getFromName($entry);if(!is_string($data))throw new RuntimeException('read_failed');$target=$root.'/'.$relative;$dir=dirname($target);if(!is_dir($dir))mkdir($dir,0755,true);
   if(is_file($target)){$old=$backup.'/'.$relative;if(!is_dir(dirname($old)))mkdir(dirname($old),0700,true);if(!copy($target,$old))throw new RuntimeException('backup_failed');}
   $tmp=$target.'.'.bin2hex(random_bytes(5)).'.tmp';if(file_put_contents($tmp,$data,LOCK_EX)===false||!rename($tmp,$target))throw new RuntimeException('write_failed');
  }$zip->close();$applied=Migrator::up(Config::db(),$root);$message='نسخه جدید اعمال شد. Migrationهای جدید: '.count($applied).' — قبل از نصب بکاپ مستقل دیتابیس ضروری است.';
  }catch(Throwable $e){error_log('[integrations] update_failed '.get_class($e));$message='به‌روزرسانی کامل نشد. برای بازیابی به بکاپ رجوع کنید.';}
 }
}
?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>آپدیت Integrations</title><style>body{background:#f5f7f7;font:16px Tahoma;display:grid;place-items:center;min-height:100vh}main{background:white;max-width:650px;padding:30px;border-radius:20px}input,button{display:block;margin:12px 0;padding:13px;width:100%;box-sizing:border-box}button{background:#174b38;color:white;border:0;cursor:pointer}</style><main><h2>به‌روزرسانی Integrations</h2><p>قبل از به‌روزرسانی، بکاپ کامل دیتابیس و فایل‌ها تهیه کنید.</p><p><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8')?>"><input type="password" name="install_key" placeholder="کلید نصب و به‌روزرسانی" required><input type="file" name="bundle" accept=".zip" required><button>اعمال آپدیت</button></form></main></html>

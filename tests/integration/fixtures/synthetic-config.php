<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
$changes=json_decode((string)file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
foreach($changes as $service=>$values){if(!in_array($service,['integrations','finance'],true)||!is_array($values))throw new RuntimeException('Synthetic service invalid');
 $path=$fixture['root'].'/'.$service.'/config/local.env.php';$config=require $path;
 foreach($values as $key=>$value){if(!str_starts_with($key,strtoupper($service).'_GREENPAY_')&&!in_array($key,['FINANCE_INTEGRATIONS_BASE_URL'],true))throw new RuntimeException('Synthetic key not allowed');$config[$key]=$value;}
 file_put_contents($path,"<?php return ".var_export($config,true).";\n");chmod($path,0600);
}
echo 'Synthetic disposable settings updated';

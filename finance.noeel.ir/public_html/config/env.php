<?php
declare(strict_types=1);
function babanoell_finance_load_env():void{static $loaded=false;if($loaded)return;$loaded=true;$f=__DIR__.'/local.env.php';if(!is_file($f))return;$v=require$f;if(!is_array($v))return;foreach($v as$k=>$val){if(!is_string($k))continue;if(is_bool($val))$val=$val?'1':'0';if(is_scalar($val)&&getenv($k)===false)putenv($k.'='.(string)$val);}
    require_once dirname(__DIR__).'/src/Platform/InstallationRepair.php';
    $effective=getenv('NOEEL_SERVICE_SHARED_SECRET');$effective=$effective===false?'':(string)$effective;
    $repair=\Babanoell\Finance\Platform\InstallationRepair::resolve(dirname(__DIR__),'finance',$v,$effective);
    $GLOBALS['babanoell_finance_service_auth_repair_status']=$repair['status'];
    if($repair['secret']!==''&&trim($effective)==='')putenv('NOEEL_SERVICE_SHARED_SECRET='.$repair['secret']);
}
function babanoell_finance_env(string$key,?string$default=null):?string{$v=getenv($key);return$v===false?$default:$v;}

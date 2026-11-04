<?php
declare(strict_types=1);

/** Local cPanel configuration is authoritative. Runtime getenv is only fallback. */
function babanoell_documents_load_env(): void
{
    static $loaded=false;if($loaded)return;$loaded=true;$GLOBALS['babanoell_documents_local_env']=[];$local=__DIR__.'/local.env.php';if(!is_file($local))return;$values=require$local;if(!is_array($values))return;foreach($values as$key=>$value){if(!is_string($key)||is_array($value)||is_object($value))continue;$GLOBALS['babanoell_documents_local_env'][$key]=(string)$value;}
    require_once dirname(__DIR__).'/src/Platform/InstallationRepair.php';
    $effective=(string)($GLOBALS['babanoell_documents_local_env']['NOEEL_SERVICE_SHARED_SECRET']??'');
    if($effective===''){$runtime=getenv('NOEEL_SERVICE_SHARED_SECRET');if($runtime!==false)$effective=(string)$runtime;}
    $repair=\Babanoell\Documents\Platform\InstallationRepair::resolve(dirname(__DIR__),'documents',$values,$effective);
    $GLOBALS['babanoell_documents_service_auth_repair_status']=$repair['status'];
    if($repair['secret']!==''&&trim($effective)==='')$GLOBALS['babanoell_documents_local_env']['NOEEL_SERVICE_SHARED_SECRET']=$repair['secret'];
}
function babanoell_documents_env(string $key,?string $default=null):?string
{
    babanoell_documents_load_env();$local=$GLOBALS['babanoell_documents_local_env'][$key]??null;if($local!==null&&$local!=='')return(string)$local;$runtime=getenv($key);if($runtime!==false&&$runtime!=='')return(string)$runtime;return$default;
}

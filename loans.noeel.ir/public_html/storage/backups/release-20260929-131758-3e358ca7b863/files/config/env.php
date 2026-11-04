<?php
declare(strict_types=1);

/** Local cPanel configuration is authoritative. Runtime getenv is only fallback. */
function babanoell_loans_load_env(): void
{
    static $loaded=false;if($loaded)return;$loaded=true;$GLOBALS['babanoell_loans_local_env']=[];$local=__DIR__.'/local.env.php';if(!is_file($local))return;$values=require$local;if(!is_array($values))return;foreach($values as$key=>$value){if(!is_string($key)||is_array($value)||is_object($value))continue;$GLOBALS['babanoell_loans_local_env'][$key]=(string)$value;}
}
function babanoell_loans_env(string $key,?string $default=null):?string
{
    babanoell_loans_load_env();$local=$GLOBALS['babanoell_loans_local_env'][$key]??null;if($local!==null&&$local!=='')return(string)$local;$runtime=getenv($key);if($runtime!==false&&$runtime!=='')return(string)$runtime;return$default;
}

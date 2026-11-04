<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/App/Config.php';
use Babanoell\Auth\App\Config;
$pepper=str_repeat('a',40);
$base=['auth_environment'=>'production','login_enabled'=>true,'session_issuing_enabled'=>true,'require_token_pepper'=>true,'token_pepper'=>$pepper];
$off=new Config('identity','production',$base);
if($off->httpLoginActivationAllowed())throw new RuntimeException('production unexpectedly active without opt-in');
$on=new Config('identity','production',$base+['allow_http_login_in_production'=>true]);
if(!$on->httpLoginActivationAllowed())throw new RuntimeException('explicit production opt-in failed');
foreach([
 ['pepper_missing', ['token_pepper'=>'']],
 ['pepper_placeholder', ['token_pepper'=>'CHANGE_ME_LONG_RANDOM_SECRET']],
 ['pepper_short', ['token_pepper'=>'abc']],
 ['sessions_disabled', ['session_issuing_enabled'=>false]],
 ['login_disabled', ['login_enabled'=>false]],
 ['wrong_auth_environment', ['auth_environment'=>'staging']],
 ['pepper_not_required', ['require_token_pepper'=>false]],
] as [$name,$overrides]){
 $cfg=new Config('identity','production',array_replace($base,['allow_http_login_in_production'=>true],$overrides));
 if($cfg->httpLoginActivationAllowed())throw new RuntimeException('unsafe production activation: '.$name);
 echo "[PASS] $name\n";
}
echo "Production auth gate: 9 checks passed\n";

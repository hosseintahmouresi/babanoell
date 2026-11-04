<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/bootstrap.php';
use Babanoell\Integrations\Http\IdentityAdminClient;
$cases=[
 ['501 disabled',501,'not_implemented','', 'Production'],
 ['401 wrong password',401,'invalid_credentials','','رمز عبور'],
 ['401 service signature',401,'service_signature_invalid','','کلید مشترک'],
 ['clock skew',401,'service_signature_expired','','ساعت سرورها'],
 ['dns',0,'','dns_failed','DNS'],
 ['tls',0,'','tls_failed','SSL'],
 ['timeout',0,'','timeout','زمان پاسخ'],
 ['bad json',200,'','invalid_json','JSON'],
 ['404 route',404,'','','404'],
 ['gateway',502,'','','502'],
];
foreach($cases as [$label,$status,$code,$transport,$part]){
 $got=IdentityAdminClient::explanation(['status'=>$status,'code'=>$code,'transport'=>$transport]);
 if(!str_contains($got,$part))throw new RuntimeException($label.' => '.$got);
 echo "[PASS] $label\n";
}
$success=IdentityAdminClient::result(200,'{"data":{"session":{"session_credential":"abc"}}}');
if(!$success['ok']||($success['data']['data']['session']['session_credential']??'')!=='abc')throw new RuntimeException('session response parse');
$disabled=IdentityAdminClient::result(501,'{"error":{"code":"not_implemented","message":"Not active"}}');
if($disabled['ok']||$disabled['code']!=='not_implemented')throw new RuntimeException('disabled login classification');
echo "Login diagnostics tests: 12 passed\n";

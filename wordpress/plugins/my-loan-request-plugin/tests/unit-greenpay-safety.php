<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
define('MLR_VERSION', '8.4.6.43-test');

$GLOBALS['gp_options'] = [];
$GLOBALS['gp_last_request'] = null;
$GLOBALS['gp_settings_errors'] = [];

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code='', $message='', $data=null){$this->code=$code;$this->message=$message;$this->data=$data;}
    public function get_error_code(){return $this->code;}
    public function get_error_message(){return $this->message;}
    public function get_error_data(){return $this->data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action(...$args){} function add_filter(...$args){} function register_setting(...$args){}
function apply_filters($tag,$value,...$args){return $value;} function has_filter($tag){return false;}
function get_option($k,$d=false){return array_key_exists($k,$GLOBALS['gp_options'])?$GLOBALS['gp_options'][$k]:$d;}
function update_option($k,$v,$autoload=null){$GLOBALS['gp_options'][$k]=$v;return true;}
function absint($v){return abs((int)$v);} function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_textarea_field($v){return trim(strip_tags((string)$v));} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function esc_url_raw($v){return filter_var((string)$v,FILTER_SANITIZE_URL);} function wp_json_encode($v,$f=0){return json_encode($v,$f);}
function wp_salt($scheme=''){return 'greenpay-unit-test-salt';} function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function wp_http_validate_url($url){return filter_var($url,FILTER_VALIDATE_URL)!==false;} function wp_generate_password($len=12,$special=true,$extra=false){return str_repeat('x',$len);}
function home_url($path=''){return 'https://example.test'.$path;} function add_settings_error($setting,$code,$message,$type='error'){$GLOBALS['gp_settings_errors'][]=[$setting,$code,$message,$type];}
function current_time($type){return $type==='timestamp'?time():date($type==='mysql'?'Y-m-d H:i:s':$type);}
function get_current_user_id(){return 1;} function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;}
function wp_next_scheduled(...$args){return false;} function wp_schedule_single_event(...$args){return true;} function wp_clear_scheduled_hook(...$args){return true;}
function set_transient(...$args){return true;} function get_transient(...$args){return false;} function delete_transient(...$args){return true;}
function wp_remote_request($url,$args){$GLOBALS['gp_last_request']=[$url,$args]; return ['code'=>200,'body'=>'{"Status":"success"}','headers'=>['content-type'=>'application/json']];}
function wp_remote_retrieve_response_code($raw){return (int)($raw['code']??0);} function wp_remote_retrieve_body($raw){return (string)($raw['body']??'');}
function wp_remote_retrieve_header($raw,$name){return (string)($raw['headers'][$name]??'');}

require dirname(__DIR__) . '/includes/greenpay/greenpay-service.php';

$passed=0; $failed=0;
function t($name,$cond){global $passed,$failed; if($cond){echo "PASS: $name\n";$passed++;}else{echo "FAIL: $name\n";$failed++;}}

$base = mlr_greenpay_default_settings();
$base['username']='user'; $base['password']='secret-pass'; $base['api_key']='secret-key';
$base['terminal_number']='123'; $base['merchant_id']='456'; $base['from_pocket_number']='1111222233334444';
$base['to_pocket_number']='5555666677778888'; $base['test_otp']='654321'; $base['test_profile_1023']=0;
$GLOBALS['gp_options']['mlr_greenpay_settings']=$base;

t('amount is integer Rial', mlr_greenpay_normalize_amount('۱٬۲۳۴٬۵۶۷')===1234567);
t('valid pockets accepted', mlr_greenpay_validate_pockets('1111222233334444','5555666677778888','test')===true);
t('test pocket rejected in live', is_wp_error(mlr_greenpay_validate_pockets('1262010000002551','','live')));
t('invalid destination pocket rejected', is_wp_error(mlr_greenpay_validate_pockets('1111222233334444','123','test')));

$snapshot = mlr_greenpay_build_config_snapshot($base,$base['from_pocket_number'],$base['to_pocket_number']);
t('snapshot is valid JSON', is_array(json_decode($snapshot,true)));
t('snapshot does not persist password', strpos($snapshot,'secret-pass')===false);
t('snapshot does not persist API key', strpos($snapshot,'secret-key')===false);
t('snapshot does not persist test OTP', strpos($snapshot,'654321')===false);
$snapshot_data = json_decode($snapshot, true);
t('snapshot includes signed provider clock fallback', array_key_exists('provider_time_shift_seconds', $snapshot_data) && (int)$snapshot_data['provider_time_shift_seconds'] === 30);
t('provider body SendTime wins over local clock shift', mlr_greenpay_extract_send_time(['SendTime'=>'12:34:56'], 0, 30) === '12:34:56');
t('provider HTTP Date is converted to Tehran time', mlr_greenpay_extract_send_time(['provider_date_header'=>'Tue, 13 Oct 2026 11:00:00 GMT'], 0, 30) === '14:30:00');

$tx=(object)[
    'config_snapshot'=>$snapshot,
    'environment'=>'test',
    'from_pocket'=>$base['from_pocket_number'],
    'to_pocket'=>$base['to_pocket_number'],
];
$same=mlr_greenpay_settings_for_transaction($tx);
t('unchanged credentials accept transaction snapshot', is_array($same) && $same['to_pocket_number']==='5555666677778888');
$GLOBALS['gp_options']['mlr_greenpay_settings']['password']='rotated-secret';
$drift=mlr_greenpay_settings_for_transaction($tx);
t('credential drift is fail-closed', is_wp_error($drift) && $drift->get_error_code()==='greenpay_credentials_changed');
$GLOBALS['gp_options']['mlr_greenpay_settings']=$base;

$raw='Token=very-secret FromPocket=1111222233334444';
$redacted=mlr_greenpay_redact_string($raw);
t('raw response token is redacted', strpos($redacted,'very-secret')===false);
t('raw response pocket is masked', strpos($redacted,'1111222233334444')===false && strpos($redacted,'1111')!==false);


// Supplied sandbox profile 1023 must be deterministic and must never send OTP SMS.
$profile = mlr_greenpay_default_settings();
$profile['api_key'] = 'sheet-secret-api-key';
$profile['password'] = 'must-not-be-used-in-test';
$GLOBALS['gp_options']['mlr_greenpay_settings'] = $profile;
$profile_effective = mlr_greenpay_apply_test_profile_1023($profile);
t('1023 profile forces wallet test password 1', ($profile_effective['password'] ?? '') === '1');
t('1023 profile forces supplied FromPocket', ($profile_effective['from_pocket_number'] ?? '') === '1262010000002551');
t('1023 profile forces empty ToPocket', ($profile_effective['to_pocket_number'] ?? 'x') === '');
t('1023 profile forces OTP 123456', ($profile_effective['test_otp'] ?? '') === '123456');
t('1023 profile matches vendor reference sandbox endpoint', strpos((string)($profile_effective['auth_base_url'] ?? ''), 'wallet.bki.ir/walletbackend/api/sandbox') !== false);

$profile_api = new MLR_GreenPay_API($profile);
$GLOBALS['gp_last_request'] = null;
$token_result = $profile_api->get_token();
$token_body = json_decode($GLOBALS['gp_last_request'][1]['body'] ?? '', true);
t('1023 token request uses password 1', ($token_body['Password'] ?? '') === '1');
t('1023 token request keeps API key from protected setting', ($token_body['ApiKey'] ?? '') === 'sheet-secret-api-key');

$GLOBALS['gp_last_request'] = ['sentinel', []];
$otp_bypass = $profile_api->send_otp('bearer-token', '9999888877776666', 1000);
t('1023 sendPurchaseOTP is fully bypassed', is_array($otp_bypass) && !empty($otp_bypass['OtpBypassed']) && $GLOBALS['gp_last_request'][0] === 'sentinel');

$profile_api->confirm_debit('bearer-token','9999888877776666','5555666677778888','999999',250000,'MLR-GP-1023','12:00:00');
$profile_transfer = json_decode($GLOBALS['gp_last_request'][1]['body'] ?? '', true);
t('1023 transfer forces FromPocket', ($profile_transfer['FromPocketNumber'] ?? '') === '1262010000002551');
t('1023 transfer forces empty ToPocket', array_key_exists('ToPocketNumber',$profile_transfer) && $profile_transfer['ToPocketNumber'] === '');
t('1023 transfer forces OTP 123456', ($profile_transfer['OTP'] ?? '') === '123456');

$GLOBALS['gp_options']['mlr_greenpay_settings']=$base;

$api=new MLR_GreenPay_API($base);
$result=$api->confirm_debit('bearer-token','1111222233334444','5555666677778888','123456',250000,'MLR-GP-1','12:00:00');
$body=json_decode($GLOBALS['gp_last_request'][1]['body']??'',true);
t('transfer uses transaction ToPocketNumber', ($body['ToPocketNumber']??'')==='5555666677778888');
t('transfer amount stays Rial without x10', ($body['Amount']??'')==='250000');
t('transfer keeps stable reference', ($body['RefNumber']??'')==='MLR-GP-1');
t('mock transfer response parsed', is_array($result) && ($result['http_code']??0)===200);



t('fresh transfer SendTime helper exists', function_exists('mlr_greenpay_fresh_transfer_send_time'));
$GLOBALS['gp_options']['mlr_greenpay_settings']['provider_time_shift_seconds'] = 30;
$fresh = mlr_greenpay_fresh_transfer_send_time([], 0);
t('fresh transfer SendTime is HH:MM:SS', (bool) preg_match('/^\\d{2}:\\d{2}:\\d{2}$/', $fresh));
t('time-window detector recognizes provider Persian deadline error', mlr_greenpay_is_time_window_rejection(['http_code'=>400,'message'=>'تراکنش با مبدا 15:21:35 در مهلت زمانی مقرر تا 15:22:06 با شکست مواجه شد']) === true);
t('time-window detector does not retry transport/5xx', mlr_greenpay_is_time_window_rejection(['http_code'=>500,'message'=>'deadline']) === false);

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed?1:0);

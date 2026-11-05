<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
$GLOBALS['mlr_test_transients'] = [];
$GLOBALS['mlr_test_user_id'] = 42;
$GLOBALS['mlr_test_schedule'] = [];
$GLOBALS['mlr_test_deleted_uploads'] = [];
class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code='', $message='', $data=null){$this->code=$code;$this->message=$message;$this->data=$data;}
    public function get_error_message(){return $this->message;}
    public function get_error_data(){return $this->data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));} function wp_unslash($v){return $v;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);} function maybe_unserialize($v){if(!is_string($v))return $v; $u=@unserialize($v); return $u===false && $v!=='b:0;' ? $v : $u;}
function is_user_logged_in(){return $GLOBALS['mlr_test_user_id']>0;} function get_current_user_id(){return $GLOBALS['mlr_test_user_id'];}
function wp_salt($scheme=''){return 'unit-test-salt';} function esc_url_raw($v){return filter_var((string)$v,FILTER_SANITIZE_URL);}
function get_transient($k){$x=$GLOBALS['mlr_test_transients'][$k]??null; if(!$x)return false; if($x['exp'] && $x['exp']<time()){unset($GLOBALS['mlr_test_transients'][$k]);return false;} return $x['v'];}
function set_transient($k,$v,$ttl=0){$GLOBALS['mlr_test_transients'][$k]=['v'=>$v,'exp'=>$ttl?time()+$ttl:0]; return true;}
function delete_transient($k){unset($GLOBALS['mlr_test_transients'][$k]);return true;}
function add_action(...$args){} function wp_next_scheduled($hook,$args=[]){return false;} function wp_schedule_single_event($ts,$hook,$args=[]){$GLOBALS['mlr_test_schedule'][]=[$ts,$hook,$args];return true;}
function wp_schedule_event($ts,$recurrence,$hook,$args=[]){return true;} function wp_clear_scheduled_hook($hook,$args=[]){return true;} function wp_unschedule_event($ts,$hook,$args=[]){return true;}
function trailingslashit($v){return rtrim($v,'/\\').'/';} function get_temp_dir(){return sys_get_temp_dir().'/';}
function wp_generate_password($len=12){return str_repeat('x',$len);} function mlr_validate_local_document_upload_url($url,&$error=''){return str_starts_with($url,'https://example.test/uploads/');}
function mlr_delete_local_document_uploads($urls){$GLOBALS['mlr_test_deleted_uploads']=array_merge($GLOBALS['mlr_test_deleted_uploads'],(array)$urls);return true;}
function _get_cron_array(){return [];}

class MLR_Test_WPDB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $referenced = false;
    public $engine = 'myisam';
    public $queries = [];
    public function esc_like($value){return addcslashes((string)$value, '_%\\');}
    public function prepare($query, ...$args){
        if (count($args) === 1 && is_array($args[0])) {$args = $args[0];}
        foreach ($args as $arg) {
            $replacement = is_numeric($arg) ? (string)$arg : "'" . str_replace("'", "''", (string)$arg) . "'";
            $query = preg_replace('/%[sdf]/', $replacement, $query, 1);
        }
        return $query;
    }
    public function get_row($query){
        $this->last_error = '';
        if (stripos($query, 'SHOW TABLE STATUS LIKE') !== false) {
            return (object)['Engine' => $this->engine];
        }
        return null;
    }
    public function query($query){
        $this->queries[] = $query;
        $this->last_error = '';
        return 0;
    }
    public function get_var($query){
        $this->last_error = '';
        if (stripos($query, 'SHOW TABLES LIKE') !== false) {
            if (stripos($query, 'cooperation_requests') !== false) return $this->prefix . 'cooperation_requests';
            return $this->prefix . 'loan_requests';
        }
        if (stripos($query, 'SELECT id FROM') !== false) return $this->referenced ? 123 : null;
        return null;
    }
    public function get_col($query, $column_offset = 0){
        $this->last_error = '';
        if (stripos($query, 'cooperation_requests') !== false) return ['id','data','files'];
        return ['id','required_documents','form_data','bank_validation_file','signature_image','contract_file','check_images','pending_docs_attachments','transfer_receipt_files','guarantor_info','guarantor_info_2'];
    }
}

require dirname(__DIR__) . '/includes/hardening.php';
require dirname(__DIR__) . '/includes/loan-calculations.php';

$passed=0; $failed=0;
function t($name,$cond){global $passed,$failed; if($cond){echo "PASS: $name\n";$passed++;}else{echo "FAIL: $name\n";$failed++;}}

t('normalize Persian digits', mlr_normalize_digits('۰۹۱۲۳۴۵۶۷۸۹', true)==='09123456789');
t('parse grouped decimal', abs(mlr_parse_decimal_number('۱٬۲۳۴٬۵۶۷.۵')-1234567.5)<0.001);

// Generate a syntactically valid national code from a non-repeating 9-digit stem.
$stem='123456789'; $sum=0; for($i=0;$i<9;$i++)$sum+=(int)$stem[$i]*(10-$i); $rem=$sum%11; $check=$rem<2?$rem:11-$rem; $valid_nc=$stem.$check;
t('national code valid', mlr_validate_iranian_national_code($valid_nc));
t('repeated national code rejected', !mlr_validate_iranian_national_code('1111111111'));

$order=mlr_build_clean_order_items(json_encode([
 ['name'=>'A','qty'=>2,'price'=>'1,250','total_price'=>1],
 ['name'=>'B','quantity'=>1,'unit_price'=>500,'total_price'=>999999],
]));
t('order parser succeeds', !is_wp_error($order));
t('order total recalculated server-side', abs($order['total']-3000)<0.001);
t('tampered line total ignored', abs($order['items'][0]['total_price']-2500)<0.001);

$plan=(object)['id'=>7,'is_active'=>1,'type'=>'leasing','settings'=>serialize(['min_amount'=>1000,'max_amount'=>10000,'active_installments'=>'12,18,24','min_interval'=>1,'max_interval'=>3])];
$v=mlr_validate_plan_request_values($plan,5000,12,3);
t('valid leasing plan accepted', is_array($v) && $v['interval']===3);
t('disallowed installment rejected', is_wp_error(mlr_validate_plan_request_values($plan,5000,10,2)));
t('divisible leasing period accepted', is_wp_error(mlr_validate_plan_request_values($plan,5000,18,2))===false); // 18/2 is valid
// Dedicated fractional case.
$plan2=(object)['id'=>8,'is_active'=>1,'type'=>'leasing','settings'=>serialize(['active_installments'=>'10','min_interval'=>1,'max_interval'=>3])];
t('fractional leasing period rejected', is_wp_error(mlr_validate_plan_request_values($plan2,5000,10,3)));
t('amount outside range rejected', is_wp_error(mlr_validate_plan_request_values($plan,99999,12,3)));

$r1=mlr_rate_limit_check('unit',2,60,['x']); $r2=mlr_rate_limit_check('unit',2,60,['x']); $r3=mlr_rate_limit_check('unit',2,60,['x']);
t('rate limiter first allowed', $r1===true); t('rate limiter second allowed', $r2===true); t('rate limiter blocks excess', is_wp_error($r3));

$url='https://example.test/uploads/a.pdf'; $tok=mlr_issue_initial_upload_token($url,'doc_a','loan_request',600);
t('initial upload token issued', is_string($tok) && strlen($tok)>=32);
t('initial upload proof valid', mlr_verify_initial_upload_token($tok,$url,'doc_a','loan_request'));
t('initial upload proof field-bound', !mlr_verify_initial_upload_token($tok,$url,'doc_b','loan_request'));
$parsed=mlr_parse_initial_staged_uploads(json_encode([['url'=>$url,'token'=>$tok]]),'doc_a','loan_request');
t('staged upload parses with proof', !is_wp_error($parsed) && $parsed['urls']==[$url]);
mlr_consume_initial_upload_tokens([$tok]);
t('consumed upload token invalidated', !mlr_verify_initial_upload_token($tok,$url,'doc_a','loan_request'));

// Regression: cleanup must still know which local file to remove after the proof transient expires.
$cleanup_url='https://example.test/uploads/orphan.pdf';
$cleanup_tok=mlr_issue_initial_upload_token($cleanup_url,'doc_cleanup','loan_request',600);
$scheduled_cleanup=end($GLOBALS['mlr_test_schedule']);
t('cleanup cron persists URL in arguments', is_array($scheduled_cleanup) && ($scheduled_cleanup[1] ?? '')==='mlr_cleanup_initial_upload' && count($scheduled_cleanup[2] ?? [])===4 && ($scheduled_cleanup[2][1] ?? '')===$cleanup_url);
delete_transient(mlr_initial_upload_transient_key($cleanup_tok)); // simulate proof expiry before delayed WP-Cron executes
$GLOBALS['wpdb'] = new MLR_Test_WPDB();
$GLOBALS['wpdb']->referenced = false;
mlr_cleanup_initial_upload_callback($cleanup_tok,$cleanup_url,'doc_cleanup','loan_request');
t('expired unreferenced staged upload still cleaned', in_array($cleanup_url,$GLOBALS['mlr_test_deleted_uploads'],true));

$protected_url='https://example.test/uploads/already-saved.pdf';
$protected_tok=mlr_issue_initial_upload_token($protected_url,'doc_saved','loan_request',600);
delete_transient(mlr_initial_upload_transient_key($protected_tok));
$GLOBALS['wpdb']->referenced = true;
$deleted_before=count($GLOBALS['mlr_test_deleted_uploads']);
mlr_cleanup_initial_upload_callback($protected_tok,$protected_url,'doc_saved','loan_request');
t('cleanup preserves file already referenced by DB', count($GLOBALS['mlr_test_deleted_uploads'])===$deleted_before && !in_array($protected_url,$GLOBALS['mlr_test_deleted_uploads'],true));
$GLOBALS['wpdb']->referenced = false;

$hash='flow-hash-1'; mlr_credit_store_flow($hash,$valid_nc,'09121234567');
t('credit flow identity-bound', is_array(mlr_credit_get_flow($hash)));
t('credit OTP mark succeeds', mlr_credit_mark_otp_verified($hash));
t('credit report proof stored after OTP', mlr_credit_store_report_proof($hash,['score'=>750]));
$proof=mlr_credit_get_report_proof($valid_nc,'09121234567');
t('credit proof retrieved', is_array($proof) && strpos($proof['report_json'],'750')!==false);
t('credit proof mobile-bound', mlr_credit_get_report_proof($valid_nc,'09120000000')===null);
$report_for_token=['score'=>750,'nested'=>['z'=>2,'a'=>1]];
$signed_credit_token=mlr_credit_issue_submission_token($hash,$report_for_token);
$signed_report_json=json_encode(['nested'=>['a'=>1,'z'=>2],'score'=>750], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$validated_signed_proof=mlr_credit_validate_submission_token($signed_credit_token,$signed_report_json,$valid_nc,'09121234567');
t('signed credit submission token validates canonical report', is_array($validated_signed_proof) && strpos($validated_signed_proof['report_json'],'750')!==false);
t('signed credit submission token is mobile-bound', mlr_credit_validate_submission_token($signed_credit_token,$signed_report_json,$valid_nc,'09120000000')===null);
t('signed credit submission token rejects tampered report', mlr_credit_validate_submission_token($signed_credit_token,json_encode(['score'=>751]),$valid_nc,'09121234567')===null);
mlr_credit_consume_report_proof($valid_nc,'09121234567');
t('credit proof consumed', mlr_credit_get_report_proof($valid_nc,'09121234567')===null);

// Financial write guard: real transactions on InnoDB, short WRITE locks on legacy MyISAM.
$GLOBALS['wpdb'] = new MLR_Test_WPDB();
$GLOBALS['wpdb']->engine = 'innodb';
$guard = mlr_begin_financial_write_guard(['wp_loan_requests']);
t('financial guard uses transaction on InnoDB', $guard === 'transaction' && in_array('START TRANSACTION', $GLOBALS['wpdb']->queries, true));
t('financial guard commits transaction', mlr_end_financial_write_guard($guard, true) === true && in_array('COMMIT', $GLOBALS['wpdb']->queries, true));
$GLOBALS['wpdb'] = new MLR_Test_WPDB();
$GLOBALS['wpdb']->engine = 'myisam';
$guard = mlr_begin_financial_write_guard(['wp_loan_requests','wp_mlr_settlement_cycles']);
$lock_sql = end($GLOBALS['wpdb']->queries);
t('financial guard falls back to table lock on MyISAM', $guard === 'table_lock' && is_string($lock_sql) && str_starts_with($lock_sql, 'LOCK TABLES '));
t('legacy financial guard locks every requested table', str_contains($lock_sql, '`wp_loan_requests` WRITE') && str_contains($lock_sql, '`wp_mlr_settlement_cycles` WRITE'));
t('legacy financial guard unlocks cleanly', mlr_end_financial_write_guard($guard, true) === true && in_array('UNLOCK TABLES', $GLOBALS['wpdb']->queries, true));
$GLOBALS['wpdb'] = new MLR_Test_WPDB();
t('table-lock helper rejects unsafe table identifiers', mlr_lock_tables_for_write(['wp_loan_requests;DROP']) === false);

// Financial formula regression/property matrix. The production formula file is intentionally unchanged;
// these checks guard against NaN/INF and structural drift on valid bounded inputs.
$market_settings=[
 'fixed_monthly_rate'=>2.5,'prepayment_percent'=>10,'supplier_costs_percent'=>3,
 'intermediary_deductions_percent'=>1,'babanovel_deductions_percent'=>1,'insurance_percent'=>0.5,
 'guarantee_check_percent'=>120,'acceptor_commission_percent'=>2,'additional_deductions_percent'=>0.5,
 'acceptor_deductions_amount'=>10000,'vat_percent'=>10,
];
$market_matrix_ok=true;
foreach([1000000,50000000,999999999] as $amount){
 foreach([1,6,12,24,36] as $count){
  $r=calculateMarketLoan($amount,$count,$market_settings,1.75);
  if(!$r || ($r['loanAmount']??0)!==round($amount) || ($r['installmentCount']??0)!==$count){$market_matrix_ok=false;continue;}
  foreach($r as $k=>$v){if(is_numeric($v) && !is_finite((float)$v)){$market_matrix_ok=false;}}
 }
}
t('market calculation matrix finite/stable', $market_matrix_ok);
t('market invalid zero input rejected', calculateMarketLoan(0,12,$market_settings)===[]);

$leasing_settings=[
 'interest_rate'=>24,'effective_interest_rate'=>28,'credit_share_percent'=>80,
 'supplier_costs_percent'=>3,'intermediary_deductions_percent'=>1,'babanovel_deductions_percent'=>1,
 'insurance_percent'=>0.5,'other_deductions_amount'=>10000,'guarantee_check_percent'=>120,
 'acceptor_commission_percent'=>2,'additional_deductions_percent'=>0.5,'acceptor_deductions_amount'=>10000,'vat_percent'=>10,
];
$leasing_matrix_ok=true;
foreach([1000000,50000000,999999999] as $amount){
 foreach([[12,1],[12,2],[12,3],[24,3],[36,3]] as [$count,$interval]){
  $r=calculateLeasingLoan($amount,$count,$interval,$leasing_settings,1.75);
  if(!$r || ($r['installmentCount']??0)!==$count || abs(($r['numberOfInstallments']??0)-($count/$interval))>0.000001){$leasing_matrix_ok=false;continue;}
  foreach($r as $k=>$v){if(is_numeric($v) && !is_finite((float)$v)){$leasing_matrix_ok=false;}}
 }
}
t('leasing calculation matrix finite/stable', $leasing_matrix_ok);
t('leasing invalid zero interval rejected', calculateLeasingLoan(1000000,12,0,$leasing_settings)===[]);

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);

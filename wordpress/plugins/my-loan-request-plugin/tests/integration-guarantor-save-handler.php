<?php
// Integration-style execution of the real guarantor save handler with a stub WP DB/runtime.
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code='', $message='', $data=null){$this->code=$code;$this->message=$message;$this->data=$data;}
    public function get_error_code(){return $this->code;}
    public function get_error_message(){return $this->message;}
    public function get_error_data(){return $this->data;}
}
class MLR_Json_Response extends Exception {
    public $success; public $data;
    public function __construct($success,$data){parent::__construct('json');$this->success=$success;$this->data=$data;}
}
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action($a,$b,$c=10,$d=1){}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));}
function sanitize_text_field($v){return is_scalar($v)?trim((string)$v):'';}
function wp_unslash($v){return is_string($v)?stripslashes($v):$v;}
function esc_url_raw($v){return trim((string)$v);}
function apply_filters($tag,$value){return $value;}
function trailingslashit($v){return rtrim((string)$v,'/\\').'/';}
function untrailingslashit($v){return rtrim((string)$v,'/\\');}
function is_ssl(){return true;}
function set_url_scheme($url,$scheme=null){return $scheme?preg_replace('#^https?://#i',$scheme.'://',(string)$url):$url;}
function wp_salt($scheme='auth'){return 'mlr-integration-salt-'.$scheme;}
function is_user_logged_in(){return true;}
$GLOBALS['mlr_test_user_id']=7;
function get_current_user_id(){return (int)$GLOBALS['mlr_test_user_id'];}
function wp_get_current_user(){return (object)['roles'=>['subscriber']];}
function current_user_can($cap){return false;}
function mlr_verify_any_nonce($name){return true;}
function mlr_can_access_loan_request($req,$uid=0){return true;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function wp_send_json_error($data=[]){throw new MLR_Json_Response(false,$data);}
function wp_send_json_success($data=[]){throw new MLR_Json_Response(true,$data);}
function mlr_handle_wp_upload($file){return new WP_Error('unexpected_legacy','legacy upload should not run in staged scenario');}
$GLOBALS['mlr_deleted_urls']=[];
function mlr_delete_local_document_uploads($urls){$GLOBALS['mlr_deleted_urls']=array_merge($GLOBALS['mlr_deleted_urls'],(array)$urls);}
$GLOBALS['mlr_cancelled_cleanup']=[];
function mlr_cancel_request_staged_upload_cleanup($url,$request_id,$field_key,$context='guarantor'){$GLOBALS['mlr_cancelled_cleanup'][]=[$url,$request_id,$field_key,$context];}
function mlr_log($message,$context=[]){ }

$base=sys_get_temp_dir().'/mlr-guarantor-integration-'.getmypid(); @mkdir($base,0777,true);
$GLOBALS['mlr_test_upload_dir']=$base;
function wp_upload_dir($time=null,$create_dir=true){return ['basedir'=>$GLOBALS['mlr_test_upload_dir'],'baseurl'=>'https://example.test/uploads','error'=>false];}
function wp_check_filetype_and_ext($path,$filename,$mimes=null){$ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));$map=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'];return isset($map[$ext])?['ext'=>$ext,'type'=>$map[$ext]]:['ext'=>'','type'=>''];}

class MLR_Test_WPDB {
    public $prefix='wp_'; public $base_prefix='wp_'; public $last_error=''; public $stored=''; public $fail_update=false; public $updates=0;
    public function prepare($query,...$args){
        if (count($args)===1 && is_array($args[0])) $args=$args[0];
        foreach($args as $arg){
            $posD=strpos($query,'%d'); $posS=strpos($query,'%s');
            if ($posD!==false && ($posS===false || $posD<$posS)) $query=preg_replace('/%d/',(string)(int)$arg,$query,1);
            elseif($posS!==false) $query=preg_replace('/%s/',"'".str_replace("'","''",(string)$arg)."'",$query,1);
        }
        return $query;
    }
    public function get_row($query){return (object)['id'=>123,'user_id'=>7,'applicant_user_id'=>0,'acceptor_user_id'=>0,'request_origin'=>'acceptor_portal','assigned_bank_user_id'=>0];}
    public function get_var($query){
        if (stripos($query,'SHOW COLUMNS')!==false) return strpos($query,'guarantor_info_2')!==false?'guarantor_info_2':'guarantor_info';
        if (stripos($query,'SELECT `guarantor_info_2`')!==false || stripos($query,'SELECT `guarantor_info`')!==false) return $this->stored;
        return null;
    }
    public function update($table,$data,$where,$formats=null,$whereformats=null){
        $this->updates++;
        if ($this->fail_update){$this->last_error='forced db error'; return false;}
        $this->stored=(string)reset($data); return 1;
    }
}
$GLOBALS['wpdb']=new MLR_Test_WPDB();

require dirname(__DIR__).'/includes/document-upload-service.php';
require dirname(__DIR__).'/includes/ajax/save-guarantor-info.php';

$passed=0;$failed=0;
function t($name,$cond){global $passed,$failed;if($cond){$passed++;echo "PASS: $name\n";}else{$failed++;echo "FAIL: $name\n";}}
function run_save($post,$files=[]){
    $_GET=[]; $_REQUEST=$post; $_POST=$post; $_FILES=$files; $_SERVER=[];
    try { mlr_save_guarantor_info_callback(); }
    catch (MLR_Json_Response $r){ return $r; }
    return null;
}

$pdfPath=$base.'/statement.pdf'; file_put_contents($pdfPath,"%PDF-1.4\nreal handler integration\n");
$pdfUrl='https://example.test/uploads/statement.pdf';
$key=mlr_guarantor_upload_field_key(2,'account_statement');
$token=mlr_create_document_upload_token($pdfUrl,123,$key,7,3600);
$staged=json_encode(['account_statement'=>['url'=>$pdfUrl,'token'=>$token]]);

// 1) Success: actual handler merges text + staged file, updates DB, clears cleanup.
$GLOBALS['wpdb']->stored=json_encode(['national_card'=>'https://example.test/uploads/existing.pdf']);
$GLOBALS['wpdb']->fail_update=false; $GLOBALS['wpdb']->updates=0; $GLOBALS['mlr_deleted_urls']=[]; $GLOBALS['mlr_cancelled_cleanup']=[];
$r=run_save(['request_id'=>123,'nonce'=>'ok','guarantor_index'=>2,'full_name'=>'ضامن تست','mobile'=>'09120000000','guarantor_staged_uploads'=>$staged]);
$stored=json_decode($GLOBALS['wpdb']->stored,true);
t('real save handler returns success for staged guarantor file', $r instanceof MLR_Json_Response && $r->success===true);
t('real save handler updates database once', $GLOBALS['wpdb']->updates===1);
t('real save handler persists staged URL', ($stored['account_statement']??'')===$pdfUrl);
t('real save handler persists text fields', ($stored['full_name']??'')==='ضامن تست' && ($stored['mobile']??'')==='09120000000');
t('real save handler preserves previous guarantor data', ($stored['national_card']??'')==='https://example.test/uploads/existing.pdf');
t('real save handler cancels orphan cleanup after DB success', count($GLOBALS['mlr_cancelled_cleanup'])===1 && $GLOBALS['mlr_cancelled_cleanup'][0][2]===$key);
t('successful staged save does not delete uploaded file', count($GLOBALS['mlr_deleted_urls'])===0);

// 2) Tampered token: rejected before DB write.
$GLOBALS['wpdb']->stored=''; $GLOBALS['wpdb']->updates=0; $GLOBALS['mlr_cancelled_cleanup']=[];
$r=run_save(['request_id'=>123,'nonce'=>'ok','guarantor_index'=>2,'guarantor_staged_uploads'=>json_encode(['account_statement'=>['url'=>$pdfUrl,'token'=>$token.'bad']])]);
t('tampered token is rejected by real save handler', $r instanceof MLR_Json_Response && $r->success===false);
t('tampered token causes no DB update', $GLOBALS['wpdb']->updates===0);

// 3) DB failure: staged file is immediately cleaned.
$GLOBALS['wpdb']->stored=''; $GLOBALS['wpdb']->fail_update=true; $GLOBALS['wpdb']->updates=0; $GLOBALS['mlr_deleted_urls']=[]; $GLOBALS['mlr_cancelled_cleanup']=[];
$r=run_save(['request_id'=>123,'nonce'=>'ok','guarantor_index'=>2,'guarantor_staged_uploads'=>$staged]);
t('database failure returns controlled error', $r instanceof MLR_Json_Response && $r->success===false);
t('database failure attempts exactly one update', $GLOBALS['wpdb']->updates===1);
t('database failure removes newly staged file', in_array($pdfUrl,$GLOBALS['mlr_deleted_urls'],true));
t('database failure does not cancel orphan cleanup as success', count($GLOBALS['mlr_cancelled_cleanup'])===0);
$GLOBALS['wpdb']->fail_update=false;

// 4) Text-only path works (important for no-file changes and cached clients).
$GLOBALS['wpdb']->stored=json_encode(['check'=>'https://example.test/uploads/old-check.pdf']); $GLOBALS['wpdb']->updates=0; $GLOBALS['mlr_deleted_urls']=[];
$r=run_save(['request_id'=>123,'nonce'=>'ok','guarantor_index'=>1,'job'=>'کارمند','guarantor_staged_uploads'=>'']);
$stored=json_decode($GLOBALS['wpdb']->stored,true);
t('text-only guarantor save still succeeds', $r instanceof MLR_Json_Response && $r->success===true);
t('text-only guarantor save preserves existing document references', ($stored['check']??'')==='https://example.test/uploads/old-check.pdf');
t('text-only guarantor save stores changed text', ($stored['job']??'')==='کارمند');

@unlink($pdfPath); @rmdir($base);
echo "RESULT: $passed passed, $failed failed\n";
exit($failed?1:0);

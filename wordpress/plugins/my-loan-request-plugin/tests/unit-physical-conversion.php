<?php
/* Standalone regression tests for per-request digital -> physical conversion. */
define('ABSPATH', '/');
$OPTIONS=[]; $TRANSIENTS=[]; $EVENTS=[];
function add_action(...$a) {}
function absint($v){return abs((int)$v);}
function current_user_can($cap){return $cap === 'manage_options';}
function get_current_user_id(){return 77;}
function sanitize_textarea_field($v){return trim((string)$v);}
function sanitize_text_field($v){return trim((string)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function wp_check_invalid_utf8($s,$x=true){return $s;}
function wp_strip_all_tags($t){return strip_tags($t);}
function wp_kses_post($s){return $s;}
function current_time($a){return '2026-10-13 18:00:00';}
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($k,$d=null){global $OPTIONS; return $OPTIONS[$k]??$d;}
function add_option($k,$v,$unused='',$autoload=false){global $OPTIONS;if(array_key_exists($k,$OPTIONS))return false;$OPTIONS[$k]=$v;return true;}
function update_option($k,$v,$autoload=false){global $OPTIONS;$OPTIONS[$k]=$v;return true;}
function delete_option($k){global $OPTIONS;$ok=array_key_exists($k,$OPTIONS);unset($OPTIONS[$k]);return $ok;}
function delete_transient($k){global $TRANSIENTS;unset($TRANSIENTS[$k]);return true;}
function mlr_add_request_event(...$args){global $EVENTS;$EVENTS[]=$args;}
function mlr_loan_contract_mode_option_name($id){return 'mlr_lc_mode_'.absint($id);}
function mlr_loan_contract_signature_option_name($id){return 'mlr_lc_sig_'.absint($id);}
function mlr_loan_contract_otp_transient_key($id){return 'otp_'.absint($id);}
function mlr_loan_contract_otp_lock_name($id){return 'otp_lock_'.absint($id);}
function mlr_get_loan_contract_mode_for_request($request,$freeze=true){return get_option(mlr_loan_contract_mode_option_name($request->id),'digital');}
function mlr_get_loan_contract_signature($id){$x=get_option(mlr_loan_contract_signature_option_name($id));return is_array($x)&&!empty($x['signed_at'])&&!empty($x['contract_digest'])?$x:null;}
function mlr_get_loan_contract_payload($request,$when='',$strict=false){
  $o=$GLOBALS['mlr_lc_reissue_preview'][$request->id]??null;
  $t=$o['contract_text']??'<p>default</p>';
  return ['contract_html'=>str_replace('[full_name]',$request->full_name,$t),'letterhead_url'=>'','footer_url'=>'','repeat_footer_html'=>''];
}
class WP_Error{function __construct(private $code,private $message){}function get_error_message(){return $this->message;}function get_error_code(){return $this->code;}}
class FakeDB {
 public $prefix='wp_'; public $rows=[];
 function prepare($sql,...$a){return [$sql,$a];}
 function get_row($prepared){$id=$prepared[1][0]??0;return $this->rows[$id]??null;}
}
$wpdb=new FakeDB();
require dirname(__DIR__).'/includes/loan-contract-reissue.php';
function ck($ok,$why){if(!$ok){fwrite(STDERR,"FAIL: $why\n");exit(1);}echo "PASS: $why\n";}
$wpdb->rows[500]=(object)['id'=>500,'installment_plan_id'=>8,'full_name'=>'امیر رضایی','status'=>'granted','contract_file'=>''];
$wpdb->rows[501]=(object)['id'=>501,'installment_plan_id'=>8,'full_name'=>'مهدی','status'=>'granted','contract_file'=>''];
$wpdb->rows[502]=(object)['id'=>502,'installment_plan_id'=>8,'full_name'=>'سارا','status'=>'pending','contract_file'=>''];
$OPTIONS['mlr_lc_mode_500']='digital';
$OPTIONS['mlr_lc_mode_501']='digital';
$OPTIONS['mlr_lc_mode_502']='digital';
$old=['signed_at'=>'2026-10-10 12:00:00','contract_digest'=>hash('sha256','previous immutable document'),'contract_html'=>'<p>old signature</p>','contract_revision'=>1];
$OPTIONS['mlr_lc_sig_500']=$old;
ck(is_wp_error(mlr_lc_physical_build($wpdb->rows[500],'<p>[full_name]</p>')), 'physical generator rejects digital without explicit preview override');
$preview=mlr_lc_physical_build($wpdb->rows[500],'<p>[full_name]</p>',true);
ck(is_array($preview)&&str_contains($preview['contract_html'],'امیر رضایی'), 'preview from digital resolves real case data before converting');
ck(get_option('mlr_lc_mode_500')==='digital', 'preview never changes signed request mode');
$result=mlr_lc_convert_digital_to_physical(500,'<section>اصلاحیه [full_name]</section>','اصلاح نام شرکت');
ck(!is_wp_error($result) && $result['revision']===2, 'signed digital contract converted to revision 2');
ck(get_option('mlr_lc_mode_500')==='physical', 'only selected case signing mode is physical');
ck(get_option('mlr_lc_mode_501')==='digital', 'other cases retain digital mode');
ck(!mlr_get_loan_contract_signature(500), 'superseded OTP proof is no longer active');
$archive=get_option('mlr_lc_archive_500_1');
ck(is_array($archive)&&($archive['proof']??[])===$old, 'prior signed evidence remains in immutable archive');
$draft=mlr_lc_get_physical_draft(500);
ck(is_array($draft)&&str_contains($draft['contract_text'],'[full_name]')&&$draft['state']==='awaiting_physical_signature', 'new dynamic physical draft is saved for case only, unsigned');
ck(mlr_lc_reissue_get_default_text($wpdb->rows[500])===$draft['contract_text'], 'physical editor loads the correction from this case');
ck(count($EVENTS)===1&&$EVENTS[0][1]==='loan_contract_converted_to_physical', 'conversion is recorded in audit event');
ck(is_wp_error(mlr_lc_convert_digital_to_physical(500,'<p>x</p>','again')), 'repeated conversion is blocked');
$OPTIONS['mlr_lc_reissue_501']=['request_id'=>501,'state'=>'pending','contract_text'=>'<p>pending</p>'];
$res=mlr_lc_convert_digital_to_physical(501,'<p>new</p>','اصلاح متن');
ck(is_wp_error($res)&&$res->get_error_code()==='mlr_lc_pending_digital', 'existing pending digital reissue blocks automatic conversion');
ck(get_option('mlr_lc_mode_501')==='digital','pending error is non-destructive');
ck(is_wp_error(mlr_lc_convert_digital_to_physical(502,'<p>[unknown_var]</p>','اصلاح فرم')), 'unresolved tags stop conversion');
ck(get_option('mlr_lc_mode_502')==='digital', 'invalid template leaves current mode unchanged');
$res=mlr_lc_convert_digital_to_physical(502,'<p>نسخه جدید [full_name]</p>','اصلاح شرط');
ck(!is_wp_error($res) && $res['archived_signed']===false,'unsigned digital can transition without inventing an archived signature');
ck(get_option('mlr_lc_mode_502')==='physical','unsigned mode conversion is stored');
echo "ALL PHYSICAL CONVERSION UNIT TESTS PASSED\n";

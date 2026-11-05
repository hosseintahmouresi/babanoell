<?php
// Focused unit tests for guarantor staged-upload binding and validation.
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code = '', $message = '', $data = null) { $this->code=$code; $this->message=$message; $this->data=$data; }
    public function get_error_code(){ return $this->code; }
    public function get_error_message(){ return $this->message; }
    public function get_error_data(){ return $this->data; }
}
function is_wp_error($v){ return $v instanceof WP_Error; }
function absint($v){ return abs((int)$v); }
function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','', (string)$v)); }
function sanitize_text_field($v){ return is_scalar($v) ? trim((string)$v) : ''; }
function wp_unslash($v){ return is_string($v) ? stripslashes($v) : $v; }
function esc_url_raw($v){ return trim((string)$v); }
function apply_filters($tag, $value){ return $value; }
function trailingslashit($v){ return rtrim((string)$v, '/\\') . '/'; }
function untrailingslashit($v){ return rtrim((string)$v, '/\\'); }
function is_ssl(){ return true; }
function set_url_scheme($url, $scheme = null){
    if (!$scheme) return $url;
    return preg_replace('#^https?://#i', $scheme . '://', (string)$url);
}
function wp_salt($scheme='auth'){ return 'mlr-unit-test-fixed-salt-' . $scheme; }
$GLOBALS['mlr_test_user_id'] = 7;
function get_current_user_id(){ return (int)$GLOBALS['mlr_test_user_id']; }

$base = sys_get_temp_dir() . '/mlr-guarantor-unit-' . getmypid();
@mkdir($base, 0777, true);
$GLOBALS['mlr_test_upload_dir'] = $base;
function wp_upload_dir($time = null, $create_dir = true){
    return [
        'basedir' => $GLOBALS['mlr_test_upload_dir'],
        'baseurl' => 'https://example.test/uploads',
        'error' => false,
    ];
}
function wp_check_filetype_and_ext($path, $filename, $mimes = null){
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'];
    if (!isset($map[$ext])) return ['ext'=>'','type'=>''];
    return ['ext'=>$ext,'type'=>$map[$ext]];
}

require dirname(__DIR__) . '/includes/document-upload-service.php';

$passed=0; $failed=0;
function t($name, $cond){
    global $passed,$failed;
    if ($cond) { $passed++; echo "PASS: $name\n"; }
    else { $failed++; echo "FAIL: $name\n"; }
}

$pdfPath = $base . '/guarantor-proof.pdf';
file_put_contents($pdfPath, "%PDF-1.4\nunit-test\n");
$pdfUrl = 'https://example.test/uploads/guarantor-proof.pdf';
$pngPath = $base . '/guarantor-card.png';
file_put_contents($pngPath, "\x89PNG\r\n\x1a\nunit-test");
$pngUrl = 'https://example.test/uploads/guarantor-card.png';

$key1 = mlr_guarantor_upload_field_key(1, 'account_statement');
$key2 = mlr_guarantor_upload_field_key(2, 'account_statement');
t('guarantor field key 1 is canonical', $key1 === 'guarantor_1_account_statement');
t('guarantor field key 2 is canonical', $key2 === 'guarantor_2_account_statement');
t('unknown guarantor field key rejected', !mlr_is_valid_guarantor_upload_field_key('guarantor_1_shell'));
t('valid guarantor key accepted', mlr_is_valid_guarantor_upload_field_key($key1));

$token = mlr_create_document_upload_token($pdfUrl, 123, $key1, 7, 3600);
$payload = json_encode(['account_statement'=>['url'=>$pdfUrl,'token'=>$token]]);
$res = mlr_parse_guarantor_staged_uploads($payload, 123, 1);
t('valid request-bound guarantor upload parses', is_array($res) && ($res['account_statement'] ?? '') === $pdfUrl);

$resWrongIndex = mlr_parse_guarantor_staged_uploads($payload, 123, 2);
t('token is bound to guarantor index', is_wp_error($resWrongIndex) && $resWrongIndex->get_error_code()==='mlr_invalid_guarantor_upload_token');

$resWrongRequest = mlr_parse_guarantor_staged_uploads($payload, 124, 1);
t('token is bound to request id', is_wp_error($resWrongRequest) && $resWrongRequest->get_error_code()==='mlr_invalid_guarantor_upload_token');

$GLOBALS['mlr_test_user_id'] = 8;
$resWrongUser = mlr_parse_guarantor_staged_uploads($payload, 123, 1);
t('token is bound to current user', is_wp_error($resWrongUser) && $resWrongUser->get_error_code()==='mlr_invalid_guarantor_upload_token');
$GLOBALS['mlr_test_user_id'] = 7;

$badField = json_encode(['evil'=>['url'=>$pdfUrl,'token'=>$token]]);
$resBadField = mlr_parse_guarantor_staged_uploads($badField, 123, 1);
t('unknown staged field is rejected', is_wp_error($resBadField) && $resBadField->get_error_code()==='mlr_invalid_guarantor_upload_field');

$resMalformed = mlr_parse_guarantor_staged_uploads('{not-json', 123, 1);
t('malformed staged JSON rejected', is_wp_error($resMalformed) && $resMalformed->get_error_code()==='mlr_invalid_guarantor_uploads');

$externalUrl='https://evil.example/guarantor-proof.pdf';
$externalToken=mlr_create_document_upload_token($externalUrl,123,$key1,7,3600);
$resExternal=mlr_parse_guarantor_staged_uploads(json_encode(['account_statement'=>['url'=>$externalUrl,'token'=>$externalToken]]),123,1);
t('external URL rejected even with valid HMAC token', is_wp_error($resExternal) && $resExternal->get_error_code()==='mlr_invalid_guarantor_upload_file');

$cardKey=mlr_guarantor_upload_field_key(1,'national_card');
$cardToken=mlr_create_document_upload_token($pngUrl,123,$cardKey,7,3600);
$resMulti=mlr_parse_guarantor_staged_uploads(json_encode([
  'account_statement'=>['url'=>$pdfUrl,'token'=>$token],
  'national_card'=>['url'=>$pngUrl,'token'=>$cardToken],
]),123,1);
t('multiple independently-bound guarantor files parse', is_array($resMulti) && count($resMulti)===2 && $resMulti['national_card']===$pngUrl);

$wrongFieldToken=mlr_create_document_upload_token($pngUrl,123,$key1,7,3600);
$resWrongFieldToken=mlr_parse_guarantor_staged_uploads(json_encode(['national_card'=>['url'=>$pngUrl,'token'=>$wrongFieldToken]]),123,1);
t('token cannot be reused for another guarantor document field', is_wp_error($resWrongFieldToken) && $resWrongFieldToken->get_error_code()==='mlr_invalid_guarantor_upload_token');

@unlink($pdfPath); @unlink($pngPath); @rmdir($base);
echo "RESULT: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);

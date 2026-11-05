<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('ABSPATH', __DIR__ . '/');

class WP_Error {
    private $code; private $message;
    public function __construct($code='', $message=''){ $this->code=$code; $this->message=$message; }
    public function get_error_message(){ return $this->message; }
}
function is_wp_error($v){ return $v instanceof WP_Error; }
function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)); }
function maybe_unserialize($v){ if(!is_string($v)) return $v; $u=@unserialize($v); return ($u===false && $v!=='b:0;') ? $v : $u; }
function mlr_get_all_document_options(){
    return [
        'national_card_front'=>'کارت ملی', 'payslip'=>'فیش حقوقی', 'employment_certificate'=>'گواهی اشتغال',
        'business_license'=>'جواز کسب', 'account_statement'=>'گردش حساب', 'additional_documents'=>'تکمیلی'
    ];
}
require dirname(__DIR__) . '/includes/occupation-documents.php';

$passed=0; $failed=0;
function t($name,$cond){ global $passed,$failed; if($cond){ echo "PASS: $name\n"; $passed++; } else { echo "FAIL: $name\n"; $failed++; } }

$legacy=['national_card_front','payslip'];
t('legacy mode keeps default documents', mlr_get_plan_required_document_keys($legacy, [], 'freelancer') === ['national_card_front','payslip']);

$settings=[
    'occupation_documents_enabled'=>1,
    'required_documents_by_occupation'=>[
        'official_employee'=>['national_card_front','payslip','employment_certificate','INVALID'],
        'freelancer'=>['national_card_front','business_license','account_statement'],
        'other'=>['additional_documents'],
    ],
];
$official=mlr_get_plan_required_document_keys($legacy,$settings,'official_employee');
$free=mlr_get_plan_required_document_keys($legacy,$settings,'freelancer');
t('official employee gets only own documents', $official === ['national_card_front','payslip','employment_certificate']);
t('freelancer gets only own documents', $free === ['national_card_front','business_license','account_statement']);
t('different occupations stay isolated', !in_array('business_license',$official,true) && !in_array('payslip',$free,true));
t('unknown document keys are removed', !in_array('invalid',$official,true));
t('empty occupation preview falls back to defaults', mlr_get_plan_required_document_keys($legacy,$settings,'') === $legacy);
t('valid configured occupation accepted', mlr_validate_plan_occupation($settings,'official_employee') === true);
t('invalid occupation rejected', is_wp_error(mlr_validate_plan_occupation($settings,'hacker_job')));
t('missing occupation config rejected', is_wp_error(mlr_validate_plan_occupation($settings,'worker')));

$emptySettings=$settings;
$emptySettings['required_documents_by_occupation']['worker']=[];
t('empty occupation document list rejected', is_wp_error(mlr_validate_plan_occupation($emptySettings,'worker')));

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);

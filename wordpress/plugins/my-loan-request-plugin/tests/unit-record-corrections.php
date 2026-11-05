<?php
define('ABSPATH', '/tmp/');
function add_action($action,$callback) {}
function mlr_get_all_cooperation_fields() {
    return [
        'company_name'=>['label'=>'نام شرکت','form'=>'legal','type'=>'text'],
        'applicant_id_number'=>['label'=>'شناسه ملی','form'=>'both','type'=>'text'],
        'store_name'=>['label'=>'نام فروشگاه','form'=>'individual','type'=>'text'],
        'company_docs'=>['label'=>'اسناد شرکت','form'=>'legal','type'=>'file'],
    ];
}
require __DIR__.'/../includes/admin-pages/record-corrections.php';
$legal=mlr_rc_editable_fields('cooperation',['request_type'=>'legal','data'=>'{"company_name":"شرکت الف", "applicant_id_number":"12345678901"}']);
$loan=mlr_rc_editable_fields('loan');
$checks=[
    'company_name present' => isset($legal['company_name']),
    'corporate identifier editable' => isset($legal['applicant_id_number']),
    'other cooperation type hidden' => !isset($legal['store_name']),
    'file keys blocked' => !isset($legal['company_docs']),
    'loan profile fields available' => isset($loan['full_name']) && isset($loan['mobile_number']),
    'loan sensitive fields immutable' => !isset($loan['settlement_status']) && !isset($loan['bank_settlement_status']) && !isset($loan['contract_file']) && !isset($loan['loan_amount'])
];
$failed=0;
foreach($checks as $label=>$result){echo ($result?'PASS: ':'FAIL: ').$label."\n"; if(!$result)$failed++;}
exit($failed>0?1:0);

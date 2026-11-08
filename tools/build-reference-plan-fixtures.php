<?php
declare(strict_types=1);
// Extract only the audited pure visibility functions; never bootstrap WordPress or provider configuration.
if(PHP_SAPI!=='cli')exit(1);
$source=$argv[1]??'';$output=$argv[2]??'';
if(!is_file($source)||$output==='')throw new RuntimeException('Usage: php build-reference-plan-fixtures.php functions.php output.json');
function maybe_unserialize($v){if(is_string($v)&&preg_match('/^[abisdN]:/',$v)){return unserialize($v,['allowed_classes'=>false,'max_depth'=>16]);}return $v;}
function sanitize_key($v){return is_scalar($v)?preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)):'';}
$wanted=['mlr_get_installment_plan_visibility_contexts','mlr_get_plan_settings_array','mlr_get_plan_visibility_contexts'];$tokens=token_get_all((string)file_get_contents($source));$found=[];
for($i=0;$i<count($tokens);$i++){
 $token=$tokens[$i];if(!is_array($token)||$token[0]!==T_FUNCTION)continue;
 $name=null;for($j=$i+1;$j<count($tokens);$j++){if(is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)continue;if(is_array($tokens[$j])&&$tokens[$j][0]===T_STRING)$name=$tokens[$j][1];break;}
 if(!in_array($name,$wanted,true))continue;$code='';$depth=0;$started=false;
 for($j=$i;$j<count($tokens);$j++){$t=$tokens[$j];$code.=is_array($t)?$t[1]:$t;if($t==='{'){$depth++;$started=true;}elseif($t==='}'&&--$depth===0&&$started)break;}
 $found[$name]=hash('sha256',$code);eval($code);$i=$j;
}
if(count($found)!==count($wanted))throw new RuntimeException('Reference pure functions missing');
$cases=[[],['applicant_visible'=>false],['applicant_visible'=>true],['applicant_visible'=>'1'],['applicant_visible'=>'0'],['visibility_contexts'=>[]],['visibility_contexts'=>null],['visibility_contexts'=>''],['visibility_contexts'=>['unknown']],['visibility_contexts'=>['mlr_loan_request_form']],['visibility_contexts'=>['mlr_applicant_loan_request_form']],['visibility_contexts'=>['mlr_loan_calculator']],['visibility_contexts'=>['mlr_loan_calculator','mlr_loan_request_form','mlr_loan_request_form']],['visibility_contexts'=>'["mlr_applicant_loan_request_form","mlr_loan_calculator"]'],['visibility_contexts'=>'mlr_loan_request_form,mlr_loan_calculator'],['visibility_contexts'=>[],'applicant_visible'=>true]];
$map=['mlr_loan_request_form'=>'merchant_request','mlr_applicant_loan_request_form'=>'applicant_request','mlr_loan_calculator'=>'calculator'];$out=[];
foreach($cases as $input)$out[]=['settings'=>$input,'expected'=>array_map(static fn(string $key):string=>$map[$key],mlr_get_plan_visibility_contexts($input))];
file_put_contents($output,json_encode(['reference_functions_sha256'=>$found,'cases'=>$out],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");echo 'Recorded '.count($out).' synthetic reference cases; no private reference records.';

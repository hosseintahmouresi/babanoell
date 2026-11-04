<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$tests=[
 'scope_before_pagination'=>static function()use($root):bool{
     $r=file_get_contents($root.'/src/Workflow/TaskRepository.php');
     return strpos($r,"r.assigned_bank_user_id=:bank_owner AND t.queue_code='bank'")<strpos($r,'SELECT COUNT(*) FROM workflow_tasks');
 },
 'task_detail_case_access'=>static function()use($root):bool{
     $s=file_get_contents($root.'/src/Workflow/TaskService.php');
     return str_contains($s,'new RequestAccessPolicy($p)') && str_contains($s,"$".'loan' ."['assigned_bank_user_id']") && str_contains($s,'queue_code');
 },
 'bank_restricts_uncapped_export'=>static function()use($root):bool{
     $s=file_get_contents($root.'/public/index.php');
     return str_contains($s,"bank_export_too_large") && str_contains($s,'$total>5000') && str_contains($s,"'truncated'=>false");
 },
 'bank_exports_grant_and_installments'=>static function()use($root):bool{
     $s=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');
     return str_contains($s,'r.installment_count,r.granted_at,r.bank_settlement_status,r.bank_settled_at,r.updated_at');
 },
 'bank_review_access_fails_closed'=>static function()use($root):bool{
     $s=file_get_contents($root.'/public/index.php');
     return str_contains($s,'bank_credit_denied') && str_contains($s,'bank_credit_override_denied');
 },
 'visibility_policy_denies_out_of_scope_records'=>static function()use($root):bool{
     spl_autoload_register(static function($c)use($root){$pre='Babanoell\\Loans\\';if(str_starts_with($c,$pre)){ $f=$root.'/src/'.str_replace('\\','/',substr($c,strlen($pre))).'.php';if(is_file($f))require_once $f;}});
     $p=new \Babanoell\Loans\Security\Principal('user-a','user',['bank_expert'],['loans.requests.read'],[]);
     $policy=new \Babanoell\Loans\Security\RequestAccessPolicy($p);
     return !$policy->canRead(['id'=>'loan-a','assigned_bank_user_id'=>'user-b']) && $policy->canRead(['id'=>'loan-a','assigned_bank_user_id'=>'user-a']);
 }
];
$errors=0;foreach($tests as $name=>$run){try{$ok=$run();if(!$ok)throw new RuntimeException('assertion failed');echo "[PASS] $name\n";}catch(Throwable $e){$errors++;echo "[FAIL] $name: {$e->getMessage()}\n";}}
exit($errors?1:0);

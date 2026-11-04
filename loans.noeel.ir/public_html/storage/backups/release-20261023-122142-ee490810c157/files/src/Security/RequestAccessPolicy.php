<?php
declare(strict_types=1); namespace Babanoell\Loans\Security;
final class RequestAccessPolicy {
 public function __construct(private readonly Principal $p,private readonly array $extraMerchantIds=[]){ }
 public function canRead(array $r):bool{
  if(!$this->p->can('loans.requests.read'))return false;
  if($this->p->isSuperAdmin())return true;
  $s=$this->p->visibilityScope();
  if($s['deny_global']!==[])return false;
  $id=(string)($r['id']??'');$org=(string)($r['organization_ref']??'');$branch=(string)($r['branch_ref']??'');$bankOrg=(string)($r['bank_organization_ref']??'');$merchant=(string)($r['merchant_id']??'');
  if($id!==''&&in_array($id,$s['deny_loan_requests'],true))return false;
  if(($org!==''&&in_array($org,$s['deny_organizations'],true))||($bankOrg!==''&&in_array($bankOrg,$s['deny_organizations'],true)))return false;
  if($branch!==''&&in_array($branch,$s['deny_branches'],true))return false;
  if($merchant!==''&&in_array($merchant,$s['deny_merchants'],true))return false;
  if($this->p->can('loans.requests.global')||$s['global']!==[])return true;
  foreach(['created_by_user_id','applicant_user_id','acceptor_user_id','assigned_bank_user_id','assigned_operations_user_id']as$f)if((string)($r[$f]??'')===$this->p->userId)return true;
  if($id!==''&&in_array($id,$s['loan_requests'],true))return true;
  if(($org!==''&&in_array($org,$s['organizations'],true))||($bankOrg!==''&&in_array($bankOrg,$s['organizations'],true)))return true;
  if($branch!==''&&in_array($branch,$s['branches'],true))return true;
  if($merchant!==''&&(in_array($merchant,$s['merchants'],true)||in_array($merchant,$this->extraMerchantIds,true)))return true;
  return false;
 }
 public function canUpdate(array$r):bool{return$this->canRead($r)&&$this->p->can('loans.requests.update');}
 public function canTransition(array$r):bool{return$this->canRead($r)&&$this->p->can('loans.requests.transition');}
}

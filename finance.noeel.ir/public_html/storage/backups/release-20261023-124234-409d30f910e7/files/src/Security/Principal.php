<?php
declare(strict_types=1); namespace Babanoell\Finance\Security;
final class Principal {
 public function __construct(public readonly string$userId,public readonly string$displayName,public readonly array$roles,public readonly array$permissions,public readonly array$scopes=[]){ }
 public function isSuperAdmin():bool{return in_array('super_admin',$this->roles,true);}
 public function can(string$p):bool{return $this->isSuperAdmin()||in_array($p,$this->permissions,true);}
 public function ids(string$type,string$effect='allow'):array{
  $out=[];
  foreach(($this->scopes['grants']??[]) as $g){
   if(!is_array($g)||($g['scope_type']??'')!==$type||($g['effect']??'allow')!==$effect)continue;
   $id=$g['scope_id']??null;if(is_string($id)&&$id!=='')$out[]=$id;
  }
  if($effect==='allow'&&$type==='organization')foreach(($this->scopes['memberships']??[])as$m)if(is_array($m)&&is_string($m['organization_id']??null)&&$m['organization_id']!=='')$out[]=$m['organization_id'];
  if($effect==='allow'&&$type==='branch')foreach(($this->scopes['memberships']??[])as$m)if(is_array($m)&&is_string($m['branch_id']??null)&&$m['branch_id']!=='')$out[]=$m['branch_id'];
  return array_values(array_unique($out));
 }
 public function visibilityScope():array{return[
  'user_id'=>$this->userId,
  'global'=>$this->ids('global'),'organizations'=>$this->ids('organization'),'branches'=>$this->ids('branch'),'merchants'=>$this->ids('merchant'),'loan_requests'=>$this->ids('loan_request'),
  'deny_global'=>$this->ids('global','deny'),'deny_organizations'=>$this->ids('organization','deny'),'deny_branches'=>$this->ids('branch','deny'),'deny_merchants'=>$this->ids('merchant','deny'),'deny_loan_requests'=>$this->ids('loan_request','deny')
 ];}
}

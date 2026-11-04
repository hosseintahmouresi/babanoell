<?php
declare(strict_types=1);
namespace Babanoell\Auth\Administration;
use Babanoell\Auth\Http\Request; use Babanoell\Auth\Repositories\RoleRepositoryInterface; use Babanoell\Auth\Repositories\PermissionRepositoryInterface; use Babanoell\Auth\Repositories\SessionRepositoryInterface; use Babanoell\Auth\Repositories\UserRepositoryInterface; use Babanoell\Auth\Security\TokenHasherInterface; use DateTimeImmutable; use Throwable;
final class AdminAuthorization {
 public function __construct(private readonly SessionRepositoryInterface$sessions,private readonly UserRepositoryInterface$users,private readonly RoleRepositoryInterface$roles,private readonly PermissionRepositoryInterface$permissions,private readonly TokenHasherInterface$tokenHasher){}
 public function requireSuperAdmin(Request$r):array{return$this->authorize($r,'',true);}
 public function requirePermission(Request$r,string$permission):array{return$this->authorize($r,$permission,false);}
 private function authorize(Request$request,string$permission,bool$superOnly):array{
  $token=$this->bearer($request);if($token===null)return['ok'=>false,'status'=>401,'code'=>'auth_required','message'=>'احراز هویت الزامی است.'];
  try{$s=$this->sessions->findByAccessTokenHash($this->tokenHasher->hash($token));if($s===null||$s->isRevoked()||$s->accessExpiresAt<=new DateTimeImmutable('now'))return['ok'=>false,'status'=>401,'code'=>'invalid_credentials','message'=>'نشست کاربری معتبر نیست.'];$u=$this->users->findById($s->userId);if($u===null||$u->status!=='active'||$u->disabledAt!==null)return['ok'=>false,'status'=>401,'code'=>'invalid_credentials','message'=>'حساب کاربری فعال نیست.'];$roleNames=array_map(static fn($x):string=>$x->name,$this->roles->findByUserId($u->id));$super=in_array('super_admin',$roleNames,true);if($superOnly&&!$super)return['ok'=>false,'status'=>403,'code'=>'permission_denied','message'=>'این عملیات فقط برای مدیر ارشد سامانه مجاز است.'];if(!$super&&!$superOnly&&!in_array($permission,$this->permissions->findPermissionNamesByUserId($u->id),true))return['ok'=>false,'status'=>403,'code'=>'permission_denied','message'=>'مجوز لازم برای این عملیات فعال نیست.'];return['ok'=>true,'status'=>200,'code'=>'','message'=>'','user_id'=>$u->id,'roles'=>$roleNames,'super_admin'=>$super];}catch(Throwable){return['ok'=>false,'status'=>503,'code'=>'auth_unavailable','message'=>'احراز هویت مدیریتی موقتاً در دسترس نیست.'];}
 }
 private function bearer(Request$r):?string{$h=$r->header('Authorization');return$h!==null&&preg_match('/^Bearer\\s+(\\S+)$/i',trim($h),$m)===1?$m[1]:null;}
}

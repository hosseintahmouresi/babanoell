<?php
declare(strict_types=1);
namespace Babanoell\Loans\Security;
use Babanoell\Loans\Http\IdentityClient; use Babanoell\Loans\Http\Request;
final class Authenticator
{
    public function __construct(private readonly IdentityClient $identity){}
    /** @return array{principal:?Principal,error:?array{status:int,code:string,message:string}} */
    public function authenticate(Request $request): array
    {
        $token=$request->bearer();if($token===null||$token==='')return['principal'=>null,'error'=>['status'=>401,'code'=>'authentication_required','message'=>'برای دسترسی به این بخش باید وارد سامانه شوید.']];
        $r=$this->identity->me($token,$request->clientIp(),$request->userAgent());if(!$r->ok()){return['principal'=>null,'error'=>['status'=>$r->status===0?503:401,'code'=>$r->status===0?'identity_unavailable':'invalid_session','message'=>$r->status===0?'سرویس احراز هویت در دسترس نیست.':'نشست کاربری معتبر نیست.']];}
        $d=$r->json['data']??null;$u=is_array($d)?($d['user']??null):null;if(!is_array($u)||!is_string($u['id']??null)||($u['id']??'')==='')return['principal'=>null,'error'=>['status'=>503,'code'=>'identity_response_invalid','message'=>'پاسخ سرویس احراز هویت قابل تأیید نیست.']];
        if(($u['status']??'active')!=='active')return['principal'=>null,'error'=>['status'=>403,'code'=>'account_inactive','message'=>'حساب کاربری فعال نیست.']];
        return['principal'=>new Principal((string)$u['id'],is_string($u['display_name']??null)?$u['display_name']:'کاربر سامانه',array_values(array_filter(is_array($d['roles']??null)?$d['roles']:[],'is_string')),array_values(array_filter(is_array($d['permissions']??null)?$d['permissions']:[],'is_string'))),'error'=>null];
    }
}

<?php
declare(strict_types=1);
namespace Babanoell\Panel\Users;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\View\Renderer;

final class UserCommissionModule
{
    public function __construct(private readonly PartnersClient $client,private readonly string $bearer,private readonly array $identity){}
    public function handle(string $path,string $method):bool
    {
        if(!preg_match('#^/users/([a-f0-9]{26})/commission$#',$path,$m))return false;
        header('Cache-Control: private,no-store');
        if(!array_intersect(['super_admin','admin'],$this->identity['roles']??[])){http_response_code(403);echo Renderer::page('دسترسی محدود','<p>تنظیم کمیسیون فقط برای مدیر سامانه مجاز است.</p>',$this->identity,'users');return true;}
        $endpoint='/api/v1/admin/users/'.$m[1].'/commission';$message='';
        if($method==='POST'){
            if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$message='اعتبار فرم منقضی شده است.';}
            else{$r=$this->client->post($endpoint,$this->bearer,['revision'=>(int)($_POST['revision']??0),'commission_percent'=>trim((string)($_POST['commission_percent']??''))]);$message=$r->ok()?'کمیسیون ذخیره شد.':(string)($r->json['error']['message']??'ذخیره کامل نشد.');}
        }elseif($method!=='GET'){http_response_code(405);return true;}
        $r=$this->client->get($endpoint,$this->bearer);$body='<div class="bn-pagehead"><h1 class="bn-title">کمیسیون اختصاصی پذیرنده</h1></div>'.($message!==''?'<p class="bn-alert" role="status">'.Renderer::e($message).'</p>':'');
        if(!$r->ok()){http_response_code($r->status>=400?$r->status:503);$body.='<p class="bn-alert bn-alert--error">'.Renderer::e((string)($r->json['error']['message']??'دریافت اطلاعات کامل نشد.')).'</p>';}
        else{$d=$r->json['data'];$x=$d['commission'];$v=$x['commission_percent']===null?'':rtrim(rtrim(number_format((float)$x['commission_percent'],4,'.',''),'0'),'.');
            $body.='<section class="bn-section"><p>کاربر: <strong>'.Renderer::e((string)($d['user']['display_name']??'پذیرنده')).'</strong></p><p>این درصد در محاسبه و ثبت پرونده از پنل همین پذیرنده، جایگزین کمیسیون پیش‌فرض طرح می‌شود. مقدار خالی یعنی استفاده از طرح؛ صفر یعنی کمیسیون اختصاصی صفر. پرونده‌های قبلی محاسبهٔ ثبت‌شدهٔ خود را حفظ می‌کنند.</p><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="revision" value="'.(int)$x['revision'].'"><label class="bn-field">کمیسیون پذیرنده (درصد)<input name="commission_percent" inputmode="decimal" value="'.Renderer::e($v).'" autocomplete="off"><span class="bn-helper">صفر تا صد؛ حداکثر چهار رقم اعشار</span></label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ذخیره کمیسیون</button><a class="bn-btn bn-btn--secondary" href="/users">بازگشت به کاربران</a></div></form></section>';}
        echo Renderer::page('کمیسیون اختصاصی پذیرنده',$body,$this->identity,'users');return true;
    }
}

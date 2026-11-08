<?php
declare(strict_types=1);
namespace Babanoell\Panel\Loans;

use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\View\Renderer;

final class RequestNumberModule
{
    public function __construct(private readonly LoansClient $client,private readonly string $bearer,private readonly array $identity){}
    public function handle(string $path,string $method):bool
    {
        if($path!=='/loans/request-numbering')return false;
        header('Cache-Control: private,no-store');
        if(!array_intersect(['super_admin','admin'],$this->identity['roles']??[])){http_response_code(403);echo Renderer::page('دسترسی محدود','<p>تنظیم شماره پرونده فقط برای مدیر مجاز است.</p>',$this->identity,'loans');return true;}
        $error='';$success='';
        if($method==='POST'){
            if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$error='اعتبار فرم منقضی شده است.';}
            else{$step=strtr((string)($_POST['step']??''),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
                $saved=$this->client->post('/api/v1/admin/request-numbering',$this->bearer,['revision'=>(int)($_POST['revision']??0),'step'=>$step]);
                if($saved->ok())$success='گام شماره‌گذاری ذخیره شد.';else $error=(string)($saved->json['error']['message']??'ذخیره کامل نشد.');}
        }elseif($method!=='GET'){http_response_code(405);return true;}
        $r=$this->client->get('/api/v1/admin/request-numbering',$this->bearer);$s=$r->json['data']['sequence']??null;
        if(!$r->ok()||!is_array($s)){http_response_code(503);$error='دریافت تنظیمات شماره پرونده ممکن نیست.';}
        $html='<div class="bn-pagehead"><h1 class="bn-title">شماره‌گذاری پرونده‌ها</h1></div>'.($error!==''?'<p class="bn-alert bn-alert--error" role="alert">'.Renderer::e($error).'</p>':'').($success!==''?'<p class="bn-alert" role="status">'.$success.'</p>':'');
        if(is_array($s))$html.='<section class="bn-section"><p>شماره‌های موجود حفظ می‌شوند. پرونده‌های تازه شمارهٔ عددی و یکتا می‌گیرند. شمارهٔ بعدی در صورت ثبت هم‌زمان پرونده می‌تواند تغییر کند.</p><p>آخرین شمارهٔ تخصیص‌یافته: <b>'.Renderer::e((string)$s['last_allocated']).'</b>؛ شمارهٔ بعدی فعلی: <b>'.Renderer::e((string)$s['next_number']).'</b></p><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="revision" value="'.(int)$s['revision'].'"><label class="bn-field">گام افزایش شماره<input name="step" inputmode="numeric" value="'.(int)$s['step'].'" required></label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ذخیره گام</button><a class="bn-btn bn-btn--secondary" href="/loans/requests">پرونده‌ها</a></div></form></section>';
        echo Renderer::page('شماره‌گذاری پرونده‌ها',$html,$this->identity,'loans');return true;
    }
}

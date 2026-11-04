<?php
declare(strict_types=1);
namespace Babanoell\Panel\Loans;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class CaseJourney
{
    public static function render(LoansClient $client,string $bearer,string $id,array $identity):string
    {
        if(!preg_match('/^[a-f0-9]{26}$/D',$id))return self::unavailable();
        $response=$client->get('/api/v1/requests/'.$id.'/readiness',$bearer);
        $data=$response->json['data']['readiness']??null;
        if(!$response->ok()||!is_array($data)||($data['request_id']??null)!==$id||!is_array($data['steps']??null)
            ||!array_is_list($data['steps'])||count($data['steps'])!==5||!is_bool($data['ready_for_grant']??null)||!is_string($data['status']??null))return self::unavailable();
        $expected=['profile','documents','verification','contracts','grant'];$steps='';$complete=0;
        foreach($data['steps']as$index=>$step){
            if(!is_array($step)||($step['code']??null)!==$expected[$index]||!is_string($step['title']??null)
                ||!is_string($step['message']??null)||strlen($step['message'])>1600
                ||!in_array($step['status']??null,['complete','blocked','unknown','waiting','ready'],true))return self::unavailable();
            $status=$step['status'];if($status==='complete')$complete++;
            $label=match($status){'complete'=>'تکمیل‌شده','blocked'=>'نیازمند اقدام','unknown'=>'وضعیت نامشخص','ready'=>'آماده',default=>'در انتظار'};
            $steps.='<li class="bn-case-step bn-case-step--'.$status.'"><span class="bn-case-step-number">'.PersianDateTime::digits((string)($index+1)).'</span><div><strong>'.Renderer::e($step['title']).'</strong><span class="bn-case-step-status">'.$label.'</span><p>'.Renderer::e($step['message']).'</p></div></li>';
        }
        $action=is_string($data['next_action']??null)?$data['next_action']:'';
        $link=self::action($action,$id,$identity,(string)($data['status']??''),$data['ready_for_grant']);
        return '<section class="bn-section bn-case-journey" aria-label="پیشرفت پرونده"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">مسیر پرونده</h2><p class="bn-sectiondesc">'.PersianDateTime::digits((string)$complete).' از ۵ مرحله تکمیل شده است.</p></div>'.$link.'</div><ol class="bn-case-steps">'.$steps.'</ol></section>';
    }

    private static function action(string $action,string $id,array $identity,string $status,bool $ready):string
    {
        $roles=is_array($identity['roles']??null)?$identity['roles']:[];$permissions=is_array($identity['permissions']??null)?$identity['permissions']:[];
        $admin=in_array('super_admin',$roles,true)||in_array('admin',$roles,true);
        $can=static fn(string$p):bool=>in_array('super_admin',$roles,true)||in_array($p,$permissions,true);
        $prefix=!$admin&&in_array('acceptor',$roles,true)?'/acceptor':(!$admin&&in_array('applicant',$roles,true)?'/applicant':'/loans');
        $destination=null;$label='';
        if($action==='edit'&&$can('loans.requests.update')&&in_array($status,['draft','need_correction'],true)){$destination=$prefix.'/requests/'.$id.'/edit';$label='تکمیل اطلاعات';}
        elseif($action==='documents'){$destination='/loans/requests/'.$id.'/documents';$label='مشاهده و تکمیل مدارک';}
        elseif($action==='contracts'){$destination='/contracts/loan/'.$id;$label='مشاهده قراردادها';}
        elseif($action==='verification'&&($admin||$can('loans.requests.bank_review'))){$destination='/bank/cases/'.$id;$label='پیگیری اعتبارسنجی';}
        elseif($action==='grant'&&$ready&&$can('loans.requests.grant')){$destination='/loans/requests/'.$id.'#case-workflow';$label='ثبت اعطا';}
        elseif($action==='review'&&$can('loans.requests.transition')){$destination='/loans/requests/'.$id.'#case-workflow';$label='بررسی و تغییر مرحله';}
        return $destination===null?'<span class="bn-helper">اقدام بعدی مطابق نقش مسئول پرونده پیگیری می‌شود.</span>':'<a class="bn-btn bn-btn--primary" href="'.Renderer::e($destination).'">'.$label.'</a>';
    }

    private static function unavailable():string
    {
        return '<section class="bn-section bn-case-journey"><h2 class="bn-sectiontitle">مسیر پرونده</h2><p class="bn-alert bn-alert--warning" role="status">پیشرفت پرونده فعلاً قابل دریافت نیست. پس از برقراری ارتباط صفحه را تازه‌سازی کنید.</p></section>';
    }
}

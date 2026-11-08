<?php
declare(strict_types=1);
namespace Babanoell\Partners\Contract;
final class ContractPrintView
{
 public static function notice(string $title,string $message,string $token='',?string $testOtp=null):string
 {
  $e=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $back=$token!==''?'<a class="bn-btn bn-btn--primary" href="/contract/'.$e($token).'">بازگشت به قرارداد</a>':'';
  $test=$testOtp!==null?'<p class="bn-alert bn-alert--info">رمز آزمایشی: <strong>'.$e($testOtp).'</strong></p>':'';
  return \Babanoell\Partners\Platform\Ui::head($title,'امضای قرارداد').'<div class="bn-pagehead"><div><h1 class="bn-title">'.$e($title).'</h1><p class="bn-subtitle">'.$e($message).'</p></div></div>'.$test.'<div class="bn-form-actions">'.$back.'</div></section>'.\Babanoell\Partners\Platform\Ui::footer().'</main></body></html>';
 }
 public static function headers():void
 {
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: private, no-store, max-age=0');
  header('Referrer-Policy: no-referrer');
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
  header("Content-Security-Policy: default-src 'none'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; font-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
  header('X-Robots-Tag: noindex, nofollow, noarchive');
 }
 public static function render(array $contract,bool $print=false,string $token='',bool $otpEnabled=false):string
 {
  $escape=static fn($s):string=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $no=$escape($contract['contract_no']??'');$state=(string)($contract['status']??'');
  $label=['pending'=>'در انتظار ارسال','sent'=>'ارسال شده','viewed'=>'مشاهده شده','signed'=>'امضا شده','cancelled'=>'لغو شده','expired'=>'منقضی','legacy_imported'=>'مهاجرتی'][$state]??$state;
  $revision=max(1,(int)($contract['revision']??1));
  $signed=(string)($contract['signed_at']??'');$hash=(string)($contract['rendered_hash']??'');
  $title='قرارداد '.$no.' — نسخه '.$revision;
  $printLink=$token!==''?'<a class="print-action" href="/contract/'.$escape($token).'/print">نمایش نسخه چاپی</a>':'';
  $actions=$print?'':$printLink;
  if(!$print&&$token!==''&&(string)($contract['mode']??'')==='digital'&&in_array($state,['pending','sent','viewed'],true)){
   if($otpEnabled){
    $url='/contract/'.$escape($token);
    $actions.='<form method="post" action="'.$url.'/otp/request"><button type="submit">دریافت رمز تأیید</button></form>';
    $actions.='<form method="post" action="'.$url.'/otp/verify"><label>کد شش‌رقمی <input type="text" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" pattern="[0-9۰-۹٠-٩]{6}" required></label><button type="submit">تأیید و امضای دیجیتال</button></form>';
   }else{$actions.='<p class="warning">ارسال رمز این قرارداد هنوز پیکربندی نشده است.</p>';}
  }
  $body=(string)($contract['rendered_content']??'');
  $referenceCss=in_array((string)($contract['template_code']??''),['hami_plus_reference','hami_retirees_reference'],true)?(string)file_get_contents(dirname(__DIR__,2).'/resources/contracts/reference-layout.css'):'';
  if(!empty($contract['buyer_confirmation_token']))$body.=SignatureCertificate::render($contract);
  if($state==='legacy_imported')$body='<p>متن تاریخی قرارداد از داده‌های مهاجرت‌شده بازیابی نشده است.</p>';
  $certificate=$state==='signed'?'<section class="certificate"><h3>گواهی ثبت وضعیت قرارداد</h3><p>شماره قرارداد: '.$no.' | نسخه: '.$revision.'</p><p>تاریخ و زمان ثبت امضا: '.$escape($signed).'</p><p>اثر انگشت محتوای سند (SHA-256): <code dir="ltr">'.$escape($hash).'</code></p><p>روش امضا: '.((string)($contract['mode']??'')==='digital'?'تأیید رمز یک‌بارمصرف':'ثبت امضای فیزیکی').'</p></section>':'';
  return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.$title.'</title>'.\Babanoell\Partners\Platform\Ui::favicons(false).\Babanoell\Partners\Platform\Ui::fonts().'<link rel="stylesheet" href="/ui-asset.php?file=platform.css&amp;v=2.16.0"><style>
@page{size:A4;margin:17mm 15mm 19mm}*{box-sizing:border-box}body{margin:0;background:#F5F7FA;color:#1d2f46;font-family:var(--bn-font),Tahoma,sans-serif;line-height:1.95;font-size:13px}.sheet{width:min(100%,210mm);margin:24px auto;background:white;padding:32px 40px;border:1px solid #E3E9F0;border-radius:14px;box-shadow:0 8px 30px #1D355908}.head{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #1d3559;padding-bottom:12px;margin-bottom:22px}.brand{font-size:16px;font-weight:700}.meta{color:#566476;font-size:11px}.content{color:#182434;overflow-wrap:anywhere}.content p{margin:0 0 10px}.content h1,.content h2,.content h3{page-break-after:avoid;break-after:avoid}.content table{border-collapse:collapse;width:100%}.content th,.content td{border:1px solid #aaa;padding:7px}.content img{max-width:100%;height:auto}.content p,.content li,.content table,.certificate{break-inside:avoid-page}.certificate{border-top:1px solid #d1d8e0;margin-top:26px;padding-top:12px;font-size:11px}.certificate code{font-family:var(--bn-font);overflow-wrap:anywhere}.actions{margin:20px auto;max-width:210mm;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:0 12px}.actions label{font-size:12px}.actions input{border:1px solid #C5D0DD;border-radius:8px;min-height:42px}.actions button{min-height:42px;font:inherit}.sheet .head{border-color:#D0E2F2}.sheet .brand{color:#1D3559}@media(max-width:600px){.sheet{margin:12px auto;padding:22px 18px;border-radius:10px}.head{gap:12px;flex-wrap:wrap}.actions form{width:100%}.actions input{max-width:160px}}.print-action,.actions button{background:#1d3559;color:white;padding:7px 16px;border-radius:8px;text-decoration:none;border:none;cursor:pointer}.actions form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.actions input{font:inherit;padding:6px}.warning{padding:8px;background:#fff0d8;color:#654619}.foot{margin-top:25px;border-top:1px solid #d1d8e0;padding-top:8px;color:#738091;font-size:10px}@media print{html,body{background:white;margin:0;padding:0;print-color-adjust:exact;-webkit-print-color-adjust:exact}.sheet{width:auto;margin:0;padding:0;box-shadow:none}.actions{display:none}.head{break-after:avoid}.foot{break-inside:avoid}}
'.$referenceCss.'</style></head><body><div class="actions">'.$actions.'</div><article class="sheet"><header class="head"><span class="brand">بابانوئل</span><span class="meta">'.$no.' | نسخه '.$revision.' | '.$escape($label).'</span></header><main class="content">'.$body.'</main>'.$certificate.'<footer class="foot">این سند مطابق متن ثبت‌شدهٔ نسخه '.$revision.' قرارداد تولید شده است.</footer></article></body></html>';
 }
}

<?php
declare(strict_types=1);
namespace Babanoell\Panel\Finance;
use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Bank\SpreadsheetSafeCell;
use Babanoell\Panel\View\Renderer;
final class BankCyclesModule {
 public function __construct(private readonly FinanceClient $client,private readonly string $bearer,private readonly array $identity){}
 private function admin():bool{return (bool)array_intersect(['super_admin','admin'],(array)($this->identity['roles']??[]));}
 private function globalBankRead():bool{return $this->admin()||(bool)array_intersect(['finance_expert','operations_expert'],(array)($this->identity['roles']??[]));}
 private function can(string $p):bool{return $this->admin()||in_array($p,(array)($this->identity['permissions']??[]),true);}
 public function handle(string $path,string $method):bool{
  if($path==='/finance/bank-cycles'&&$method==='GET'){$this->index();return true;}
  if($path==='/finance/bank-cycles'&&$method==='POST'){$this->create();return true;}
  if($path==='/finance/bank-cycles/export'&&$method==='GET'){$this->export();return true;}
  if(preg_match('#^/finance/bank-cycles/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){$this->detail($m[1]);return true;}
  if(preg_match('#^/finance/bank-cycles/([a-f0-9]{26})/retry-sync$#',$path,$m)&&$method==='POST'){$this->retry($m[1]);return true;}
  return false;
 }
 private function page(string $title,string $description,string $content):void{
  $head='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">بخش مالی</div><h1 class="bn-title">'.Renderer::e($title).'</h1><p class="bn-subtitle">'.Renderer::e($description).'</p></div></div>';
  $flash=$_SESSION['finance_bank_flash']??null;unset($_SESSION['finance_bank_flash']);
  if(is_array($flash))$head.='<div class="bn-alert bn-alert--'.(($flash['ok']??false)?'success':'error').'">'.Renderer::e((string)$flash['text']).'</div>';
  echo Renderer::page($title,$head.$content,$this->identity,'finance');
 }
 private function denied():void{http_response_code(403);$this->page('دسترسی محدود','دسترسی به سیکل‌های بانک مجاز نیست.','');}
 private function error(HttpResponse $r):void{http_response_code($r->status>=400&&$r->status<=599?$r->status:503);$this->page('سیکل‌های بانک','خطای سرویس مالی','<section class="bn-section">'.Renderer::e((string)($r->json['error']['message']??'سرویس در دسترس نیست.')).'</section>');}
 private function redirect(string $path):never{header('Location: '.$path,true,303);exit;}
 private function csrf():void{if(Csrf::valid((string)($_POST['_csrf']??'')))return;$_SESSION['finance_bank_flash']=['ok'=>false,'text'=>'اعتبار فرم منقضی شده است.'];$this->redirect('/finance/bank-cycles');}
 private function filters():array{return ['bank_user_id'=>trim((string)($_GET['bank_user_id']??'')),'settlement_date'=>trim((string)($_GET['settlement_date']??'')),'q'=>trim((string)($_GET['q']??''))];}
 private function index():void{
  if(!$this->can('finance.bank_settlement.read')){$this->denied();return;}
  $filter=$this->filters();$page=max(1,(int)($_GET['page']??1));$r=$this->client->get('/api/v1/bank-cycles',$this->bearer,$filter+['page'=>$page,'per_page'=>25]);if(!$r->ok()){$this->error($r);return;}
  $d=(array)($r->json['data']['cycles']??[]);$rows='';
  foreach((array)($d['items']??[])as$x)if(is_array($x)){
   $id=Renderer::e((string)($x['id']??''));$rows.='<tr><td><a class="bn-table-link" href="/finance/bank-cycles/'.$id.'">'.Renderer::e((string)($x['cycle_no']??$id)).'</a></td><td>'.Renderer::e((string)($x['bank_user_id']??'')).'</td><td>'.Renderer::e((string)($x['settlement_date']??'')).'</td><td>'.Renderer::e((string)($x['item_count']??'')).'</td><td>'.Renderer::e((string)($x['total_loan_amount']??'')).'</td><td>'.Renderer::e((string)($x['projection_status']??'')).'</td></tr>';
  }
  if($rows==='')$rows='<tr><td colspan="6">سیکلی ثبت نشده است.</td></tr>';
  $form='';if($this->can('finance.bank_settlement.manage')){
   $form='<section class="bn-section"><h2 class="bn-sectiontitle">ایجاد سیکل بانک</h2><p>حداکثر ۱۰۰ پرونده اعطاشده متعلق به کارشناس. شناسه هر پرونده را در یک خط وارد کنید؛ مبلغ از Loans خوانده می‌شود.</p><form class="bn-form-grid" method="post" action="/finance/bank-cycles"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="operation_key" value="'.bin2hex(random_bytes(16)).'"><div class="bn-field"><label class="bn-label">تاریخ تسویه (شمسی)</label><input type="text" name="settlement_date" placeholder="۱۴۰۵/۰۷/۰۱" required></div><div class="bn-field bn-field--full"><label class="bn-label">شناسه پرونده‌ها</label><textarea rows="5" name="request_ids" required></textarea></div><div class="bn-field bn-field--full"><label class="bn-label">توضیحات (اختیاری)</label><textarea name="notes" maxlength="3000"></textarea></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت سیکل بانک</button></div></form></section>';
  }
  $url='/finance/bank-cycles/export?'.http_build_query($filter);
  $search='<section class="bn-section"><form method="get" class="bn-filterbar"><label>سیکل یا پرونده <input name="q" value="'.Renderer::e($filter['q']).'"></label><label>تاریخ <input type="text" name="settlement_date" placeholder="۱۴۰۵/۰۷/۰۱" value="'.Renderer::e($filter['settlement_date']).'"></label>'.($this->globalBankRead()?'<label>شناسه کارشناس <input name="bank_user_id" value="'.Renderer::e($filter['bank_user_id']).'"></label>':'').'<button class="bn-btn bn-btn--secondary">فیلتر</button></form></section>';
  $table='<section class="bn-section"><div class="bn-actions"><a class="bn-btn bn-btn--secondary" href="'.Renderer::e($url).'">خروجی CSV</a><a class="bn-btn bn-btn--secondary" href="/finance">بازگشت</a></div><p>تعداد نتایج: '.Renderer::e((string)($d['total']??0)).'</p><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>سیکل</th><th>کارشناس</th><th>تاریخ</th><th>تعداد</th><th>جمع ریال</th><th>همگام‌سازی Loans</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
  $paging='<div class="bn-actions">';$total=(int)($d['total']??0);
  if($page>1)$paging.='<a class="bn-btn bn-btn--secondary" href="/finance/bank-cycles?'.Renderer::e(http_build_query($filter+['page'=>$page-1])).'">صفحه قبل</a>';
  if($page*25<$total)$paging.='<a class="bn-btn bn-btn--secondary" href="/finance/bank-cycles?'.Renderer::e(http_build_query($filter+['page'=>$page+1])).'">صفحه بعد</a>';
  $paging.='</div>';
  $this->page('سیکل‌های تسویه بانک با نوئل','سیکل‌های بانک مستقل از تسویه پذیرندگان هستند.',$search.$form.$table.$paging);
 }
 private function detail(string $id):void{
  if(!$this->can('finance.bank_settlement.read')){$this->denied();return;}
  $r=$this->client->get('/api/v1/bank-cycles/'.$id,$this->bearer);if(!$r->ok()){$this->error($r);return;}
  $c=(array)($r->json['data']['cycle']??[]);$rows='';
  foreach((array)($c['items']??[])as$x)if(is_array($x))$rows.='<tr><td>'.Renderer::e((string)($x['request_id']??'')).'</td><td>'.Renderer::e((string)($x['applicant_name']??'')).'</td><td>'.Renderer::e((string)($x['national_code']??'')).'</td><td>'.Renderer::e((string)($x['loan_amount']??'')).'</td></tr>';
  $state=(string)($c['projection_status']??'pending');$retry=($state==='pending'&&$this->can('finance.bank_settlement.manage'))?'<form method="post" action="/finance/bank-cycles/'.Renderer::e($id).'/retry-sync"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--secondary">بازهمگام‌سازی Loans</button></form>':'';
  $content='<section class="bn-section"><a class="bn-btn bn-btn--secondary" href="/finance/bank-cycles">بازگشت</a><p>سیکل: '.Renderer::e((string)($c['cycle_no']??'')).' | تاریخ: '.Renderer::e((string)($c['settlement_date']??'')).' | تعداد: '.Renderer::e((string)($c['item_count']??0)).' | جمع ریال: '.Renderer::e((string)($c['total_loan_amount']??0)).' | وضعیت نمایش: '.Renderer::e($state).'</p><p>'.nl2br(Renderer::e((string)($c['notes']??''))).'</p>'.$retry.'<div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>متقاضی</th><th>کد ملی</th><th>مبلغ ریال</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
  $this->page('جزئیات سیکل بانک','سوابق تجمیعی پس از ثبت تغییرناپذیرند.',$content);
 }
 private function create():void{
  if(!$this->can('finance.bank_settlement.manage')){$this->denied();return;}$this->csrf();
  $ids=preg_split('/[\s,،;]+/u',trim((string)($_POST['request_ids']??'')),-1,PREG_SPLIT_NO_EMPTY);
  $r=$this->client->post('/api/v1/bank-cycles',$this->bearer,['request_ids'=>$ids,'settlement_date'=>trim((string)($_POST['settlement_date']??'')),'notes'=>trim((string)($_POST['notes']??''))],trim((string)($_POST['operation_key']??'')));
  $cycle=$r->json['data']['cycle']??[];$pending=($cycle['projection_status']??'')==='pending';
  $_SESSION['finance_bank_flash']=['ok'=>$r->ok()&&!$pending,'text'=>$r->ok()?($pending?'ثبت Finance انجام شد، اما نمای Loans نیازمند همگام‌سازی مجدد است.':'سیکل بانک ثبت و همگام شد.'):(string)($r->json['error']['message']??'ثبت سیکل انجام نشد.')];
  $id=is_array($cycle)?(string)($cycle['id']??''):'';$this->redirect($r->ok()&&preg_match('/^[a-f0-9]{26}$/D',$id)?'/finance/bank-cycles/'.$id:'/finance/bank-cycles');
 }
 private function retry(string $id):void{
  if(!$this->can('finance.bank_settlement.manage')){$this->denied();return;}$this->csrf();$r=$this->client->post('/api/v1/bank-cycles/'.$id.'/retry-sync',$this->bearer);
  $synced=$r->ok()&&($r->json['data']['cycle']['projection_status']??'')==='synced';
  $_SESSION['finance_bank_flash']=['ok'=>$synced,'text'=>$synced?'همگام‌سازی Loans موفق بود.':(string)($r->json['error']['message']??'همگام‌سازی همچنان تکمیل نشده است.')];$this->redirect('/finance/bank-cycles/'.$id);
 }
 private function export():void{
  if(!$this->can('finance.bank_settlement.read')){$this->denied();return;}
  $filter=$this->filters();$records=[];$total=null;
  for($page=1;$page<=10;$page++){$r=$this->client->get('/api/v1/bank-cycles',$this->bearer,$filter+['page'=>$page,'per_page'=>100]);if(!$r->ok()){$this->error($r);return;}$d=(array)($r->json['data']['cycles']??[]);$total=(int)($d['total']??0);if($total>1000){http_response_code(422);$this->page('گزارش بزرگ','فیلتر را محدود کنید؛ سقف خروجی ۱۰۰۰ سیکل است.','');return;}foreach((array)($d['items']??[])as$x)$records[]=$x;if(count($records)>=$total)break;}
  if($total===null||count($records)!==$total){http_response_code(503);$this->page('گزارش ناقص','در صورت اختلال یا تغییر داده، خروجی ناقص تحویل نمی‌شود.','');return;}
  $stream=fopen('php://temp','w+');if($stream===false){http_response_code(500);return;}fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['شناسه','شماره سیکل','کارشناس','تاریخ','تعداد پرونده','مبلغ ریال','همگام‌سازی'],',','"','');
  foreach($records as$x)fputcsv($stream,array_map([SpreadsheetSafeCell::class,'escape'],[(string)($x['id']??''),(string)($x['cycle_no']??''),(string)($x['bank_user_id']??''),(string)($x['settlement_date']??''),(string)($x['item_count']??''),(string)($x['total_loan_amount']??''),(string)($x['projection_status']??'')]),',','"','');
  rewind($stream);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="noel-bank-cycles-'.gmdate('Ymd-His').'.csv"');header('Cache-Control: no-store');fpassthru($stream);fclose($stream);
 }
}

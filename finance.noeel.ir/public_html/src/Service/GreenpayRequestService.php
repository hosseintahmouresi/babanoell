<?php
declare(strict_types=1);
namespace Babanoell\Finance\Service;

use Babanoell\Finance\Http\GreenpayGateway;
use Babanoell\Finance\Repository\PdoFinanceRepository;
use Babanoell\Finance\Support\RandomId;
use PDO;

/** Explicit per-case enrollment, immutable attempt snapshots and no retry of an uncertain debit. */
final class GreenpayRequestService
{
    private readonly GreenpayVault $vault;
    public function __construct(private readonly PDO $pdo,private readonly PdoFinanceRepository $events,private readonly GreenpayGateway $gateway,
        private readonly \Closure $canonical,string $key,private readonly string $destination,private readonly bool $enabled=false,private readonly bool $sandboxProfile=false)
    {$this->vault=new GreenpayVault($key);}
    private function fail(string $code,string $message,int $status=409):never{throw new RequestDebitException($code,$message,$status);}
    private function loan(string $id):array
    {
        if(!preg_match('/^[a-f0-9]{26}$/D',$id))$this->fail('invalid_request_id','شناسه پرونده معتبر نیست.',422);
        $loan=($this->canonical)($id);
        if(!is_array($loan)||($loan['id']??'')!==$id)$this->fail('greenpay_loan_unavailable','اطلاعات مرجع پرونده در دسترس نیست.',503);return $loan;
    }
    public static function amount(array $loan):int
    {
        // Reference current_amount_for_request: facility if present; otherwise total purchase. No toman conversion.
        $value=$loan['facility_amount']??null;if($value===null||$value==='')$value=$loan['requested_amount']??0;
        $amount=is_numeric($value)?max(0,(int)round((float)$value)):0;
        if($amount<1||$amount>100000000000000)throw new RequestDebitException('greenpay_amount_invalid','مبلغ تسهیلات پرونده معتبر نیست.',422);return $amount;
    }
    private function row(string $request,bool $lock=false):?array
    {
        $q=$this->pdo->prepare('SELECT * FROM finance_greenpay_request_debits WHERE request_id=:id'.($lock?' FOR UPDATE':''));$q->execute([':id'=>$request]);$r=$q->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;
    }
    private function attempt(string $id,bool $lock=false):array
    {
        $q=$this->pdo->prepare('SELECT * FROM finance_greenpay_request_attempts WHERE id=:id'.($lock?' FOR UPDATE':''));$q->execute([':id'=>$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))$this->fail('greenpay_attempt_missing','سابقه عملیات پیدا نشد.');return $r;
    }
    private function version(array $row,int $expected):void
    {
        if($expected<1||$expected!==(int)$row['row_version'])$this->fail('greenpay_request_changed','اطلاعات عملیات هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
    }
    public function get(string $request):array
    {
        if(!preg_match('/^[a-f0-9]{26}$/D',$request))$this->fail('invalid_request_id','شناسه پرونده معتبر نیست.',422);
        $row=$this->row($request);if($row===null){$loan=$this->loan($request);return ['request_no'=>(string)($loan['request_no']??$request),'request_id'=>$request,'eligible'=>false,'status'=>'not_applicable','row_version'=>0,'attempts'=>[]];}
        $q=$this->pdo->prepare('SELECT id,sequence_no,external_reference,amount_rial,status,otp_generation,otp_requested_at,dispatch_started_at,provider_reference,last_error,reviewed_by,review_note,created_at FROM finance_greenpay_request_attempts WHERE debit_id=:id ORDER BY sequence_no DESC');$q->execute([':id'=>$row['id']]);
        return array_intersect_key($row,array_flip(['id','request_id','request_no','status','from_mask','to_mask','active_attempt_id','attempt_count','row_version','created_at','updated_at']))+['eligible'=>(bool)$row['eligible'],'attempts'=>$q->fetchAll(PDO::FETCH_ASSOC)];
    }
    public function listing(int $page=1):array
    {
        $page=max(1,$page);$q=$this->pdo->prepare('SELECT request_id,request_no,eligible,status,from_mask,to_mask,row_version,updated_at FROM finance_greenpay_request_debits ORDER BY updated_at DESC LIMIT 25 OFFSET :offset');$q->bindValue(':offset',($page-1)*25,PDO::PARAM_INT);$q->execute();
        return ['items'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>(int)$this->pdo->query('SELECT COUNT(*) FROM finance_greenpay_request_debits')->fetchColumn(),'page'=>$page];
    }
    public function enroll(string $request,string $actor,array $input):array
    {
        $loan=$this->loan($request);$eligible=($input['eligible']??false)===true;$expected=(int)($input['row_version']??0);
        $this->pdo->beginTransaction();
        try{
            $row=$this->row($request,true);
            if($row!==null){
                if((bool)$row['eligible']===$eligible&&(!$eligible||trim((string)($input['from_pocket']??''))===''||hash_equals($row['pockets_fingerprint'],$this->vault->fingerprint(($this->sandboxProfile?'1262010000002551':trim((string)$input['from_pocket'])).'|'.($this->sandboxProfile?'':$this->destination))))){$this->pdo->commit();return $this->get($request);}
                $this->version($row,$expected);
                if(in_array($row['status'],['success','processing','unknown','awaiting_otp','otp_pending'],true))$this->fail('greenpay_enrollment_locked','پرونده دارای عملیات انجام‌شده، در جریان یا نامشخص است؛ مشمول بودن و کیف پول آن تغییر نمی‌کند.');
            }elseif($expected!==0)$this->fail('greenpay_request_changed','اطلاعات عملیات تغییر کرده است.');
            if(!$eligible&&$row===null){$this->pdo->commit();return $this->get($request);}
            if($eligible){
                self::amount($loan);$from=$this->sandboxProfile?'1262010000002551':trim((string)($input['from_pocket']??''));if($from===''&&$row!==null)$from=$this->vault->decrypt($row['from_encrypted']);$to=$this->sandboxProfile?'':$this->destination;
                if(!preg_match('/^[0-9]{8,32}$/D',$from)||(!$this->sandboxProfile&&!preg_match('/^[0-9]{8,32}$/D',$to))||$from===$to)$this->fail('greenpay_pocket_invalid','شمارهٔ کیف پول مبدأ یا مقصد تنظیم‌شده معتبر نیست.',422);
                $boxedFrom=$this->vault->encrypt($from);$boxedTo=$this->vault->encrypt($to);$fingerprint=$this->vault->fingerprint($from.'|'.$to);
            }
            if($row===null){
                $id=RandomId::make();$q=$this->pdo->prepare("INSERT INTO finance_greenpay_request_debits(id,request_id,request_no,eligible,status,from_encrypted,to_encrypted,from_mask,to_mask,pockets_fingerprint,created_by,updated_by) VALUES(:id,:request,:number,1,'not_started',:from,:to,:frommask,:tomask,:fingerprint,:actor,:updater)");
                $q->execute([':id'=>$id,':request'=>$request,':number'=>$loan['request_no']??$request,':from'=>$boxedFrom,':to'=>$boxedTo,':frommask'=>'***'.substr($from,-4),':tomask'=>($this->sandboxProfile?'—':'***'.substr($to,-4)),':fingerprint'=>$fingerprint,':actor'=>$actor,':updater'=>$actor]);
            }else{
                $id=$row['id'];$q=$this->pdo->prepare('UPDATE finance_greenpay_request_debits SET eligible=:eligible,status=:status,from_encrypted=:from,to_encrypted=:to,from_mask=:frommask,to_mask=:tomask,pockets_fingerprint=:fingerprint,updated_by=:actor,row_version=row_version+1 WHERE id=:id');
                $q->execute([':eligible'=>$eligible?1:0,':status'=>$eligible?'not_started':'not_applicable',':from'=>$eligible?$boxedFrom:$row['from_encrypted'],':to'=>$eligible?$boxedTo:$row['to_encrypted'],':frommask'=>$eligible?'***'.substr($from,-4):$row['from_mask'],':tomask'=>$eligible?($this->sandboxProfile?'—':'***'.substr($to,-4)):$row['to_mask'],':fingerprint'=>$eligible?$fingerprint:$row['pockets_fingerprint'],':actor'=>$actor,':id'=>$id]);
            }
            $this->events->event('greenpay.request.enrollment','greenpay_request',$id,$actor,['request_id'=>$request,'eligible'=>$eligible]);$this->pdo->commit();return $this->get($request);
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function configuration():array
    {
        if(!$this->enabled)$this->fail('greenpay_request_disabled','اجرای GreenPay در تنظیمات مالی غیرفعال است.',503);
        $cap=$this->gateway->greenpayCapabilities();if(!$cap->ok()||($cap->json['data']['authorized']??false)!==true||($cap->json['data']['transfers_enabled']??false)!==true)$this->fail('greenpay_execution_unavailable','مجوز ارتباط مالی GreenPay قابل تأیید نیست.',503);
        $response=$this->gateway->greenpayConfiguration();$data=$response->json['data']??[];
        if(!$response->ok()||($data['configured']??false)!==true||($data['transfers_enabled']??false)!==true||!preg_match('/^[a-f0-9]{64}$/D',(string)($data['fingerprint']??'')))$this->fail('greenpay_configuration_unavailable','تنظیمات اتصال GreenPay کامل نیست.',503);if(($data['sandbox_profile_1023']??false)!==$this->sandboxProfile)$this->fail('greenpay_environment_mismatch','حالت آزمون دو سرویس مالی و اتصال یکسان نیست.',503);return $data;
    }
    public function sendOtp(string $request,string $actor,int $expected):array
    {
        $loan=$this->loan($request);$configuration=$this->configuration();
        $this->pdo->beginTransaction();
        try{
            $row=$this->row($request,true);if($row===null||!(bool)$row['eligible'])$this->fail('greenpay_request_not_enrolled','این پرونده برای GreenPay مشمول نشده است.');$this->version($row,$expected);
            if($row['status']==='otp_pending'){
                $interrupted=$this->attempt($row['active_attempt_id'],true);
                if(strtotime((string)$interrupted['updated_at'].' UTC')>time()-120)$this->fail('greenpay_otp_in_progress','ارسال رمز در جریان است؛ کمی صبر کنید.');
                $this->pdo->prepare("UPDATE finance_greenpay_request_attempts SET status='failed',last_error='otp_dispatch_interrupted' WHERE id=:id AND status='otp_pending'")->execute([':id'=>$interrupted['id']]);$row['status']='failed';
            }
            if($row['status']==='awaiting_otp'){
                $attempt=$this->attempt($row['active_attempt_id'],true);
                if(strtotime(($attempt['otp_requested_at']??$attempt['created_at']).' UTC')>time()-60)$this->fail('greenpay_otp_cooldown','برای ارسال مجدد رمز، یک دقیقه صبر کنید.');
                if(!hash_equals($attempt['configuration_fingerprint'],$configuration['fingerprint']))$this->fail('greenpay_configuration_changed','تنظیمات اتصال پس از شروع عملیات تغییر کرده است؛ ابتدا وضعیت قبلی را تعیین تکلیف کنید.');
                $generation=(int)$attempt['otp_generation']+1;$id=$attempt['id'];
                $this->pdo->prepare("UPDATE finance_greenpay_request_attempts SET status='otp_pending',otp_generation=:generation WHERE id=:id")->execute([':generation'=>$generation,':id'=>$id]);
            }else{
                if(!in_array($row['status'],['not_started','failed','cancelled'],true))$this->fail('greenpay_request_busy','عملیات قبلی باید ابتدا تعیین تکلیف شود.');
                if(($loan['status']??'')!=='docs_verified')$this->fail('greenpay_documents_not_verified','شروع کسری فقط در مرحلهٔ تأیید مدارک مجاز است.');
                $id=RandomId::make();$generation=1;$amount=self::amount($loan);$sequence=(int)$row['attempt_count']+1;
                $q=$this->pdo->prepare("INSERT INTO finance_greenpay_request_attempts(id,debit_id,sequence_no,external_reference,amount_rial,from_encrypted,to_encrypted,configuration_fingerprint,status,created_by) VALUES(:id,:debit,:seq,:reference,:amount,:from,:to,:fingerprint,'otp_pending',:actor)");
                $q->execute([':id'=>$id,':debit'=>$row['id'],':seq'=>$sequence,':reference'=>'N'.$id,':amount'=>$amount,':from'=>$row['from_encrypted'],':to'=>$row['to_encrypted'],':fingerprint'=>$configuration['fingerprint'],':actor'=>$actor]);
                $this->pdo->prepare('UPDATE finance_greenpay_request_debits SET attempt_count=:count WHERE id=:id')->execute([':count'=>$sequence,':id'=>$row['id']]);
            }
            $this->pdo->prepare("UPDATE finance_greenpay_request_debits SET status='otp_pending',active_attempt_id=:attempt,updated_by=:actor,row_version=row_version+1 WHERE id=:id")->execute([':attempt'=>$id,':actor'=>$actor,':id'=>$row['id']]);
            $this->events->event('greenpay.request.otp_reserved','greenpay_request',$row['id'],$actor,['attempt_id'=>$id,'generation'=>$generation]);$this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $attempt=$this->attempt($id);$accepted=false;
        try{$response=$this->gateway->greenpayOtp($this->vault->decrypt($attempt['from_encrypted']),(int)$attempt['amount_rial'],hash('sha256','request-otp|'.$id.'|'.$generation));$accepted=$response->ok()&&($response->json['data']['accepted']??false)===true;}catch(\Throwable){}
        $this->finish($request,$id,'otp_pending',$accepted?'awaiting_otp':'failed',$accepted?null:'greenpay_otp_not_confirmed',$actor,null,true);
        $current=$this->get($request);
        if($accepted&&$this->sandboxProfile)return $this->execute($request,$actor,(int)$current['row_version'],'123456');
        return $current;
    }
    public function execute(string $request,string $actor,int $expected,string $otp):array
    {
        $otp=strtr(trim($otp),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        if(!preg_match('/^[0-9]{6}$/D',$otp))$this->fail('greenpay_otp_invalid','رمز تأیید باید شش رقم باشد.',422);
        $configuration=$this->configuration();$this->pdo->beginTransaction();
        try{
            $row=$this->row($request,true);if($row===null||!(bool)$row['eligible'])$this->fail('greenpay_request_not_enrolled','پرونده مشمول GreenPay نیست.');
            if($row['status']==='success'){$this->pdo->commit();return $this->get($request);}
            $this->version($row,$expected);if($row['status']!=='awaiting_otp')$this->fail('greenpay_request_not_awaiting_otp','عملیات در انتظار رمز نیست؛ ارسال دوباره مجاز نیست.');
            $attempt=$this->attempt($row['active_attempt_id'],true);if($attempt['status']!=='awaiting_otp')$this->fail('greenpay_request_busy','عملیات هم‌زمان تغییر کرده است.');
            if(!hash_equals($attempt['configuration_fingerprint'],$configuration['fingerprint']))$this->fail('greenpay_configuration_changed','تنظیمات اتصال بعد از ایجاد عملیات تغییر کرده است؛ انتقال ارسال نشد.');
            $this->pdo->prepare("UPDATE finance_greenpay_request_attempts SET status='processing',dispatch_started_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':id'=>$attempt['id']]);
            $this->pdo->prepare("UPDATE finance_greenpay_request_debits SET status='processing',row_version=row_version+1,updated_by=:actor WHERE id=:id")->execute([':actor'=>$actor,':id'=>$row['id']]);
            $this->events->event('greenpay.request.dispatch_reserved','greenpay_request',$row['id'],$actor,['attempt_id'=>$attempt['id'],'amount_rial'=>(int)$attempt['amount_rial']]);$this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $status='unknown';$code='greenpay_result_unknown';$reference=null;
        try{
            $payload=['finance_intent_id'=>$attempt['id'],'reference'=>$attempt['external_reference'],'configuration_fingerprint'=>$attempt['configuration_fingerprint'],
                'from_pocket'=>$this->vault->decrypt($attempt['from_encrypted']),'to_pocket'=>$this->vault->decrypt($attempt['to_encrypted']),'amount_rial'=>(int)$attempt['amount_rial'],'otp'=>$otp];
            $response=$this->gateway->greenpayRequestDebit($payload,$attempt['id']);$data=$response->json['data']??[];
            if($response->ok()&&($data['reference']??'')===$attempt['external_reference']){
                if(($data['accepted']??false)===true&&($data['outcome']??'')==='success'){$status='success';$code=null;}
                elseif(($data['accepted']??null)===false&&($data['outcome']??'')==='failed'){$status='failed';$code='greenpay_request_rejected';}
                $candidate=$data['provider_reference']??null;if(is_string($candidate)&&preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$candidate))$reference=$candidate;
            }elseif(in_array($response->errorCode(),['greenpay_configuration_changed','greenpay_transfer_disabled','greenpay_transfer_authorization_missing','greenpay_transfer_authorization_invalid','greenpay_reference_mismatch','validation_failed','greenpay_not_configured'],true)){$status='failed';$code='greenpay_preflight_not_sent';}
        }catch(\Throwable){}
        $this->finish($request,$attempt['id'],'processing',$status,$code,$actor,$reference);return $this->get($request);
    }
    private function finish(string $request,string $attempt,string $from,string $to,?string $error,string $actor,?string $reference=null,bool $otp=false):void
    {
        $this->pdo->beginTransaction();
        try{
            $row=$this->row($request,true);$a=$this->attempt($attempt,true);
            if($row===null||$row['active_attempt_id']!==$attempt||$row['status']!==$from||$a['status']!==$from)$this->fail('greenpay_request_changed','نتیجه با وضعیت جاری عملیات قابل تطبیق نیست.');
            $sql='UPDATE finance_greenpay_request_attempts SET status=:status,last_error=:error,provider_reference=:reference'.($otp?',otp_requested_at=UTC_TIMESTAMP(6)':'').' WHERE id=:id';
            $this->pdo->prepare($sql)->execute([':status'=>$to,':error'=>$error,':reference'=>$reference,':id'=>$attempt]);
            $this->pdo->prepare('UPDATE finance_greenpay_request_debits SET status=:status,row_version=row_version+1,updated_by=:actor WHERE id=:id')->execute([':status'=>$to,':actor'=>$actor,':id'=>$row['id']]);
            $this->events->event('greenpay.request.result','greenpay_request',$row['id'],$actor,['attempt_id'=>$attempt,'from'=>$from,'to'=>$to,'error'=>$error,'provider_reference'=>$reference]);$this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function reconcile(string $request,string $actor,int $expected,array $input):array
    {
        $decision=(string)($input['decision']??'');$note=trim((string)($input['note']??''));$reference=trim((string)($input['reference']??''));
        if(($input['confirmed']??false)!==true||!in_array($decision,['paid','unpaid'],true)||mb_strlen($note)<10||mb_strlen($note)>2000)$this->fail('greenpay_reconciliation_invalid','نتیجه بررسی، توضیح و تأیید بررسی پنل بانکی الزامی است.',422);
        $this->pdo->beginTransaction();
        try{
            $row=$this->row($request,true);if($row===null)$this->fail('greenpay_request_missing','عملیات پیدا نشد.',404);$this->version($row,$expected);
            $attempt=$this->attempt((string)$row['active_attempt_id'],true);
            if(!hash_equals($attempt['external_reference'],$reference))$this->fail('greenpay_reconciliation_reference','شناسه مرجع بررسی با همین تلاش یکسان نیست.',422);
            if(!in_array($row['status'],['unknown','processing'],true)||($row['status']==='processing'&&strtotime((string)$attempt['dispatch_started_at'].' UTC')>time()-120))$this->fail('greenpay_reconciliation_denied','فقط نتیجه نامشخص یا ارسال متوقف‌شده پس از دو دقیقه قابل تطبیق است.');
            $status=$decision==='paid'?'success':'failed';
            $this->pdo->prepare('UPDATE finance_greenpay_request_attempts SET status=:status,reviewed_by=:actor,review_note=:note,last_error=:error WHERE id=:id')->execute([':status'=>$status,':actor'=>$actor,':note'=>$note,':error'=>$decision==='paid'?'manual_reconciled_paid':'manual_reconciled_unpaid',':id'=>$attempt['id']]);
            $this->pdo->prepare('UPDATE finance_greenpay_request_debits SET status=:status,row_version=row_version+1,updated_by=:actor WHERE id=:id')->execute([':status'=>$status,':actor'=>$actor,':id'=>$row['id']]);
            $this->events->event('greenpay.request.reconciled','greenpay_request',$row['id'],$actor,['attempt_id'=>$attempt['id'],'decision'=>$decision,'reference'=>$reference,'note'=>$note]);$this->pdo->commit();return $this->get($request);
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}

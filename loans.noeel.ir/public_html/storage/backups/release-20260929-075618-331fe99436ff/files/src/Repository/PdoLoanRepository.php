<?php
declare(strict_types=1);
namespace Babanoell\Loans\Repository;

use Babanoell\Loans\Support\Json;
use Babanoell\Loans\Support\RandomId;
use PDO;
use RuntimeException;
use Throwable;

final class PdoLoanRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function pdo(): PDO { return $this->pdo; }

    /** @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int} */
    public function listPlans(int $page=1,int $perPage=25,?string $status=null,?string $type=null): array
    {
        $page=max(1,$page);$perPage=max(1,min(100,$perPage));$where=[];$params=[];
        $status=$status!==null?strtolower(trim($status)):null;
        $allowedStatuses=['draft','active','inactive','archived'];
        if($status==='all'){$status=null;}
        elseif($status!==null&&$status!==''){
            if(!in_array($status,$allowedStatuses,true))throw new \InvalidArgumentException('فیلتر وضعیت طرح معتبر نیست.');
            $where[]='p.status = :status';$params[':status']=$status;
        }else{
            // Archived plans are intentionally hidden from the normal management list.
            // They remain available through the explicit archived/all filters and by direct id.
            $where[]="p.status <> 'archived'";
        }
        if($type!==null&&$type!==''){$where[]='p.type = :type';$params[':type']=$type;}
        $w=$where?(' WHERE '.implode(' AND ',$where)):'';
        $count=$this->pdo->prepare('SELECT COUNT(*) FROM installment_plans p'.$w);$count->execute($params);$total=(int)$count->fetchColumn();
        $sql='SELECT p.*,v.settings_json,v.display_fields_json,v.required_documents_json,v.conditions_text,v.id AS current_version_id,v.created_at AS version_created_at FROM installment_plans p JOIN installment_plan_versions v ON v.plan_id=p.id AND v.version=p.current_version'.$w.' ORDER BY p.updated_at DESC LIMIT :limit OFFSET :offset';
        $s=$this->pdo->prepare($sql);foreach($params as$k=>$v)$s->bindValue($k,$v);$s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$s->execute();
        return['items'=>array_map([$this,'decodePlan'],$s->fetchAll()),'page'=>$page,'per_page'=>$perPage,'total'=>$total];
    }

    public function getPlan(string $id): ?array
    {
        $s=$this->pdo->prepare('SELECT p.*,v.settings_json,v.display_fields_json,v.required_documents_json,v.conditions_text,v.id AS current_version_id,v.created_at AS version_created_at FROM installment_plans p JOIN installment_plan_versions v ON v.plan_id=p.id AND v.version=p.current_version WHERE p.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return is_array($r)?$this->decodePlan($r):null;
    }

    public function getPlanVersion(string $id): ?array
    {
        $s=$this->pdo->prepare('SELECT v.*,p.code,p.name,p.status AS plan_status FROM installment_plan_versions v JOIN installment_plans p ON p.id=v.plan_id WHERE v.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return is_array($r)?$this->decodeVersion($r):null;
    }

    public function createPlan(string $actor,string $code,string $name,string $type,array $settings,array $displayFields=[],array $requiredDocuments=[],string $conditions=''): array
    {
        $id=RandomId::make();$versionId=RandomId::make();$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO installment_plans (id,code,name,type,status,current_version,created_by_user_id) VALUES (:id,:code,:name,:type,:status,1,:actor)');$s->execute([':id'=>$id,':code'=>$code,':name'=>$name,':type'=>$type,':status'=>'draft',':actor'=>$actor]);
            $v=$this->pdo->prepare('INSERT INTO installment_plan_versions (id,plan_id,version,type,settings_json,display_fields_json,required_documents_json,conditions_text,created_by_user_id) VALUES (:id,:plan_id,1,:type,:settings,:display_fields,:required_documents,:conditions,:actor)');
            $v->execute([':id'=>$versionId,':plan_id'=>$id,':type'=>$type,':settings'=>Json::encode($settings),':display_fields'=>Json::encode($displayFields),':required_documents'=>Json::encode($requiredDocuments),':conditions'=>$conditions!==''?$conditions:null,':actor'=>$actor]);
            $this->pdo->commit();return $this->getPlan($id)??throw new RuntimeException('طرح پس از ثبت قابل بازیابی نیست.');
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function revisePlan(string $id,string $actor,string $name,string $type,array $settings,array $displayFields=[],array $requiredDocuments=[],string $conditions=''): array
    {
        $plan=$this->getPlan($id);if($plan===null)throw new RuntimeException('طرح پیدا نشد.');$next=(int)$plan['current_version']+1;$versionId=RandomId::make();$this->pdo->beginTransaction();
        try{
            $v=$this->pdo->prepare('INSERT INTO installment_plan_versions (id,plan_id,version,type,settings_json,display_fields_json,required_documents_json,conditions_text,created_by_user_id) VALUES (:id,:plan_id,:version,:type,:settings,:display_fields,:required_documents,:conditions,:actor)');
            $v->execute([':id'=>$versionId,':plan_id'=>$id,':version'=>$next,':type'=>$type,':settings'=>Json::encode($settings),':display_fields'=>Json::encode($displayFields),':required_documents'=>Json::encode($requiredDocuments),':conditions'=>$conditions!==''?$conditions:null,':actor'=>$actor]);
            $p=$this->pdo->prepare('UPDATE installment_plans SET name=:name,type=:type,current_version=:version WHERE id=:id');$p->execute([':name'=>$name,':type'=>$type,':version'=>$next,':id'=>$id]);
            $this->pdo->commit();return $this->getPlan($id)??throw new RuntimeException('نسخه جدید طرح قابل بازیابی نیست.');
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function setPlanStatus(string $id,string $status): array
    {
        $s=$this->pdo->prepare('UPDATE installment_plans SET status=:status WHERE id=:id');$s->execute([':status'=>$status,':id'=>$id]);if($s->rowCount()===0&&$this->getPlan($id)===null)throw new RuntimeException('طرح پیدا نشد.');return $this->getPlan($id)??throw new RuntimeException('طرح قابل بازیابی نیست.');
    }

    public function createCalculation(string $actor,array $plan,float $amount,int $installments,int $interval,?float $commission,array $result): array
    {
        $id=RandomId::make();$input=['amount'=>$amount,'installments'=>$installments,'interval'=>$interval,'commission_override'=>$commission];$resultJson=Json::encode($result);$hash=hash('sha256',$resultJson);
        $s=$this->pdo->prepare('INSERT INTO calculation_runs (id,plan_id,plan_version_id,actor_user_id,input_amount,installment_count,installment_interval,commission_override,input_json,result_json,result_hash) VALUES (:id,:plan_id,:version_id,:actor,:amount,:count,:interval,:commission,:input,:result,:hash)');
        $s->execute([':id'=>$id,':plan_id'=>$plan['id'],':version_id'=>$plan['current_version_id'],':actor'=>$actor,':amount'=>$amount,':count'=>$installments,':interval'=>$interval,':commission'=>$commission,':input'=>Json::encode($input),':result'=>$resultJson,':hash'=>$hash]);
        return['id'=>$id,'plan_id'=>$plan['id'],'plan_version_id'=>$plan['current_version_id'],'input'=>$input,'result'=>$result,'result_hash'=>$hash];
    }

    public function findRequestByIdempotency(string $actor,string $key): ?array
    {
        $s=$this->pdo->prepare('SELECT id FROM loan_requests WHERE created_by_user_id=:actor AND idempotency_key=:key LIMIT 1');$s->execute([':actor'=>$actor,':key'=>$key]);$id=$s->fetchColumn();return is_string($id)?$this->getRequest($id):null;
    }

    /** @param array<string,mixed> $d */
    public function insertRequest(string $actor,array $d,array $plan,array $calculation,array $schedule,bool $submitted,string $idempotencyKey=''): array
    {
        $id=RandomId::make();$requestNo='LN-'.date('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8));$r=$calculation['result'];$status=$submitted?'under_review':'draft';$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO loan_requests (id,request_no,idempotency_key,applicant_user_id,acceptor_user_id,created_by_user_id,merchant_ref,assigned_bank_user_id,plan_id,plan_version_id,calculation_run_id,status,full_name,father_name,certificate_number,national_code,mobile_number,phone_number,birth_date,occupation,residential_address,postal_code,work_address,product_name,order_items_json,order_total,requested_amount,facility_amount,prepayment,wallet_charge,acceptor_payment_amount,installment_interval,installment_count,installment_amount,total_payment,profit,guarantee_check_principal,guarantee_check_total,first_due_date,submitted_at) VALUES (:id,:request_no,:idem,:applicant,:acceptor_user,:actor,:merchant,:bank_user,:plan_id,:version_id,:calc_id,:status,:full_name,:father_name,:certificate,:national,:mobile,:phone,:birth_date,:occupation,:residential,:postal,:work,:product,:items,:order_total,:requested,:facility,:prepayment,:wallet,:acceptor,:interval,:count,:installment_amount,:total_payment,:profit,:gprincipal,:gtotal,:first_due,'.($submitted?'NOW(6)':'NULL').')');
            $s->execute([
                ':id'=>$id,':request_no'=>$requestNo,':idem'=>$idempotencyKey!==''?$idempotencyKey:null,':applicant'=>$d['applicant_user_id']??null,':acceptor_user'=>$d['acceptor_user_id']??null,':actor'=>$actor,':merchant'=>$d['merchant_ref']!==''?$d['merchant_ref']:null,':bank_user'=>$d['assigned_bank_user_id']!==''?$d['assigned_bank_user_id']:null,':plan_id'=>$plan['id'],':version_id'=>$plan['current_version_id'],':calc_id'=>$calculation['id'],':status'=>$status,
                ':full_name'=>$d['full_name']!==''?$d['full_name']:null,':father_name'=>$d['father_name']!==''?$d['father_name']:null,':certificate'=>$d['certificate_number']!==''?$d['certificate_number']:null,':national'=>$d['national_code']!==''?$d['national_code']:null,':mobile'=>$d['mobile_number']!==''?$d['mobile_number']:null,':phone'=>$d['phone_number']!==''?$d['phone_number']:null,':birth_date'=>$d['birth_date'],':occupation'=>$d['occupation']!==''?$d['occupation']:null,':residential'=>$d['residential_address']!==''?$d['residential_address']:null,':postal'=>$d['postal_code']!==''?$d['postal_code']:null,':work'=>$d['work_address']!==''?$d['work_address']:null,':product'=>$d['product_name']!==''?$d['product_name']:null,':items'=>Json::encode($d['order_items']),':order_total'=>$d['order_total'],':requested'=>$d['requested_amount'],
                ':facility'=>(float)($r['loanAmount']??0),':prepayment'=>(float)($r['prepayment']??0),':wallet'=>(float)($r['walletCharge']??0),':acceptor'=>(float)($r['acceptorPayment']??0),':interval'=>$d['installment_interval'],':count'=>$d['installment_count'],':installment_amount'=>(float)($r['installmentAmount']??0),':total_payment'=>(float)($r['totalPayment']??0),':profit'=>(float)($r['profit']??0),':gprincipal'=>(float)($r['guaranteeCheckPrincipal']??0),':gtotal'=>(float)($r['guaranteeCheckTotal']??0),':first_due'=>$d['first_due_date'],
            ]);
            $this->insertHistory($id,null,'draft',$actor,'ایجاد پرونده','api');
            if($submitted){$this->insertHistory($id,'draft','submitted',$actor,'ثبت نهایی پرونده','api');$this->insertHistory($id,'submitted','under_review',$actor,'ورود به صف بررسی','api');}
            foreach($schedule as$row){$i=$this->pdo->prepare('INSERT INTO installment_schedule (id,request_id,sequence_no,due_date,amount,principal_amount,profit_amount,status) VALUES (:id,:request_id,:sequence,:due_date,:amount,:principal,:profit,:status)');$i->execute([':id'=>RandomId::make(),':request_id'=>$id,':sequence'=>$row['sequence'],':due_date'=>$row['due_date'],':amount'=>$row['amount'],':principal'=>$row['principal_amount'],':profit'=>$row['profit_amount'],':status'=>'scheduled']);}
            $this->recordEvent($id,'request.created',$actor,'allowed',['submitted'=>$submitted,'plan_version_id'=>$plan['current_version_id']]);
            $this->pdo->commit();return $this->getRequest($id)??throw new RuntimeException('پرونده پس از ثبت قابل بازیابی نیست.');
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    /** @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int} */
    public function listRequests(int $page=1,int $perPage=25,?string $status=null,string $search='',?string $principalUserId=null,?array $accessScope=null): array
    {
        $page=max(1,$page);
        $perPage=max(1,min(100,$perPage));
        $where=[];
        $params=[];
        if($status!==null&&$status!==''){$where[]='r.status=:status';$params[':status']=$status;}
        if($search!==''){$where[]='(r.request_no LIKE :q1 OR r.full_name LIKE :q2 OR r.national_code LIKE :q3 OR r.mobile_number LIKE :q4)';$like='%'.$search.'%';$params[':q1']=$like;$params[':q2']=$like;$params[':q3']=$like;$params[':q4']=$like;}
        if($accessScope!==null){$this->appendVisibility($where,$params,$accessScope);}elseif($principalUserId!==null&&$principalUserId!==''){
            $where[]='(r.created_by_user_id=:principal_created OR r.applicant_user_id=:principal_applicant OR r.acceptor_user_id=:principal_acceptor OR r.assigned_bank_user_id=:principal_bank OR r.assigned_operations_user_id=:principal_ops)';
            foreach(['created','applicant','acceptor','bank','ops'] as $x)$params[':principal_'.$x]=$principalUserId;
        }
        $w=$where?' WHERE '.implode(' AND ',$where):'';
        $c=$this->pdo->prepare('SELECT COUNT(*) FROM loan_requests r'.$w);
        $c->execute($params);
        $total=(int)$c->fetchColumn();
        $sql='SELECT r.*,p.name AS plan_name,v.type AS plan_type,v.version AS plan_version FROM loan_requests r JOIN installment_plans p ON p.id=r.plan_id JOIN installment_plan_versions v ON v.id=r.plan_version_id'.$w.' ORDER BY r.updated_at DESC LIMIT :limit OFFSET :offset';
        $s=$this->pdo->prepare($sql);
        foreach($params as$k=>$v)$s->bindValue($k,$v);
        $s->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $s->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);
        $s->execute();
        return['items'=>array_map([$this,'decodeRequest'],$s->fetchAll()),'page'=>$page,'per_page'=>$perPage,'total'=>$total];
    }

    public function getRequest(string $id): ?array
    {
        $s=$this->pdo->prepare('SELECT r.*,p.name AS plan_name,p.code AS plan_code,v.type AS plan_type,v.version AS plan_version,v.settings_json,v.required_documents_json,cr.result_json AS calculation_result_json,cr.result_hash AS calculation_result_hash FROM loan_requests r JOIN installment_plans p ON p.id=r.plan_id JOIN installment_plan_versions v ON v.id=r.plan_version_id LEFT JOIN calculation_runs cr ON cr.id=r.calculation_run_id WHERE r.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return is_array($r)?$this->decodeRequest($r):null;
    }

    public function updateDraft(string $id,array $fields,string $actor): array
    {
        $request=$this->getRequest($id);if($request===null)throw new RuntimeException('پرونده پیدا نشد.');if($request['status']!=='draft'&&$request['status']!=='need_correction')throw new RuntimeException('ویرایش اطلاعات فقط در پیش‌نویس یا وضعیت نقص مدارک مجاز است.');
        $allowed=['full_name','father_name','certificate_number','national_code','mobile_number','phone_number','birth_date','occupation','residential_address','postal_code','work_address','product_name','merchant_ref','assigned_bank_user_id'];$set=[];$params=[':id'=>$id];foreach($allowed as$k){if(array_key_exists($k,$fields)){$ph=':f_'.$k;$set[]=$k.'='.$ph;$params[$ph]=$fields[$k]!==''?$fields[$k]:null;}}
        if($set!==[]){$s=$this->pdo->prepare('UPDATE loan_requests SET '.implode(',',$set).' WHERE id=:id');$s->execute($params);$this->recordEvent($id,'request.updated',$actor,'allowed',['fields'=>array_keys($fields)]);}return $this->getRequest($id)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
    }

    public function setAssignedBankUser(string $id,?string $bankUserId): void
    { $s=$this->pdo->prepare('UPDATE loan_requests SET assigned_bank_user_id=:bank WHERE id=:id');$s->execute([':bank'=>$bankUserId,':id'=>$id]); }

    public function applyTransition(string $id,string $from,string $to,string $actor,string $reason,string $source='api',?string $assignedBankUserId=null): array
    {
        $this->pdo->beginTransaction();
        try{
            $sql='UPDATE loan_requests SET status=:to_status, assigned_bank_user_id=CASE WHEN :bank_supplied=1 THEN :bank_user ELSE assigned_bank_user_id END, submitted_at=CASE WHEN :submitted_status = \'submitted\' AND submitted_at IS NULL THEN NOW(6) ELSE submitted_at END, granted_at=CASE WHEN :granted_status = \'granted\' THEN NOW(6) ELSE granted_at END, rejected_at=CASE WHEN :to_rejected = \'rejected\' THEN NOW(6) ELSE rejected_at END, cancelled_at=CASE WHEN :to_cancelled = \'cancelled\' THEN NOW(6) ELSE cancelled_at END, archived_at=CASE WHEN :to_archived = \'archived\' THEN NOW(6) ELSE archived_at END, last_transition_at=NOW(6), row_version=row_version+1 WHERE id=:id AND status=:from_status';
            $s=$this->pdo->prepare($sql);
            $s->execute([
                ':to_status'=>$to,
                ':bank_supplied'=>$assignedBankUserId!==null?1:0,
                ':bank_user'=>$assignedBankUserId!==null&&trim($assignedBankUserId)!==''?trim($assignedBankUserId):null,
                ':submitted_status'=>$to,
                ':granted_status'=>$to,
                ':to_rejected'=>$to,
                ':to_cancelled'=>$to,
                ':to_archived'=>$to,
                ':id'=>$id,
                ':from_status'=>$from,
            ]);
            if($s->rowCount()!==1)throw new RuntimeException('وضعیت پرونده هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            $this->insertHistory($id,$from,$to,$actor,$reason,$source);
            $this->recordEvent($id,'request.transition',$actor,'allowed',['from'=>$from,'to'=>$to,'reason'=>$reason,'source'=>$source,'assigned_bank_user_changed'=>$assignedBankUserId!==null]);
            $this->notifyRequestParticipants($id,$actor,'request.status_changed','تغییر وضعیت پرونده','وضعیت پرونده به مرحله جدید تغییر کرد.');
            $this->queueOutbox('request.status_changed','loan_request',$id,'notifications',['request_id'=>$id,'from'=>$from,'to'=>$to,'reason'=>$reason,'source'=>$source]);
            $this->pdo->commit();
            return$this->getRequest($id)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function recordEvent(?string $requestId,string $type,?string $actor,string $result,array $details=[]): void
    { $s=$this->pdo->prepare('INSERT INTO loan_request_events (id,request_id,event_type,actor_user_id,result,details_json) VALUES (:id,:request_id,:type,:actor,:result,:details)');$s->execute([':id'=>RandomId::make(),':request_id'=>$requestId,':type'=>$type,':actor'=>$actor,':result'=>$result,':details'=>$details!==[]?Json::encode($details):null]); }

    private function insertHistory(string $requestId,?string $from,string $to,string $actor,string $reason,string $source): void
    { $s=$this->pdo->prepare('INSERT INTO loan_request_status_history (id,request_id,from_status,to_status,actor_user_id,reason,source) VALUES (:id,:request_id,:from_status,:to_status,:actor,:reason,:source)');$s->execute([':id'=>RandomId::make(),':request_id'=>$requestId,':from_status'=>$from,':to_status'=>$to,':actor'=>$actor,':reason'=>$reason!==''?$reason:null,':source'=>$source]); }

    public function history(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM loan_request_status_history WHERE request_id=:id ORDER BY created_at ASC,id ASC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }
    public function events(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM loan_request_events WHERE request_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$requestId]);$rows=$s->fetchAll();foreach($rows as&$r){$r['details']=Json::decode((string)($r['details_json']??''));unset($r['details_json']);}return$rows; }
    public function schedule(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM installment_schedule WHERE request_id=:id ORDER BY sequence_no ASC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }
    public function guarantors(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM loan_request_guarantors WHERE request_id=:id ORDER BY created_at ASC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }
    public function replaceGuarantors(string $requestId,array $items,?string $actor=null): void
    {
        $this->pdo->beginTransaction();
        try{
            $d=$this->pdo->prepare('DELETE FROM loan_request_guarantors WHERE request_id=:id');
            $d->execute([':id'=>$requestId]);
            $s=$this->pdo->prepare('INSERT INTO loan_request_guarantors (id,request_id,full_name,national_code,mobile_number,relation_label) VALUES (:id,:request_id,:full_name,:national_code,:mobile_number,:relation_label)');
            foreach($items as$g){
                if(!is_array($g)||trim((string)($g['full_name']??''))==='')continue;
                $s->execute([
                    ':id'=>RandomId::make(),
                    ':request_id'=>$requestId,
                    ':full_name'=>trim((string)$g['full_name']),
                    ':national_code'=>trim((string)($g['national_code']??''))?:null,
                    ':mobile_number'=>trim((string)($g['mobile_number']??''))?:null,
                    ':relation_label'=>trim((string)($g['relation_label']??''))?:null,
                ]);
            }
            $this->recordEvent($requestId,'request.guarantors.updated',$actor,'allowed',['count'=>count($items)]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function messages(string $requestId,bool $includeInternal=false): array
    {
        $sql='SELECT * FROM request_messages WHERE request_id=:id'.($includeInternal?'':" AND visibility='participants'").' ORDER BY created_at ASC,id ASC';
        $s=$this->pdo->prepare($sql);$s->execute([':id'=>$requestId]);$rows=$s->fetchAll();
        foreach($rows as&$row){$row['attachment_document_ids']=Json::decode((string)($row['attachment_document_ids_json']??''));unset($row['attachment_document_ids_json']);}
        return$rows;
    }

    public function addMessage(string $requestId,string $actor,string $role,string $message,string $visibility,array $attachments=[]): array
    {
        $id=RandomId::make();$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO request_messages (id,request_id,sender_user_id,sender_role,message,visibility,attachment_document_ids_json) VALUES (:id,:request_id,:actor,:role,:message,:visibility,:attachments)');
            $s->execute([':id'=>$id,':request_id'=>$requestId,':actor'=>$actor,':role'=>$role!==''?$role:null,':message'=>$message,':visibility'=>$visibility,':attachments'=>$attachments!==[]?Json::encode($attachments):null]);
            $this->recordEvent($requestId,$visibility==='internal'?'request.internal_message.created':'request.message.created',$actor,'allowed',['message_id'=>$id,'attachment_count'=>count($attachments)]);
            if($visibility==='participants')$this->notifyRequestParticipants($requestId,$actor,'request.message','پیام جدید پرونده','یک پیام جدید برای پرونده ثبت شده است.');
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $row=$this->pdo->prepare('SELECT * FROM request_messages WHERE id=:id');$row->execute([':id'=>$id]);$r=$row->fetch();
        if(!is_array($r))throw new RuntimeException('پیام ثبت شد اما قابل بازیابی نیست.');$r['attachment_document_ids']=Json::decode((string)($r['attachment_document_ids_json']??''));unset($r['attachment_document_ids_json']);return$r;
    }

    public function comments(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM request_comments WHERE request_id=:id ORDER BY created_at ASC,id ASC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }

    public function addComment(string $requestId,string $actor,string $comment): array
    {
        $id=RandomId::make();$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare("INSERT INTO request_comments (id,request_id,author_user_id,comment,visibility) VALUES (:id,:request_id,:actor,:comment,'internal')");$s->execute([':id'=>$id,':request_id'=>$requestId,':actor'=>$actor,':comment'=>$comment]);
            $this->recordEvent($requestId,'request.internal_note.created',$actor,'allowed',['comment_id'=>$id]);$this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $q=$this->pdo->prepare('SELECT * FROM request_comments WHERE id=:id');$q->execute([':id'=>$id]);$row=$q->fetch();return is_array($row)?$row:throw new RuntimeException('یادداشت قابل بازیابی نیست.');
    }

    public function assignments(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM loan_request_assignments WHERE request_id=:id ORDER BY assigned_at DESC,id DESC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }

    public function assign(string $requestId,string $type,string $userId,string $actor,string $reason=''): array
    {
        $request=$this->getRequest($requestId);if($request===null)throw new RuntimeException('پرونده پیدا نشد.');$this->pdo->beginTransaction();
        try{
            $release=$this->pdo->prepare("UPDATE loan_request_assignments SET status='released',released_at=NOW(6) WHERE request_id=:id AND assignment_type=:type AND status='active'");$release->execute([':id'=>$requestId,':type'=>$type]);
            $assignmentId=RandomId::make();$ins=$this->pdo->prepare("INSERT INTO loan_request_assignments (id,request_id,assignment_type,assigned_user_id,assigned_by_user_id,status,reason) VALUES (:id,:request_id,:type,:user_id,:actor,'active',:reason)");$ins->execute([':id'=>$assignmentId,':request_id'=>$requestId,':type'=>$type,':user_id'=>$userId,':actor'=>$actor,':reason'=>$reason!==''?$reason:null]);
            $column=$type==='bank_expert'?'assigned_bank_user_id':'assigned_operations_user_id';$u=$this->pdo->prepare('UPDATE loan_requests SET `'.$column.'`=:user_id,row_version=row_version+1 WHERE id=:id');$u->execute([':user_id'=>$userId,':id'=>$requestId]);
            $this->recordEvent($requestId,'request.assignment.changed',$actor,'allowed',['assignment_type'=>$type,'assigned_user_id'=>$userId,'reason'=>$reason]);
            $this->queueNotification($userId,'request.assigned','ارجاع پرونده','یک پرونده جدید به شما ارجاع شد.','/loans/requests/'.$requestId,['request_id'=>$requestId,'assignment_type'=>$type]);
            $this->pdo->commit();return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function setDocumentReceipt(string $requestId,string $status,?string $receivedAt,string $note,string $actor): array
    {
        if($this->getRequest($requestId)===null)throw new RuntimeException('پرونده پیدا نشد.');$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('UPDATE loan_requests SET document_receipt_status=:status,document_received_at=:received_at,document_receipt_note=:note,document_received_by_user_id=:actor,row_version=row_version+1 WHERE id=:id');
            $s->execute([':status'=>$status,':received_at'=>$receivedAt,':note'=>$note!==''?$note:null,':actor'=>$actor,':id'=>$requestId]);
            $this->recordEvent($requestId,'request.document_receipt.updated',$actor,'allowed',['status'=>$status,'received_at'=>$receivedAt,'note'=>$note]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
    }

    public function bankReviews(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM bank_reviews WHERE request_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$requestId]);$rows=$s->fetchAll();foreach($rows as&$r){$r['metadata']=Json::decode((string)($r['metadata_json']??''));unset($r['metadata_json']);}return$rows; }

    public function addBankReview(string $requestId,string $actor,string $status,string $reason,string $note,string $externalReference=''): array
    {
        $id=RandomId::make();$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO bank_reviews (id,request_id,reviewer_user_id,status,reason,note,external_reference,metadata_json) VALUES (:id,:request_id,:actor,:status,:reason,:note,:external,NULL)');$s->execute([':id'=>$id,':request_id'=>$requestId,':actor'=>$actor,':status'=>$status,':reason'=>$reason!==''?$reason:null,':note'=>$note!==''?$note:null,':external'=>$externalReference!==''?$externalReference:null]);
            $this->recordEvent($requestId,'request.bank_review.created',$actor,'allowed',['review_id'=>$id,'status'=>$status,'reason'=>$reason]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $q=$this->pdo->prepare('SELECT * FROM bank_reviews WHERE id=:id');$q->execute([':id'=>$id]);$row=$q->fetch();if(!is_array($row))throw new RuntimeException('بررسی بانک قابل بازیابی نیست.');$row['metadata']=Json::decode((string)($row['metadata_json']??''));unset($row['metadata_json']);return$row;
    }

    public function grantRecord(string $requestId): ?array
    { $s=$this->pdo->prepare('SELECT * FROM grant_records WHERE request_id=:id LIMIT 1');$s->execute([':id'=>$requestId]);$row=$s->fetch();if(!is_array($row))return null;$row['metadata']=Json::decode((string)($row['metadata_json']??''));unset($row['metadata_json']);return$row; }

    public function grant(string $requestId,string $actor,float $amount,string $grantedAt,string $loanNumber,string $contractNumber,array $metadata=[]): array
    {
        $request=$this->getRequest($requestId);if($request===null)throw new RuntimeException('پرونده پیدا نشد.');if((string)$request['status']!=='docs_verified')throw new RuntimeException('ثبت اعطا فقط پس از تأیید مدارک مجاز است.');
        $this->pdo->beginTransaction();
        try{
            $existing=$this->grantRecord($requestId);if($existing!==null)throw new RuntimeException('اعطای این پرونده قبلاً ثبت شده است.');
            $id=RandomId::make();$s=$this->pdo->prepare("INSERT INTO grant_records (id,request_id,grant_status,loan_number,contract_number,granted_amount,granted_at,recorded_by_user_id,metadata_json) VALUES (:id,:request_id,'recorded',:loan_no,:contract_no,:amount,:granted_at,:actor,:metadata)");
            $s->execute([':id'=>$id,':request_id'=>$requestId,':loan_no'=>$loanNumber!==''?$loanNumber:null,':contract_no'=>$contractNumber!==''?$contractNumber:null,':amount'=>$amount,':granted_at'=>$grantedAt,':actor'=>$actor,':metadata'=>$metadata!==[]?Json::encode($metadata):null]);
            $u=$this->pdo->prepare("UPDATE loan_requests SET status='granted',granted_at=:granted_at,last_transition_at=NOW(6),row_version=row_version+1 WHERE id=:id AND status='docs_verified'");$u->execute([':granted_at'=>$grantedAt,':id'=>$requestId]);if($u->rowCount()!==1)throw new RuntimeException('وضعیت پرونده هم‌زمان تغییر کرده است.');
            $this->insertHistory($requestId,'docs_verified','granted',$actor,'ثبت اعطای اعتبار','api');$this->recordEvent($requestId,'request.grant.recorded',$actor,'allowed',['grant_id'=>$id,'amount'=>$amount,'granted_at'=>$grantedAt,'loan_number'=>$loanNumber,'contract_number'=>$contractNumber]);
            $this->notifyRequestParticipants($requestId,$actor,'request.granted','اعطای اعتبار ثبت شد','وضعیت پرونده به «اعطا شده» تغییر کرد.');$this->queueOutbox('request.granted','loan_request',$requestId,'notifications',['request_id'=>$requestId,'grant_id'=>$id,'amount'=>$amount]);
            $this->pdo->commit();return['request'=>$this->getRequest($requestId),'grant'=>$this->grantRecord($requestId)];
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function bankSettlementHistory(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM bank_settlement_history WHERE request_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }

    public function recordBankSettlement(string $requestId,string $status,?string $settledAt,string $note,string $actor): array
    {
        $request=$this->getRequest($requestId);if($request===null)throw new RuntimeException('پرونده پیدا نشد.');if((string)$request['status']!=='granted'&&$status==='recorded')throw new RuntimeException('ثبت تسویه بانک فقط برای پرونده اعطاشده مجاز است.');
        $this->pdo->beginTransaction();try{
            $u=$this->pdo->prepare('UPDATE loan_requests SET bank_settlement_status=:status,bank_settlement_note=:note,bank_settled_at=:settled_at,bank_settled_by_user_id=:actor,bank_settlement_updated_at=NOW(6),row_version=row_version+1 WHERE id=:id');$u->execute([':status'=>$status,':note'=>$note!==''?$note:null,':settled_at'=>$settledAt,':actor'=>$actor,':id'=>$requestId]);
            $id=RandomId::make();$h=$this->pdo->prepare('INSERT INTO bank_settlement_history (id,request_id,status,settled_at,note,actor_user_id) VALUES (:id,:request_id,:status,:settled_at,:note,:actor)');$h->execute([':id'=>$id,':request_id'=>$requestId,':status'=>$status,':settled_at'=>$settledAt,':note'=>$note!==''?$note:null,':actor'=>$actor]);
            $this->recordEvent($requestId,'request.bank_settlement.updated',$actor,'allowed',['status'=>$status,'settled_at'=>$settledAt,'note'=>$note]);$this->queueOutbox('request.bank_settlement.updated','loan_request',$requestId,'finance',['request_id'=>$requestId,'status'=>$status,'settled_at'=>$settledAt]);$this->pdo->commit();return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function merchantSettlementHistory(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM merchant_settlement_history WHERE request_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }

    public function recordMerchantSettlement(string $requestId,string $status,?string $settlementDate,?string $cycleId,string $note,string $actor): array
    {
        $request=$this->getRequest($requestId);if($request===null)throw new RuntimeException('پرونده پیدا نشد.');if((string)$request['status']!=='granted'&&$status!=='not_started')throw new RuntimeException('تسویه پذیرنده فقط برای پرونده اعطاشده قابل انجام است.');
        $this->pdo->beginTransaction();try{
            $u=$this->pdo->prepare('UPDATE loan_requests SET settlement_status=:status,settlement_date=:settlement_date,settlement_cycle_id=:cycle_id,settlement_updated_by_user_id=:actor,row_version=row_version+1 WHERE id=:id');$u->execute([':status'=>$status,':settlement_date'=>$settlementDate,':cycle_id'=>$cycleId,':actor'=>$actor,':id'=>$requestId]);
            $id=RandomId::make();$h=$this->pdo->prepare('INSERT INTO merchant_settlement_history (id,request_id,status,settlement_date,cycle_id,note,actor_user_id) VALUES (:id,:request_id,:status,:settlement_date,:cycle_id,:note,:actor)');$h->execute([':id'=>$id,':request_id'=>$requestId,':status'=>$status,':settlement_date'=>$settlementDate,':cycle_id'=>$cycleId,':note'=>$note!==''?$note:null,':actor'=>$actor]);
            $this->recordEvent($requestId,'request.merchant_settlement.updated',$actor,'allowed',['status'=>$status,'settlement_date'=>$settlementDate,'cycle_id'=>$cycleId,'note'=>$note]);$this->queueOutbox('request.merchant_settlement.updated','loan_request',$requestId,'finance',['request_id'=>$requestId,'status'=>$status,'settlement_date'=>$settlementDate,'cycle_id'=>$cycleId]);$this->pdo->commit();return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function financierPaymentHistory(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM financier_payment_history WHERE request_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }

    public function recordFinancierPayment(string $requestId,string $status,string $note,string $actor): array
    {
        $request=$this->getRequest($requestId);if($request===null)throw new RuntimeException('پرونده پیدا نشد.');$this->pdo->beginTransaction();try{
            $u=$this->pdo->prepare('UPDATE loan_requests SET financier_payment_status=:status,financier_payment_note=:note,financier_payment_updated_at=NOW(6),financier_payment_updated_by_user_id=:actor,row_version=row_version+1 WHERE id=:id');$u->execute([':status'=>$status,':note'=>$note!==''?$note:null,':actor'=>$actor,':id'=>$requestId]);
            $id=RandomId::make();$h=$this->pdo->prepare('INSERT INTO financier_payment_history (id,request_id,status,note,actor_user_id) VALUES (:id,:request_id,:status,:note,:actor)');$h->execute([':id'=>$id,':request_id'=>$requestId,':status'=>$status,':note'=>$note!==''?$note:null,':actor'=>$actor]);
            $this->recordEvent($requestId,'request.financier_payment.updated',$actor,'allowed',['status'=>$status,'note'=>$note]);$this->pdo->commit();return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function queueNotification(string $recipient,string $type,string $title,string $body,?string $targetUrl=null,array $metadata=[]): void
    {
        if($recipient==='')return;$s=$this->pdo->prepare("INSERT INTO notifications (id,recipient_user_id,notification_type,channel,title,body,target_url,status,metadata_json) VALUES (:id,:recipient,:type,'panel',:title,:body,:target,'queued',:metadata)");$s->execute([':id'=>RandomId::make(),':recipient'=>$recipient,':type'=>$type,':title'=>$title,':body'=>$body,':target'=>$targetUrl,':metadata'=>$metadata!==[]?Json::encode($metadata):null]);
    }

    public function notifyRequestParticipants(string $requestId,string $excludeUser,string $type,string $title,string $body): void
    {
        $r=$this->getRequest($requestId);if($r===null)return;$ids=[];foreach(['applicant_user_id','acceptor_user_id','assigned_bank_user_id','assigned_operations_user_id']as$key){$id=trim((string)($r[$key]??''));if($id!==''&&$id!==$excludeUser)$ids[$id]=true;}foreach(array_keys($ids)as$id)$this->queueNotification($id,$type,$title,$body,'/loans/requests/'.$requestId,['request_id'=>$requestId]);
    }

    public function queueOutbox(string $eventType,string $aggregateType,?string $aggregateId,string $destination,array $payload): void
    {
        $json=Json::encode($payload);$s=$this->pdo->prepare("INSERT INTO integration_outbox (id,event_type,aggregate_type,aggregate_id,destination,payload_json,payload_hash,status) VALUES (:id,:event_type,:aggregate_type,:aggregate_id,:destination,:payload,:hash,'pending')");$s->execute([':id'=>RandomId::make(),':event_type'=>$eventType,':aggregate_type'=>$aggregateType,':aggregate_id'=>$aggregateId,':destination'=>$destination,':payload'=>$json,':hash'=>hash('sha256',$json)]);
    }

    public function stats(?array $accessScope=null): array
    {
        $plans=(int)$this->pdo->query("SELECT COUNT(*) FROM installment_plans WHERE status='active'")->fetchColumn();$where=[];$params=[];if($accessScope!==null)$this->appendVisibility($where,$params,$accessScope);$w=$where?' WHERE '.implode(' AND ',$where):'';$base=' FROM loan_requests r'.$w;$q=$this->pdo->prepare('SELECT COUNT(*)'.$base);$q->execute($params);$requests=(int)$q->fetchColumn();$extra=$where?' AND ':' WHERE ';$q=$this->pdo->prepare('SELECT COUNT(*) FROM loan_requests r'.$w.$extra."r.status IN ('submitted','under_review','need_correction','expert_approved','docs_completion','docs_verified')");$q->execute($params);$review=(int)$q->fetchColumn();$q=$this->pdo->prepare('SELECT COUNT(*) FROM loan_requests r'.$w.$extra."r.status='granted'");$q->execute($params);$granted=(int)$q->fetchColumn();return['active_plans'=>$plans,'requests'=>$requests,'in_progress'=>$review,'granted'=>$granted];
    }
    private function appendVisibility(array &$where,array &$params,array $scope): void
    {
        $denyGlobal=array_values(array_filter(is_array($scope['deny_global']??null)?$scope['deny_global']:[],static fn($v)=>is_string($v)&&$v!==''));
        if($denyGlobal!==[]){$where[]='1=0';return;}
        $global=array_values(array_filter(is_array($scope['global']??null)?$scope['global']:[],static fn($v)=>is_string($v)&&$v!==''));
        if($global===[]){
            $or=[];$uid=trim((string)($scope['user_id']??''));
            if($uid!==''){$or[]='r.created_by_user_id=:scope_user_created';$or[]='r.applicant_user_id=:scope_user_applicant';$or[]='r.acceptor_user_id=:scope_user_acceptor';$or[]='r.assigned_bank_user_id=:scope_user_bank';$or[]='r.assigned_operations_user_id=:scope_user_ops';foreach(['created','applicant','acceptor','bank','ops']as$x)$params[':scope_user_'.$x]=$uid;}
            foreach(['organizations'=>'organization_ref','branches'=>'branch_ref','merchants'=>'merchant_id','loan_requests'=>'id']as$key=>$column){$ids=array_values(array_unique(array_filter(is_array($scope[$key]??null)?$scope[$key]:[],static fn($v)=>is_string($v)&&$v!=='')));if($ids!==[]){$ph=[];foreach($ids as$i=>$id){$p=':scope_'.$key.'_'.$i;$ph[]=$p;$params[$p]=$id;}$or[]='r.'.$column.' IN ('.implode(',',$ph).')';}}
            $orgs=array_values(array_unique(array_filter(is_array($scope['organizations']??null)?$scope['organizations']:[],static fn($v)=>is_string($v)&&$v!=='')));if($orgs!==[]){$ph=[];foreach($orgs as$i=>$id){$p=':scope_bank_org_'.$i;$ph[]=$p;$params[$p]=$id;}$or[]='r.bank_organization_ref IN ('.implode(',',$ph).')';}
            $where[]=$or===[]?'1=0':'('.implode(' OR ',$or).')';
        }
        foreach(['deny_branches'=>'branch_ref','deny_merchants'=>'merchant_id','deny_loan_requests'=>'id']as$key=>$column){$ids=array_values(array_unique(array_filter(is_array($scope[$key]??null)?$scope[$key]:[],static fn($v)=>is_string($v)&&$v!=='')));if($ids!==[]){$ph=[];foreach($ids as$i=>$id){$p=':'.$key.'_'.$i;$ph[]=$p;$params[$p]=$id;}$where[]='(r.'.$column.' IS NULL OR r.'.$column.' NOT IN ('.implode(',',$ph).'))';}}
        $ids=array_values(array_unique(array_filter(is_array($scope['deny_organizations']??null)?$scope['deny_organizations']:[],static fn($v)=>is_string($v)&&$v!=='')));
        if($ids!==[]){$orgPh=[];$bankPh=[];foreach($ids as$i=>$id){$p1=':deny_org_'.$i;$p2=':deny_bank_org_'.$i;$orgPh[]=$p1;$bankPh[]=$p2;$params[$p1]=$id;$params[$p2]=$id;}$where[]='(r.organization_ref IS NULL OR r.organization_ref NOT IN ('.implode(',',$orgPh).'))';$where[]='(r.bank_organization_ref IS NULL OR r.bank_organization_ref NOT IN ('.implode(',',$bankPh).'))';}
    }

    private function decodePlan(array $r): array { $r['settings']=Json::decode((string)($r['settings_json']??''));$r['display_fields']=Json::decode((string)($r['display_fields_json']??''));$r['required_documents']=Json::decode((string)($r['required_documents_json']??''));unset($r['settings_json'],$r['display_fields_json'],$r['required_documents_json']);return$r; }
    private function decodeVersion(array $r): array { return$this->decodePlan($r); }
    private function decodeRequest(array $r): array { $r['order_items']=Json::decode((string)($r['order_items_json']??''));unset($r['order_items_json']);if(isset($r['settings_json'])){$r['plan_settings']=Json::decode((string)$r['settings_json']);unset($r['settings_json']);}if(isset($r['required_documents_json'])){$r['required_documents']=Json::decode((string)$r['required_documents_json']);unset($r['required_documents_json']);}if(isset($r['calculation_result_json'])){$r['calculation_result']=Json::decode((string)$r['calculation_result_json']);unset($r['calculation_result_json']);}return$r; }
}

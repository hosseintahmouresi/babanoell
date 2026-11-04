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
        if($status!==null&&$status!==''){$where[]='p.status = :status';$params[':status']=$status;}
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
            $s=$this->pdo->prepare('INSERT INTO loan_requests (id,request_no,idempotency_key,applicant_user_id,created_by_user_id,merchant_ref,assigned_bank_user_id,plan_id,plan_version_id,calculation_run_id,status,full_name,father_name,certificate_number,national_code,mobile_number,phone_number,birth_date,occupation,residential_address,postal_code,work_address,product_name,order_items_json,order_total,requested_amount,facility_amount,prepayment,wallet_charge,acceptor_payment_amount,installment_interval,installment_count,installment_amount,total_payment,profit,guarantee_check_principal,guarantee_check_total,first_due_date,submitted_at) VALUES (:id,:request_no,:idem,:applicant,:actor,:merchant,:bank_user,:plan_id,:version_id,:calc_id,:status,:full_name,:father_name,:certificate,:national,:mobile,:phone,:birth_date,:occupation,:residential,:postal,:work,:product,:items,:order_total,:requested,:facility,:prepayment,:wallet,:acceptor,:interval,:count,:installment_amount,:total_payment,:profit,:gprincipal,:gtotal,:first_due,'.($submitted?'NOW(6)':'NULL').')');
            $s->execute([
                ':id'=>$id,':request_no'=>$requestNo,':idem'=>$idempotencyKey!==''?$idempotencyKey:null,':applicant'=>$d['applicant_user_id']??null,':actor'=>$actor,':merchant'=>$d['merchant_ref']!==''?$d['merchant_ref']:null,':bank_user'=>$d['assigned_bank_user_id']!==''?$d['assigned_bank_user_id']:null,':plan_id'=>$plan['id'],':version_id'=>$plan['current_version_id'],':calc_id'=>$calculation['id'],':status'=>$status,
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
    public function listRequests(int $page=1,int $perPage=25,?string $status=null,string $search='',?string $principalUserId=null): array
    {
        $page=max(1,$page);
        $perPage=max(1,min(100,$perPage));
        $where=[];
        $params=[];
        if($status!==null&&$status!==''){$where[]='r.status=:status';$params[':status']=$status;}
        if($search!==''){$where[]='(r.request_no LIKE :q1 OR r.full_name LIKE :q2 OR r.national_code LIKE :q3 OR r.mobile_number LIKE :q4)';$like='%'.$search.'%';$params[':q1']=$like;$params[':q2']=$like;$params[':q3']=$like;$params[':q4']=$like;}
        if($principalUserId!==null&&$principalUserId!==''){
            $where[]='(r.created_by_user_id=:principal_created OR r.applicant_user_id=:principal_applicant OR r.assigned_bank_user_id=:principal_bank)';
            $params[':principal_created']=$principalUserId;
            $params[':principal_applicant']=$principalUserId;
            $params[':principal_bank']=$principalUserId;
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
            $sql='UPDATE loan_requests SET status=:to_status, assigned_bank_user_id=CASE WHEN :bank_supplied=1 THEN :bank_user ELSE assigned_bank_user_id END, submitted_at=CASE WHEN :submitted_status = \'submitted\' AND submitted_at IS NULL THEN NOW(6) ELSE submitted_at END, granted_at=CASE WHEN :granted_status = \'granted\' THEN NOW(6) ELSE granted_at END WHERE id=:id AND status=:from_status';
            $s=$this->pdo->prepare($sql);
            $s->execute([
                ':to_status'=>$to,
                ':bank_supplied'=>$assignedBankUserId!==null?1:0,
                ':bank_user'=>$assignedBankUserId!==null&&trim($assignedBankUserId)!==''?trim($assignedBankUserId):null,
                ':submitted_status'=>$to,
                ':granted_status'=>$to,
                ':id'=>$id,
                ':from_status'=>$from,
            ]);
            if($s->rowCount()!==1)throw new RuntimeException('وضعیت پرونده هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            $this->insertHistory($id,$from,$to,$actor,$reason,$source);
            $this->recordEvent($id,'request.transition',$actor,'allowed',['from'=>$from,'to'=>$to,'reason'=>$reason,'source'=>$source,'assigned_bank_user_changed'=>$assignedBankUserId!==null]);
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

    public function stats(): array
    {
        $plans=(int)$this->pdo->query("SELECT COUNT(*) FROM installment_plans WHERE status='active'")->fetchColumn();$requests=(int)$this->pdo->query('SELECT COUNT(*) FROM loan_requests')->fetchColumn();$review=(int)$this->pdo->query("SELECT COUNT(*) FROM loan_requests WHERE status IN ('submitted','under_review','need_correction','expert_approved','docs_completion','docs_verified')")->fetchColumn();$granted=(int)$this->pdo->query("SELECT COUNT(*) FROM loan_requests WHERE status='granted'")->fetchColumn();return['active_plans'=>$plans,'requests'=>$requests,'in_progress'=>$review,'granted'=>$granted];
    }

    private function decodePlan(array $r): array { $r['settings']=Json::decode((string)($r['settings_json']??''));$r['display_fields']=Json::decode((string)($r['display_fields_json']??''));$r['required_documents']=Json::decode((string)($r['required_documents_json']??''));unset($r['settings_json'],$r['display_fields_json'],$r['required_documents_json']);return$r; }
    private function decodeVersion(array $r): array { return$this->decodePlan($r); }
    private function decodeRequest(array $r): array { $r['order_items']=Json::decode((string)($r['order_items_json']??''));unset($r['order_items_json']);if(isset($r['settings_json'])){$r['plan_settings']=Json::decode((string)$r['settings_json']);unset($r['settings_json']);}if(isset($r['required_documents_json'])){$r['required_documents']=Json::decode((string)$r['required_documents_json']);unset($r['required_documents_json']);}if(isset($r['calculation_result_json'])){$r['calculation_result']=Json::decode((string)$r['calculation_result_json']);unset($r['calculation_result_json']);}return$r; }
}

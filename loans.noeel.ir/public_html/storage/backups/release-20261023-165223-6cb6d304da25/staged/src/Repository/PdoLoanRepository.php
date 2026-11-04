<?php
declare(strict_types=1);
namespace Babanoell\Loans\Repository;

use Babanoell\Loans\Support\Json;
use Babanoell\Loans\Support\RandomId;
use Babanoell\Loans\Domain\PlanRules;
use Babanoell\Loans\Domain\CreditValidationPolicy;
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
        $sql='SELECT p.*,v.settings_json,v.display_fields_json,v.required_documents_json,v.rules_json,v.eligibility_json AS version_eligibility_json,v.configuration_hash,v.published_at AS version_published_at,v.conditions_text,v.id AS current_version_id,v.created_at AS version_created_at FROM installment_plans p JOIN installment_plan_versions v ON v.plan_id=p.id AND v.version=p.current_version'.$w.' ORDER BY p.display_order ASC,p.updated_at DESC LIMIT :limit OFFSET :offset';
        $s=$this->pdo->prepare($sql);foreach($params as$k=>$v)$s->bindValue($k,$v);$s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$s->execute();
        return['items'=>array_map([$this,'decodePlan'],$s->fetchAll()),'page'=>$page,'per_page'=>$perPage,'total'=>$total];
    }

    /** @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int} */
    public function listPlansForContext(int $page,int $perPage,string $context,?string $type=null): array
    {
        if(!isset(PlanRules::VISIBILITY_CONTEXTS[$context]))throw new \InvalidArgumentException('محل استفاده از طرح معتبر نیست.');
        $page=max(1,$page);$perPage=max(1,min(100,$perPage));$where=["p.status='active'"];$params=[];
        if($type!==null&&trim($type)!==''){$where[]='p.type=:type';$params[':type']=trim($type);}
        $sql='SELECT p.*,v.settings_json,v.display_fields_json,v.required_documents_json,v.rules_json,v.eligibility_json AS version_eligibility_json,v.configuration_hash,v.published_at AS version_published_at,v.conditions_text,v.id AS current_version_id,v.created_at AS version_created_at FROM installment_plans p JOIN installment_plan_versions v ON v.plan_id=p.id AND v.version=p.current_version WHERE '.implode(' AND ',$where).' ORDER BY p.display_order ASC,p.updated_at DESC';
        $s=$this->pdo->prepare($sql);$s->execute($params);$visible=[];foreach($s->fetchAll()as$row){$plan=$this->decodePlan($row);if(PlanRules::visibleIn($plan,$context))$visible[]=$plan;}
        $total=count($visible);return['items'=>array_slice($visible,($page-1)*$perPage,$perPage),'page'=>$page,'per_page'=>$perPage,'total'=>$total];
    }

    public function getPlan(string $id): ?array
    {
        $s=$this->pdo->prepare('SELECT p.*,v.settings_json,v.display_fields_json,v.required_documents_json,v.rules_json,v.eligibility_json AS version_eligibility_json,v.configuration_hash,v.published_at AS version_published_at,v.conditions_text,v.id AS current_version_id,v.created_at AS version_created_at FROM installment_plans p JOIN installment_plan_versions v ON v.plan_id=p.id AND v.version=p.current_version WHERE p.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return is_array($r)?$this->decodePlan($r):null;
    }

    public function getPlanVersion(string $id): ?array
    {
        $s=$this->pdo->prepare('SELECT v.*,p.code,p.name,p.status AS plan_status FROM installment_plan_versions v JOIN installment_plans p ON p.id=v.plan_id WHERE v.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return is_array($r)?$this->decodeVersion($r):null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listPlanVersions(string $planId): array
    {
        $s=$this->pdo->prepare('SELECT v.*,p.code,p.name,p.status AS plan_status,p.display_order FROM installment_plan_versions v JOIN installment_plans p ON p.id=v.plan_id WHERE v.plan_id=:id ORDER BY v.version DESC');
        $s->execute([':id'=>$planId]);
        return array_map([$this,'decodeVersion'],$s->fetchAll());
    }

    public function setPlanOrder(string $id,int $order): array
    {
        $s=$this->pdo->prepare('UPDATE installment_plans SET display_order=:display_order WHERE id=:id');
        $s->execute([':display_order'=>max(0,$order),':id'=>$id]);
        if($s->rowCount()===0&&$this->getPlan($id)===null)throw new RuntimeException('طرح پیدا نشد.');
        return $this->getPlan($id)??throw new RuntimeException('طرح قابل بازیابی نیست.');
    }

    public function setMerchantPlanPermission(string $merchantId,string $planId,string $status,array $limits,?string $actor): array
    {
        if(!in_array($status,['active','inactive'],true))throw new \InvalidArgumentException('وضعیت دسترسی پذیرنده معتبر نیست.');
        $s=$this->pdo->prepare('INSERT INTO merchant_plan_permissions (merchant_id,plan_id,status,limits_json,created_by_user_id) VALUES (:merchant,:plan,:status,:limits,:actor) ON DUPLICATE KEY UPDATE status=VALUES(status),limits_json=VALUES(limits_json),updated_at=NOW(6)');
        $s->execute([':merchant'=>$merchantId,':plan'=>$planId,':status'=>$status,':limits'=>$limits!==[]?Json::encode($limits):null,':actor'=>$actor]);
        return ['merchant_id'=>$merchantId,'plan_id'=>$planId,'status'=>$status,'limits'=>$limits];
    }

    public function merchantPlanPermission(string $merchantId,string $planId): ?array
    {
        $s=$this->pdo->prepare('SELECT * FROM merchant_plan_permissions WHERE merchant_id=:merchant AND plan_id=:plan LIMIT 1');
        $s->execute([':merchant'=>$merchantId,':plan'=>$planId]);$r=$s->fetch();
        if(!is_array($r))return null;$r['limits']=Json::decode((string)($r['limits_json']??''));unset($r['limits_json']);return$r;
    }

    public function merchantCanUsePlan(string $merchantId,array $plan): bool
    {
        $permission=$this->merchantPlanPermission($merchantId,(string)$plan['id']);
        $mode=(string)($plan['rules']['merchant_access_mode']??'all');
        if($mode==='allowlist')return is_array($permission)&&(string)($permission['status']??'')==='active';
        return !is_array($permission)||(string)($permission['status']??'')!=='inactive';
    }

    public function duplicatePlan(string $sourceId,string $actor,string $code,string $name): array
    {
        $source=$this->getPlan($sourceId);if($source===null)throw new RuntimeException('طرح مبدا پیدا نشد.');
        return $this->createPlan($actor,$code,$name,(string)$source['type'],(array)$source['settings'],(array)$source['display_fields'],(array)$source['required_documents'],(string)($source['conditions_text']??''),(array)($source['rules']??[]),(array)($source['eligibility']??[]),(int)($source['display_order']??0));
    }

    public function createPlan(string $actor,string $code,string $name,string $type,array $settings,array $displayFields=[],array $requiredDocuments=[],string $conditions='',array $rules=[],array $eligibility=[],int $displayOrder=0): array
    {
        $id=RandomId::make();$versionId=RandomId::make();$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO installment_plans (id,code,name,type,status,current_version,display_order,eligibility_json,created_by_user_id) VALUES (:id,:code,:name,:type,:status,1,:display_order,:eligibility,:actor)');$s->execute([':id'=>$id,':code'=>$code,':name'=>$name,':type'=>$type,':status'=>'draft',':display_order'=>max(0,$displayOrder),':eligibility'=>$eligibility!==[]?Json::encode($eligibility):null,':actor'=>$actor]);
            $v=$this->pdo->prepare('INSERT INTO installment_plan_versions (id,plan_id,version,type,settings_json,display_fields_json,required_documents_json,rules_json,eligibility_json,configuration_hash,conditions_text,created_by_user_id) VALUES (:id,:plan_id,1,:type,:settings,:display_fields,:required_documents,:rules,:eligibility,:configuration_hash,:conditions,:actor)');
            $v->execute([':id'=>$versionId,':plan_id'=>$id,':type'=>$type,':settings'=>Json::encode($settings),':display_fields'=>Json::encode($displayFields),':required_documents'=>Json::encode($requiredDocuments),':rules'=>Json::encode($rules),':eligibility'=>Json::encode($eligibility),':configuration_hash'=>$this->planConfigurationHash($type,$settings,$displayFields,$requiredDocuments,$rules,$eligibility,$conditions),':conditions'=>$conditions!==''?$conditions:null,':actor'=>$actor]);
            $this->pdo->commit();return $this->getPlan($id)??throw new RuntimeException('طرح پس از ثبت قابل بازیابی نیست.');
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function revisePlan(string $id,string $actor,string $name,string $type,array $settings,array $displayFields=[],array $requiredDocuments=[],string $conditions='',array $rules=[],array $eligibility=[]): array
    {
        $plan=$this->getPlan($id);if($plan===null)throw new RuntimeException('طرح پیدا نشد.');$next=(int)$plan['current_version']+1;$versionId=RandomId::make();$this->pdo->beginTransaction();
        try{
            $v=$this->pdo->prepare('INSERT INTO installment_plan_versions (id,plan_id,version,type,settings_json,display_fields_json,required_documents_json,rules_json,eligibility_json,configuration_hash,conditions_text,created_by_user_id) VALUES (:id,:plan_id,:version,:type,:settings,:display_fields,:required_documents,:rules,:eligibility,:configuration_hash,:conditions,:actor)');
            $v->execute([':id'=>$versionId,':plan_id'=>$id,':version'=>$next,':type'=>$type,':settings'=>Json::encode($settings),':display_fields'=>Json::encode($displayFields),':required_documents'=>Json::encode($requiredDocuments),':rules'=>Json::encode($rules),':eligibility'=>Json::encode($eligibility),':configuration_hash'=>$this->planConfigurationHash($type,$settings,$displayFields,$requiredDocuments,$rules,$eligibility,$conditions),':conditions'=>$conditions!==''?$conditions:null,':actor'=>$actor]);
            $p=$this->pdo->prepare('UPDATE installment_plans SET name=:name,type=:type,current_version=:version,eligibility_json=:eligibility WHERE id=:id');$p->execute([':name'=>$name,':type'=>$type,':version'=>$next,':eligibility'=>$eligibility!==[]?Json::encode($eligibility):null,':id'=>$id]);
            $this->pdo->commit();return $this->getPlan($id)??throw new RuntimeException('نسخه جدید طرح قابل بازیابی نیست.');
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function setPlanStatus(string $id,string $status): array
    {
        $s=$this->pdo->prepare("UPDATE installment_plans SET status=:status,published_at=CASE WHEN :status2='active' THEN COALESCE(published_at,NOW(6)) ELSE published_at END,archived_at=CASE WHEN :status3='archived' THEN NOW(6) WHEN :status4<>'archived' THEN NULL ELSE archived_at END WHERE id=:id");$s->execute([':status'=>$status,':status2'=>$status,':status3'=>$status,':status4'=>$status,':id'=>$id]);if($s->rowCount()===0&&$this->getPlan($id)===null)throw new RuntimeException('طرح پیدا نشد.');if($status==='active'){$v=$this->pdo->prepare('UPDATE installment_plan_versions v JOIN installment_plans p ON p.id=v.plan_id AND p.current_version=v.version SET v.published_at=COALESCE(v.published_at,NOW(6)) WHERE p.id=:id');$v->execute([':id'=>$id]);}return $this->getPlan($id)??throw new RuntimeException('طرح قابل بازیابی نیست.');
    }

    public function createCalculation(string $actor,array $plan,float $amount,int $installments,int $interval,?float $commission,array $result): array
    {
        $id=RandomId::make();$input=['amount'=>$amount,'installments'=>$installments,'interval'=>$interval,'commission_override'=>$commission];$inputJson=Json::encode($input);$inputHash=hash('sha256',$inputJson);$resultJson=Json::encode($result);$hash=hash('sha256',$resultJson);$engine=(string)($plan['rules']['calculation_schema_version']??'v1');$snapshot=$this->planSnapshot($plan);
        $s=$this->pdo->prepare('INSERT INTO calculation_runs (id,plan_id,plan_version_id,actor_user_id,input_amount,installment_count,installment_interval,commission_override,input_json,result_json,result_hash,engine_version,plan_snapshot_json,input_hash,mode) VALUES (:id,:plan_id,:version_id,:actor,:amount,:count,:interval,:commission,:input,:result,:hash,:engine,:snapshot,:input_hash,:mode)');
        $s->execute([':id'=>$id,':plan_id'=>$plan['id'],':version_id'=>$plan['current_version_id'],':actor'=>$actor,':amount'=>$amount,':count'=>$installments,':interval'=>$interval,':commission'=>$commission,':input'=>$inputJson,':result'=>$resultJson,':hash'=>$hash,':engine'=>$engine,':snapshot'=>Json::encode($snapshot),':input_hash'=>$inputHash,':mode'=>'operational']);
        return['id'=>$id,'plan_id'=>$plan['id'],'plan_version_id'=>$plan['current_version_id'],'input'=>$input,'input_hash'=>$inputHash,'result'=>$result,'result_hash'=>$hash,'engine_version'=>$engine,'plan_snapshot'=>$snapshot];
    }

    public function findRequestByIdempotency(string $actor,string $key): ?array
    {
        $s=$this->pdo->prepare('SELECT id FROM loan_requests WHERE created_by_user_id=:actor AND idempotency_key=:key LIMIT 1');$s->execute([':actor'=>$actor,':key'=>$key]);$id=$s->fetchColumn();return is_string($id)?$this->getRequest($id):null;
    }

    /** @param array<string,mixed> $d */
    public function insertRequest(string $actor,array $d,array $plan,array $calculation,array $schedule,bool $submitted,string $idempotencyKey=''): array
    {
        $id=RandomId::make();$requestNo='LN-'.gmdate('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8));$r=$calculation['result'];$status=$submitted?'under_review':'draft';$origin=(string)($d['request_origin']??'admin');$creditStatus=!empty($plan['rules']['requires_bank_validation'])?'pending':'not_required';$identityStatus=!empty($plan['rules']['requires_identity_verification'])?'pending':'not_required';$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO loan_requests (id,request_no,idempotency_key,applicant_user_id,acceptor_user_id,created_by_user_id,merchant_ref,assigned_bank_user_id,plan_id,plan_version_id,calculation_run_id,status,request_origin,credit_validation_status,identity_verification_status,full_name,father_name,certificate_number,national_code,mobile_number,phone_number,birth_date,occupation,residential_address,postal_code,work_address,product_name,order_items_json,order_total,requested_amount,facility_amount,prepayment,wallet_charge,acceptor_payment_amount,installment_interval,installment_count,installment_amount,total_payment,profit,guarantee_check_principal,guarantee_check_total,first_due_date,submitted_at) VALUES (:id,:request_no,:idem,:applicant,:acceptor_user,:actor,:merchant,:bank_user,:plan_id,:version_id,:calc_id,:status,:origin,:credit_status,:identity_status,:full_name,:father_name,:certificate,:national,:mobile,:phone,:birth_date,:occupation,:residential,:postal,:work,:product,:items,:order_total,:requested,:facility,:prepayment,:wallet,:acceptor,:interval,:count,:installment_amount,:total_payment,:profit,:gprincipal,:gtotal,:first_due,'.($submitted?'NOW(6)':'NULL').')');
            $s->execute([
                ':id'=>$id,':request_no'=>$requestNo,':idem'=>$idempotencyKey!==''?$idempotencyKey:null,':applicant'=>trim((string)($d['applicant_user_id']??''))!==''?$d['applicant_user_id']:null,':acceptor_user'=>trim((string)($d['acceptor_user_id']??''))!==''?$d['acceptor_user_id']:null,':actor'=>$actor,':merchant'=>$d['merchant_ref']!==''?$d['merchant_ref']:null,':bank_user'=>$d['assigned_bank_user_id']!==''?$d['assigned_bank_user_id']:null,':plan_id'=>$plan['id'],':version_id'=>$plan['current_version_id'],':calc_id'=>$calculation['id'],':status'=>$status,':origin'=>$origin,':credit_status'=>$creditStatus,':identity_status'=>$identityStatus,
                ':full_name'=>$d['full_name']!==''?$d['full_name']:null,':father_name'=>$d['father_name']!==''?$d['father_name']:null,':certificate'=>$d['certificate_number']!==''?$d['certificate_number']:null,':national'=>$d['national_code']!==''?$d['national_code']:null,':mobile'=>$d['mobile_number']!==''?$d['mobile_number']:null,':phone'=>$d['phone_number']!==''?$d['phone_number']:null,':birth_date'=>$d['birth_date'],':occupation'=>$d['occupation']!==''?$d['occupation']:null,':residential'=>$d['residential_address']!==''?$d['residential_address']:null,':postal'=>$d['postal_code']!==''?$d['postal_code']:null,':work'=>$d['work_address']!==''?$d['work_address']:null,':product'=>$d['product_name']!==''?$d['product_name']:null,':items'=>Json::encode($d['order_items']),':order_total'=>$d['order_total'],':requested'=>$d['requested_amount'],
                ':facility'=>(float)($r['loanAmount']??0),':prepayment'=>(float)($r['prepayment']??0),':wallet'=>(float)($r['walletCharge']??0),':acceptor'=>(float)($r['acceptorPayment']??0),':interval'=>$d['installment_interval'],':count'=>$d['installment_count'],':installment_amount'=>(float)($r['installmentAmount']??0),':total_payment'=>(float)($r['totalPayment']??0),':profit'=>(float)($r['profit']??0),':gprincipal'=>(float)($r['guaranteeCheckPrincipal']??0),':gtotal'=>(float)($r['guaranteeCheckTotal']??0),':first_due'=>$d['first_due_date'],
            ]);
            $requiredSnapshot=PlanRules::requiredDocuments($plan,(string)($d['occupation']??''));
            $planRules=is_array($plan['rules']??null)?$plan['rules']:[];
            $snapshotHash=hash('sha256',Json::encode($this->planSnapshot($plan)));
            $snap=$this->pdo->prepare('UPDATE loan_requests SET required_documents_snapshot_json=:docs,plan_rules_snapshot_json=:rules,plan_snapshot_hash=:hash,merchant_id=:merchant_id WHERE id=:id');
            $snap->execute([':docs'=>Json::encode($requiredSnapshot),':rules'=>Json::encode($planRules),':hash'=>$snapshotHash,':merchant_id'=>trim((string)($d['merchant_id']??''))!==''?(string)$d['merchant_id']:null,':id'=>$id]);
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
        $s=$this->pdo->prepare('SELECT r.*,p.name AS plan_name,p.code AS plan_code,v.type AS plan_type,v.version AS plan_version,v.settings_json,v.required_documents_json,v.rules_json AS version_rules_json,v.eligibility_json AS version_eligibility_json,cr.result_json AS calculation_result_json,cr.result_hash AS calculation_result_hash FROM loan_requests r JOIN installment_plans p ON p.id=r.plan_id JOIN installment_plan_versions v ON v.id=r.plan_version_id LEFT JOIN calculation_runs cr ON cr.id=r.calculation_run_id WHERE r.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return is_array($r)?$this->decodeRequest($r):null;
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

    public function submitForReview(string $requestId,string $actor,string $source='self_service'): array
    {
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->prepare('SELECT status FROM loan_requests WHERE id=:id FOR UPDATE');$lock->execute([':id'=>$requestId]);$from=$lock->fetchColumn();
            if(!is_string($from))throw new RuntimeException('پرونده پیدا نشد.');
            if(!in_array($from,['draft','need_correction'],true))throw new RuntimeException('ارسال پرونده در وضعیت فعلی مجاز نیست.');
            $u=$this->pdo->prepare("UPDATE loan_requests SET status='under_review',submitted_at=COALESCE(submitted_at,NOW(6)),last_transition_at=NOW(6),row_version=row_version+1 WHERE id=:id AND status=:from_status");
            $u->execute([':id'=>$requestId,':from_status'=>$from]);if($u->rowCount()!==1)throw new RuntimeException('وضعیت پرونده هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            if($from==='draft'){$this->insertHistory($requestId,'draft','submitted',$actor,'ثبت نهایی پرونده',$source);$this->insertHistory($requestId,'submitted','under_review',$actor,'ورود به صف بررسی',$source);}
            else{$this->insertHistory($requestId,'need_correction','under_review',$actor,'ارسال مجدد پس از اصلاح',$source);}
            $this->recordEvent($requestId,$from==='draft'?'request.submitted':'request.resubmitted',$actor,'allowed',['from'=>$from,'to'=>'under_review','source'=>$source]);
            $this->notifyRequestParticipants($requestId,$actor,'request.status_changed','تغییر وضعیت پرونده','پرونده برای بررسی ارسال شد.');
            $this->queueOutbox('request.status_changed','loan_request',$requestId,'notifications',['request_id'=>$requestId,'from'=>$from,'to'=>'under_review','source'=>$source]);
            $this->pdo->commit();return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function recordEvent(?string $requestId,string $type,?string $actor,string $result,array $details=[]): void
    { $details=array_merge(\Babanoell\Loans\Platform\RequestContext::auditMeta(),$details);$s=$this->pdo->prepare('INSERT INTO loan_request_events (id,request_id,event_type,actor_user_id,result,details_json) VALUES (:id,:request_id,:type,:actor,:result,:details)');$s->execute([':id'=>RandomId::make(),':request_id'=>$requestId,':type'=>$type,':actor'=>$actor,':result'=>$result,':details'=>Json::encode($details)]); }

    private function insertHistory(string $requestId,?string $from,string $to,string $actor,string $reason,string $source): void
    { $s=$this->pdo->prepare('INSERT INTO loan_request_status_history (id,request_id,from_status,to_status,actor_user_id,reason,source) VALUES (:id,:request_id,:from_status,:to_status,:actor,:reason,:source)');$s->execute([':id'=>RandomId::make(),':request_id'=>$requestId,':from_status'=>$from,':to_status'=>$to,':actor'=>$actor,':reason'=>$reason!==''?$reason:null,':source'=>$source]); }

    public function history(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM loan_request_status_history WHERE request_id=:id ORDER BY created_at ASC,id ASC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }
    public function events(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM loan_request_events WHERE request_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$requestId]);$rows=$s->fetchAll();foreach($rows as&$r){$r['details']=Json::decode((string)($r['details_json']??''));unset($r['details_json']);}return$rows; }
    public function schedule(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM installment_schedule WHERE request_id=:id ORDER BY sequence_no ASC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }
    public function guarantors(string $requestId,bool $includeInactive=false): array
    {
        $sql='SELECT * FROM loan_request_guarantors WHERE request_id=:id'.($includeInactive?'':" AND status='active'").' ORDER BY position_no ASC,created_at ASC,id ASC';
        $s=$this->pdo->prepare($sql);$s->execute([':id'=>$requestId]);return$s->fetchAll();
    }

    public function getGuarantor(string $id): ?array
    {
        $s=$this->pdo->prepare('SELECT g.*,r.request_no,r.status AS request_status,r.applicant_user_id,r.acceptor_user_id,r.created_by_user_id,r.assigned_bank_user_id,r.assigned_operations_user_id,r.organization_ref,r.branch_ref,r.bank_organization_ref,r.merchant_id FROM loan_request_guarantors g JOIN loan_requests r ON r.id=g.request_id WHERE g.id=:id LIMIT 1');
        $s->execute([':id'=>$id]);$row=$s->fetch();return is_array($row)?$row:null;
    }

    public function replaceGuarantors(string $requestId,array $items,?string $actor=null): void
    {
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->prepare("SELECT id FROM loan_requests WHERE id=:id FOR UPDATE");$lock->execute([':id'=>$requestId]);if(!$lock->fetchColumn())throw new RuntimeException('پرونده پیدا نشد.');
            $existing=$this->guarantors($requestId,false);$byId=[];foreach($existing as$row)$byId[(string)$row['id']]=$row;
            $kept=[];$position=0;
            $update=$this->pdo->prepare("UPDATE loan_request_guarantors SET full_name=:full_name,national_code=:national_code,mobile_number=:mobile_number,relation_label=:relation_label,position_no=:position,status='active',removed_at=NULL WHERE id=:id AND request_id=:request_id");
            $insert=$this->pdo->prepare("INSERT INTO loan_request_guarantors (id,request_id,full_name,national_code,mobile_number,relation_label,position_no,status) VALUES (:id,:request_id,:full_name,:national_code,:mobile_number,:relation_label,:position,'active')");
            foreach($items as$g){
                if(!is_array($g)||trim((string)($g['full_name']??''))==='')continue;
                $position++;$givenId=trim((string)($g['id']??''));$id='';
                if($givenId!==''&&isset($byId[$givenId])&&!in_array($givenId,$kept,true))$id=$givenId;
                if($id===''){$id=RandomId::make();$insert->execute([':id'=>$id,':request_id'=>$requestId,':full_name'=>trim((string)$g['full_name']),':national_code'=>trim((string)($g['national_code']??''))?:null,':mobile_number'=>trim((string)($g['mobile_number']??''))?:null,':relation_label'=>trim((string)($g['relation_label']??''))?:null,':position'=>$position]);}
                else{$update->execute([':id'=>$id,':request_id'=>$requestId,':full_name'=>trim((string)$g['full_name']),':national_code'=>trim((string)($g['national_code']??''))?:null,':mobile_number'=>trim((string)($g['mobile_number']??''))?:null,':relation_label'=>trim((string)($g['relation_label']??''))?:null,':position'=>$position]);}
                $kept[]=$id;
            }
            foreach($existing as$row){$id=(string)$row['id'];if(in_array($id,$kept,true))continue;$d=$this->pdo->prepare("UPDATE loan_request_guarantors SET status='inactive',removed_at=NOW(6) WHERE id=:id AND request_id=:request_id AND status='active'");$d->execute([':id'=>$id,':request_id'=>$requestId]);}
            $this->recordEvent($requestId,'request.guarantors.updated',$actor,'allowed',['count'=>count($kept),'guarantor_ids'=>$kept]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function latestCreditCheck(string $requestId): ?array
    {
        $s=$this->pdo->prepare('SELECT * FROM credit_checks WHERE request_id=:id ORDER BY requested_at DESC,id DESC LIMIT 1');$s->execute([':id'=>$requestId]);$row=$s->fetch();if(!is_array($row))return null;$row['result_summary']=Json::decode((string)($row['result_summary_json']??''));unset($row['result_summary_json']);return$row;
    }

    public function recordCreditCheck(string $requestId,string $actor,string $provider,string $status,string $externalReference,?int $score,string $risk,array $summary,string $responseHash): array
    {
        $id=RandomId::make();$completed=in_array($status,['verified','failed','expired'],true);$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO credit_checks (id,request_id,provider,external_report_id,status,score,risk,result_summary_json,response_hash,requested_by_user_id,completed_at) VALUES (:id,:request_id,:provider,:external,:status,:score,:risk,:summary,:hash,:actor,'.($completed?'NOW(6)':'NULL').')');
            $s->execute([':id'=>$id,':request_id'=>$requestId,':provider'=>$provider,':external'=>$externalReference!==''?$externalReference:null,':status'=>$status,':score'=>$score,':risk'=>$risk!==''?$risk:null,':summary'=>$summary!==[]?Json::encode($summary):null,':hash'=>$responseHash,':actor'=>$actor]);
            $u=$this->pdo->prepare("UPDATE loan_requests SET credit_validation_status=:status,credit_validation_provider=:provider,credit_validation_reference=:reference,credit_validation_verified_at=CASE WHEN :verified='verified' THEN NOW(6) ELSE NULL END,credit_validation_check_id=:check_id,credit_report_external_id=:external,credit_score=:score,credit_risk=:risk,row_version=row_version+1 WHERE id=:request_id");
            $u->execute([':status'=>$status,':provider'=>$provider,':reference'=>$externalReference!==''?$externalReference:null,':verified'=>$status,':check_id'=>$id,':external'=>$externalReference!==''?$externalReference:null,':score'=>$score,':risk'=>$risk!==''?$risk:null,':request_id'=>$requestId]);
            if($u->rowCount()!==1)throw new RuntimeException('پرونده برای ثبت اعتبارسنجی پیدا نشد.');
            $this->recordEvent($requestId,'request.credit_validation.updated',$actor,'allowed',['check_id'=>$id,'provider'=>$provider,'status'=>$status,'reference'=>$externalReference,'response_hash'=>$responseHash]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return $this->latestCreditCheck($requestId)??throw new RuntimeException('نتیجه اعتبارسنجی قابل بازیابی نیست.');
    }

    /** Atomically reserves a queued check before reaching an external provider. */
    public function claimProviderVerification(string $verificationId,string $actor):array {
        $this->pdo->beginTransaction();
        try {
            $sql="UPDATE verification_requests SET status='pending',metadata_json=:metadata WHERE id=:id AND status='queued'";
            $s=$this->pdo->prepare($sql);$s->execute([':id'=>$verificationId,':metadata'=>Json::encode(['source'=>'integrations','stage'=>'provider_request_reserved','claimed_by'=>$actor,'claimed_at'=>gmdate('c')])]);
            if($s->rowCount()!==1)throw new RuntimeException('verification_already_dispatched');
            $v=$this->getVerificationRequest($verificationId)??throw new RuntimeException('verification_missing');
            $this->recordEvent((string)$v['request_id'],'request.verification.dispatched',$actor,'allowed',['verification_id'=>$verificationId,'provider'=>(string)$v['provider']]);
            $this->pdo->commit();return $v;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** Stores safe dispatch metadata without ever storing an OTP or credentials. */
    public function noteProviderVerification(string $verificationId,string $actor,array $details,?string $reference=null):array {
        $v=$this->getVerificationRequest($verificationId);if($v===null||(string)$v['status']!=='pending')throw new RuntimeException('verification_not_pending');
        $allowed=['delivery','stage','http_status','code','otp_verified','provider_status','status_checked_at'];
        foreach($details as $key=>$value)if(!in_array($key,$allowed,true)||!is_scalar($value))throw new InvalidArgumentException('فیلد اطلاعات ارائه‌دهنده مجاز نیست.');
        $meta=is_array($v['metadata']??null)?$v['metadata']:[];
        foreach($details as $key=>$value)$meta[$key]=$value;
        $s=$this->pdo->prepare("UPDATE verification_requests SET metadata_json=:meta,provider_reference=COALESCE(:ref,provider_reference) WHERE id=:id AND status='pending'");
        $s->execute([':id'=>$verificationId,':meta'=>Json::encode($meta),':ref'=>$reference]);
        if($s->rowCount()!==1 && ($this->getVerificationRequest($verificationId)['status']??null)!=='pending')throw new RuntimeException('verification_changed_during_update');
        $this->recordEvent((string)$v['request_id'],'request.verification.provider_update',$actor,'allowed',['verification_id'=>$verificationId,'stage'=>$details['stage']??null]);
        return $this->getVerificationRequest($verificationId)??throw new RuntimeException('verification_missing');
    }

    /** @return array<int,array<string,mixed>> */
    public function verificationRequests(string $requestId): array
    {
        $s=$this->pdo->prepare('SELECT * FROM verification_requests WHERE request_id=:id ORDER BY requested_at DESC,id DESC');$s->execute([':id'=>$requestId]);$rows=$s->fetchAll();
        foreach($rows as&$row){$row['metadata']=Json::decode((string)($row['metadata_json']??''));unset($row['metadata_json']);}unset($row);return$rows;
    }

    public function getVerificationRequest(string $id): ?array
    {
        $s=$this->pdo->prepare('SELECT v.*,r.request_no,r.full_name,r.national_code,r.mobile_number,r.status AS request_status,r.plan_rules_snapshot_json FROM verification_requests v JOIN loan_requests r ON r.id=v.request_id WHERE v.id=:id LIMIT 1');$s->execute([':id'=>$id]);$row=$s->fetch();if(!is_array($row))return null;$row['metadata']=Json::decode((string)($row['metadata_json']??''));$row['plan_rules']=Json::decode((string)($row['plan_rules_snapshot_json']??''));unset($row['metadata_json'],$row['plan_rules_snapshot_json']);return$row;
    }

    public function createVerificationRequest(string $requestId,string $type,string $provider,string $idempotencyKey,string $subjectFingerprint,string $requestHash,string $actor,int $ttlMinutes=1440): array
    {
        $old=$this->pdo->prepare('SELECT id,provider,request_hash FROM verification_requests WHERE request_id=:request_id AND verification_type=:type AND idempotency_key=:idem LIMIT 1');$old->execute([':request_id'=>$requestId,':type'=>$type,':idem'=>$idempotencyKey]);$oldRow=$old->fetch();if(is_array($oldRow)){if((string)$oldRow['provider']!==$provider||(string)$oldRow['request_hash']!==$requestHash)throw new RuntimeException('Idempotency-Key قبلاً برای درخواست متفاوتی استفاده شده است.');return$this->getVerificationRequest((string)$oldRow['id'])??throw new RuntimeException('درخواست اعتبارسنجی قابل بازیابی نیست.');}
        $id=RandomId::make();$expiresAt=gmdate('Y-m-d H:i:s',time()+max(5,min(10080,$ttlMinutes))*60);$this->pdo->beginTransaction();try{
            $s=$this->pdo->prepare("INSERT INTO verification_requests (id,request_id,verification_type,provider,status,idempotency_key,subject_fingerprint,request_hash,requested_by_user_id,expires_at) VALUES (:id,:request_id,:type,:provider,'queued',:idem,:fingerprint,:request_hash,:actor,:expires_at)");
            $s->execute([':id'=>$id,':request_id'=>$requestId,':type'=>$type,':provider'=>$provider,':idem'=>$idempotencyKey,':fingerprint'=>$subjectFingerprint,':request_hash'=>$requestHash,':actor'=>$actor,':expires_at'=>$expiresAt]);
            if($type==='identity_match'){$u=$this->pdo->prepare("UPDATE loan_requests SET identity_verification_status='queued',identity_verification_provider=:provider,identity_verification_request_id=:vid,row_version=row_version+1 WHERE id=:id");$u->execute([':provider'=>$provider,':vid'=>$id,':id'=>$requestId]);}
            else{$u=$this->pdo->prepare("UPDATE loan_requests SET credit_validation_status='pending',credit_validation_provider=:provider,row_version=row_version+1 WHERE id=:id");$u->execute([':provider'=>$provider,':id'=>$requestId]);}
            $this->recordEvent($requestId,'request.verification.queued',$actor,'allowed',['verification_id'=>$id,'verification_type'=>$type,'provider'=>$provider,'request_hash'=>$requestHash]);
            $this->queueOutbox('verification.requested','verification_request',$id,'integrations',['verification_id'=>$id,'request_id'=>$requestId,'verification_type'=>$type,'provider'=>$provider]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return$this->getVerificationRequest($id)??throw new RuntimeException('درخواست اعتبارسنجی قابل بازیابی نیست.');
    }

    public function completeVerificationRequest(string $verificationId,string $actor,string $status,string $providerReference,string $responseHash,?int $score,string $risk,array $metadata): array
    {
        $this->pdo->beginTransaction();try{
            $q=$this->pdo->prepare('SELECT * FROM verification_requests WHERE id=:id FOR UPDATE');$q->execute([':id'=>$verificationId]);$v=$q->fetch();if(!is_array($v))throw new RuntimeException('درخواست اعتبارسنجی پیدا نشد.');
            $terminal=in_array((string)$v['status'],['verified','failed','expired','cancelled'],true);if($terminal){if((string)($v['response_hash']??'')===$responseHash){$this->pdo->commit();return$this->getVerificationRequest($verificationId)??$v;}throw new RuntimeException('نتیجه این درخواست اعتبارسنجی قبلاً نهایی شده است.');}
            $u=$this->pdo->prepare('UPDATE verification_requests SET status=:status,provider_reference=:reference,response_hash=:response_hash,score=:score,risk=:risk,metadata_json=:metadata,completed_by_user_id=:actor,completed_at=NOW(6) WHERE id=:id');
            $u->execute([':status'=>$status,':reference'=>$providerReference!==''?$providerReference:null,':response_hash'=>$responseHash,':score'=>$score,':risk'=>$risk!==''?$risk:null,':metadata'=>$metadata!==[]?Json::encode($metadata):null,':actor'=>$actor,':id'=>$verificationId]);
            $requestId=(string)$v['request_id'];$type=(string)$v['verification_type'];$provider=(string)$v['provider'];
            if($type==='identity_match'){
                $r=$this->pdo->prepare("UPDATE loan_requests SET identity_verification_status=:status,identity_verification_provider=:provider,identity_verification_reference=:reference,identity_verification_verified_at=CASE WHEN :verified='verified' THEN NOW(6) ELSE NULL END,identity_verification_request_id=:vid,row_version=row_version+1 WHERE id=:request_id");
                $r->execute([':status'=>$status,':provider'=>$provider,':reference'=>$providerReference!==''?$providerReference:null,':verified'=>$status,':vid'=>$verificationId,':request_id'=>$requestId]);
            }else{
                $checkId=RandomId::make();$c=$this->pdo->prepare('INSERT INTO credit_checks (id,request_id,provider,external_report_id,status,score,risk,result_summary_json,response_hash,requested_by_user_id,completed_at,verification_request_id,proof_hash) VALUES (:id,:request_id,:provider,:external,:status,:score,:risk,:summary,:hash,:actor,NOW(6),:verification_id,:proof_hash)');
                $c->execute([':id'=>$checkId,':request_id'=>$requestId,':provider'=>$provider,':external'=>$providerReference!==''?$providerReference:null,':status'=>$status,':score'=>$score,':risk'=>$risk!==''?$risk:null,':summary'=>$metadata!==[]?Json::encode($metadata):null,':hash'=>$responseHash,':actor'=>$actor,':verification_id'=>$verificationId,':proof_hash'=>$responseHash]);
                $r=$this->pdo->prepare("UPDATE loan_requests SET credit_validation_status=:status,credit_validation_provider=:provider,credit_validation_reference=:reference,credit_validation_verified_at=CASE WHEN :verified='verified' THEN NOW(6) ELSE NULL END,credit_validation_check_id=:check_id,credit_report_external_id=:external,credit_score=:score,credit_risk=:risk,row_version=row_version+1 WHERE id=:request_id");
                $r->execute([':status'=>$status,':provider'=>$provider,':reference'=>$providerReference!==''?$providerReference:null,':verified'=>$status,':check_id'=>$checkId,':external'=>$providerReference!==''?$providerReference:null,':score'=>$score,':risk'=>$risk!==''?$risk:null,':request_id'=>$requestId]);
            }
            $this->recordEvent($requestId,'request.verification.completed',$actor,'allowed',['verification_id'=>$verificationId,'verification_type'=>$type,'provider'=>$provider,'status'=>$status,'provider_reference'=>$providerReference,'response_hash'=>$responseHash]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return$this->getVerificationRequest($verificationId)??throw new RuntimeException('نتیجه اعتبارسنجی قابل بازیابی نیست.');
    }

    /** @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int} */
    public function bankCases(int $page,int $perPage,?string $bankUserId,bool $global,array $filters=[]): array
    {
        $page=max(1,$page);$perPage=max(1,min(100,$perPage));$where=[];$params=[];
        if(!$global){if($bankUserId===null||$bankUserId==='')return['items'=>[],'page'=>$page,'per_page'=>$perPage,'total'=>0];$where[]='r.assigned_bank_user_id=:bank_user';$params[':bank_user']=$bankUserId;}
        elseif(($filters['scope']??'')==='unassigned')$where[]="(r.assigned_bank_user_id IS NULL OR r.assigned_bank_user_id='')";
        $status=trim((string)($filters['status']??''));if($status!==''){$where[]='r.status=:status';$params[':status']=$status;}
        $credit=trim((string)($filters['credit_status']??''));if($credit!==''){$where[]='r.credit_validation_status=:credit_status';$params[':credit_status']=$credit;}
        $identity=trim((string)($filters['identity_status']??''));if($identity!==''){$where[]='r.identity_verification_status=:identity_status';$params[':identity_status']=$identity;}
        $search=trim((string)($filters['q']??''));if($search!==''){$where[]='(r.request_no LIKE :q OR r.full_name LIKE :q OR r.national_code LIKE :q OR r.mobile_number LIKE :q)';$params[':q']='%'.$search.'%';}
        $where[]="r.status NOT IN ('draft','cancelled','archived')";$w=' WHERE '.implode(' AND ',$where);
        $c=$this->pdo->prepare('SELECT COUNT(*) FROM loan_requests r'.$w);$c->execute($params);$total=(int)$c->fetchColumn();
        $sql='SELECT r.id,r.request_no,r.full_name,r.national_code,r.mobile_number,r.status,r.assigned_bank_user_id,r.credit_validation_status,r.credit_validation_provider,r.credit_score,r.credit_risk,r.identity_verification_status,r.identity_verification_provider,r.installment_count,r.granted_at,r.bank_settlement_status,r.bank_settled_at,r.updated_at,r.last_transition_at,p.name AS plan_name FROM loan_requests r JOIN installment_plans p ON p.id=r.plan_id'.$w.' ORDER BY CASE WHEN r.credit_validation_status IN (\'failed\',\'expired\') OR r.identity_verification_status IN (\'failed\',\'expired\') THEN 0 WHEN r.credit_validation_status IN (\'pending\',\'queued\') OR r.identity_verification_status IN (\'pending\',\'queued\') THEN 1 ELSE 2 END,r.updated_at ASC LIMIT :limit OFFSET :offset';
        $s=$this->pdo->prepare($sql);foreach($params as$k=>$v)$s->bindValue($k,$v);$s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$s->execute();return['items'=>$s->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total];
    }

    public function bankSummary(?string $bankUserId,bool $global): array
    {
        $where=$global?'1=1':'assigned_bank_user_id=:bank_user';$params=$global?[]:[':bank_user'=>$bankUserId];$s=$this->pdo->prepare("SELECT COUNT(*) total,SUM(status='under_review') under_review,SUM(credit_validation_status IN ('pending','queued')) credit_pending,SUM(identity_verification_status IN ('pending','queued')) identity_pending,SUM(credit_validation_status IN ('failed','expired') OR identity_verification_status IN ('failed','expired')) attention,SUM(status='expert_approved') approved FROM loan_requests WHERE {$where} AND status NOT IN ('draft','cancelled','archived')");$s->execute($params);$row=$s->fetch();return is_array($row)?array_map(static fn($v)=>(int)$v,$row):['total'=>0,'under_review'=>0,'credit_pending'=>0,'identity_pending'=>0,'attention'=>0,'approved'=>0];
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
    {
        $s=$this->pdo->prepare('SELECT * FROM request_comments WHERE request_id=:id ORDER BY created_at ASC,id ASC');$s->execute([':id'=>$requestId]);
        return array_map([$this,'decodeComment'],$s->fetchAll());
    }

    private function decodeComment(array $row):array
    {
        $row['attachment_document_ids']=Json::decode((string)($row['attachment_document_ids_json']??''));unset($row['attachment_document_ids_json']);return $row;
    }

    public function addComment(string $requestId,string $actor,string $comment,array $attachments=[],string $operationKey=''): array
    {
        $id=RandomId::make();$digest=hash('sha256',Json::encode(['text'=>$comment,'attachments'=>$attachments]));$this->pdo->beginTransaction();
        try{
            $guard=$this->pdo->prepare('SELECT id FROM loan_requests WHERE id=:id FOR UPDATE');$guard->execute([':id'=>$requestId]);
            if(!is_string($guard->fetchColumn()))throw new RuntimeException('پرونده پیدا نشد.');
            if($operationKey!==''){
                $check=$this->pdo->prepare('SELECT * FROM request_comments WHERE request_id=:request_id AND author_user_id=:actor AND operation_key=:op LIMIT 1');
                $check->execute([':request_id'=>$requestId,':actor'=>$actor,':op'=>$operationKey]);$existing=$check->fetch();
                if(is_array($existing)){
                    if(!hash_equals((string)$existing['payload_sha256'],$digest))throw new RuntimeException('این شناسه عملیات با محتوای دیگری استفاده شده است.');
                    $this->pdo->commit();return $this->decodeComment($existing);
                }
            }
            $s=$this->pdo->prepare("INSERT INTO request_comments (id,request_id,author_user_id,comment,visibility,attachment_document_ids_json,operation_key,payload_sha256) VALUES (:id,:request_id,:actor,:comment,'internal',:files,:op,:digest)");
            $s->execute([':id'=>$id,':request_id'=>$requestId,':actor'=>$actor,':comment'=>$comment,':files'=>Json::encode($attachments),':op'=>$operationKey!==''?$operationKey:null,':digest'=>$digest]);
            $this->recordEvent($requestId,'request.internal_note.created',$actor,'allowed',['comment_id'=>$id,'attachment_count'=>count($attachments)]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $q=$this->pdo->prepare('SELECT * FROM request_comments WHERE id=:id');$q->execute([':id'=>$id]);$row=$q->fetch();return is_array($row)?$this->decodeComment($row):throw new RuntimeException('یادداشت قابل بازیابی نیست.');
    }

    public function editComment(string $requestId,string $noteId,string $actor,string $newText,int $expectedVersion,bool $admin=false):array
    {
        $this->pdo->beginTransaction();
        try{
            $guard=$this->pdo->prepare('SELECT id FROM loan_requests WHERE id=:id FOR UPDATE');$guard->execute([':id'=>$requestId]);if(!is_string($guard->fetchColumn()))throw new RuntimeException('پرونده پیدا نشد.');
            $s=$this->pdo->prepare('SELECT * FROM request_comments WHERE id=:id AND request_id=:request FOR UPDATE');$s->execute([':id'=>$noteId,':request'=>$requestId]);$row=$s->fetch();
            if(!is_array($row))throw new RuntimeException('یادداشت پیدا نشد.');
            if(!$admin&&(string)$row['author_user_id']!==$actor)throw new RuntimeException('فقط نویسنده یا مدیر مجاز می‌تواند یادداشت را اصلاح کند.');
            if((int)$row['version_no']!==$expectedVersion)throw new RuntimeException('یادداشت قبلاً تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            if((string)$row['comment']===$newText){$this->pdo->commit();return $this->decodeComment($row);}
            $h=$this->pdo->prepare('INSERT INTO request_note_revisions (id,note_id,request_id,changed_by_user_id,previous_comment,previous_version) VALUES (:id,:note,:request,:actor,:previous,:version)');
            $h->execute([':id'=>RandomId::make(),':note'=>$noteId,':request'=>$requestId,':actor'=>$actor,':previous'=>(string)$row['comment'],':version'=>$expectedVersion]);
            $u=$this->pdo->prepare('UPDATE request_comments SET comment=:comment,version_no=version_no+1,updated_at=NOW(6) WHERE id=:id AND version_no=:version');
            $u->execute([':comment'=>$newText,':id'=>$noteId,':version'=>$expectedVersion]);if($u->rowCount()!==1)throw new RuntimeException('تداخل ویرایش یادداشت رخ داده است.');
            $this->recordEvent($requestId,'request.internal_note.edited',$actor,'allowed',['comment_id'=>$noteId,'previous_version'=>$expectedVersion]);
            $this->pdo->commit();
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        $q=$this->pdo->prepare('SELECT * FROM request_comments WHERE id=:id');$q->execute([':id'=>$noteId]);$r=$q->fetch();return is_array($r)?$this->decodeComment($r):throw new RuntimeException('یادداشت قابل بازیابی نیست.');
    }

    public function noteHistory(string $requestId,string $noteId):array
    {
        $s=$this->pdo->prepare('SELECT h.id,h.changed_by_user_id,h.previous_comment,h.previous_version,h.created_at FROM request_note_revisions h JOIN request_comments c ON c.id=h.note_id AND c.request_id=:request WHERE h.note_id=:note ORDER BY h.created_at ASC');
        $s->execute([':request'=>$requestId,':note'=>$noteId]);return $s->fetchAll();
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

    /** Finance-owned bank batch projection; repeatable and all-or-nothing within Loans. */
    public function projectBankCycle(string $cycle,string $bankUser,string $date,string $note,array $requestIds):array
    {
        $this->pdo->beginTransaction();
        try {
            foreach($requestIds as $requestId){
                $s=$this->pdo->prepare('SELECT id,status,assigned_bank_user_id,bank_settlement_status,bank_settlement_cycle_id FROM loan_requests WHERE id=:id FOR UPDATE');
                $s->execute([':id'=>$requestId]);$row=$s->fetch();
                if(!is_array($row)||$row['status']!=='granted'||$row['assigned_bank_user_id']!==$bankUser)throw new RuntimeException('مالکیت کارشناس یا وضعیت اعطای پرونده تغییر کرده است.');
                if(!empty($row['bank_settlement_cycle_id'])&&$row['bank_settlement_cycle_id']!==$cycle)throw new RuntimeException('پرونده در سیکل بانک دیگری ثبت شده است.');
                if(empty($row['bank_settlement_cycle_id'])&&($row['bank_settlement_status']==='recorded'))throw new RuntimeException('پرونده قبلاً به‌صورت مستقل تسویه بانک شده است.');
                if($row['bank_settlement_cycle_id']===$cycle)continue;
                $u=$this->pdo->prepare("UPDATE loan_requests SET bank_settlement_status='recorded',bank_settlement_cycle_id=:cycle,bank_settled_at=:at,bank_settlement_note=:note,bank_settled_by_user_id=:actor,bank_settlement_updated_at=NOW(6),row_version=row_version+1 WHERE id=:id");
                $u->execute([':cycle'=>$cycle,':at'=>$date.' 00:00:00',':note'=>$note!==''?$note:null,':actor'=>$bankUser,':id'=>$requestId]);
                $h=$this->pdo->prepare("INSERT INTO bank_settlement_history(id,request_id,status,settled_at,note,actor_user_id) VALUES(:id,:request,'recorded',:at,:note,:actor)");
                $h->execute([':id'=>RandomId::make(),':request'=>$requestId,':at'=>$date.' 00:00:00',':note'=>$note!==''?$note:null,':actor'=>$bankUser]);
                $this->recordEvent($requestId,'request.bank_settlement.cycle_projected',$bankUser,'allowed',['cycle_id'=>$cycle]);
            }
            $this->pdo->commit();return ['cycle_id'=>$cycle,'projected_count'=>count($requestIds)];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function bankSettlementHistory(string $requestId): array
    { $s=$this->pdo->prepare('SELECT * FROM bank_settlement_history WHERE request_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$requestId]);return$s->fetchAll(); }

    public function recordBankSettlement(string $requestId,string $status,?string $settledAt,string $note,string $actor): array
    {
        $request=$this->getRequest($requestId);if($request===null)throw new RuntimeException('پرونده پیدا نشد.');if(!empty($request['bank_settlement_cycle_id']))throw new RuntimeException('تسویه بانک عضو سیکل از مسیر انفرادی قابل ویرایش نیست.');if((string)$request['status']!=='granted'&&$status==='recorded')throw new RuntimeException('ثبت تسویه بانک فقط برای پرونده اعطاشده مجاز است.');
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


    public function financeShadowPage(int $page=1,int $perPage=100): array
    {
        $page=max(1,$page);$perPage=max(1,min(200,$perPage));$where="status='granted' OR bank_settlement_status<>'not_recorded' OR settlement_status<>'not_started' OR financier_payment_status<>'unreviewed'";
        $total=(int)$this->pdo->query('SELECT COUNT(*) FROM loan_requests WHERE '.$where)->fetchColumn();
        $q=$this->pdo->prepare('SELECT id,request_no,merchant_id,status,facility_amount,acceptor_payment_amount,bank_settlement_status,bank_settlement_note,bank_settled_at,bank_settled_by_user_id,settlement_status,settlement_date,settlement_cycle_id,financier_payment_status,financier_payment_note,financier_payment_updated_at,financier_payment_updated_by_user_id,updated_at FROM loan_requests WHERE '.$where.' ORDER BY updated_at ASC,id ASC LIMIT :lim OFFSET :off');
        $q->bindValue(':lim',$perPage,\PDO::PARAM_INT);$q->bindValue(':off',($page-1)*$perPage,\PDO::PARAM_INT);$q->execute();return['items'=>$q->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total];
    }
    public function financeShadowCycles(): array
    {
        return['cycles'=>$this->pdo->query('SELECT * FROM settlement_cycles ORDER BY created_at ASC,id ASC')->fetchAll(),'items'=>$this->pdo->query('SELECT * FROM settlement_cycle_items ORDER BY created_at ASC,cycle_id ASC,request_id ASC')->fetchAll()];
    }
    public function financeShadowReceipts(): array
    { return['items'=>$this->pdo->query('SELECT *,NULL AS cycle_id FROM financial_receipts ORDER BY submitted_at ASC,id ASC')->fetchAll()]; }

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

    private function decodePlan(array $r): array {
        $r['settings']=Json::decode((string)($r['settings_json']??''));$r['display_fields']=Json::decode((string)($r['display_fields_json']??''));$r['required_documents']=Json::decode((string)($r['required_documents_json']??''));$r['rules']=Json::decode((string)($r['rules_json']??''));$r['eligibility']=Json::decode((string)($r['version_eligibility_json']??$r['eligibility_json']??''));unset($r['settings_json'],$r['display_fields_json'],$r['required_documents_json'],$r['rules_json'],$r['version_eligibility_json'],$r['eligibility_json']);return$r;
    }
    private function decodeVersion(array $r): array { return$this->decodePlan($r); }

    public function exportPartnerShadow(string $entity,int $page=1,int $perPage=200): array
    {
        $page=max(1,$page);$perPage=max(1,min(500,$perPage));
        $defs=[
            'merchants'=>['table'=>'merchants','order'=>'id'],
            'memberships'=>['table'=>'merchant_user_memberships','order'=>'merchant_id,user_id'],
            'plan_permissions'=>['table'=>'merchant_plan_permissions','order'=>'merchant_id,plan_id'],
            'cooperation_requests'=>['table'=>'cooperation_requests','order'=>'id'],
            'cooperation_documents'=>['table'=>'cooperation_documents','order'=>'cooperation_request_id,document_id,relation_code'],
        ];
        if(!isset($defs[$entity]))throw new \InvalidArgumentException('نوع داده انتقال شرکا معتبر نیست.');
        $table=$defs[$entity]['table'];$order=$defs[$entity]['order'];
        $total=(int)$this->pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        $st=$this->pdo->prepare('SELECT * FROM '.$table.' ORDER BY '.$order.' LIMIT :limit OFFSET :offset');
        $st->bindValue(':limit',$perPage,PDO::PARAM_INT);$st->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$st->execute();$items=$st->fetchAll();
        foreach($items as&$r){
            if($entity==='merchants'){
                $r['financial_settings']=Json::decode((string)($r['financial_settings_json']??''));$r['metadata']=Json::decode((string)($r['metadata_json']??''));unset($r['financial_settings_json'],$r['metadata_json']);
            }elseif($entity==='plan_permissions'){
                $r['limits']=Json::decode((string)($r['limits_json']??''));unset($r['limits_json']);
            }elseif($entity==='cooperation_requests'){
                $r['data']=Json::decode((string)($r['data_json']??''));unset($r['data_json'],$r['otp_hash'],$r['contract_token_hash']);
            }
        }unset($r);
        return['entity'=>$entity,'items'=>$items,'page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>(int)ceil($total/$perPage)];
    }

    public function upsertShadowMerchant(array $merchant): array
    {
        $id=strtolower(trim((string)($merchant['id']??'')));$code=strtoupper(trim((string)($merchant['code']??'')));$name=trim((string)($merchant['name']??''));
        if(preg_match('/^[a-f0-9]{26}$/',$id)!==1||$code===''||$name==='')throw new \InvalidArgumentException('اطلاعات همگام‌سازی پذیرنده معتبر نیست.');
        $status=strtolower(trim((string)($merchant['status']??'active')));if(!in_array($status,['active','inactive','suspended','archived'],true))$status='inactive';
        $coop=strtolower(trim((string)($merchant['cooperation_status']??'approved')));if($coop==='')$coop='approved';
        $s=$this->pdo->prepare('INSERT INTO merchants(id,code,name,legal_name,owner_user_id,organization_ref,status,cooperation_status,credit_level,financial_settings_json,metadata_json,legacy_user_id,legacy_profile_ref) VALUES(:id,:code,:name,:legal,:owner,:org,:status,:coop,:credit,:financial,:metadata,:legacy_user,:legacy_ref) ON DUPLICATE KEY UPDATE code=VALUES(code),name=VALUES(name),legal_name=VALUES(legal_name),owner_user_id=VALUES(owner_user_id),organization_ref=VALUES(organization_ref),status=VALUES(status),cooperation_status=VALUES(cooperation_status),credit_level=VALUES(credit_level),financial_settings_json=VALUES(financial_settings_json),metadata_json=VALUES(metadata_json),legacy_user_id=VALUES(legacy_user_id),legacy_profile_ref=VALUES(legacy_profile_ref),updated_at=NOW(6)');
        $financial=is_array($merchant['financial_settings']??null)?$merchant['financial_settings']:[];$metadata=is_array($merchant['metadata']??null)?$merchant['metadata']:[];
        $s->execute([':id'=>$id,':code'=>$code,':name'=>$name,':legal'=>trim((string)($merchant['legal_name']??''))?:null,':owner'=>trim((string)($merchant['owner_user_id']??''))?:null,':org'=>trim((string)($merchant['organization_ref']??''))?:null,':status'=>$status,':coop'=>$coop,':credit'=>trim((string)($merchant['credit_level']??''))?:null,':financial'=>$financial!==[]?Json::encode($financial):null,':metadata'=>$metadata!==[]?Json::encode($metadata):null,':legacy_user'=>(int)($merchant['legacy_user_id']??0)>0?(int)$merchant['legacy_user_id']:null,':legacy_ref'=>trim((string)($merchant['legacy_profile_ref']??''))?:null]);
        return['id'=>$id,'code'=>$code,'name'=>$name,'status'=>$status,'cooperation_status'=>$coop];
    }

    public function configureApplicantRouting(string $requestId,array $d,string $actor): array
    {
        $mode=(string)($d['acceptor_assignment_mode']??'');$market=$mode==='open_acceptor_marketplace'?'open':'';
        $s=$this->pdo->prepare('UPDATE loan_requests SET acceptor_assignment_mode=:mode,marketplace_status=:marketplace,marketplace_claimed_by_user_id=NULL,marketplace_claimed_at=NULL,requested_service_scope=:scope,requested_cities_json=:cities,requested_location_terms_json=:locations,requested_activity_categories_json=:activities,request_public_summary=:summary,acceptor_invitation_code=:invitation,acceptor_invitation_locked=:locked WHERE id=:id');
        $s->execute([':mode'=>$mode,':marketplace'=>$market,':scope'=>(string)($d['requested_service_scope']??''),':cities'=>Json::encode(is_array($d['requested_cities']??null)?$d['requested_cities']:[]),':locations'=>Json::encode(is_array($d['requested_location_terms']??null)?$d['requested_location_terms']:[]),':activities'=>Json::encode(is_array($d['requested_activity_categories']??null)?$d['requested_activity_categories']:[]),':summary'=>trim((string)($d['request_public_summary']??''))?:null,':invitation'=>trim((string)($d['acceptor_invitation_code']??''))?:null,':locked'=>trim((string)($d['acceptor_invitation_code']??''))!==''?1:0,':id'=>$requestId]);
        $this->recordEvent($requestId,'request.partner_routing.configured',$actor,'allowed',['acceptor_assignment_mode'=>$mode,'marketplace_status'=>$market]);
        return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
    }

    public function listMarketplace(int$page,int$perPage,string$q=''):array
    {
        $page=max(1,$page);$perPage=max(1,min(100,$perPage));$where=["r.request_origin='applicant'","r.acceptor_assignment_mode='open_acceptor_marketplace'","r.marketplace_status IN ('open','claimed')"];$params=[];
        if($q!==''){$where[]='(r.request_no LIKE :q OR r.request_public_summary LIKE :q OR p.name LIKE :q)';$params[':q']='%'.$q.'%';}$w=implode(' AND ',$where);
        $c=$this->pdo->prepare('SELECT COUNT(*) FROM loan_requests r LEFT JOIN installment_plans p ON p.id=r.plan_id WHERE '.$w);$c->execute($params);$total=(int)$c->fetchColumn();
        $sql='SELECT r.id,r.request_no,r.plan_id,p.name AS plan_name,r.requested_amount,r.installment_count,r.occupation,r.requested_service_scope,r.requested_cities_json,r.requested_location_terms_json,r.requested_activity_categories_json,r.request_public_summary,r.marketplace_status,r.marketplace_claimed_at,r.created_at FROM loan_requests r LEFT JOIN installment_plans p ON p.id=r.plan_id WHERE '.$w.' ORDER BY CASE WHEN r.marketplace_status=\'open\' THEN 0 ELSE 1 END,r.created_at DESC LIMIT :limit OFFSET :offset';
        $s=$this->pdo->prepare($sql);foreach($params as$k=>$v)$s->bindValue($k,$v);$s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$s->execute();$items=[];foreach($s->fetchAll()as$r){$r['requested_cities']=Json::decode((string)($r['requested_cities_json']??''));$r['requested_location_terms']=Json::decode((string)($r['requested_location_terms_json']??''));$r['requested_activity_categories']=Json::decode((string)($r['requested_activity_categories_json']??''));unset($r['requested_cities_json'],$r['requested_location_terms_json'],$r['requested_activity_categories_json']);$items[]=$r;}return['items'=>$items,'page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>(int)ceil($total/$perPage)];
    }

    public function claimMarketplace(string$requestId,string$acceptorUserId,string$merchantId,string$actor):array
    {
        $this->pdo->beginTransaction();try{$s=$this->pdo->prepare("UPDATE loan_requests SET acceptor_user_id=:user,merchant_id=:merchant,merchant_ref=:merchant_ref,marketplace_status='claimed',marketplace_claimed_by_user_id=:user,marketplace_claimed_at=NOW(6),row_version=row_version+1 WHERE id=:id AND request_origin='applicant' AND acceptor_assignment_mode='open_acceptor_marketplace' AND marketplace_status='open' AND (acceptor_user_id IS NULL OR acceptor_user_id='')");$s->execute([':user'=>$acceptorUserId,':merchant'=>$merchantId,':merchant_ref'=>$merchantId,':id'=>$requestId]);if($s->rowCount()!==1){$this->pdo->rollBack();throw new \DomainException('این پرونده لحظاتی قبل توسط پذیرنده دیگری پذیرفته شده است.');}$this->recordEvent($requestId,'request.marketplace.claimed',$actor,'allowed',['acceptor_user_id'=>$acceptorUserId,'merchant_id'=>$merchantId]);$this->pdo->commit();return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function inviteMerchantToRequest(string$requestId,string$merchantId,string$code,string$actor):array
    {
        $s=$this->pdo->prepare("UPDATE loan_requests SET merchant_id=:merchant,merchant_ref=:merchant_ref,acceptor_assignment_mode='applicant_selected_acceptor',marketplace_status='',acceptor_invitation_code=:code,acceptor_invited_by_user_id=:actor,acceptor_invitation_locked=1,row_version=row_version+1 WHERE id=:id AND request_origin='applicant' AND (acceptor_user_id IS NULL OR acceptor_user_id='')");$s->execute([':merchant'=>$merchantId,':merchant_ref'=>$merchantId,':code'=>$code,':actor'=>$actor,':id'=>$requestId]);if($s->rowCount()!==1)throw new RuntimeException('دعوت پذیرنده برای این پرونده قابل ثبت نیست.');$this->recordEvent($requestId,'request.merchant.invited',$actor,'allowed',['merchant_id'=>$merchantId]);return$this->getRequest($requestId)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
    }

    private function decodeRequest(array $r): array {
        $r['order_items']=Json::decode((string)($r['order_items_json']??''));unset($r['order_items_json']);if(isset($r['settings_json'])){$r['plan_settings']=Json::decode((string)$r['settings_json']);unset($r['settings_json']);}
        $versionDocs=isset($r['required_documents_json'])?Json::decode((string)$r['required_documents_json']):[];unset($r['required_documents_json']);$snapshotDocs=isset($r['required_documents_snapshot_json'])?Json::decode((string)$r['required_documents_snapshot_json']):[];unset($r['required_documents_snapshot_json']);$r['required_documents']=$snapshotDocs!==[]?$snapshotDocs:$versionDocs;
        if(isset($r['version_rules_json'])){$r['plan_rules']=Json::decode((string)$r['version_rules_json']);unset($r['version_rules_json']);}if(isset($r['plan_rules_snapshot_json'])){$snap=Json::decode((string)$r['plan_rules_snapshot_json']);if($snap!==[])$r['plan_rules']=$snap;unset($r['plan_rules_snapshot_json']);}
        if(isset($r['version_eligibility_json'])){$r['plan_eligibility']=Json::decode((string)$r['version_eligibility_json']);unset($r['version_eligibility_json']);}
        if(isset($r['calculation_result_json'])){$r['calculation_result']=Json::decode((string)$r['calculation_result_json']);unset($r['calculation_result_json']);}
        foreach(['requested_cities_json'=>'requested_cities','requested_location_terms_json'=>'requested_location_terms','requested_activity_categories_json'=>'requested_activity_categories']as$jk=>$outKey){if(isset($r[$jk])){$r[$outKey]=Json::decode((string)$r[$jk]);unset($r[$jk]);}}
        $r['credit_validation']=CreditValidationPolicy::summary($r);
        return$r;
    }

    private function planConfigurationHash(string $type,array $settings,array $displayFields,array $requiredDocuments,array $rules,array $eligibility,string $conditions): string
    { return hash('sha256',Json::encode(['type'=>$type,'settings'=>$settings,'display_fields'=>$displayFields,'required_documents'=>$requiredDocuments,'rules'=>$rules,'eligibility'=>$eligibility,'conditions'=>$conditions])); }
    private function planSnapshot(array $plan): array
    { return ['id'=>$plan['id']??null,'code'=>$plan['code']??null,'name'=>$plan['name']??null,'type'=>$plan['type']??null,'version'=>$plan['current_version']??$plan['version']??null,'version_id'=>$plan['current_version_id']??$plan['id']??null,'settings'=>$plan['settings']??[],'display_fields'=>$plan['display_fields']??[],'required_documents'=>$plan['required_documents']??[],'rules'=>$plan['rules']??[],'eligibility'=>$plan['eligibility']??[],'conditions'=>$plan['conditions_text']??null,'configuration_hash'=>$plan['configuration_hash']??null]; }
    /** Profile-only administrative correction. Never touches financial, contract or signature data. */
    public function correctProfile(string $id, string $actor, array $fields, int $expectedVersion, string $reason, bool $acknowledgeSigned): array
    {
        if ($expectedVersion < 1 || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) {
            throw new \InvalidArgumentException('نسخه رکورد و دلیل اصلاح حداقل ۱۰ کاراکتری الزامی است.');
        }
        $allowed = ['full_name','father_name','certificate_number','national_code','mobile_number','phone_number','birth_date','occupation','residential_address','postal_code','work_address'];
        if ($fields === [] || array_diff(array_keys($fields), $allowed) !== []) {
            throw new \InvalidArgumentException('فیلدهای اصلاح پرونده مجاز نیستند.');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM loan_requests WHERE id=:id FOR UPDATE');
            $stmt->execute([':id'=>$id]);$old=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($old)) throw new RuntimeException('پرونده پیدا نشد.');
            if ((int)$old['row_version'] !== $expectedVersion) throw new RuntimeException('اطلاعات پرونده تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            if (!in_array((string)$old['status'],['draft','need_correction'],true) && !$acknowledgeSigned) {
                throw new RuntimeException('برای پرونده‌های در گردش یا اعطاشده، تأیید آگاهانه اصلاح پروفایل الزامی است. قرارداد قبلی تغییر نخواهد کرد.');
            }
            $updates=[];$values=[':id'=>$id,':version'=>$expectedVersion];$before=[];$after=[];
            foreach ($allowed as $key) {
                if (!array_key_exists($key,$fields)) continue;
                $value=$fields[$key]===null?'':trim((string)$fields[$key]);
                if (mb_strlen($value)>($key==='residential_address'||$key==='work_address'?5000:190)) throw new \InvalidArgumentException('طول اطلاعات واردشده مجاز نیست.');
                $next=$value===''?null:$value;
                if (($old[$key]??null)===$next) continue;
                $before[$key]=$old[$key]??null;$after[$key]=$next;
                $updates[]=$key.'=:f_'.$key;$values[':f_'.$key]=$next;
            }
            if ($updates===[]) throw new \InvalidArgumentException('هیچ تغییری در اطلاعات انتخاب‌شده وجود ندارد.');
            $sql='UPDATE loan_requests SET '.implode(',',$updates).',row_version=row_version+1 WHERE id=:id AND row_version=:version';
            $up=$this->pdo->prepare($sql);$up->execute($values);
            if ($up->rowCount()!==1) throw new RuntimeException('تداخل تغییر همزمان؛ دوباره تلاش کنید.');
            $log=$this->pdo->prepare('INSERT INTO loan_profile_corrections(id,request_id,actor_user_id,reason,changed_fields_json,before_json,after_json,row_version_before,row_version_after) VALUES(:id,:request,:actor,:reason,:keys,:before,:after,:v,:next)');
            $log->execute([':id'=>\Babanoell\Loans\Support\RandomId::make(),':request'=>$id,':actor'=>$actor,':reason'=>$reason,':keys'=>Json::encode(array_keys($after)),':before'=>Json::encode($before),':after'=>Json::encode($after),':v'=>$expectedVersion,':next'=>$expectedVersion+1]);
            $this->recordEvent($id,'request.profile_corrected',$actor,'allowed',['fields'=>array_keys($after),'correction'=>true,'version'=>$expectedVersion+1]);
            $this->pdo->commit();
        } catch (Throwable $e) {if ($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return $this->getRequest($id)??throw new RuntimeException('پرونده قابل بازیابی نیست.');
    }

    public function profileCorrections(string $id): array
    {
        $stmt=$this->pdo->prepare('SELECT id,request_id,actor_user_id,reason,changed_fields_json,before_json,after_json,row_version_before,row_version_after,created_at FROM loan_profile_corrections WHERE request_id=:id ORDER BY created_at DESC,id DESC LIMIT 100');
        $stmt->execute([':id'=>$id]);$rows=$stmt->fetchAll();
        foreach ($rows as &$row) {foreach (['changed_fields_json','before_json','after_json'] as $key) {$row[substr($key,0,-5)]=Json::decode((string)($row[$key]??''));unset($row[$key]);}}
        return $rows;
    }

    public function findByLegacyId(int $legacyId): ?array
    {
        $s=$this->pdo->prepare('SELECT id FROM loan_requests WHERE legacy_id=:legacy LIMIT 1');$s->execute([':legacy'=>$legacyId]);$id=$s->fetchColumn();return is_string($id)?$this->getRequest($id):null;
    }


    /** Narrow and idempotent compatibility projection; finance is the source of truth. */
    public function syncMerchantSettlementResetShadow(string $id,string $actor,string $reason):array
    {
        if(mb_strlen(trim($reason))<10)throw new \InvalidArgumentException('دلیل ثبت‌شده برای تطبیق مالی معتبر نیست.');
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT id,status,settlement_status,settlement_date,settlement_cycle_id FROM loan_requests WHERE id=:id FOR UPDATE');
            $s->execute([':id'=>$id]);$old=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($old))throw new RuntimeException('پرونده در Loans پیدا نشد.');
            if((string)$old['status']!=='granted')throw new RuntimeException('تطبیق مالی فقط برای پرونده اعطاشده مجاز است.');
            $check=$this->pdo->prepare('SELECT request_id FROM settlement_cycle_items WHERE request_id=:id LIMIT 1 FOR UPDATE');
            $check->execute([':id'=>$id]);
            if($check->fetch()!==false||!empty($old['settlement_cycle_id']))throw new RuntimeException('پرونده در سیکل قدیمی Loans عضویت دارد.');
            if((string)$old['settlement_status']==='awaiting_date'&&$old['settlement_date']===null){$this->pdo->commit();return ['synced'=>true,'already_current'=>true];}
            $u=$this->pdo->prepare("UPDATE loan_requests SET settlement_status='awaiting_date',settlement_date=NULL,settlement_updated_by_user_id=:actor,row_version=row_version+1 WHERE id=:id");
            $u->execute([':actor'=>$actor,':id'=>$id]);
            $h=$this->pdo->prepare("INSERT INTO merchant_settlement_history(id,request_id,status,settlement_date,cycle_id,note,actor_user_id) VALUES(:id,:r,'awaiting_date',NULL,NULL,:reason,:actor)");
            $h->execute([':id'=>\Babanoell\Loans\Support\RandomId::make(),':r'=>$id,':reason'=>$reason,':actor'=>$actor]);
            $this->recordEvent($id,'request.merchant_settlement.shadow_synced',$actor,'allowed',['status'=>'awaiting_date','settlement_date'=>null]);
            $this->pdo->commit();return ['synced'=>true,'already_current'=>false];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

}

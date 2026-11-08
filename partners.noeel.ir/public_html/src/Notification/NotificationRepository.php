<?php
declare(strict_types=1);
namespace Babanoell\Partners\Notification;
use Babanoell\Partners\Support\Json;
use Babanoell\Partners\Support\RandomId;

final class NotificationRepository
{
    public function __construct(private readonly \PDO $pdo){}
    public function settings(): array
    {
        $row=$this->pdo->query('SELECT revision,settings_json FROM notification_settings WHERE id=1')->fetch();
        return ['revision'=>(int)$row['revision'],'settings'=>Json::decode((string)$row['settings_json'])];
    }
    public function saveSettings(int $revision,array $settings,string $actor): array
    {
        $normalized=NotificationSettings::normalize($settings);
        $query=$this->pdo->prepare('UPDATE notification_settings SET settings_json=:json,revision=revision+1,updated_by=:actor WHERE id=1 AND revision=:revision');
        $query->execute([':json'=>Json::encode($normalized),':actor'=>$actor,':revision'=>$revision]);
        if($query->rowCount()!==1)throw new \DomainException('تنظیمات هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
        return $this->settings();
    }
    /** Called under the workflow transaction, with a frozen template and recipient. */
    public function queue(string $eventId,array $request,string $status): void
    {
        $rule=$this->settings()['settings']['cooperation_statuses'][$status]??[];
        if(($rule['enabled']??false)!==true)return;
        $d=$request['data']??[];$mobile=NotificationSettings::mobile((string)($d['mobile_number']??$d['mobile']??$d['phone_number']??$d['phone']??''));
        if(!preg_match('/^09[0-9]{9}$/D',$mobile))return;
        $vars=array_map(static fn(string $v):string=>strtr($v,NotificationSettings::variables($request,$status)),$rule['variables']);
        foreach($vars as $v)if(strlen($v)>720)throw new \InvalidArgumentException('متن متغیر پیامک پس از جایگذاری بیش از حد مجاز است.');
        $payload=Json::encode(['request_id'=>$request['id'],'status'=>$status]);
        $this->pdo->prepare("INSERT INTO integration_outbox(id,event_type,destination,payload_json,payload_hash) VALUES(:id,'cooperation.status.changed','notifications',:payload,:hash)")->execute([':id'=>$eventId,':payload'=>$payload,':hash'=>hash('sha256',$payload)]);
        $q=$this->pdo->prepare("INSERT INTO notification_deliveries(id,event_id,request_id,recipient_hash,recipient_label,target_masked,payload_json) VALUES(:id,:event,:request,:hash,'applicant',:mask,:payload)");
        $q->execute([':id'=>RandomId::make(),':event'=>$eventId,':request'=>$request['id'],':hash'=>hash('sha256',$mobile),':mask'=>substr($mobile,0,4).'***'.substr($mobile,-4),':payload'=>Json::encode(['to'=>$mobile,'pattern_id'=>$rule['pattern_id'],'variables'=>$vars])]);
    }
    public function deliveries(int $page=1): array
    {
        $total=(int)$this->pdo->query('SELECT COUNT(*) FROM notification_deliveries')->fetchColumn();
        $query=$this->pdo->prepare('SELECT id,event_id,request_id,recipient_label,target_masked,status,attempts,next_attempt_at,provider_reference,last_error,created_at,accepted_at FROM notification_deliveries ORDER BY created_at DESC,id DESC LIMIT 25 OFFSET :offset');
        $query->bindValue(':offset',(max(1,$page)-1)*25,\PDO::PARAM_INT);$query->execute();
        return ['items'=>$query->fetchAll(),'total'=>$total,'page'=>max(1,$page)];
    }
    public function retry(string $id,string $actor): void
    {
        $this->pdo->beginTransaction();
        try {
        $query=$this->pdo->prepare("UPDATE notification_deliveries SET status='pending',next_attempt_at=NULL,last_error=NULL WHERE id=:id AND status IN ('retry','failed')");$query->execute([':id'=>$id]);
        if($query->rowCount()!==1)throw new \DomainException('ارسال تأییدشده یا نامعلوم را نمی‌توان دوباره ارسال کرد.');
        // Audit includes neither phone number nor rendered text.
        $query=$this->pdo->prepare("INSERT INTO partner_events(id,aggregate_type,aggregate_id,event_type,actor_user_id,result,details_json) SELECT :event,'cooperation_request',request_id,'notification.retry',:actor,'allowed',:meta FROM notification_deliveries WHERE id=:id");
        $query->execute([':event'=>RandomId::make(),':actor'=>$actor,':meta'=>Json::encode(['delivery_id'=>$id]),':id'=>$id]);
            $this->pdo->commit();
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}

    }
    public function reconcile(string $id,string $actor,array $input): void
    {
        $status=$input['status']??'';$reference=trim((string)($input['reference']??''));$note=trim((string)($input['note']??''));
        if(($input['confirmed']??false)!==true||!in_array($status,['accepted','failed'],true)||strlen($note)<6||strlen($note)>2000
            ||($status==='accepted'&&!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$reference)))throw new \InvalidArgumentException('نتیجه بررسی پنل پیامک، توضیح و تأیید بررسی الزامی است.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare("UPDATE notification_deliveries SET status=:status,provider_reference=:ref,last_error='manual_provider_reconciliation',accepted_at=CASE WHEN :accepted=1 THEN UTC_TIMESTAMP(6) ELSE NULL END WHERE id=:id AND status='uncertain'");
            $q->execute([':status'=>$status,':ref'=>$status==='accepted'?$reference:null,':accepted'=>$status==='accepted'?1:0,':id'=>$id]);
            if($q->rowCount()!==1)throw new \DomainException('فقط نتیجهٔ نامشخص قابل تطبیق است یا کاربر دیگری آن را بررسی کرده است.');
            $q=$this->pdo->prepare("INSERT INTO partner_events(id,aggregate_type,aggregate_id,event_type,actor_user_id,result,details_json) SELECT :event,'cooperation_request',request_id,'notification.reconciled',:actor,'allowed',:meta FROM notification_deliveries WHERE id=:id");
            $q->execute([':event'=>RandomId::make(),':actor'=>$actor,':meta'=>Json::encode(['delivery_id'=>$id,'status'=>$status,'provider_reference'=>$reference,'note'=>$note]),':id'=>$id]);
            $this->pdo->commit();
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}

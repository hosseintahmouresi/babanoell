<?php
declare(strict_types=1);
namespace Babanoell\Loans\Notification;
use Babanoell\Loans\Support\Json;
use Babanoell\Loans\Support\RandomId;

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
    /** Called inside the same transaction as the workflow change; recipient and template are frozen. */
    public function queue(string $eventId,array $loan,string $status,array $acceptor=[]): void
    {
        $rule=$this->settings()['settings']['loan_statuses'][$status]??[];
        if(($rule['enabled']??false)!==true)return;
        $replacements=NotificationSettings::variables($loan,$status,!empty($loan['acceptor_user_id'])?'[[acceptor_name]]':(($loan['request_origin']??'')==='applicant'?'درخواست مستقیم متقاضی':'---'));
        $variables=array_map(static fn(string $template):string=>strtr($template,$replacements),$rule['variables']);
        foreach($variables as $value)if(strlen($value)>720)throw new \InvalidArgumentException('متن متغیر پیامک پس از جایگذاری بیش از حد مجاز است.');
        $targets=[];
        if($rule['send_to_applicant'])$targets['applicant']=$loan['mobile_number']??'';
        if($rule['send_to_acceptor'])$targets['acceptor']='user:'.($loan['acceptor_user_id']??'');
        $seen=[];
        foreach($targets as $label=>$mobile){
            $mobile=\Babanoell\Loans\Domain\InputValidator::normalizeDigits((string)$mobile);
            $deferred=$label==='acceptor'&&preg_match('/^user:[a-f0-9]{26}$/D',$mobile);
            if((!$deferred&&!preg_match('/^09[0-9]{9}$/D',$mobile))||isset($seen[$mobile]))continue;
            $seen[$mobile]=true;
            $query=$this->pdo->prepare("INSERT INTO notification_deliveries(id,event_id,request_id,recipient_hash,recipient_label,target_masked,payload_json) VALUES(:id,:event,:request,:hash,:label,:masked,:payload)");
            $query->execute([':id'=>RandomId::make(),':event'=>$eventId,':request'=>$loan['id'],':hash'=>hash('sha256',$mobile),':label'=>$label,':masked'=>$deferred?'پذیرنده':substr($mobile,0,4).'***'.substr($mobile,-4),':payload'=>Json::encode(['to'=>$deferred?'':$mobile,'pattern_id'=>$rule['pattern_id'],'variables'=>$variables,'acceptor_user_id'=>$loan['acceptor_user_id']??null])]);
        }
    }
    public function inbox(string $user,int $page=1): array
    {
        $count=$this->pdo->prepare('SELECT COUNT(*),SUM(read_at IS NULL) FROM notifications WHERE recipient_user_id=:user');$count->execute([':user'=>$user]);$totals=$count->fetch(\PDO::FETCH_NUM);
        $query=$this->pdo->prepare('SELECT id,notification_type,title,body,status,read_at,created_at,metadata_json FROM notifications WHERE recipient_user_id=:user ORDER BY created_at DESC,id DESC LIMIT 25 OFFSET :offset');
        $query->bindValue(':user',$user);$query->bindValue(':offset',(max(1,$page)-1)*25,\PDO::PARAM_INT);$query->execute();$rows=$query->fetchAll();
        foreach($rows as &$row){$row['metadata']=Json::decode((string)($row['metadata_json']??''));unset($row['metadata_json']);}
        return ['items'=>$rows,'total'=>(int)$totals[0],'unread'=>(int)$totals[1],'page'=>max(1,$page)];
    }
    public function markRead(string $id,string $user): void
    {
        $query=$this->pdo->prepare("UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP(6)),status='read' WHERE id=:id AND recipient_user_id=:user");$query->execute([':id'=>$id,':user'=>$user]);
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
        $query=$this->pdo->prepare("INSERT INTO loan_request_events(id,request_id,event_type,actor_user_id,result,details_json) SELECT :event,request_id,'notification.retry',:actor,'allowed',:meta FROM notification_deliveries WHERE id=:id");
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
            $q=$this->pdo->prepare("INSERT INTO loan_request_events(id,request_id,event_type,actor_user_id,result,details_json) SELECT :event,request_id,'notification.reconciled',:actor,'allowed',:meta FROM notification_deliveries WHERE id=:id");
            $q->execute([':event'=>RandomId::make(),':actor'=>$actor,':meta'=>Json::encode(['delivery_id'=>$id,'status'=>$status,'provider_reference'=>$reference,'note'=>$note]),':id'=>$id]);
            $this->pdo->commit();
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}

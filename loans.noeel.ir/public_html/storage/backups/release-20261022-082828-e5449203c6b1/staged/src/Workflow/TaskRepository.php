<?php
declare(strict_types=1);
namespace Babanoell\Loans\Workflow;

use Babanoell\Loans\Support\Json;
use Babanoell\Loans\Support\RandomId;
use PDO;
use RuntimeException;
use Throwable;

final class TaskRepository
{
    public function __construct(private readonly PDO $pdo){}
    public function pdo():PDO{return$this->pdo;}

    public function policy(string $code):?array
    { $s=$this->pdo->prepare("SELECT * FROM workflow_sla_policies WHERE code=:code AND is_active=1 LIMIT 1");$s->execute([':code'=>$code]);$r=$s->fetch();return is_array($r)?$r:null; }


    /** @return array<int,array<string,mixed>> */
    public function policies():array
    { $s=$this->pdo->query('SELECT * FROM workflow_sla_policies ORDER BY queue_code,task_code,code');return$s->fetchAll(); }

    public function updatePolicy(string $code,array $fields,bool $applyExisting,string $actor):array
    {
        $current=$this->policy($code);if($current===null)throw new \InvalidArgumentException('سیاست SLA پیدا نشد.');
        $due=max(5,min(43200,(int)($fields['due_minutes']??$current['due_minutes'])));$warning=max(0,min($due,(int)($fields['warning_minutes']??$current['warning_minutes'])));$escalation=max(0,min(10080,(int)($fields['escalation_minutes']??$current['escalation_minutes'])));
        $this->pdo->beginTransaction();try{$u=$this->pdo->prepare('UPDATE workflow_sla_policies SET due_minutes=:due,warning_minutes=:warning,escalation_minutes=:escalation,is_active=1 WHERE code=:code');$u->execute([':due'=>$due,':warning'=>$warning,':escalation'=>$escalation,':code'=>$code]);if($applyExisting){$sql="UPDATE workflow_tasks SET due_at=DATE_ADD(created_at,INTERVAL {$due} MINUTE),warning_at=DATE_ADD(DATE_ADD(created_at,INTERVAL {$due} MINUTE),INTERVAL -{$warning} MINUTE),escalation_at=DATE_ADD(DATE_ADD(created_at,INTERVAL {$due} MINUTE),INTERVAL {$escalation} MINUTE),last_sla_event=NULL,last_sla_event_at=NULL,version_no=version_no+1 WHERE sla_policy_code=:code AND status IN ('open','in_progress')";$q=$this->pdo->prepare($sql);$q->execute([':code'=>$code]);}$this->pdo->commit();return$this->policy($code)??throw new RuntimeException('سیاست SLA قابل بازیابی نیست.');}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function report(int $days=30):array
    {
        $days=max(1,min(365,$days));$since=(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->modify('-'.$days.' days')->format('Y-m-d H:i:s');
        $q=$this->pdo->prepare("SELECT queue_code,COUNT(*) total,SUM(status IN ('open','in_progress')) active,SUM(status='completed') completed,SUM(status IN ('open','in_progress') AND due_at IS NOT NULL AND due_at<NOW(6)) overdue,ROUND(AVG(CASE WHEN status='completed' AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,completed_at) END),1) avg_completion_minutes FROM workflow_tasks WHERE created_at>=:since GROUP BY queue_code ORDER BY queue_code");$q->execute([':since'=>$since]);$queues=$q->fetchAll();
        $q=$this->pdo->prepare("SELECT assigned_user_id,COUNT(*) total,SUM(status IN ('open','in_progress')) active,SUM(status='completed') completed,SUM(status IN ('open','in_progress') AND due_at IS NOT NULL AND due_at<NOW(6)) overdue,ROUND(AVG(CASE WHEN status='completed' AND completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,created_at,completed_at) END),1) avg_completion_minutes FROM workflow_tasks WHERE created_at>=:since AND assigned_user_id IS NOT NULL GROUP BY assigned_user_id ORDER BY completed DESC,total DESC LIMIT 100");$q->execute([':since'=>$since]);$assignees=$q->fetchAll();
        $q=$this->pdo->prepare("SELECT status,COUNT(*) cases,ROUND(AVG(TIMESTAMPDIFF(MINUTE,COALESCE(last_transition_at,created_at),NOW(6))),1) avg_current_stage_minutes,MAX(TIMESTAMPDIFF(MINUTE,COALESCE(last_transition_at,created_at),NOW(6))) max_current_stage_minutes FROM loan_requests WHERE updated_at>=:since GROUP BY status ORDER BY cases DESC");$q->execute([':since'=>$since]);$stages=$q->fetchAll();
        $q=$this->pdo->prepare("SELECT action,COUNT(*) events FROM workflow_task_history WHERE created_at>=:since AND action IN ('sla_warning','sla_escalated') GROUP BY action");$q->execute([':since'=>$since]);$sla=[];foreach($q->fetchAll()as$r)$sla[(string)$r['action']]=(int)$r['events'];
        return['window_days'=>$days,'queues'=>$queues,'assignees'=>$assignees,'current_stages'=>$stages,'sla_events'=>['warnings'=>(int)($sla['sla_warning']??0),'escalations'=>(int)($sla['sla_escalated']??0)]];
    }

    public function get(string $id):?array
    {
        $s=$this->pdo->prepare('SELECT t.*,r.request_no,r.full_name,r.status AS request_status FROM workflow_tasks t JOIN loan_requests r ON r.id=t.request_id WHERE t.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;
    }

    /** @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int} */
    public function list(array $filters,array $permissions,string $userId,bool $global):array
    {
        $page=max(1,(int)($filters['page']??1));$per=max(1,min(100,(int)($filters['per_page']??25)));$where=[];$params=[];
        $status=trim((string)($filters['status']??''));if($status===''){$where[]="t.status IN ('open','in_progress')";}elseif($status!=='all'){if(!in_array($status,TaskCatalog::STATUSES,true))throw new \InvalidArgumentException('فیلتر وضعیت تسک معتبر نیست.');$where[]='t.status=:status';$params[':status']=$status;}
        $queue=trim((string)($filters['queue']??''));if($queue!==''){$where[]='t.queue_code=:queue';$params[':queue']=$queue;}
        $requestId=trim((string)($filters['request_id']??''));if($requestId!==''){$where[]='t.request_id=:request_id';$params[':request_id']=$requestId;}
        $scope=trim((string)($filters['scope']??''));if($scope==='mine'){$where[]='t.assigned_user_id=:mine';$params[':mine']=$userId;}elseif($scope==='unassigned'){$where[]='t.assigned_user_id IS NULL';}
        if(!empty($filters['overdue']))$where[]="t.status IN ('open','in_progress') AND t.due_at IS NOT NULL AND t.due_at<NOW(6)";
        $search=trim((string)($filters['search']??''));if($search!==''){$where[]='(r.request_no LIKE :search OR r.full_name LIKE :search OR t.title LIKE :search)';$params[':search']='%'.$search.'%';}
        if(!$global){
            $access=['t.assigned_user_id=:access_user'];$params[':access_user']=$userId;
            $allowed=array_values(array_unique(array_filter($permissions,static fn($v)=>is_string($v)&&$v!=='')));
            if($allowed!==[]){$ph=[];foreach($allowed as$i=>$p){$k=':perm_'.$i;$ph[]=$k;$params[$k]=$p;}$access[]='(t.assigned_user_id IS NULL AND t.required_permission IN ('.implode(',',$ph).'))';}
            $where[]='('.implode(' OR ',$access).')';
        }
        $w=$where?' WHERE '.implode(' AND ',$where):'';
        $c=$this->pdo->prepare('SELECT COUNT(*) FROM workflow_tasks t JOIN loan_requests r ON r.id=t.request_id'.$w);foreach($params as$k=>$v)$c->bindValue($k,$v);$c->execute();$total=(int)$c->fetchColumn();
        $sql='SELECT t.*,r.request_no,r.full_name,r.status AS request_status FROM workflow_tasks t JOIN loan_requests r ON r.id=t.request_id'.$w.' ORDER BY FIELD(t.priority,\'urgent\',\'high\',\'normal\',\'low\'),CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END,t.due_at ASC,t.created_at ASC LIMIT :limit OFFSET :offset';
        $s=$this->pdo->prepare($sql);foreach($params as$k=>$v)$s->bindValue($k,$v);$s->bindValue(':limit',$per,PDO::PARAM_INT);$s->bindValue(':offset',($page-1)*$per,PDO::PARAM_INT);$s->execute();
        return['items'=>$s->fetchAll(),'page'=>$page,'per_page'=>$per,'total'=>$total];
    }

    public function summary(array $permissions,string $userId,bool $global):array
    {
        $base=$this->list(['page'=>1,'per_page'=>1,'status'=>'all'],$permissions,$userId,$global);$mine=$this->list(['page'=>1,'per_page'=>1,'status'=>'all','scope'=>'mine'],$permissions,$userId,$global);$over=$this->list(['page'=>1,'per_page'=>1,'status'=>'all','overdue'=>1],$permissions,$userId,$global);
        $q=$this->pdo->prepare("SELECT status,COUNT(*) c FROM workflow_tasks WHERE assigned_user_id=:u GROUP BY status");$q->execute([':u'=>$userId]);$by=[];foreach($q->fetchAll()as$r)$by[(string)$r['status']]=(int)$r['c'];
        return['accessible'=>$base['total'],'mine'=>$mine['total'],'overdue'=>$over['total'],'open'=>(int)($by['open']??0),'in_progress'=>(int)($by['in_progress']??0),'completed'=>(int)($by['completed']??0)];
    }

    public function ensure(array $spec,string $actor):array
    {
        $requestId=(string)$spec['request_id'];$code=(string)$spec['task_code'];$activeKey='workflow:'.$code;
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->prepare('SELECT id,assigned_user_id,status FROM workflow_tasks WHERE request_id=:request_id AND active_key=:active_key LIMIT 1 FOR UPDATE');$lock->execute([':request_id'=>$requestId,':active_key'=>$activeKey]);$existing=$lock->fetch();
            if(is_array($existing)){
                $newAssignee=$spec['assigned_user_id']??null;$old=(string)($existing['assigned_user_id']??'');$new=(string)($newAssignee??'');
                if($old!==$new){$u=$this->pdo->prepare('UPDATE workflow_tasks SET assigned_user_id=:assignee,assigned_by_user_id=:actor,version_no=version_no+1 WHERE id=:id');$u->execute([':assignee'=>$new!==''?$new:null,':actor'=>$actor!==''?$actor:null,':id'=>$existing['id']]);$this->history((string)$existing['id'],$requestId,'reassigned',null,null,$actor,$new!==''?$new:null,'همگام‌سازی ارجاع پرونده',['old_assignee'=>$old]);}
                $this->pdo->commit();return$this->get((string)$existing['id'])??throw new RuntimeException('تسک قابل بازیابی نیست.');
            }
            $policy=$this->policy((string)($spec['sla_policy_code']??''));$now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));$due=null;$warning=null;$escalation=null;
            if($policy!==null){$due=$now->modify('+'.(int)$policy['due_minutes'].' minutes')->format('Y-m-d H:i:s.u');$warningMin=(int)$policy['warning_minutes'];$escMin=(int)$policy['escalation_minutes'];if($warningMin>0)$warning=(new \DateTimeImmutable($due,new \DateTimeZone('UTC')))->modify('-'.$warningMin.' minutes')->format('Y-m-d H:i:s.u');if($escMin>0)$escalation=(new \DateTimeImmutable($due,new \DateTimeZone('UTC')))->modify('+'.$escMin.' minutes')->format('Y-m-d H:i:s.u');}
            $id=RandomId::make();$ins=$this->pdo->prepare("INSERT INTO workflow_tasks (id,request_id,task_code,title,description,queue_code,required_permission,assigned_user_id,assigned_by_user_id,status,priority,active_key,sla_policy_code,due_at,warning_at,escalation_at,source,source_event,created_by_user_id) VALUES (:id,:request_id,:task_code,:title,:description,:queue_code,:required_permission,:assignee,:assigned_by,'open',:priority,:active_key,:policy,:due_at,:warning_at,:escalation_at,:source,:source_event,:created_by)");
            $ins->execute([':id'=>$id,':request_id'=>$requestId,':task_code'=>$code,':title'=>$spec['title'],':description'=>$spec['description']??null,':queue_code'=>$spec['queue_code'],':required_permission'=>$spec['required_permission'],':assignee'=>($spec['assigned_user_id']??null)?:null,':assigned_by'=>$actor!==''?$actor:null,':priority'=>$spec['priority']??'normal',':active_key'=>$activeKey,':policy'=>$spec['sla_policy_code']??null,':due_at'=>$due,':warning_at'=>$warning,':escalation_at'=>$escalation,':source'=>$spec['source']??'workflow',':source_event'=>$spec['source_event']??null,':created_by'=>$actor!==''?$actor:null]);
            $this->history($id,$requestId,'created',null,'open',$actor,($spec['assigned_user_id']??null)?:null,'تسک گردش‌کار ایجاد شد',['task_code'=>$code,'queue_code'=>$spec['queue_code']]);$this->pdo->commit();return$this->get($id)??throw new RuntimeException('تسک قابل بازیابی نیست.');
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function activeByCode(string $requestId,string $taskCode):?array
    { $s=$this->pdo->prepare("SELECT * FROM workflow_tasks WHERE request_id=:request_id AND task_code=:task_code AND active_key IS NOT NULL AND status IN ('open','in_progress') LIMIT 1");$s->execute([':request_id'=>$requestId,':task_code'=>$taskCode]);$r=$s->fetch();return is_array($r)?$r:null; }

    /** @return array<int,array<string,mixed>> */
    public function activeWorkflowTasks(string $requestId):array
    { $s=$this->pdo->prepare("SELECT * FROM workflow_tasks WHERE request_id=:id AND active_key IS NOT NULL AND source='workflow' AND status IN ('open','in_progress') FOR UPDATE");$s->execute([':id'=>$requestId]);return$s->fetchAll(); }

    public function autoComplete(string $id,string $actor,string $note):void
    { $this->changeStatus($id,'completed',$actor,$note,true); }

    public function claim(string $id,string $userId):array
    {
        $this->pdo->beginTransaction();try{$t=$this->lock($id);if(!in_array((string)$t['status'],['open','in_progress'],true))throw new RuntimeException('این تسک دیگر فعال نیست.');$assigned=trim((string)($t['assigned_user_id']??''));if($assigned!==''&&$assigned!==$userId)throw new RuntimeException('این تسک به کاربر دیگری ارجاع شده است.');$u=$this->pdo->prepare("UPDATE workflow_tasks SET assigned_user_id=:u,status='in_progress',started_at=COALESCE(started_at,NOW(6)),version_no=version_no+1 WHERE id=:id");$u->execute([':u'=>$userId,':id'=>$id]);$this->history($id,(string)$t['request_id'],$assigned===''?'claimed':'started',(string)$t['status'],'in_progress',$userId,$userId,null,[]);$this->pdo->commit();return$this->get($id)??throw new RuntimeException('تسک قابل بازیابی نیست.');}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function complete(string $id,string $userId,string $note=''):array{return$this->changeStatus($id,'completed',$userId,$note,false);}
    public function cancel(string $id,string $actor,string $note=''):array{return$this->changeStatus($id,'cancelled',$actor,$note,false);}

    public function release(string $id,string $userId,string $note=''):array
    {
        $this->pdo->beginTransaction();try{$t=$this->lock($id);if(!in_array((string)$t['status'],['open','in_progress'],true))throw new RuntimeException('این تسک دیگر فعال نیست.');if(trim((string)($t['assigned_user_id']??''))!==$userId)throw new RuntimeException('فقط مسئول فعلی تسک می‌تواند آن را آزاد کند.');$u=$this->pdo->prepare("UPDATE workflow_tasks SET assigned_user_id=NULL,assigned_by_user_id=:actor,status='open',started_at=NULL,version_no=version_no+1 WHERE id=:id");$u->execute([':actor'=>$userId,':id'=>$id]);$this->history($id,(string)$t['request_id'],'released',(string)$t['status'],'open',$userId,null,$note,[]);$this->pdo->commit();return$this->get($id)??throw new RuntimeException('تسک قابل بازیابی نیست.');}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function reassign(string $id,string $actor,?string $userId,string $note=''):array
    {
        $this->pdo->beginTransaction();try{$t=$this->lock($id);if(!in_array((string)$t['status'],['open','in_progress'],true))throw new RuntimeException('این تسک دیگر فعال نیست.');$u=$this->pdo->prepare("UPDATE workflow_tasks SET assigned_user_id=:u,assigned_by_user_id=:actor,status='open',started_at=NULL,version_no=version_no+1 WHERE id=:id");$u->execute([':u'=>$userId!==null&&trim($userId)!==''?trim($userId):null,':actor'=>$actor!==''?$actor:null,':id'=>$id]);$this->history($id,(string)$t['request_id'],'reassigned',(string)$t['status'],'open',$actor,$userId,$note,['old_assignee'=>$t['assigned_user_id']??null]);$this->pdo->commit();return$this->get($id)??throw new RuntimeException('تسک قابل بازیابی نیست.');}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    /** @return array<int,array<string,mixed>> */
    public function historyForTask(string $id):array
    { $s=$this->pdo->prepare('SELECT * FROM workflow_task_history WHERE task_id=:id ORDER BY created_at ASC,id ASC');$s->execute([':id'=>$id]);$rows=$s->fetchAll();foreach($rows as&$r){$r['metadata']=Json::decode((string)($r['metadata_json']??''));unset($r['metadata_json']);}return$rows; }

    /** @return array{warnings:int,escalations:int} */
    public function runSlaSweep(string $actor='system'):array
    {
        $warnings=0;$escalations=0;$this->pdo->beginTransaction();
        try{
            $s=$this->pdo->query("SELECT * FROM workflow_tasks WHERE status IN ('open','in_progress') AND due_at IS NOT NULL AND (last_sla_event IS NULL OR last_sla_event<>'escalated') FOR UPDATE");
            foreach($s->fetchAll()as$t){$now=microtime(true);$warningAt=isset($t['warning_at'])&&$t['warning_at']!==null?strtotime((string)$t['warning_at']):false;$escalationAt=isset($t['escalation_at'])&&$t['escalation_at']!==null?strtotime((string)$t['escalation_at']):false;$event=(string)($t['last_sla_event']??'');
                if($escalationAt!==false&&$escalationAt<=$now){$priority=in_array((string)$t['priority'],['urgent'],true)?'urgent':'urgent';$u=$this->pdo->prepare("UPDATE workflow_tasks SET priority=:priority,last_sla_event='escalated',last_sla_event_at=NOW(6),version_no=version_no+1 WHERE id=:id");$u->execute([':priority'=>$priority,':id'=>$t['id']]);$this->history((string)$t['id'],(string)$t['request_id'],'sla_escalated',(string)$t['status'],(string)$t['status'],$actor,(string)($t['assigned_user_id']??'')?:null,'SLA task escalated',['due_at'=>$t['due_at'],'queue_code'=>$t['queue_code']]);$this->notify((string)($t['assigned_user_id']??''),'task.sla_escalated','هشدار SLA تسک','مهلت این تسک عبور کرده و به سطح بحرانی رسیده است.','/loans/tasks?scope=mine',['task_id'=>$t['id'],'request_id'=>$t['request_id']]);$escalations++;continue;}
                if($event===''&&$warningAt!==false&&$warningAt<=$now){$u=$this->pdo->prepare("UPDATE workflow_tasks SET last_sla_event='warning',last_sla_event_at=NOW(6),version_no=version_no+1 WHERE id=:id");$u->execute([':id'=>$t['id']]);$this->history((string)$t['id'],(string)$t['request_id'],'sla_warning',(string)$t['status'],(string)$t['status'],$actor,(string)($t['assigned_user_id']??'')?:null,'SLA warning',['due_at'=>$t['due_at']]);$this->notify((string)($t['assigned_user_id']??''),'task.sla_warning','نزدیک‌شدن مهلت تسک','مهلت انجام این تسک رو به پایان است.','/loans/tasks?scope=mine',['task_id'=>$t['id'],'request_id'=>$t['request_id']]);$warnings++;}
            }
            $this->pdo->commit();return['warnings'=>$warnings,'escalations'=>$escalations];
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    private function changeStatus(string $id,string $to,string $actor,string $note,bool $system):array
    {
        $this->pdo->beginTransaction();try{$t=$this->lock($id);$from=(string)$t['status'];if(!in_array($from,['open','in_progress'],true)){$this->pdo->commit();return$this->get($id)??throw new RuntimeException('تسک قابل بازیابی نیست.');}if(!$system&&trim((string)($t['assigned_user_id']??''))!==''&&trim((string)$t['assigned_user_id'])!==$actor)throw new RuntimeException('این تسک به کاربر دیگری ارجاع شده است.');$timeCol=$to==='completed'?'completed_at':'cancelled_at';$u=$this->pdo->prepare("UPDATE workflow_tasks SET status=:to_status,active_key=NULL,{$timeCol}=NOW(6),completion_note=:note,version_no=version_no+1 WHERE id=:id");$u->execute([':to_status'=>$to,':note'=>$note!==''?mb_substr($note,0,2000):null,':id'=>$id]);$this->history($id,(string)$t['request_id'],$to==='completed'?'completed':'cancelled',$from,$to,$actor,(string)($t['assigned_user_id']??'')?:null,$note,[]);$this->pdo->commit();return$this->get($id)??throw new RuntimeException('تسک قابل بازیابی نیست.');}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    private function lock(string $id):array
    { $s=$this->pdo->prepare('SELECT * FROM workflow_tasks WHERE id=:id LIMIT 1 FOR UPDATE');$s->execute([':id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new RuntimeException('تسک پیدا نشد.');return$r; }

    private function history(string $taskId,string $requestId,string $action,?string $from,?string $to,string $actor,?string $assigned,string $note,array $metadata):void
    { $s=$this->pdo->prepare('INSERT INTO workflow_task_history (id,task_id,request_id,action,from_status,to_status,actor_user_id,assigned_user_id,note,metadata_json) VALUES (:id,:task_id,:request_id,:action,:from_status,:to_status,:actor,:assigned,:note,:metadata)');$s->execute([':id'=>RandomId::make(),':task_id'=>$taskId,':request_id'=>$requestId,':action'=>$action,':from_status'=>$from,':to_status'=>$to,':actor'=>$actor!==''?$actor:null,':assigned'=>$assigned!==''?$assigned:null,':note'=>$note!==''?mb_substr($note,0,2000):null,':metadata'=>$metadata!==[]?Json::encode($metadata):null]); }

    private function notify(string $recipient,string $type,string $title,string $body,string $target,array $metadata):void
    { if(trim($recipient)==='')return;$s=$this->pdo->prepare("INSERT INTO notifications (id,recipient_user_id,notification_type,channel,title,body,target_url,status,metadata_json) VALUES (:id,:recipient,:type,'panel',:title,:body,:target,'queued',:metadata)");$s->execute([':id'=>RandomId::make(),':recipient'=>$recipient,':type'=>$type,':title'=>$title,':body'=>$body,':target'=>$target,':metadata'=>Json::encode($metadata)]); }
}

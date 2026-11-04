<?php
declare(strict_types=1);
namespace Babanoell\Loans\Workflow;

use Babanoell\Loans\Repository\PdoLoanRepository;
use Babanoell\Loans\Security\Principal;
use Babanoell\Loans\Security\AccessDeniedException;
use Babanoell\Loans\Security\RequestAccessPolicy;
use InvalidArgumentException;
use RuntimeException;

final class TaskService
{
    public function __construct(private readonly TaskRepository $tasks,private readonly PdoLoanRepository $loans){}

    public function syncForRequest(string $requestId,string $actor='system',string $sourceEvent='request.sync'):array
    {
        $request=$this->loans->getRequest($requestId);if($request===null)throw new InvalidArgumentException('پرونده پیدا نشد.');$desired=TaskCatalog::desiredForRequest($request);$desiredCodes=array_column($desired,'task_code');
        $pdo=$this->tasks->pdo();$pdo->beginTransaction();try{$active=$this->tasks->activeWorkflowTasks($requestId);$pdo->commit();}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        foreach($active as$t){if(!in_array((string)$t['task_code'],$desiredCodes,true))$this->tasks->autoComplete((string)$t['id'],$actor,'تکمیل خودکار با تغییر مرحله پرونده');}
        $created=[];foreach($desired as$d){$d['request_id']=$requestId;$d['source']='workflow';$d['source_event']=$sourceEvent;$created[]=$this->tasks->ensure($d,$actor);}
        return['request_id'=>$requestId,'tasks'=>$created];
    }

    public function listFor(Principal $principal,array $filters):array
    {
        $global=$principal->isSuperAdmin()||$principal->can('loans.requests.workflow_admin')||$principal->can('loans.requests.global');
        $bank=in_array('bank_expert',$principal->roles,true)&&!$global?$principal->userId:null;
        return$this->tasks->list($filters,$principal->permissions,$principal->userId,$global,$bank);
    }
    public function summaryFor(Principal $principal):array
    {
        $global=$principal->isSuperAdmin()||$principal->can('loans.requests.workflow_admin')||$principal->can('loans.requests.global');
        $bank=in_array('bank_expert',$principal->roles,true)&&!$global?$principal->userId:null;
        return$this->tasks->summary($principal->permissions,$principal->userId,$global,$bank);
    }

    public function policies(Principal $principal):array
    { if(!$principal->can('loans.requests.workflow_admin'))throw new AccessDeniedException('اجازه مشاهده تنظیمات SLA را ندارید.');return$this->tasks->policies(); }
    public function updatePolicy(Principal $principal,string $code,array $payload):array
    { if(!$principal->can('loans.requests.workflow_admin'))throw new AccessDeniedException('اجازه ویرایش تنظیمات SLA را ندارید.');return$this->tasks->updatePolicy($code,$payload,!empty($payload['apply_existing']),$principal->userId); }
    public function report(Principal $principal,int $days):array
    { if(!$principal->can('loans.requests.workflow_admin')&&!$principal->can('loans.requests.operations')&&!$principal->can('loans.requests.finance'))throw new AccessDeniedException('اجازه مشاهده گزارش گردش‌کار را ندارید.');return$this->tasks->report($days); }

    public function getFor(Principal $principal,string $id):array
    {
        $t=$this->tasks->get($id);if($t===null)throw new InvalidArgumentException('تسک پیدا نشد.');if(!$this->canSee($principal,$t))throw new AccessDeniedException('دسترسی به این تسک مجاز نیست.');$t['history']=$this->tasks->historyForTask($id);return$t;
    }

    public function claim(Principal $principal,string $id):array
    { $t=$this->getFor($principal,$id);$this->assertPermission($principal,(string)$t['required_permission']);return$this->tasks->claim($id,$principal->userId); }
    public function start(Principal $principal,string $id):array{return$this->claim($principal,$id);}
    public function complete(Principal $principal,string $id,string $note=''):array
    { $t=$this->getFor($principal,$id);$this->assertPermission($principal,(string)$t['required_permission']);return$this->tasks->complete($id,$principal->userId,mb_substr(trim($note),0,2000)); }
    public function release(Principal $principal,string $id,string $note=''):array
    { $this->getFor($principal,$id);return$this->tasks->release($id,$principal->userId,mb_substr(trim($note),0,2000)); }
    public function reassign(Principal $principal,string $id,?string $userId,string $note=''):array
    { if(!$principal->can('loans.requests.workflow_admin'))throw new AccessDeniedException('اجازه مدیریت ارجاع تسک را ندارید.');if($userId!==null&&$userId!==''&&preg_match('/^[a-f0-9]{26}$/',$userId)!==1)throw new InvalidArgumentException('شناسه کاربر مقصد معتبر نیست.');return$this->tasks->reassign($id,$principal->userId,$userId,mb_substr(trim($note),0,2000)); }
    public function cancel(Principal $principal,string $id,string $note=''):array
    { if(!$principal->can('loans.requests.workflow_admin'))throw new AccessDeniedException('اجازه لغو تسک را ندارید.');return$this->tasks->cancel($id,$principal->userId,mb_substr(trim($note),0,2000)); }
    public function completeWorkflowCode(string $requestId,string $taskCode,string $actor,string $note=''):void
    { $task=$this->tasks->activeByCode($requestId,$taskCode);if($task!==null)$this->tasks->autoComplete((string)$task['id'],$actor,$note!==''?$note:'تکمیل خودکار با ثبت عملیات متناظر'); }

    public function runSla(Principal $principal):array
    { if(!$principal->can('loans.requests.workflow_admin'))throw new AccessDeniedException('اجازه اجرای پایش SLA را ندارید.');return$this->tasks->runSlaSweep($principal->userId); }

    private function canSee(Principal $p,array $t):bool
    {
        $loan=$this->loans->getRequest((string)($t['request_id']??''));
        if($loan===null||!(new RequestAccessPolicy($p))->canRead($loan))return false;
        $global=$p->isSuperAdmin()||$p->can('loans.requests.workflow_admin')||$p->can('loans.requests.global');
        if(in_array('bank_expert',$p->roles,true)&&!$global){
            if((string)($t['queue_code']??'')!=='bank'||(string)($loan['assigned_bank_user_id']??'')!==$p->userId)return false;
        }
        if($global)return true;
        if((string)($t['assigned_user_id']??'')===$p->userId)return true;
        return trim((string)($t['assigned_user_id']??''))===''&&$p->can((string)($t['required_permission']??''));
    }
    private function assertPermission(Principal $p,string $permission):void
    { if(!$p->can($permission)&&!$p->can('loans.requests.workflow_admin'))throw new AccessDeniedException('دسترسی لازم برای انجام این تسک وجود ندارد.'); }
}

<?php
declare(strict_types=1);
namespace Babanoell\Loans\Workflow;

final class TaskCatalog
{
    public const STATUSES=['open','in_progress','completed','cancelled'];
    public const PRIORITIES=['low','normal','high','urgent'];

    /** @return array<string,array<string,string>> */
    public static function definitions(): array
    {
        return [
            'operations_review'=>['title'=>'بررسی اولیه پرونده','queue'=>'operations','permission'=>'loans.requests.operations','policy'=>'operations_review'],
            'bank_review'=>['title'=>'بررسی کارشناس بانک','queue'=>'bank','permission'=>'loans.requests.bank_review','policy'=>'bank_review'],
            'document_followup'=>['title'=>'پیگیری تکمیل مدارک','queue'=>'operations','permission'=>'loans.requests.document_receipt','policy'=>'document_followup'],
            'document_verification'=>['title'=>'تأیید نهایی مدارک','queue'=>'operations','permission'=>'loans.requests.document_receipt','policy'=>'document_verification'],
            'grant_registration'=>['title'=>'ثبت نهایی اعطای تسهیلات','queue'=>'operations','permission'=>'loans.requests.grant','policy'=>'grant_registration'],
            'bank_settlement'=>['title'=>'ثبت تسویه بانک با بابانوئل','queue'=>'finance','permission'=>'loans.requests.bank_settlement','policy'=>'bank_settlement'],
            'finance_review'=>['title'=>'بررسی مالی پرونده','queue'=>'finance','permission'=>'loans.requests.finance','policy'=>'finance_review'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function desiredForRequest(array $request): array
    {
        $status=(string)($request['status']??'');$defs=self::definitions();$out=[];
        $add=static function(string $code,?string $assignee=null)use(&$out,$defs):void{
            if(!isset($defs[$code]))return;$d=$defs[$code];$out[]=['task_code'=>$code,'title'=>$d['title'],'queue_code'=>$d['queue'],'required_permission'=>$d['permission'],'sla_policy_code'=>$d['policy'],'assigned_user_id'=>$assignee!==null&&trim($assignee)!==''?trim($assignee):null];
        };
        if($status==='under_review'){
            $add('operations_review',(string)($request['assigned_operations_user_id']??''));
            if(trim((string)($request['assigned_bank_user_id']??''))!=='')$add('bank_review',(string)$request['assigned_bank_user_id']);
        }elseif($status==='expert_approved'){
            $add('document_followup',(string)($request['assigned_operations_user_id']??''));
        }elseif($status==='docs_completion'){
            $add('document_verification',(string)($request['assigned_operations_user_id']??''));
        }elseif($status==='docs_verified'){
            $add('grant_registration',(string)($request['assigned_operations_user_id']??''));
        }elseif($status==='granted'){
            $add('bank_settlement',null);$add('finance_review',null);
        }
        return$out;
    }
}

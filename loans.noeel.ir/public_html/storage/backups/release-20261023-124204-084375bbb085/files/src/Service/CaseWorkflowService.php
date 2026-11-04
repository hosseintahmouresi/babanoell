<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\WorkflowCatalog;
use Babanoell\Loans\Domain\CreditValidationPolicy;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;

final class CaseWorkflowService
{
    public function __construct(private readonly PdoLoanRepository $repo) {}

    /** @return array<string,mixed> */
    public function snapshot(string $requestId, bool $includeInternal): array
    {
        return [
            'messages'=>$this->repo->messages($requestId,$includeInternal),
            'comments'=>$includeInternal?$this->repo->comments($requestId):[],
            'assignments'=>$includeInternal?$this->repo->assignments($requestId):[],
            'bank_reviews'=>$includeInternal?$this->repo->bankReviews($requestId):[],
            'grant'=>$this->repo->grantRecord($requestId),
            'credit_validation'=>$this->repo->getRequest($requestId)['credit_validation']??CreditValidationPolicy::summary([]),
            'latest_credit_check'=>$includeInternal?$this->repo->latestCreditCheck($requestId):null,
            'bank_settlement_history'=>$includeInternal?$this->repo->bankSettlementHistory($requestId):[],
            'merchant_settlement_history'=>$includeInternal?$this->repo->merchantSettlementHistory($requestId):[],
            'financier_payment_history'=>$includeInternal?$this->repo->financierPaymentHistory($requestId):[],
            'catalogs'=>[
                'document_receipt'=>WorkflowCatalog::documentReceiptStatuses(),
                'bank_review'=>WorkflowCatalog::bankReviewStatuses(),
                'bank_settlement'=>WorkflowCatalog::bankSettlementStatuses(),
                'merchant_settlement'=>WorkflowCatalog::merchantSettlementStatuses(),
                'financier_payment'=>WorkflowCatalog::financierPaymentStatuses(),
            ],
        ];
    }

    public function message(string $requestId,string $actor,string $role,string $message,array $attachments=[],string $visibility='participants'): array
    {
        $message=trim($message);
        if($message==='') throw new InvalidArgumentException('متن پیام نمی‌تواند خالی باشد.');
        if(mb_strlen($message)>5000) throw new InvalidArgumentException('متن پیام بیش از حد مجاز است.');
        $visibility=strtolower(trim($visibility));
        if(!in_array($visibility,['participants','internal'],true)) throw new InvalidArgumentException('سطح نمایش پیام معتبر نیست.');
        $attachments=array_values(array_unique(array_filter(array_map(static fn($v)=>trim((string)$v),$attachments),static fn($v)=>preg_match('/^[a-f0-9]{26}$/',$v)===1)));
        if(count($attachments)>20) throw new InvalidArgumentException('حداکثر ۲۰ پیوست برای هر پیام قابل ثبت است.');
        return $this->repo->addMessage($requestId,$actor,$role,$message,$visibility,$attachments);
    }

    public function note(string $requestId,string $actor,string $comment): array
    {
        $comment=trim($comment);
        if($comment==='') throw new InvalidArgumentException('متن یادداشت نمی‌تواند خالی باشد.');
        if(mb_strlen($comment)>5000) throw new InvalidArgumentException('متن یادداشت بیش از حد مجاز است.');
        return $this->repo->addComment($requestId,$actor,$comment);
    }

    public function assign(string $requestId,string $actor,string $type,string $userId,string $reason=''): array
    {
        $type=strtolower(trim($type));
        if(!in_array($type,['bank_expert','operations_expert'],true)) throw new InvalidArgumentException('نوع ارجاع معتبر نیست.');
        $userId=trim($userId);
        if(preg_match('/^[a-f0-9]{26}$/',$userId)!==1) throw new InvalidArgumentException('شناسه کاربر مقصد معتبر نیست.');
        return $this->repo->assign($requestId,$type,$userId,$actor,mb_substr(trim($reason),0,1000));
    }

    public function documentReceipt(string $requestId,string $actor,string $status,?string $receivedAt,string $note=''): array
    {
        $status=WorkflowCatalog::assertValue($status,WorkflowCatalog::documentReceiptStatuses(),'وضعیت دریافت مدارک معتبر نیست.');
        if($status==='received' && ($receivedAt===null || trim($receivedAt)==='')) $receivedAt=gmdate('Y-m-d H:i:s');
        if($status!=='received') $receivedAt=null;
        return $this->repo->setDocumentReceipt($requestId,$status,$receivedAt,mb_substr(trim($note),0,1000),$actor);
    }

    public function bankReview(string $requestId,string $actor,string $status,string $reason='',string $note='',string $externalReference=''): array
    {
        $status=WorkflowCatalog::assertValue($status,WorkflowCatalog::bankReviewStatuses(),'نتیجه بررسی بانک معتبر نیست.');
        if(in_array($status,['needs_documents','rejected'],true) && trim($reason)==='') throw new InvalidArgumentException('برای این نتیجه بررسی، ثبت دلیل الزامی است.');
        if($status==='approved'){$request=$this->repo->getRequest($requestId);if($request===null)throw new InvalidArgumentException('پرونده پیدا نشد.');CreditValidationPolicy::assertForTarget($request,'expert_approved');}
        return $this->repo->addBankReview($requestId,$actor,$status,mb_substr(trim($reason),0,1000),mb_substr(trim($note),0,5000),mb_substr(trim($externalReference),0,190));
    }

    public function grant(string $requestId,string $actor,float $amount,string $grantedAt,string $loanNumber='',string $contractNumber='',array $metadata=[]): array
    {
        $request=$this->repo->getRequest($requestId);if($request===null) throw new InvalidArgumentException('پرونده پیدا نشد.');
        CreditValidationPolicy::assertForTarget($request,'granted');
        if($amount<=0) throw new InvalidArgumentException('مبلغ اعطاشده باید بیشتر از صفر باشد.');
        $grantedAt=trim($grantedAt);
        if($grantedAt==='' || strtotime($grantedAt)===false) throw new InvalidArgumentException('تاریخ اعطا معتبر نیست.');
        return $this->repo->grant($requestId,$actor,$amount,date('Y-m-d H:i:s',strtotime($grantedAt)),mb_substr(trim($loanNumber),0,190),mb_substr(trim($contractNumber),0,190),$metadata);
    }

    public function bankSettlement(string $requestId,string $actor,string $status,?string $settledAt,string $note=''): array
    {
        $status=WorkflowCatalog::assertValue($status,WorkflowCatalog::bankSettlementStatuses(),'وضعیت تسویه بانک معتبر نیست.');
        if($status==='recorded' && ($settledAt===null || trim($settledAt)==='')) throw new InvalidArgumentException('برای ثبت تسویه بانک با بابانوئل، تاریخ تسویه الزامی است.');
        if($settledAt!==null && trim($settledAt)!=='' && strtotime($settledAt)===false) throw new InvalidArgumentException('تاریخ تسویه بانک معتبر نیست.');
        if($status!=='recorded') $settledAt=null;
        return $this->repo->recordBankSettlement($requestId,$status,$settledAt!==null&&trim($settledAt)!==''?date('Y-m-d H:i:s',strtotime($settledAt)):null,mb_substr(trim($note),0,1000),$actor);
    }

    public function merchantSettlement(string $requestId,string $actor,string $status,?string $date,?string $cycleId,string $note=''): array
    {
        $status=WorkflowCatalog::assertValue($status,WorkflowCatalog::merchantSettlementStatuses(),'وضعیت تسویه پذیرنده معتبر نیست.');
        if($status==='settled' && ($date===null || trim($date)==='')) throw new InvalidArgumentException('برای وضعیت تسویه‌شده، تاریخ تسویه الزامی است.');
        if($date!==null && trim($date)!=='' && strtotime($date)===false) throw new InvalidArgumentException('تاریخ تسویه معتبر نیست.');
        if($cycleId!==null && $cycleId!=='' && preg_match('/^[a-f0-9]{26}$/',$cycleId)!==1) throw new InvalidArgumentException('شناسه دوره تسویه معتبر نیست.');
        return $this->repo->recordMerchantSettlement($requestId,$status,$date!==null&&trim($date)!==''?date('Y-m-d H:i:s',strtotime($date)):null,$cycleId!==''?$cycleId:null,mb_substr(trim($note),0,1000),$actor);
    }

    public function financierPayment(string $requestId,string $actor,string $status,string $note=''): array
    {
        $status=WorkflowCatalog::assertValue($status,WorkflowCatalog::financierPaymentStatuses(),'وضعیت پرداخت تأمین‌کننده مالی معتبر نیست.');
        return $this->repo->recordFinancierPayment($requestId,$status,mb_substr(trim($note),0,1000),$actor);
    }
}

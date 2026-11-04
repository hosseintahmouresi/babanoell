<?php
declare(strict_types=1);
namespace Babanoell\Documents\Service;
use Babanoell\Documents\Domain\ValidatedUpload;
use Babanoell\Documents\Domain\ContractDocumentOwner;
use Babanoell\Documents\Http\PartnersClient;
use Babanoell\Documents\Pdf\ContractPdfRenderer;
use Babanoell\Documents\Repository\PdoDocumentRepository;
use Babanoell\Documents\Storage\PrivateStorage;
use PDO;
use RuntimeException;

/** Signed PDF is a separate immutable document, keyed by the original contract revision ID. */
final class ContractPdfService
{
    public function __construct(private readonly PdoDocumentRepository $repo, private readonly PrivateStorage $storage,
        private readonly PartnersClient $partners, private readonly ContractPdfRenderer $renderer){}

    public function ensure(array $contract, ?string $ip, ?string $ua): array
    {
        $cid=(string)($contract['id']??'');
        if(!preg_match('/^[a-f0-9]{26}$/',$cid)||($contract['status']??'')!=='signed')throw new RuntimeException('قرارداد امضاشده معتبر نیست.');
        $owner=ContractDocumentOwner::resolve($contract);
        $pdo=$this->repo->pdo();$lock='noel_pdf_'.hash('sha256',$cid);
        $l=$pdo->prepare('SELECT GET_LOCK(:name,10)');$l->execute([':name'=>$lock]);
        if((int)$l->fetchColumn()!==1)throw new RuntimeException('تولید PDF هم‌زمان است؛ بعداً مجدداً تلاش کنید.');
        try {
            $q=$pdo->prepare('SELECT document_id,checksum_sha256,source_rendered_hash FROM contract_pdf_artifacts WHERE contract_id=:id');$q->execute([':id'=>$cid]);
            $stored=$q->fetch(PDO::FETCH_ASSOC);
            if(is_array($stored)){
                $doc=$this->repo->get((string)$stored['document_id']);
                if(!is_array($doc)||!hash_equals((string)$stored['checksum_sha256'],(string)($doc['checksum_sha256']??''))||!hash_equals((string)($contract['rendered_hash']??''),(string)($stored['source_rendered_hash']??'')))throw new RuntimeException('سند PDF آرشیوشده نیازمند بررسی صحت است.');
                $this->ack($cid,(string)$stored['document_id'],(string)$stored['checksum_sha256']);
                return $doc;
            }
            $binary=$this->renderer->render($contract);
            $sha=hash('sha256',$binary);
            $tmp=tempnam(sys_get_temp_dir(),'noel-pdf-');if($tmp===false)throw new RuntimeException('فضای موقت ساخت سند موجود نیست.');
            try{
                if(file_put_contents($tmp,$binary,LOCK_EX)!==strlen($binary))throw new RuntimeException('نوشتن فایل PDF ناقص بود.');
                $no=preg_replace('/[^A-Za-z0-9_-]/','',(string)($contract['contract_no']??''))?:'signed';
                $file=new ValidatedUpload($tmp,'noel-contract-'.$no.'-revision-'.(int)($contract['revision']??1).'.pdf','application/pdf','pdf',strlen($binary),$sha);
                $key=$this->storage->store($file);
                $subjectType=(string)($contract['subject_type']??'');$subjectId=(string)($contract['subject_id']??'');
                if(!in_array($subjectType,['loan_request','cooperation_request'],true)||!preg_match('/^[a-f0-9]{26}$/',$subjectId))throw new RuntimeException('موضوع سند PDF معتبر نیست.');
                $actor=(string)($contract['created_by_user_id']??'');if(!preg_match('/^(?!0{26}$)[a-f0-9]{26}$/D',$actor))$actor=$owner;
                try {
                    $storeIndex=static function(array$created)use($pdo,$cid,$sha,$contract):void{
                        $s=$pdo->prepare('INSERT INTO contract_pdf_artifacts (contract_id,document_id,checksum_sha256,source_rendered_hash) VALUES(:cid,:did,:sha,:source)');
                        $s->execute([':cid'=>$cid,':did'=>$created['id'],':sha'=>$sha,':source'=>$contract['rendered_hash']]);
                    };
                    $doc=$this->repo->create($actor,'signed_contract_pdf','contract','PDF قرارداد امضاشده','confidential',$owner,$subjectType,$subjectId,'signed_contract_pdf_'.$cid,$file,$key,$ip,$ua,$storeIndex);
                }catch(\Throwable $e){$this->storage->remove($key);throw $e;}
                $this->ack($cid,(string)$doc['id'],$sha);
                return $doc;
            }finally{@unlink($tmp);}
        }finally{
            try{$r=$pdo->prepare('SELECT RELEASE_LOCK(:name)');$r->execute([':name'=>$lock]);}catch(\Throwable){}
        }
    }
    private function ack(string $cid,string $doc,string $sha): void
    {
        $response=$this->partners->contractPdfStored($cid,$doc,$sha);
        if(!$response->ok())error_log('[documents] PDF stored, pending Partners acknowledgement for contract '.substr($cid,0,8));
    }
}

<?php
declare(strict_types=1);
namespace Babanoell\Documents\Service;
use Babanoell\Documents\Domain\ValidatedUpload;use Babanoell\Documents\Domain\ContractDocumentOwner;use Babanoell\Documents\Http\PartnersClient;use Babanoell\Documents\Repository\PdoDocumentRepository;use Babanoell\Documents\Storage\PrivateStorage;use InvalidArgumentException;use RuntimeException;
final class ContractArtifactService
{
 public function __construct(private readonly PdoDocumentRepository $repo,private readonly PrivateStorage $storage,private readonly PartnersClient $partners,private readonly ?ContractPdfService $pdf=null){}
 public function store(array $b,?string $ip,?string $ua):array
 {
  $cid=strtolower(trim((string)($b['contract_id']??'')));$token=trim((string)($b['handoff_token']??''));$sha=strtolower(trim((string)($b['sha256']??'')));$encoded=(string)($b['content_base64']??'');$name=trim((string)($b['filename']??'contract.html'));
  if(!preg_match('/^[a-f0-9]{26}$/',$cid)||strlen($token)<30||!preg_match('/^[a-f0-9]{64}$/',$sha))throw new InvalidArgumentException('اطلاعات تحویل قرارداد معتبر نیست.');
  $content=base64_decode($encoded,true);if(!is_string($content)||$content===''||strlen($content)>5*1024*1024)throw new InvalidArgumentException('محتوای قرارداد معتبر نیست.');if(!hash_equals($sha,hash('sha256',$content)))throw new InvalidArgumentException('هش محتوای قرارداد مطابقت ندارد.');
  $auth=$this->partners->consumeContractArtifact($cid,$token,$sha);if(!$auth->ok())throw new RuntimeException('مجوز یک‌بارمصرف ذخیره قرارداد تأیید نشد.');$expectedSha=strtolower((string)($auth->json['data']['expected_sha256']??''));if(!preg_match('/^[a-f0-9]{64}$/',$expectedSha)||!hash_equals($expectedSha,$sha))throw new RuntimeException('هش مجاز قرارداد با محتوای تحویلی مطابقت ندارد.');$contract=$auth->json['data']['contract']??null;if(!is_array($contract)||($contract['status']??'')!=='signed')throw new RuntimeException('قرارداد امضاشده قابل تأیید نیست.');
  $subjectType=(string)($contract['subject_type']??'');$subjectId=(string)($contract['subject_id']??'');if(!in_array($subjectType,['loan_request','cooperation_request'],true)||!preg_match('/^[a-f0-9]{26}$/',$subjectId))throw new RuntimeException('موضوع قرارداد معتبر نیست.');
  $documentOwner=ContractDocumentOwner::resolve($contract);
  $relation='signed_contract_'.$cid;$pdo=$this->repo->pdo();$q=$pdo->prepare('SELECT d.id FROM document_links l JOIN documents d ON d.id=l.document_id WHERE l.entity_type=:t AND l.entity_id=:e AND l.relation_code=:r AND d.status=\'active\' LIMIT 1');$q->execute([':t'=>$subjectType,':e'=>$subjectId,':r'=>$relation]);$existing=$q->fetchColumn();if(is_string($existing)&&$existing!==''){$doc=$this->repo->get($existing);$this->partners->contractArtifactStored($cid,$existing,$sha);$this->generatePdf($contract,$ip,$ua);return $doc??['id'=>$existing];}
  $tmp=tempnam(sys_get_temp_dir(),'noel-contract-');if($tmp===false)throw new RuntimeException('فایل موقت قرارداد ساخته نشد.');file_put_contents($tmp,$content,LOCK_EX);$key=null;
  try{$file=new ValidatedUpload($tmp,$name!==''?$name:'contract.html','text/html','html',strlen($content),$sha);$key=$this->storage->store($file);$doc=$this->repo->create($documentOwner,'signed_contract','contract','قرارداد امضا شده','confidential',$documentOwner,$subjectType,$subjectId,$relation,$file,$key,$ip,$ua);}
  catch(\Throwable$e){if(is_string($key))$this->storage->remove($key);throw$e;}
  finally{@unlink($tmp);}
  $notify=$this->partners->contractArtifactStored($cid,(string)$doc['id'],$sha);if(!$notify->ok())error_log('[documents] contract artifact stored but Partners acknowledgement failed: '.$cid);$this->generatePdf($contract,$ip,$ua);return$doc;
 }
 private function generatePdf(array $contract,?string $ip,?string $ua):void
 {
  if($this->pdf===null)return;
  try{$this->pdf->ensure($contract,$ip,$ua);}catch(\Throwable $e){error_log('[documents] signed PDF deferred: '.$e->getMessage());}
 }
}

<?php
declare(strict_types=1);
namespace Babanoell\Partners\Contract;
use Babanoell\Partners\Support\RandomId;
use PDO;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

/** Reissue never mutates a previously signed snapshot or its signature. */
final class ContractRevisionService
{
 public function __construct(private readonly PDO $pdo,private readonly ContractRepository $contracts){}

 public function reissue(string $id,string $actor,array $input):array
 {
  $reason=trim((string)($input['reason']??''));
  $expected=(int)($input['row_version']??0);
  if((function_exists('mb_strlen')?mb_strlen($reason,'UTF-8'):strlen($reason))<10||(function_exists('mb_strlen')?mb_strlen($reason,'UTF-8'):strlen($reason))>1000||$expected<1)throw new InvalidArgumentException('دلیل بازصدور (حداقل ده کاراکتر) و نسخه صحیح رکورد الزامی است.');
  $mode=strtolower(trim((string)($input['mode']??'')));
  if($mode!==''&&!in_array($mode,['physical','digital'],true))throw new InvalidArgumentException('نوع قرارداد باید فیزیکی یا دیجیتال باشد.');
  $hasNewHtml=array_key_exists('content_html',$input);
  $newHtml=$hasNewHtml?(string)$input['content_html']:null;
  if($hasNewHtml){
   if(trim($newHtml)===''||strlen($newHtml)>500000)throw new InvalidArgumentException('متن قرارداد جدید خالی یا بیش از حد مجاز است.');
   // The public renderer also sends a restrictive CSP. Do not permit active markup in newly authored revisions.
   if(preg_match('~<\s*(?:script|iframe|object|embed|form|input|button|base|meta|link|svg|math)\b|\bon[a-z]+\s*=|(?:javascript|vbscript|data)\s*:~iu',$newHtml))throw new InvalidArgumentException('متن قرارداد حاوی کد یا عناصر فعال مجازنشده است.');
  }
  $this->pdo->beginTransaction();
  try {
   $s=$this->pdo->prepare('SELECT * FROM contracts WHERE id=:id FOR UPDATE');$s->execute([':id'=>$id]);$old=$s->fetch(PDO::FETCH_ASSOC);
   if(!is_array($old))throw new RuntimeException('قرارداد پیدا نشد.');
   if((int)$old['row_version']!==$expected)throw new RuntimeException('قرارداد همزمان تغییر کرده است. اطلاعات را تازه‌سازی کنید.');
   if(!in_array((string)$old['status'],['pending','sent','viewed','signed'],true))throw new RuntimeException('بازصدور از این وضعیت قرارداد مجاز نیست.');
   if((string)$old['mode']==='legacy')throw new RuntimeException('برای قرارداد مهاجرتی، ابتدا متن و مدارک واقعی نسخه اصلی باید بازیابی شوند.');
   $s=$this->pdo->prepare('SELECT id,revision FROM contracts WHERE subject_type=:type AND subject_id=:subject AND contract_kind=:kind ORDER BY revision DESC LIMIT 1 FOR UPDATE');
   $s->execute([':type'=>$old['subject_type'],':subject'=>$old['subject_id'],':kind'=>$old['contract_kind']]);$latest=$s->fetch(PDO::FETCH_ASSOC);
   if(!is_array($latest)||$latest['id']!==$old['id'])throw new RuntimeException('فقط آخرین نسخه قرارداد قابل بازصدور است.');
   $revision=(int)$old['revision']+1;$newId=RandomId::make();$number='CTR-'.gmdate('Ymd').'-'.strtoupper(substr($newId,0,10));
   $content=$hasNewHtml?$newHtml:(string)$old['rendered_content'];
   $targetMode=$mode!==''?$mode:(string)$old['mode'];
   if((string)$old['status']!=='signed'){
    $s=$this->pdo->prepare("UPDATE contracts SET status='cancelled',public_token_hash=NULL,public_token_expires_at=NULL,row_version=row_version+1 WHERE id=:id AND status IN ('pending','sent','viewed')");
    $s->execute([':id'=>$id]);if($s->rowCount()!==1)throw new RuntimeException('نسخه قبلی دیگر قابل لغو نیست.');
    $this->pdo->prepare("UPDATE contract_otp_challenges SET status='superseded' WHERE contract_id=:id AND status='active'")->execute([':id'=>$id]);
   }
   $sql='INSERT INTO contracts(id,contract_no,contract_kind,revision,subject_type,subject_id,reissued_from_id,reissue_reason,signer_user_id,signer_mobile,mode,status,template_id,template_code,template_version,template_hash,rendered_content,rendered_hash,artifact_status,created_by_user_id) VALUES(:id,:no,:kind,:revision,:stype,:subject,:from,:reason,:signer,:mobile,:mode,\'pending\',:template_id,:template_code,:template_version,:template_hash,:html,:hash,:artifact,:actor)';
   $s=$this->pdo->prepare($sql);
   $s->execute([':id'=>$newId,':no'=>$number,':kind'=>$old['contract_kind'],':revision'=>$revision,':stype'=>$old['subject_type'],':subject'=>$old['subject_id'],':from'=>$old['id'],':reason'=>$reason,':signer'=>$old['signer_user_id'],':mobile'=>$old['signer_mobile'],':mode'=>$targetMode,':template_id'=>$old['template_id'],':template_code'=>$old['template_code'],':template_version'=>$old['template_version'],':template_hash'=>$old['template_hash'],':html'=>$content,':hash'=>hash('sha256',$content),':artifact'=>$targetMode==='physical'?'not_required':'pending',':actor'=>$actor]);
   $this->contracts->event($id,'contract.reissued',$actor,'allowed',['new_contract_id'=>$newId,'revision'=>$revision,'reason'=>$reason],null,null);
   $this->contracts->event($newId,'contract.revision_created',$actor,'allowed',['previous_contract_id'=>$id,'revision'=>$revision,'reason'=>$reason],null,null);
   $this->pdo->commit();
   return $this->contracts->contract($newId)??throw new RuntimeException('قرارداد جدید قابل بازیابی نیست.');
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
}

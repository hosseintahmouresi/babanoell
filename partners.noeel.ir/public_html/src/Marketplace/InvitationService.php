<?php
declare(strict_types=1);
namespace Babanoell\Partners\Marketplace;
use PDO;
use Babanoell\Partners\Support\Json;

/** Reference referral codes are reusable; a user's existing unexpired merchant lock wins by default. */
final class InvitationService
{
 public function __construct(private readonly PDO $pdo,private readonly MarketplaceConfiguration $configuration){}
 public function code(array $merchant):array{
  $settings=$this->configuration->current();
  if(!$settings['enable_acceptor_invitation_links']||$merchant['status']!=='active'||$merchant['cooperation_status']!=='approved')throw new \InvalidArgumentException('دعوت‌نامهٔ این پذیرنده فعال نیست.');
  $q=$this->pdo->prepare('INSERT IGNORE INTO merchant_referral_codes(merchant_id,code) VALUES(:id,:code)');$q->execute([':id'=>$merchant['id'],':code'=>'N'.strtoupper(bin2hex(random_bytes(10)))]);
  $q=$this->pdo->prepare('SELECT code FROM merchant_referral_codes WHERE merchant_id=:id');$q->execute([':id'=>$merchant['id']]);$code=$q->fetchColumn();
  if(!is_string($code))throw new \RuntimeException('referral_code_unavailable');
  return ['code'=>$code,'merchant_id'=>$merchant['id'],'path'=>'/applicant/invitation?code='.rawurlencode($code),'lock_days'=>$settings['invitation_lock_days']];
 }
 public function current(string $applicant):?array{
  if(!$this->configuration->current()['enable_acceptor_invitation_links'])return null;
  $q=$this->pdo->prepare('SELECT l.merchant_id,l.expires_at,m.name,m.owner_user_id,m.status,m.cooperation_status FROM applicant_invitation_locks l JOIN merchants m ON m.id=l.merchant_id WHERE l.applicant_user_id=:user AND l.expires_at>=UTC_TIMESTAMP(6)');$q->execute([':user'=>$applicant]);$row=$q->fetch(PDO::FETCH_ASSOC);
  return is_array($row)?$row:null;
 }
 public function apply(string $applicant,string $code):array{
  $settings=$this->configuration->current();
  if(!$settings['enable_acceptor_invitation_links']||!preg_match('/^[A-Za-z0-9_-]{8,40}$/D',$code))throw new \InvalidArgumentException('کد دعوت فعال یا معتبر نیست.');
  $q=$this->pdo->prepare("SELECT m.id FROM merchant_referral_codes r JOIN merchants m ON m.id=r.merchant_id WHERE r.code=:code AND m.status='active' AND m.cooperation_status='approved'");$q->execute([':code'=>$code]);$merchant=$q->fetchColumn();
  if(!is_string($merchant))throw new \InvalidArgumentException('کد دعوت فعال یا معتبر نیست.');
  $this->pdo->beginTransaction();
  try{
   $q=$this->pdo->prepare('INSERT IGNORE INTO applicant_invitation_locks(applicant_user_id) VALUES(:user)');$q->execute([':user'=>$applicant]);
   $q=$this->pdo->prepare('SELECT merchant_id,expires_at FROM applicant_invitation_locks WHERE applicant_user_id=:user FOR UPDATE');$q->execute([':user'=>$applicant]);$old=$q->fetch(PDO::FETCH_ASSOC);
   $active=!empty($old['merchant_id'])&&!empty($old['expires_at'])&&strtotime($old['expires_at'].' UTC')>=time();
   if(!$active||$settings['allow_override_existing_invitation_lock']){
    $expires=gmdate('Y-m-d H:i:s',time()+$settings['invitation_lock_days']*86400);
    $q=$this->pdo->prepare('UPDATE applicant_invitation_locks SET merchant_id=:merchant,expires_at=:expires WHERE applicant_user_id=:user');$q->execute([':merchant'=>$merchant,':expires'=>$expires,':user'=>$applicant]);
    $q=$this->pdo->prepare("INSERT INTO partner_events(id,aggregate_type,aggregate_id,event_type,actor_user_id,result,details_json) VALUES(:id,'merchant',:merchant,'referral.applicant.bound',:actor,'allowed',:details)");$q->execute([':id'=>bin2hex(random_bytes(13)),':merchant'=>$merchant,':actor'=>$applicant,':details'=>Json::encode(['expires_at'=>$expires])]);
   }
   $this->pdo->commit();
  }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
  return $this->current($applicant)??throw new \RuntimeException('referral_binding_unavailable');
 }
}

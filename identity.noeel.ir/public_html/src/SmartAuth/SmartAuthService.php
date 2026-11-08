<?php
declare(strict_types=1);
namespace Babanoell\Auth\SmartAuth;

use Babanoell\Auth\Audit\{AuditContext,AuditEvent,AuditEventType,AuditWriterInterface};
use Babanoell\Auth\Models\User;
use Babanoell\Auth\Security\{InputNormalizer,PasswordHasherInterface};
use Babanoell\Auth\Support\RandomIdGenerator;
use Babanoell\Auth\UseCases\Login\{LoginSecurityContext,SessionIssuerInterface};
use PDO;
use Throwable;

/** All entry points are called only by the body-authenticated Panel boundary. */
final class SmartAuthService {
 public function __construct(private readonly PDO $pdo,private readonly PasswordHasherInterface $hasher,private readonly SessionIssuerInterface $issuer,private readonly AuditWriterInterface $audit,private readonly string $pepper,private readonly \Closure $send){}
 public function settings():array {
  $s=$this->pdo->query('SELECT * FROM auth_smart_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
  if(!$s)throw new AuthFailure(503,'auth_unavailable','تنظیمات ورود دریافت نشد.');
  return ['sms_pattern_id'=>$s['sms_pattern_id']===null?null:(int)$s['sms_pattern_id'],'registration_enabled'=>(bool)$s['registration_enabled'],'otp_enabled'=>(bool)$s['otp_enabled'],'revision'=>(int)$s['revision'],'sms_configured'=>(int)($s['sms_pattern_id']??0)>0];
 }
 public function saveSettings(array $b,string $actor):array {
  foreach(['registration_enabled','otp_enabled'] as $key)if(!is_bool($b[$key]??null))throw new AuthFailure(422,'validation_failed','تنظیم فعال‌سازی معتبر نیست.');
  $pattern=$b['sms_pattern_id']??null;if($pattern!==null&&(!is_int($pattern)||$pattern<1||$pattern>2147483647))throw new AuthFailure(422,'validation_failed','شناسهٔ الگوی پیامک معتبر نیست.');
  if(!is_int($b['revision']??null)||$b['revision']<0)throw new AuthFailure(422,'validation_failed','نسخهٔ تنظیمات معتبر نیست.');
  $this->pdo->beginTransaction();try{$q=$this->pdo->prepare('UPDATE auth_smart_settings SET sms_pattern_id=?,registration_enabled=?,otp_enabled=?,revision=revision+1 WHERE id=1 AND revision=?');$q->execute([$pattern,(int)$b['registration_enabled'],(int)$b['otp_enabled'],$b['revision']]);if($q->rowCount()!==1)throw new AuthFailure(409,'settings_conflict','تنظیمات تغییر کرده است؛ صفحه را تازه کنید.');$this->writeAudit(AuditEventType::SmartSettingsChanged,$actor,['revision'=>$b['revision']+1]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}return $this->settings();
 }
 public function discover(array $b):array {
  $identifier=$this->text($b,'identifier',255);$identifier=(new InputNormalizer())->loginIdentifier($identifier);
  if($identifier==='')throw new AuthFailure(422,'validation_failed','شماره موبایل یا نام کاربری را وارد کنید.');
  $this->limit('discover-ip|'.$this->source($b),30,300);$this->limit('discover-id|'.$identifier,10,300);
  $u=$this->find($identifier);if($u){$this->active($u);if(trim((string)$u['password_hash'])!=='')return ['next'=>'password','identifier'=>$identifier];return $this->newChallenge('login',(string)$u['mobile'],(string)$u['id'],null,$b);}
  if(!$this->settings()['registration_enabled'])throw new AuthFailure(403,'registration_disabled','ثبت‌نام تازه در حال حاضر فعال نیست.');
  if(!preg_match('/^09[0-9]{9}$/D',$identifier)&&!self::validUsername($identifier))throw new AuthFailure(422,'invalid_identifier','موبایل معتبر یا نام کاربری شامل حروف انگلیسی وارد کنید.');
  return ['next'=>'register','identifier'=>$identifier,'mobile'=>preg_match('/^09[0-9]{9}$/D',$identifier)?$identifier:'','username'=>self::validUsername($identifier)?$identifier:''];
 }
 public function register(array $b):array {
  if(!$this->settings()['registration_enabled'])throw new AuthFailure(403,'registration_disabled','ثبت‌نام تازه فعال نیست.');
  $this->limit('register-ip|'.$this->source($b),10,900);
  $mobile=InputNormalizer::mobile($this->text($b,'mobile',32));$name=$this->text($b,'display_name',200);$username=strtolower($this->text($b,'username',64,false));$password=$this->text($b,'password',4096,false,false);
  if(!preg_match('/^09[0-9]{9}$/D',$mobile)||mb_strlen($name)<3||preg_match('/[<>\x00-\x1f]/u',$name))throw new AuthFailure(422,'validation_failed','نام و نام خانوادگی و شماره موبایل معتبر را وارد کنید.');
  if($username!==''&&!self::validUsername($username))throw new AuthFailure(422,'invalid_username','نام کاربری باید ۳ تا ۶۴ نویسه و شامل حروف انگلیسی، عدد، نقطه یا زیرخط باشد و با حرف شروع شود.');
  if($password!==''&&(mb_strlen($password)<10||strlen($password)>1024))throw new AuthFailure(422,'invalid_password','رمز عبور باید دست‌کم ۱۰ نویسه و حداکثر ۱۰۲۴ بایت باشد.');
  if($this->find($mobile)!==null||($username!==''&&$this->find($username)!==null))throw new AuthFailure(409,'account_exists','این موبایل یا نام کاربری قبلاً ثبت شده است؛ از مرحلهٔ ورود ادامه دهید.');
  $data=['display_name'=>$name,'username'=>$username?:null,'password_hash'=>$password!==''?$this->hasher->hash($password):null];
  return $this->newChallenge('register',$mobile,null,$data,$b);
 }
 public function resend(array $b):array {
  $id=$this->challengeId($b);$this->smsReady();$this->limit('resend-ip|'.$this->source($b),10,900);
  $this->pdo->beginTransaction();try{$c=$this->locked($id);if($c['consumed_at']!==null||(int)$c['attempts']>=5)throw new AuthFailure(410,'challenge_expired','اعتبار درخواست تمام شده است؛ از ورود شروع کنید.');$age=time()-strtotime($c['created_at'].' UTC');if((int)$c['send_count']>=3||$age>900)throw new AuthFailure(429,'resend_limit','برای دریافت کد تازه، دوباره از مرحلهٔ ورود شروع کنید.');$this->smsLimits($c['mobile'],$b);$code=$this->code();$q=$this->pdo->prepare("UPDATE auth_phone_challenges SET code_hash=?,delivery_status='sending',send_count=send_count+1,expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE) WHERE id=?");$q->execute([$this->hash($id,$code),$id]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  return $this->deliver($id,$c['mobile'],$code,(int)$c['send_count']+1);
 }
 public function verify(array $b,LoginSecurityContext $context):array {
  $id=$this->challengeId($b);$code=InputNormalizer::digits($this->text($b,'code',12));if(!preg_match('/^[0-9]{6}$/D',$code))throw new AuthFailure(422,'invalid_code','کد شش‌رقمی پیامک را وارد کنید.');
  if(!$this->settings()['otp_enabled'])throw new AuthFailure(403,'otp_disabled','ورود پیامکی فعال نیست.');$this->limit('verify-ip|'.$this->source($b),30,900);$this->pdo->beginTransaction();
  try {
   $c=$this->locked($id);$this->usable($c);if($c['delivery_status']!=='sent')throw new AuthFailure(409,'sms_not_sent','ارسال کد تأیید نشده است؛ دوباره کد بگیرید.');
   if(!hash_equals($c['code_hash'],$this->hash($id,$code))){$q=$this->pdo->prepare('UPDATE auth_phone_challenges SET attempts=attempts+1 WHERE id=?');$q->execute([$id]);$this->pdo->commit();throw new AuthFailure(422,'invalid_code','کد نادرست است یا اعتبار ندارد.');}
   if($c['purpose']==='register'){
    if(!$this->settings()['registration_enabled'])throw new AuthFailure(403,'registration_disabled','ثبت‌نام تازه فعال نیست.');
    $d=json_decode((string)$c['registration_json'],true,512,JSON_THROW_ON_ERROR);$role=$this->pdo->query("SELECT id FROM roles WHERE name='applicant'")->fetchColumn();if(!$role)throw new AuthFailure(503,'auth_unavailable','نقش متقاضی آماده نیست.');
    if($this->find($c['mobile'])!==null||(!empty($d['username'])&&$this->find($d['username'])!==null))throw new AuthFailure(409,'account_exists','حساب قبلاً ایجاد شده است؛ از ورود ادامه دهید.');
    $uid=(new RandomIdGenerator())->generate();$q=$this->pdo->prepare("INSERT INTO users (id,mobile,username,display_name,status,password_hash,password_changed_at,mobile_verified_at) VALUES (?,?,?,?,'active',?,CASE WHEN ? IS NULL THEN NULL ELSE UTC_TIMESTAMP(6) END,UTC_TIMESTAMP(6))");$q->execute([$uid,$c['mobile'],$d['username'],$d['display_name'],$d['password_hash'],$d['password_hash']]);
    $q=$this->pdo->prepare('INSERT INTO user_roles (user_id,role_id,created_at,created_by_user_id) VALUES (?,?,UTC_TIMESTAMP(6),NULL)');$q->execute([$uid,$role]);$this->writeAudit(AuditEventType::ApplicantRegistered,$uid,[], $context);
   }else{
    if(!$this->settings()['otp_enabled'])throw new AuthFailure(403,'otp_disabled','ورود پیامکی فعال نیست.');
    $q=$this->pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$c['user_id']]);$row=$q->fetch(PDO::FETCH_ASSOC);$this->active($row?:null);
    if(trim((string)$row['password_hash'])!==''||$row['mobile']!==$c['mobile'])throw new AuthFailure(409,'login_changed','روش ورود حساب تغییر کرده است؛ دوباره از ورود شروع کنید.');$uid=$row['id'];
    $q=$this->pdo->prepare('UPDATE users SET mobile_verified_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$uid]);
   }
   $q=$this->pdo->prepare('SELECT * FROM users WHERE id=?');$q->execute([$uid]);$u=User::fromRow($q->fetch(PDO::FETCH_ASSOC));$session=$this->issuer->issue($u,$context);
   if(!$session->issuedSuccessfully())throw new AuthFailure(503,'auth_unavailable','نشست ورود ایجاد نشد.');
   $this->writeAudit(AuditEventType::LoginSucceeded,$uid,['method'=>'sms'],$context,$session->sessionId());
   $q=$this->pdo->prepare('UPDATE auth_phone_challenges SET consumed_at=UTC_TIMESTAMP(6),registration_json=NULL,code_hash=? WHERE id=?');$q->execute([str_repeat('0',64),$id]);
   $q=$this->pdo->prepare('UPDATE users SET last_login_at=UTC_TIMESTAMP(6) WHERE id=?');$q->execute([$uid]);$this->pdo->commit();
   return ['session'=>array_filter(['session_credential'=>$session->sessionCredential(),'refresh_credential'=>$session->refreshCredential(),'access_expires_at'=>$session->accessExpiresAt()?->format(DATE_ATOM),'refresh_expires_at'=>$session->refreshExpiresAt()?->format(DATE_ATOM)],static fn($x)=>$x!==null)];
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException&&(string)$e->getCode()==='23000')throw new AuthFailure(409,'account_exists','حساب قبلاً ایجاد شده است؛ از ورود ادامه دهید.');throw $e;}
 }
 private function newChallenge(string $purpose,string $mobile,?string $user,?array $data,array $b):array {
  $this->smsReady();if(!preg_match('/^09[0-9]{9}$/D',$mobile))throw new AuthFailure(409,'mobile_missing','شمارهٔ موبایل معتبر برای این حساب ثبت نشده است؛ با پشتیبانی تماس بگیرید.');
  $this->pdo->beginTransaction();try{$this->smsLimits($mobile,$b);$id=bin2hex(random_bytes(24));$code=$this->code();$q=$this->pdo->prepare("INSERT INTO auth_phone_challenges (id,user_id,purpose,mobile,code_hash,registration_json,delivery_status,created_at,expires_at) VALUES (?,?,?,?,?,?,'sending',UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE))");$q->execute([$id,$user,$purpose,$mobile,$this->hash($id,$code),$data===null?null:json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  return $this->deliver($id,$mobile,$code,1);
 }
 private function deliver(string $id,string $mobile,string $code,int $sequence):array {
  try{$ok=($this->send)($mobile,(int)$this->settings()['sms_pattern_id'],$code,'auth_'.$id.'_'.$sequence);}catch(Throwable){$ok=false;}
  $q=$this->pdo->prepare('UPDATE auth_phone_challenges SET delivery_status=?,sent_at=CASE WHEN ?=1 THEN UTC_TIMESTAMP(6) ELSE NULL END WHERE id=?');$q->execute([$ok?'sent':'failed',(int)$ok,$id]);
  if(!$ok)throw new AuthFailure(503,'sms_unavailable','ارسال پیامک تأیید نشد. کمی بعد دوباره تلاش کنید.');
  return ['next'=>'otp','challenge_id'=>$id,'mobile_hint'=>substr($mobile,0,4).'•••'.substr($mobile,-2),'expires_in'=>300,'resend_after'=>60];
 }
 private function smsReady():void {$s=$this->settings();if(!$s['otp_enabled']||!$s['sms_configured']||strlen($this->pepper)<32)throw new AuthFailure(503,'sms_not_configured','ورود و ثبت‌نام پیامکی هنوز توسط مدیر سامانه آماده نشده است.');}
 private function usable(array $c):void {if($c['consumed_at']!==null||(int)$c['attempts']>=5||strtotime($c['expires_at'].' UTC')<=time())throw new AuthFailure(410,'challenge_expired','اعتبار کد تمام شده است؛ دوباره از ورود شروع کنید.');}
 private function locked(string $id):array {$q=$this->pdo->prepare('SELECT * FROM auth_phone_challenges WHERE id=? FOR UPDATE');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:throw new AuthFailure(410,'challenge_expired','اعتبار درخواست ورود تمام شده است.');}
 private function active(?array $u):void {if(!$u||$u['status']!=='active'||$u['disabled_at']!==null||($u['locked_until']!==null&&strtotime($u['locked_until'].' UTC')>time()))throw new AuthFailure(403,'account_unavailable','امکان ورود به این حساب وجود ندارد؛ با پشتیبانی تماس بگیرید.');}
 private function find(string $identifier):?array {$q=$this->pdo->prepare('SELECT * FROM users WHERE mobile=? OR username=? OR email=? LIMIT 1');$q->execute([$identifier,$identifier,$identifier]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
 private function challengeId(array $b):string {$id=$this->text($b,'challenge_id',48);if(!preg_match('/^[a-f0-9]{48}$/D',$id))throw new AuthFailure(422,'invalid_challenge','درخواست ورود معتبر نیست.');return $id;}
 private function smsLimits(string $mobile,array $b):void {$this->cooldown($mobile);$this->limit('sms-mobile|'.$mobile,5,900);$this->limit('sms-ip|'.$this->source($b),10,900);}
 private function cooldown(string $mobile):void {
  $hash=hash_hmac('sha256','sms-cooldown|'.$mobile,$this->pepper);$q=$this->pdo->prepare('INSERT IGNORE INTO auth_smart_limits (bucket_hash,window_start,expires_at) VALUES (?,0,0)');$q->execute([$hash]);$q=$this->pdo->prepare('SELECT expires_at FROM auth_smart_limits WHERE bucket_hash=? AND window_start=0 FOR UPDATE');$q->execute([$hash]);$expires=(int)$q->fetchColumn();if($expires>time())throw new AuthFailure(429,'rate_limited','برای ارسال دوبارهٔ پیامک کمی صبر کنید.',$expires-time());$q=$this->pdo->prepare('UPDATE auth_smart_limits SET expires_at=?,hits=hits+1 WHERE bucket_hash=? AND window_start=0');$q->execute([time()+60,$hash]);
 }
 private function limit(string $subject,int $max,int $window):void {
  $own=!$this->pdo->inTransaction();if($own)$this->pdo->beginTransaction();try{$now=time();$start=intdiv($now,$window)*$window;$hash=hash_hmac('sha256','smart-limit|'.$subject,$this->pepper);$q=$this->pdo->prepare('INSERT IGNORE INTO auth_smart_limits (bucket_hash,window_start,expires_at) VALUES (?,?,?)');$q->execute([$hash,$start,$start+$window]);$q=$this->pdo->prepare('SELECT hits FROM auth_smart_limits WHERE bucket_hash=? AND window_start=? FOR UPDATE');$q->execute([$hash,$start]);if((int)$q->fetchColumn()>=$max)throw new AuthFailure(429,'rate_limited','تعداد تلاش‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.',$start+$window-$now);$q=$this->pdo->prepare('UPDATE auth_smart_limits SET hits=hits+1 WHERE bucket_hash=? AND window_start=?');$q->execute([$hash,$start]);if($own){$this->pdo->exec('DELETE FROM auth_smart_limits WHERE expires_at<UNIX_TIMESTAMP()-86400 LIMIT 100');$this->pdo->exec('DELETE FROM auth_phone_challenges WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 DAY) LIMIT 100');$this->pdo->commit();}}catch(Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function hash(string $id,string $code):string{return hash_hmac('sha256','phone-auth|'.$id.'|'.$code,$this->pepper);}
 private function code():string{return (string)random_int(100000,999999);}
 private function source(array $b):string{$ip=$b['client_ip']??'';return is_string($ip)&&filter_var($ip,FILTER_VALIDATE_IP)?$ip:'unknown';}
 private function text(array $b,string $key,int $max,bool $required=true,bool $trim=true):string{$v=$b[$key]??'';if(!is_string($v)||strlen($v)>$max||($required&&trim($v)===''))throw new AuthFailure(422,'validation_failed','اطلاعات فرم کامل یا معتبر نیست.');return $trim?trim($v):$v;}
 public static function validUsername(string $v):bool{return preg_match('/^[a-z][a-z0-9_.]{2,63}$/D',$v)===1;}
 private function writeAudit(AuditEventType $type,string $user,array $meta=[],?LoginSecurityContext $context=null,?string $session=null):void{$this->audit->write(new AuditEvent($type,'succeeded',AuditContext::fromRawRequestData(actorUserId:$user,targetUserId:$user,sessionId:$session,ipAddress:$context?->clientSource(),userAgent:$context?->userAgent()),$meta));}
}

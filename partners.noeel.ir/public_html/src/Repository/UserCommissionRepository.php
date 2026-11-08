<?php
declare(strict_types=1);
namespace Babanoell\Partners\Repository;
use PDO;

/** WordPress acceptor_commission_percent is a user property, including an explicit zero. */
final class UserCommissionRepository
{
    public function __construct(private readonly PDO $pdo){}
    public function get(string $user):array
    {
        $q=$this->pdo->prepare('SELECT commission_percent,revision,updated_at FROM partner_user_commissions WHERE user_id=:id');$q->execute([':id'=>$user]);$row=$q->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?['commission_percent'=>$row['commission_percent']===null?null:(float)$row['commission_percent'],'revision'=>(int)$row['revision'],'updated_at'=>$row['updated_at']]:['commission_percent'=>null,'revision'=>0,'updated_at'=>null];
    }
    public function save(string $user,string $actor,int $revision,mixed $input):array
    {
        $value=null;
        if($input!==null&&$input!==''){
            if(!is_scalar($input))throw new \InvalidArgumentException('درصد کمیسیون معتبر نیست.');
            $raw=strtr(trim((string)$input),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٫'=>'.']);
            if(!preg_match('/^[0-9]{1,3}(?:\.[0-9]{1,4})?$/D',$raw)||(float)$raw>100)throw new \InvalidArgumentException('کمیسیون باید از صفر تا صد و حداکثر چهار رقم اعشار باشد.');$value=$raw;
        }
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT revision FROM partner_user_commissions WHERE user_id=:id FOR UPDATE');$q->execute([':id'=>$user]);$old=$q->fetchColumn();
            if(($old===false&&$revision!==0)||($old!==false&&(int)$old!==$revision))throw new \DomainException('تنظیم کمیسیون هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            if($old===false){$q=$this->pdo->prepare('INSERT INTO partner_user_commissions(user_id,commission_percent,updated_by) VALUES(:id,:percent,:actor)');$q->execute([':id'=>$user,':percent'=>$value,':actor'=>$actor]);}
            else{$q=$this->pdo->prepare('UPDATE partner_user_commissions SET commission_percent=:percent,revision=revision+1,updated_by=:actor WHERE user_id=:id');$q->execute([':percent'=>$value,':actor'=>$actor,':id'=>$user]);}
            (new PdoPartnerRepository($this->pdo))->event('user_commission',$user,'user.commission.updated',$actor,'allowed',['commission_percent'=>$value,'revision'=>$revision+1]);$this->pdo->commit();return $this->get($user);
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException&&(string)$e->getCode()==='23000')throw new \DomainException('تنظیم کمیسیون هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');throw $e;}
    }
}

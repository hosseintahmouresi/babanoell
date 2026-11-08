<?php
declare(strict_types=1);
namespace Babanoell\Loans\Repository;

use PDO;
use InvalidArgumentException;
use DomainException;
use RuntimeException;

final class RequestNumberRepository
{
    public function __construct(private readonly PDO $pdo){}
    public function settings():array
    {
        $r=$this->pdo->query('SELECT last_allocated,step,revision FROM request_number_sequence WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))throw new RuntimeException('تنظیم شماره پرونده موجود نیست.');
        return ['last_allocated'=>(int)$r['last_allocated'],'step'=>(int)$r['step'],'revision'=>(int)$r['revision'],
            'next_number'=>(int)$r['last_allocated']>0?(int)$r['last_allocated']+(int)$r['step']:1];
    }
    /** Allocated under the same transaction as the case; internal IDs never change. */
    public function allocate():string
    {
        if(!$this->pdo->inTransaction())throw new RuntimeException('تخصیص شماره پرونده به تراکنش نیاز دارد.');
        $r=$this->pdo->query('SELECT last_allocated,step FROM request_number_sequence WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))throw new RuntimeException('تنظیم شماره پرونده موجود نیست.');
        $number=(int)$r['last_allocated']>0?(int)$r['last_allocated']+(int)$r['step']:1;
        $exists=$this->pdo->prepare('SELECT id FROM loan_requests WHERE request_no=:no LIMIT 1');
        for($i=0;$i<1000;$i++){$exists->execute([':no'=>(string)$number]);if($exists->fetchColumn()===false)break;$number+=(int)$r['step'];}
        if($i===1000||$number>9000000000000000)throw new RuntimeException('محدوده شماره پرونده نیازمند بررسی مدیر است.');
        $this->pdo->prepare('UPDATE request_number_sequence SET last_allocated=:number WHERE id=1')->execute([':number'=>$number]);
        return (string)$number;
    }
    public function save(int $revision,int $step,string $actor):array
    {
        if($revision<1||$step<1||$step>1000000000)throw new InvalidArgumentException('گام شماره پرونده باید عددی بین ۱ و ۱٬۰۰۰٬۰۰۰٬۰۰۰ باشد.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('UPDATE request_number_sequence SET step=:step,revision=revision+1,updated_by=:actor WHERE id=1 AND revision=:revision');
            $q->execute([':step'=>$step,':actor'=>$actor,':revision'=>$revision]);if($q->rowCount()!==1)throw new DomainException('تنظیمات هم‌زمان تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
            $out=$this->settings();(new PdoLoanRepository($this->pdo))->recordEvent(null,'request_number.settings.updated',$actor,'allowed',['step'=>$step,'revision'=>$out['revision']]);
            $this->pdo->commit();return $out;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}

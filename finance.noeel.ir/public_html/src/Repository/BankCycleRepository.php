<?php
declare(strict_types=1);
namespace Babanoell\Finance\Repository;

use Babanoell\Finance\Support\RandomId;
use PDO;
use RuntimeException;
use Throwable;

/** Finance is authoritative; the Loans projection may lag, never redefine a cycle. */
final class BankCycleRepository {
    public function __construct(private readonly PDO $db) {}
    public function findByKey(string $actor,string $key):?array {
        $s=$this->db->prepare('SELECT id FROM bank_settlement_cycles WHERE created_by_user_id=:actor AND idempotency_key=:key');
        $s->execute([':actor'=>$actor,':key'=>$key]);$id=$s->fetchColumn();return is_string($id)?$this->get($id):null;
    }
    public function get(string $id):?array {
        $s=$this->db->prepare('SELECT * FROM bank_settlement_cycles WHERE id=:id');$s->execute([':id'=>$id]);$cycle=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($cycle))return null;
        $s=$this->db->prepare('SELECT request_id,applicant_name,national_code,loan_amount FROM bank_settlement_cycle_items WHERE cycle_id=:id ORDER BY request_id ASC');
        $s->execute([':id'=>$id]);$cycle['items']=$s->fetchAll(PDO::FETCH_ASSOC);return $cycle;
    }
    public function list(int $page,int $per,string $owner='',string $date='',string $search=''):array {
        $where=[];$p=[];
        if($owner!==''){$where[]='c.bank_user_id=:owner';$p[':owner']=$owner;}
        if($date!==''){$where[]='c.settlement_date=:date';$p[':date']=$date;}
        if($search!==''){$where[]='(c.cycle_no=:search OR c.id=:searchId OR EXISTS(SELECT 1 FROM bank_settlement_cycle_items ci WHERE ci.cycle_id=c.id AND ci.request_id=:searchRequest))';$p[':search']=$search;$p[':searchId']=$search;$p[':searchRequest']=$search;}
        $w=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$this->db->prepare('SELECT COUNT(*) FROM bank_settlement_cycles c'.$w);$count->execute($p);$total=(int)$count->fetchColumn();
        $s=$this->db->prepare('SELECT c.* FROM bank_settlement_cycles c'.$w.' ORDER BY c.created_at DESC,c.id DESC LIMIT :lim OFFSET :off');
        foreach($p as $k=>$v)$s->bindValue($k,$v);$s->bindValue(':lim',$per,PDO::PARAM_INT);$s->bindValue(':off',($page-1)*$per,PDO::PARAM_INT);$s->execute();
        return ['items'=>$s->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'per_page'=>$per,'total'=>$total];
    }
    public function create(string $actor,string $date,string $notes,array $items,string $key,string $hash):array {
        $this->db->beginTransaction();
        try {
            $found=$this->db->prepare('SELECT id,request_hash FROM bank_settlement_cycles WHERE created_by_user_id=:actor AND idempotency_key=:key FOR UPDATE');
            $found->execute([':actor'=>$actor,':key'=>$key]);$prior=$found->fetch(PDO::FETCH_ASSOC);
            if(is_array($prior)){
                if(!hash_equals((string)$prior['request_hash'],$hash))throw new RuntimeException('idempotency_conflict');
                $this->db->commit();return $this->get((string)$prior['id'])??[];
            }
            // All bank-settlement membership claims are unique across all cycles and independent of merchant cycles.
            $total=0;
            foreach($items as $item){
                $total+=(int)$item['loan_cents'];
                $s=$this->db->prepare('SELECT status FROM bank_settlements WHERE request_id=:id FOR UPDATE');$s->execute([':id'=>$item['request_id']]);
                $existing=$s->fetchColumn();if($existing==='recorded')throw new RuntimeException('bank_case_already_recorded');
                $s=$this->db->prepare('SELECT request_id FROM bank_settlement_cycle_items WHERE request_id=:id FOR UPDATE');$s->execute([':id'=>$item['request_id']]);
                if($s->fetchColumn()!==false)throw new RuntimeException('bank_case_already_in_cycle');
            }
            $id=RandomId::make();$number='BC-'.gmdate('Ymd-His').'-'.strtoupper(substr($id,-6));
            $s=$this->db->prepare("INSERT INTO bank_settlement_cycles(id,cycle_no,bank_user_id,created_by_user_id,settlement_date,notes,item_count,total_loan_amount,request_hash,idempotency_key,amount_basis) VALUES(:id,:number,:owner,:actor,:date,:notes,:count,:total,:hash,:key,'reference_purchase_amount')");
            $s->execute([':id'=>$id,':number'=>$number,':owner'=>$actor,':actor'=>$actor,':date'=>$date,':notes'=>$notes!==''?$notes:null,':count'=>count($items),':total'=>sprintf('%d.%02d',intdiv($total,100),$total%100),':hash'=>$hash,':key'=>$key]);
            foreach($items as $item){
                $amount=(int)$item['loan_cents'];$amount=sprintf('%d.%02d',intdiv($amount,100),$amount%100);$requestId=(string)$item['request_id'];
                $s=$this->db->prepare('INSERT INTO bank_settlement_cycle_items(cycle_id,request_id,applicant_name,national_code,loan_amount) VALUES(:cycle,:request,:name,:national,:amount)');
                $s->execute([':cycle'=>$id,':request'=>$requestId,':name'=>$item['applicant_name'],':national'=>$item['national_code'],':amount'=>$amount]);
                $s=$this->db->prepare("INSERT INTO bank_settlements(id,request_id,status,settled_at,note,actor_user_id,source) VALUES(:id,:request,'recorded',:date,:note,:actor,'bank_cycle') ON DUPLICATE KEY UPDATE status='recorded',settled_at=VALUES(settled_at),note=VALUES(note),actor_user_id=VALUES(actor_user_id),source='bank_cycle',row_version=row_version+1");
                $s->execute([':id'=>RandomId::make(),':request'=>$requestId,':date'=>$date.' 00:00:00',':note'=>$notes!==''?$notes:null,':actor'=>$actor]);
                $s=$this->db->prepare("INSERT INTO bank_settlement_history(id,request_id,status,settled_at,note,actor_user_id,source) VALUES(:id,:request,'recorded',:date,:note,:actor,'bank_cycle')");
                $s->execute([':id'=>RandomId::make(),':request'=>$requestId,':date'=>$date.' 00:00:00',':note'=>$notes!==''?$notes:null,':actor'=>$actor]);
            }
            $this->db->commit();return $this->get($id)??throw new RuntimeException('bank_cycle_not_found');
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function recordProjectionAttempt(string $id,bool $ok):void {
        $s=$this->db->prepare("UPDATE bank_settlement_cycles SET projection_status=CASE WHEN :ok=1 THEN 'synced' ELSE 'pending' END,projected_at=CASE WHEN :ok2=1 THEN NOW(6) ELSE projected_at END,projection_attempts=projection_attempts+1 WHERE id=:id");
        $s->execute([':ok'=>$ok?1:0,':ok2'=>$ok?1:0,':id'=>$id]);
    }
}

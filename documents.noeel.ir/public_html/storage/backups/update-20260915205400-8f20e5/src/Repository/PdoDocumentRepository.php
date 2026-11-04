<?php
declare(strict_types=1);
namespace Babanoell\Documents\Repository;
use Babanoell\Documents\Domain\ValidatedUpload;
use Babanoell\Documents\Domain\DocumentCompletion;
use Babanoell\Documents\Support\Json;
use Babanoell\Documents\Support\RandomId;
use PDO;
use RuntimeException;
use Throwable;

final class PdoDocumentRepository
{
    public function __construct(private readonly PDO$pdo){}
    public function pdo():PDO{return$this->pdo;}

    public function stats():array
    {
        $total=(int)$this->pdo->query("SELECT COUNT(*) FROM documents WHERE status='active'")->fetchColumn();
        $pending=(int)$this->pdo->query("SELECT COUNT(*) FROM documents WHERE status='active' AND review_status='pending'")->fetchColumn();
        $approved=(int)$this->pdo->query("SELECT COUNT(*) FROM documents WHERE status='active' AND review_status='approved'")->fetchColumn();
        $rejected=(int)$this->pdo->query("SELECT COUNT(*) FROM documents WHERE status='active' AND review_status='rejected'")->fetchColumn();
        return['active_documents'=>$total,'pending_review'=>$pending,'approved'=>$approved,'rejected'=>$rejected];
    }

    public function create(
        string$actor,string$documentType,string$category,string$label,string$visibility,?string$ownerUserId,
        string$entityType,string$entityId,string$relation,ValidatedUpload$file,string$storageKey,?string$ip,?string$ua
    ):array{
        $id=RandomId::make();$versionId=RandomId::make();$linkId=RandomId::make();
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('INSERT INTO documents (id,document_type,category,label,visibility,owner_user_id,status,review_status,current_version,created_by_user_id) VALUES (:id,:type,:category,:label,:visibility,:owner,:status,:review,1,:actor)');
            $s->execute([':id'=>$id,':type'=>$documentType,':category'=>$category,':label'=>$label,':visibility'=>$visibility,':owner'=>$ownerUserId!==''?$ownerUserId:null,':status'=>'active',':review'=>'pending',':actor'=>$actor]);
            $v=$this->pdo->prepare('INSERT INTO document_versions (id,document_id,version,storage_key,original_name,mime_type,extension,size_bytes,checksum_sha256,uploaded_by_user_id) VALUES (:id,:document,1,:key,:name,:mime,:ext,:size,:hash,:actor)');
            $v->execute([':id'=>$versionId,':document'=>$id,':key'=>$storageKey,':name'=>$file->originalName,':mime'=>$file->mimeType,':ext'=>$file->extension,':size'=>$file->sizeBytes,':hash'=>$file->sha256,':actor'=>$actor]);
            $l=$this->pdo->prepare('INSERT INTO document_links (id,document_id,entity_type,entity_id,relation_code,created_by_user_id) VALUES (:id,:document,:type,:entity,:relation,:actor)');
            $l->execute([':id'=>$linkId,':document'=>$id,':type'=>$entityType,':entity'=>$entityId,':relation'=>$relation,':actor'=>$actor]);
            $this->audit($id,$versionId,$entityType,$entityId,'document.uploaded',$actor,'allowed',null,['mime_type'=>$file->mimeType,'size_bytes'=>$file->sizeBytes,'checksum_sha256'=>$file->sha256],$ip,$ua);
            $created=$this->get($id);if($created===null)throw new RuntimeException('مدرک پس از ثبت قابل بازیابی نیست.');
            $this->pdo->commit();return$created;
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function replace(string$id,string$actor,ValidatedUpload$file,string$storageKey,?string$ip,?string$ua):array
    {
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT current_version,status FROM documents WHERE id=:id FOR UPDATE');$q->execute([':id'=>$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))throw new RuntimeException('مدرک پیدا نشد.');if(($row['status']??'')!=='active')throw new RuntimeException('مدرک بایگانی‌شده قابل جایگزینی نیست.');
            $next=(int)$row['current_version']+1;$versionId=RandomId::make();
            $v=$this->pdo->prepare('INSERT INTO document_versions (id,document_id,version,storage_key,original_name,mime_type,extension,size_bytes,checksum_sha256,uploaded_by_user_id) VALUES (:id,:document,:version,:key,:name,:mime,:ext,:size,:hash,:actor)');
            $v->execute([':id'=>$versionId,':document'=>$id,':version'=>$next,':key'=>$storageKey,':name'=>$file->originalName,':mime'=>$file->mimeType,':ext'=>$file->extension,':size'=>$file->sizeBytes,':hash'=>$file->sha256,':actor'=>$actor]);
            $u=$this->pdo->prepare("UPDATE documents SET current_version=:version,review_status='pending',latest_review_reason=NULL,reviewed_by_user_id=NULL,reviewed_at=NULL WHERE id=:id");$u->execute([':version'=>$next,':id'=>$id]);
            $link=$this->primaryLink($id);$this->audit($id,$versionId,$link['entity_type']??null,$link['entity_id']??null,'document.replaced',$actor,'allowed',null,['version'=>$next,'mime_type'=>$file->mimeType,'size_bytes'=>$file->sizeBytes,'checksum_sha256'=>$file->sha256],$ip,$ua);
            $updated=$this->get($id);if($updated===null)throw new RuntimeException('نسخه جدید مدرک قابل بازیابی نیست.');
            $this->pdo->commit();return$updated;
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function review(string$id,string$actor,string$status,string$reason,?string$ip,?string$ua):array
    {
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT d.id,d.current_version,v.id AS version_id FROM documents d JOIN document_versions v ON v.document_id=d.id AND v.version=d.current_version WHERE d.id=:id AND d.status=\'active\' FOR UPDATE');$q->execute([':id'=>$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!is_array($row))throw new RuntimeException('مدرک فعال پیدا نشد.');
            $r=$this->pdo->prepare('INSERT INTO document_reviews (id,document_id,version_id,status,reason,reviewer_user_id) VALUES (:id,:document,:version,:status,:reason,:actor)');
            $r->execute([':id'=>RandomId::make(),':document'=>$id,':version'=>$row['version_id'],':status'=>$status,':reason'=>$reason!==''?$reason:null,':actor'=>$actor]);
            $u=$this->pdo->prepare('UPDATE documents SET review_status=:status,latest_review_reason=:reason,reviewed_by_user_id=:actor,reviewed_at=NOW(6) WHERE id=:id');$u->execute([':status'=>$status,':reason'=>$reason!==''?$reason:null,':actor'=>$actor,':id'=>$id]);
            $link=$this->primaryLink($id);$this->audit($id,(string)$row['version_id'],$link['entity_type']??null,$link['entity_id']??null,'document.reviewed',$actor,'allowed',$reason,['status'=>$status],$ip,$ua);
            $updated=$this->get($id);if($updated===null)throw new RuntimeException('مدرک قابل بازیابی نیست.');
            $this->pdo->commit();return$updated;
        }catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    public function archive(string$id,string$actor,string$reason,?string$ip,?string$ua):array
    {
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare("SELECT status FROM documents WHERE id=:id FOR UPDATE");
            $q->execute([':id'=>$id]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))throw new RuntimeException('مدرک پیدا نشد.');
            if(($row['status']??'')!=='active')throw new RuntimeException('مدرک قبلاً بایگانی شده است.');

            $s=$this->pdo->prepare("UPDATE documents SET status='archived',archived_by_user_id=:actor,archive_reason=:reason,archived_at=NOW(6) WHERE id=:id AND status='active'");
            $s->execute([':actor'=>$actor,':reason'=>$reason!==''?$reason:null,':id'=>$id]);
            if($s->rowCount()!==1)throw new RuntimeException('بایگانی مدرک به‌دلیل تغییر هم‌زمان کامل نشد.');

            $doc=$this->get($id);
            if($doc===null)throw new RuntimeException('مدرک پس از بایگانی قابل بازیابی نیست.');
            $link=$this->primaryLink($id);
            $this->audit($id,$doc['current_version_id']??null,$link['entity_type']??null,$link['entity_id']??null,'document.archived',$actor,'allowed',$reason,[],$ip,$ua);
            $this->pdo->commit();
            return$doc;
        } catch(Throwable$e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw$e;
        }
    }

    public function get(string$id):?array
    {
        $s=$this->pdo->prepare('SELECT d.*,v.id AS current_version_id,v.storage_key,v.original_name,v.mime_type,v.extension,v.size_bytes,v.checksum_sha256,v.uploaded_by_user_id,v.uploaded_at FROM documents d JOIN document_versions v ON v.document_id=d.id AND v.version=d.current_version WHERE d.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($r))return null;$r['links']=$this->links($id);$r['versions']=$this->versions($id);return$r;
    }

    public function currentVersion(string$id):?array
    {
        $s=$this->pdo->prepare('SELECT v.*,d.visibility,d.owner_user_id,d.status,d.review_status,d.document_type,d.category,d.label FROM documents d JOIN document_versions v ON v.document_id=d.id AND v.version=d.current_version WHERE d.id=:id LIMIT 1');$s->execute([':id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;
    }
    public function versionById(string$documentId,string$versionId):?array{$s=$this->pdo->prepare('SELECT v.*,d.visibility,d.owner_user_id,d.status,d.review_status,d.document_type,d.category,d.label FROM documents d JOIN document_versions v ON v.document_id=d.id WHERE d.id=:document AND v.id=:version LIMIT 1');$s->execute([':document'=>$documentId,':version'=>$versionId]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
    public function versions(string$id):array{$s=$this->pdo->prepare('SELECT id,version,original_name,mime_type,extension,size_bytes,checksum_sha256,uploaded_by_user_id,uploaded_at FROM document_versions WHERE document_id=:id ORDER BY version DESC');$s->execute([':id'=>$id]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    public function links(string$id):array{$s=$this->pdo->prepare('SELECT entity_type,entity_id,relation_code,created_at FROM document_links WHERE document_id=:id ORDER BY created_at ASC');$s->execute([':id'=>$id]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    private function primaryLink(string$id):array{$links=$this->links($id);return is_array($links[0]??null)?$links[0]:[];}

    public function listByEntity(string$entityType,string$entityId,bool$includeArchived=false):array
    {
        $sql='SELECT d.*,v.id AS current_version_id,v.original_name,v.mime_type,v.size_bytes,v.checksum_sha256,v.uploaded_at,l.relation_code FROM document_links l JOIN documents d ON d.id=l.document_id JOIN document_versions v ON v.document_id=d.id AND v.version=d.current_version WHERE l.entity_type=:type AND l.entity_id=:entity'.($includeArchived?'':" AND d.status='active'").' ORDER BY v.uploaded_at DESC,v.version DESC,d.id DESC';
        $s=$this->pdo->prepare($sql);$s->execute([':type'=>$entityType,':entity'=>$entityId]);return$s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAll(int$page=1,int$perPage=25,string$q='',string$category='',string$reviewStatus='',string$documentStatus='active'):array
    {
        $page=max(1,$page);$perPage=max(1,min(100,$perPage));$where=[];$params=[];
        if($category!==''){$where[]='d.category=:category';$params[':category']=$category;}
        if($reviewStatus!==''){$where[]='d.review_status=:review';$params[':review']=$reviewStatus;}
        if($documentStatus!=='all'){$where[]='d.status=:document_status';$params[':document_status']=in_array($documentStatus,['active','archived'],true)?$documentStatus:'active';}
        if($q!==''){$where[]='(d.label LIKE :q1 OR v.original_name LIKE :q2 OR l.entity_id LIKE :q3)';$like='%'.$q.'%';$params[':q1']=$like;$params[':q2']=$like;$params[':q3']=$like;}
        $w=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$this->pdo->prepare('SELECT COUNT(DISTINCT d.id) FROM documents d JOIN document_versions v ON v.document_id=d.id AND v.version=d.current_version LEFT JOIN document_links l ON l.document_id=d.id'.$w);$count->execute($params);$total=(int)$count->fetchColumn();
        $sql='SELECT d.*,v.id AS current_version_id,v.original_name,v.mime_type,v.size_bytes,v.uploaded_at,MIN(l.entity_type) AS entity_type,MIN(l.entity_id) AS entity_id FROM documents d JOIN document_versions v ON v.document_id=d.id AND v.version=d.current_version LEFT JOIN document_links l ON l.document_id=d.id'.$w.' GROUP BY d.id ORDER BY d.updated_at DESC LIMIT :limit OFFSET :offset';
        $s=$this->pdo->prepare($sql);foreach($params as$k=>$v)$s->bindValue($k,$v);$s->bindValue(':limit',$perPage,PDO::PARAM_INT);$s->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$s->execute();
        return['items'=>$s->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'per_page'=>$perPage,'total'=>$total];
    }

    /** @param list<string> $requiredTypes */
    public function completion(string$entityType,string$entityId,array$requiredTypes):array
    {
        return DocumentCompletion::evaluate($requiredTypes,$this->listByEntity($entityType,$entityId,false));
    }

    public function reviews(string$id):array{$s=$this->pdo->prepare('SELECT * FROM document_reviews WHERE document_id=:id ORDER BY created_at DESC,id DESC');$s->execute([':id'=>$id]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    public function audits(string$id,int$limit=100):array{$s=$this->pdo->prepare('SELECT * FROM document_audit_logs WHERE document_id=:id ORDER BY created_at DESC,id DESC LIMIT :limit');$s->bindValue(':id',$id);$s->bindValue(':limit',max(1,min(200,$limit)),PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll(PDO::FETCH_ASSOC);foreach($rows as&$r){$r['details']=Json::decode((string)($r['details_json']??''));unset($r['details_json']);}return$rows;}

    public function audit(?string$documentId,?string$versionId,?string$entityType,?string$entityId,string$event,?string$actor,string$result,?string$reason,array$details,?string$ip,?string$ua):void
    {
        $s=$this->pdo->prepare('INSERT INTO document_audit_logs (id,document_id,version_id,entity_type,entity_id,event_type,actor_user_id,result,reason,details_json,ip_address,user_agent) VALUES (:id,:document,:version,:entity_type,:entity_id,:event,:actor,:result,:reason,:details,:ip,:ua)');
        $s->execute([':id'=>RandomId::make(),':document'=>$documentId,':version'=>$versionId,':entity_type'=>$entityType,':entity_id'=>$entityId,':event'=>$event,':actor'=>$actor,':result'=>$result,':reason'=>$reason!==''?$reason:null,':details'=>$details!==[]?Json::encode($details):null,':ip'=>$ip,':ua'=>$ua]);
    }

    public function issueAccessToken(string$documentId,string$versionId,string$userId,string$purpose,int$ttlSeconds=120):string
    {
        $plain=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$hash=hash('sha256',$plain);$expires=(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->modify('+'.max(30,min(300,$ttlSeconds)).' seconds')->format('Y-m-d H:i:s.u');
        $s=$this->pdo->prepare('INSERT INTO document_access_tokens (id,token_hash,document_id,version_id,issued_to_user_id,purpose,expires_at) VALUES (:id,:hash,:document,:version,:user,:purpose,:expires)');$s->execute([':id'=>RandomId::make(),':hash'=>$hash,':document'=>$documentId,':version'=>$versionId,':user'=>$userId,':purpose'=>$purpose,':expires'=>$expires]);return$plain;
    }
    public function consumeAccessToken(string$plain,string$purpose):?array
    {
        $hash=hash('sha256',$plain);$this->pdo->beginTransaction();try{$s=$this->pdo->prepare('SELECT * FROM document_access_tokens WHERE token_hash=:hash AND purpose=:purpose AND consumed_at IS NULL AND expires_at>NOW(6) FOR UPDATE');$s->execute([':hash'=>$hash,':purpose'=>$purpose]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($r)){$this->pdo->rollBack();return null;}$u=$this->pdo->prepare('UPDATE document_access_tokens SET consumed_at=NOW(6) WHERE id=:id AND consumed_at IS NULL');$u->execute([':id'=>$r['id']]);if($u->rowCount()!==1){$this->pdo->rollBack();return null;}$this->pdo->commit();return$r;}catch(Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }
}

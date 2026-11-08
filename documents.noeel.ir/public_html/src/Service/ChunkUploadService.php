<?php
declare(strict_types=1);
namespace Babanoell\Documents\Service;

use Babanoell\Documents\Domain\ApiException;
use Babanoell\Documents\Security\Principal;
use Babanoell\Documents\Support\{Json,RandomId};
use PDO;
use RuntimeException;

/** Opaque sessions, private parts, repeated authorization and one atomic final document. */
final class ChunkUploadService
{
    public const CHUNK_BYTES=262144;
    public function __construct(private readonly PDO $pdo,private readonly DocumentService $documents,private readonly string $base,private readonly int $maxBytes){}
    public function start(Principal $p,string $bearer,array $b):array
    {
        $type=(string)($b['entity_type']??'');$entity=(string)($b['entity_id']??'');$fields=is_array($b['fields']??null)?$b['fields']:[];
        $this->documents->authorizeUpload($p,$bearer,$type,$entity,$fields);
        $key=(string)($b['client_key']??'');$hash=(string)($b['checksum_sha256']??'');$size=filter_var($b['total_bytes']??null,FILTER_VALIDATE_INT);
        $name=basename(str_replace('\\','/',(string)($b['file_name']??'')));
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||$size===false||$size<1||$size>$this->maxBytes||mb_strlen($name)>220||preg_match('/[\x00-\x1f\x7f]/',$name)||!preg_match('/\.(?:png|jpe?g|jpe|webp|pdf)$/iD',$name))throw new ApiException(422,'chunk_metadata_invalid','نام، اندازه یا مشخصات فایل معتبر نیست.');
        $fields=array_intersect_key($fields,array_flip(['document_type','visibility','owner_user_id','relation_code']));ksort($fields);
        $fingerprint=hash('sha256',Json::encode([$type,$entity,$fields,$name,$size,$hash]));
        $this->cleanup(10);
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('INSERT IGNORE INTO chunk_upload_owners(actor_id) VALUES(:actor)')->execute([':actor'=>$p->userId]);
            $q=$this->pdo->prepare('SELECT actor_id FROM chunk_upload_owners WHERE actor_id=:actor FOR UPDATE');$q->execute([':actor'=>$p->userId]);
            $q=$this->pdo->prepare('SELECT * FROM chunk_uploads WHERE actor_id=:actor AND client_key=:key');$q->execute([':actor'=>$p->userId,':key'=>$key]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if(is_array($old)){if(!hash_equals($old['request_hash'],$fingerprint))throw new ApiException(409,'chunk_key_conflict','مشخصات فایل با بارگذاری قبلی یکسان نیست.');$this->pdo->commit();return $this->status($p,$bearer,$old['id']);}
            $q=$this->pdo->prepare("SELECT COUNT(*) FROM chunk_uploads WHERE actor_id=:actor AND status='active' AND expires_at>UTC_TIMESTAMP(6)");$q->execute([':actor'=>$p->userId]);
            if((int)$q->fetchColumn()>=5)throw new ApiException(429,'chunk_quota_exceeded','حداکثر پنج بارگذاری ناتمام مجاز است؛ یکی را تکمیل یا لغو کنید.');
            $id=RandomId::make();$count=(int)ceil($size/self::CHUNK_BYTES);
            $q=$this->pdo->prepare("INSERT INTO chunk_uploads(id,actor_id,client_key,request_hash,entity_type,entity_id,fields_json,file_name,total_bytes,checksum_sha256,chunk_size,total_chunks,expires_at) VALUES(:id,:actor,:key,:fingerprint,:type,:entity,:fields,:name,:size,:hash,:chunk,:count,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 24 HOUR))");
            $q->execute([':id'=>$id,':actor'=>$p->userId,':key'=>$key,':fingerprint'=>$fingerprint,':type'=>$type,':entity'=>$entity,':fields'=>Json::encode($fields),':name'=>$name,':size'=>$size,':hash'=>$hash,':chunk'=>self::CHUNK_BYTES,':count'=>$count]);
            $this->pdo->commit();return $this->status($p,$bearer,$id);
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function owner(Principal $p,string $bearer,string $id):array
    {
        $q=$this->pdo->prepare('SELECT * FROM chunk_uploads WHERE id=:id AND actor_id=:actor');$q->execute([':id'=>$id,':actor'=>$p->userId]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))throw new ApiException(404,'chunk_upload_not_found','بارگذاری پیدا نشد.');
        if($r['status']==='complete')$this->documents->requireAccessible($p,$bearer,(string)$r['document_id']);else $this->documents->authorizeUpload($p,$bearer,$r['entity_type'],$r['entity_id'],Json::decode($r['fields_json']));
        if($r['status']!=='complete'&&strtotime($r['expires_at'].' UTC')<=time())throw new ApiException(410,'chunk_upload_expired','مهلت بارگذاری پایان یافته است؛ فایل را دوباره انتخاب کنید.');
        if($r['status']==='aborted')throw new ApiException(410,'chunk_upload_aborted','این بارگذاری لغو شده است.');
        return $r;
    }
    public function status(Principal $p,string $bearer,string $id):array
    {
        $r=$this->owner($p,$bearer,$id);$q=$this->pdo->prepare('SELECT part_index FROM chunk_upload_parts WHERE upload_id=:id ORDER BY part_index');$q->execute([':id'=>$id]);
        return ['id'=>$id,'status'=>$r['status'],'chunk_size'=>(int)$r['chunk_size'],'total_chunks'=>(int)$r['total_chunks'],'received_parts'=>array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),'document_id'=>$r['document_id'],'expires_at'=>$r['expires_at']];
    }
    /** Lock files stay until expiry cleanup; unlinking an active lock would split mutual exclusion. */
    private function locked(string $id,callable $action):mixed
    {
        if(!preg_match('/^[a-f0-9]{26}$/D',$id)||is_link($this->base))throw new RuntimeException('مسیر بارگذاری معتبر نیست.');
        $dir=$this->base.'/'.$id;
        if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('فضای موقت فایل در دسترس نیست.');
        if(is_link($dir)||is_link($dir.'/lock'))throw new RuntimeException('مسیر بارگذاری معتبر نیست.');
        $lock=fopen($dir.'/lock','c+b');if($lock===false||!flock($lock,LOCK_EX))throw new RuntimeException('قفل بارگذاری در دسترس نیست.');
        try{return $action($dir);}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function part(Principal $p,string $bearer,string $id,int $index,array $file):array
    {
        $this->owner($p,$bearer,$id);
        return $this->locked($id,function(string $dir)use($p,$bearer,$id,$index,$file):array{
            $r=$this->owner($p,$bearer,$id);if($r['status']==='complete')return $this->status($p,$bearer,$id);
            $expected=$index===(int)$r['total_chunks']-1?(int)$r['total_bytes']-$index*(int)$r['chunk_size']:(int)$r['chunk_size'];
            $tmp=(string)($file['tmp_name']??'');
            if($index<0||$index>=(int)$r['total_chunks']||(int)($file['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($tmp)||filesize($tmp)!==$expected)throw new ApiException(422,'chunk_part_invalid','قطعه فایل ناقص است یا اندازهٔ درستی ندارد.');
            $hash=(string)hash_file('sha256',$tmp);$q=$this->pdo->prepare('SELECT checksum_sha256 FROM chunk_upload_parts WHERE upload_id=:id AND part_index=:idx');$q->execute([':id'=>$id,':idx'=>$index]);$old=$q->fetchColumn();
            if(is_string($old)){if(!hash_equals($old,$hash))throw new ApiException(409,'chunk_part_conflict','قطعه ثبت‌شده با فایل انتخاب‌شده یکسان نیست.');return $this->status($p,$bearer,$id);}
            $path=$dir.'/'.$index.'.part';if(is_link($path))throw new RuntimeException('مسیر قطعه معتبر نیست.');
            if(!move_uploaded_file($tmp,$path))throw new RuntimeException('ذخیره قطعه کامل نشد.');chmod($path,0600);
            $q=$this->pdo->prepare('INSERT INTO chunk_upload_parts(upload_id,part_index,size_bytes,checksum_sha256) VALUES(:id,:idx,:size,:hash)');$q->execute([':id'=>$id,':idx'=>$index,':size'=>$expected,':hash'=>$hash]);
            return $this->status($p,$bearer,$id);
        });
    }
    public function complete(Principal $p,string $bearer,string $id,?string $ip,?string $ua):array
    {
        $this->owner($p,$bearer,$id);
        return $this->locked($id,function(string $dir)use($p,$bearer,$id,$ip,$ua):array{
            $r=$this->owner($p,$bearer,$id);if($r['status']==='complete')return $this->documents->requireAccessible($p,$bearer,$r['document_id']);
            $q=$this->pdo->prepare('SELECT * FROM chunk_upload_parts WHERE upload_id=:id ORDER BY part_index');$q->execute([':id'=>$id]);$parts=$q->fetchAll(PDO::FETCH_ASSOC);
            if(count($parts)!==(int)$r['total_chunks'])throw new ApiException(409,'chunk_upload_incomplete','تمام قطعه‌های فایل دریافت نشده‌اند.');
            $tmp=$dir.'/assembled';if(is_link($tmp))throw new RuntimeException('مسیر فایل معتبر نیست.');$out=fopen($tmp,'wb');if($out===false)throw new RuntimeException('ساخت فایل کامل نشد.');
            try{foreach($parts as $part){$path=$dir.'/'.(int)$part['part_index'].'.part';if(is_link($path)||!is_file($path)||!hash_equals($part['checksum_sha256'],(string)hash_file('sha256',$path)))throw new ApiException(409,'chunk_integrity_failed','صحت قطعه‌های فایل تأیید نشد.');$in=fopen($path,'rb');try{if($in===false||stream_copy_to_stream($in,$out)!==(int)$part['size_bytes'])throw new RuntimeException('تجمیع قطعه کامل نشد.');}finally{if(is_resource($in))fclose($in);}}}catch(\Throwable $e){@unlink($tmp);throw $e;}finally{fclose($out);}
            try{
                if(filesize($tmp)!==(int)$r['total_bytes']||!hash_equals($r['checksum_sha256'],(string)hash_file('sha256',$tmp)))throw new ApiException(409,'chunk_integrity_failed','فایل کامل با فایل انتخاب‌شده یکسان نیست.');
                $file=['name'=>$r['file_name'],'tmp_name'=>$tmp,'size'=>(int)$r['total_bytes'],'error'=>UPLOAD_ERR_OK];
                $document=$this->documents->upload($p,$bearer,$r['entity_type'],$r['entity_id'],$file,Json::decode($r['fields_json']),$ip,$ua,true,function(array $created)use($id):void{
                    $q=$this->pdo->prepare("UPDATE chunk_uploads SET status='complete',document_id=:document WHERE id=:id AND status='active'");$q->execute([':document'=>$created['id'],':id'=>$id]);if($q->rowCount()!==1)throw new RuntimeException('ثبت هم‌زمان بارگذاری کامل نشد.');
                });
                foreach($parts as $part)@unlink($dir.'/'.(int)$part['part_index'].'.part');return $document;
            }finally{@unlink($tmp);}
        });
    }
    public function abort(Principal $p,string $bearer,string $id):void
    {
        $this->owner($p,$bearer,$id);$this->locked($id,function(string $dir)use($p,$bearer,$id):void{
            $r=$this->owner($p,$bearer,$id);if($r['status']==='complete')throw new ApiException(409,'chunk_upload_complete','فایل کامل شده است؛ لغو بارگذاری مجاز نیست.');
            $this->pdo->prepare("UPDATE chunk_uploads SET status='aborted' WHERE id=:id AND status='active'")->execute([':id'=>$id]);
            foreach(glob($dir.'/*.part')?:[] as $part)if(!is_link($part))@unlink($part);if(!is_link($dir.'/assembled'))@unlink($dir.'/assembled');
        });
    }
    /** Bounded opportunistic maintenance; finished private documents are never removed. */
    public function cleanup(int $limit=20):int
    {
        $limit=max(1,min(50,$limit));$rows=$this->pdo->query("SELECT id FROM chunk_uploads WHERE expires_at<UTC_TIMESTAMP(6) AND status<>'expired' ORDER BY expires_at LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);$count=0;
        foreach($rows as $id){
            $this->locked($id,function(string $dir)use($id,&$count):void{
                $q=$this->pdo->prepare("SELECT id FROM chunk_uploads WHERE id=:id AND expires_at<UTC_TIMESTAMP(6) AND status<>'expired'");$q->execute([':id'=>$id]);if($q->fetchColumn()===false)return;
                foreach(glob($dir.'/*.part')?:[] as $part)if(!is_link($part))@unlink($part);
                if(!is_link($dir.'/assembled'))@unlink($dir.'/assembled');
                $this->pdo->prepare("UPDATE chunk_uploads SET status='expired' WHERE id=:id")->execute([':id'=>$id]);
                $this->pdo->prepare('DELETE FROM chunk_upload_parts WHERE upload_id=:id')->execute([':id'=>$id]);$count++;
            });
        }return $count;
    }
}

<?php
declare(strict_types=1);
namespace Babanoell\Documents\Database;
use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private const TRACKING_TABLE='documents_schema_migrations';
    public function __construct(private readonly PDO $pdo,private readonly string $path){}
    public function status(): array
    {
        $applied=$this->hasTable(self::TRACKING_TABLE)?$this->applied():[];$rows=[];
        foreach($this->load() as$m)$rows[]=['version'=>$m->version(),'name'=>$m->name(),'status'=>isset($applied[$m->version()])?'applied':'pending'];
        return ['tracking_table'=>self::TRACKING_TABLE,'migrations'=>$rows];
    }
    public function up(): array
    {
        DatabaseIsolationGuard::assertDocumentsDatabase($this->pdo);$this->ensure();$applied=$this->applied();$done=[];
        foreach($this->load() as$m){if(isset($applied[$m->version()]))continue;try{$m->up($this->pdo);$s=$this->pdo->prepare('INSERT INTO '.self::TRACKING_TABLE.'(version,name) VALUES(:v,:n)');$s->execute([':v'=>$m->version(),':n'=>$m->name()]);$done[]=['version'=>$m->version(),'name'=>$m->name()];}catch(Throwable$e){throw new RuntimeException('Migration '.$m->version().' ('.$m->name().') failed: '.$e->getMessage(),0,$e);}}
        return $done;
    }
    private function ensure(): void{$this->pdo->exec('CREATE TABLE IF NOT EXISTS '.self::TRACKING_TABLE."(version VARCHAR(32) NOT NULL PRIMARY KEY,name VARCHAR(255) NOT NULL,applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
    private function hasTable(string$table):bool{$s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');$s->execute([':table'=>$table]);return(int)$s->fetchColumn()===1;}
    private function applied():array{$r=[];foreach($this->pdo->query('SELECT version FROM '.self::TRACKING_TABLE)->fetchAll(PDO::FETCH_ASSOC)as$x)$r[(string)$x['version']]=true;return$r;}
    /** @return Migration[] */
    private function load():array{$files=glob(rtrim($this->path,'/').'/*.php')?:[];sort($files,SORT_STRING);$out=[];$seen=[];foreach($files as$f){$m=require$f;if(!$m instanceof Migration)throw new RuntimeException('فایل migration معتبر نیست: '.basename($f));if(isset($seen[$m->version()]))throw new RuntimeException('نسخه migration تکراری است: '.$m->version());$seen[$m->version()]=true;$out[]=$m;}return$out;}
}

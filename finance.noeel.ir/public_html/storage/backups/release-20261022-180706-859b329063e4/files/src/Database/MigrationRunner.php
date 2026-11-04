<?php
declare(strict_types=1);
namespace Babanoell\Finance\Database;
use PDO;use RuntimeException;use Throwable;
final class MigrationRunner
{
 private const TRACKING_TABLE='finance_schema_migrations';
 public function __construct(private readonly PDO$pdo,private readonly string$path){}
 public function status():array{$exists=$this->hasTable(self::TRACKING_TABLE);$applied=$exists?$this->applied():[];$rows=[];foreach($this->load()as$m)$rows[]=['version'=>$m->version(),'name'=>$m->name(),'status'=>isset($applied[$m->version()])?'applied':'pending'];return['tracking_table_exists'=>$exists,'tracking_table'=>self::TRACKING_TABLE,'migrations'=>$rows];}
 public function up():array{DatabaseIsolationGuard::assertFinanceDatabase($this->pdo);$this->ensure();$applied=$this->applied();$now=[];foreach($this->load()as$m){if(isset($applied[$m->version()]))continue;try{$m->up($this->pdo);$s=$this->pdo->prepare('INSERT INTO '.self::TRACKING_TABLE.'(version,name) VALUES(:v,:n)');$s->execute([':v'=>$m->version(),':n'=>$m->name()]);$now[]=['version'=>$m->version(),'name'=>$m->name()];}catch(Throwable$e){throw new RuntimeException('Migration '.$m->version().' ('.$m->name().') failed: '.$e->getMessage(),0,$e);}}return$now;}
 private function hasTable(string$t):bool{$s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:t');$s->execute([':t'=>$t]);return(int)$s->fetchColumn()===1;}
 private function ensure():void{$this->pdo->exec('CREATE TABLE IF NOT EXISTS '.self::TRACKING_TABLE.'(version VARCHAR(32) NOT NULL,name VARCHAR(255) NOT NULL,applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),PRIMARY KEY(version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');}
 private function applied():array{$r=[];foreach($this->pdo->query('SELECT version FROM '.self::TRACKING_TABLE)->fetchAll()as$x)$r[(string)$x['version']]=true;return$r;}
 private function load():array{$files=glob($this->path.'/*.php')?:[];sort($files,SORT_STRING);$out=[];$seen=[];foreach($files as$f){$m=require$f;if(!$m instanceof Migration)throw new RuntimeException('Invalid migration: '.$f);if(!preg_match('/^\d{12}$/',$m->version()))throw new RuntimeException('Invalid migration version: '.$f);if(isset($seen[$m->version()]))throw new RuntimeException('Duplicate migration: '.$m->version());$seen[$m->version()]=1;$out[]=$m;}return$out;}
}

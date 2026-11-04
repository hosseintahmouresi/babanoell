<?php
declare(strict_types=1);
namespace Babanoell\Loans\Database;
use PDO; use RuntimeException; use Throwable;
final class MigrationRunner
{
    private const TRACKING_TABLE='loans_schema_migrations';
    private const LEGACY_TRACKING_TABLE='schema_migrations';

    public function __construct(private readonly PDO $pdo, private readonly string $path) {}

    public function status(): array
    {
        $exists=$this->hasTable(self::TRACKING_TABLE);
        $applied=$exists?$this->applied():[];
        $legacy=$this->legacyApplied();
        $rows=[];
        foreach($this->load() as $m){
            $version=$m->version();
            $isApplied=isset($applied[$version]);
            $isAdoptable=!$isApplied&&isset($legacy[$version])&&$legacy[$version]===$m->name();
            $rows[]=[
                'version'=>$version,
                'name'=>$m->name(),
                'status'=>($isApplied||$isAdoptable)?'applied':'pending',
                'source'=>$isApplied?'loans':($isAdoptable?'legacy_exact':'pending'),
            ];
        }
        return ['tracking_table_exists'=>$exists,'tracking_table'=>self::TRACKING_TABLE,'migrations'=>$rows];
    }

    public function up(): array
    {
        DatabaseIsolationGuard::assertLoansDatabase($this->pdo);
        $this->ensure();
        $this->adoptLegacyLoansRows();
        $applied=$this->applied();$now=[];
        foreach($this->load() as $m){
            if(isset($applied[$m->version()]))continue;
            try{
                $m->up($this->pdo);
                $st=$this->pdo->prepare('INSERT INTO '.self::TRACKING_TABLE.'(version,name) VALUES(:v,:n)');
                $st->execute([':v'=>$m->version(),':n'=>$m->name()]);
                $now[]=['version'=>$m->version(),'name'=>$m->name()];
            }catch(Throwable $e){
                throw new RuntimeException('Migration '.$m->version().' ('.$m->name().') failed: '.$e->getMessage(),0,$e);
            }
        }
        return $now;
    }

    private function hasTable(string $table): bool
    {
        $s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $s->execute([':table'=>$table]);
        return (int)$s->fetchColumn()===1;
    }

    private function ensure(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS '.self::TRACKING_TABLE."(
            version VARCHAR(32) NOT NULL,
            name VARCHAR(255) NOT NULL,
            applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            PRIMARY KEY(version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Supports a partially installed Loans 1.0.0 database without ever
     * trusting unrelated migration rows from another service.
     */
    private function adoptLegacyLoansRows(): void
    {
        if(!$this->hasTable(self::LEGACY_TRACKING_TABLE))return;
        $known=[];
        foreach($this->load() as $m)$known[$m->version()]=$m->name();
        $stmt=$this->pdo->query('SELECT version,name FROM '.self::LEGACY_TRACKING_TABLE);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $version=(string)($row['version']??'');$name=(string)($row['name']??'');
            if(!isset($known[$version])||$known[$version]!==$name)continue;
            $insert=$this->pdo->prepare('INSERT IGNORE INTO '.self::TRACKING_TABLE.'(version,name) VALUES(:v,:n)');
            $insert->execute([':v'=>$version,':n'=>$name]);
        }
    }

    private function applied(): array
    {
        $r=[];foreach($this->pdo->query('SELECT version FROM '.self::TRACKING_TABLE)->fetchAll() as $x)$r[(string)$x['version']]=true;return$r;
    }

    /** @return array<string,string> */
    private function legacyApplied(): array
    {
        if(!$this->hasTable(self::LEGACY_TRACKING_TABLE))return[];
        $rows=$this->pdo->query('SELECT version,name FROM '.self::LEGACY_TRACKING_TABLE)->fetchAll(PDO::FETCH_ASSOC);
        $out=[];
        foreach($rows as $row){
            $version=(string)($row['version']??'');
            $name=(string)($row['name']??'');
            if($version!==''&&$name!=='')$out[$version]=$name;
        }
        return$out;
    }

    private function load(): array
    {
        $files=glob($this->path.'/*.php')?:[];sort($files,SORT_STRING);$out=[];$seen=[];
        foreach($files as $file){$m=require$file;if(!$m instanceof Migration)throw new RuntimeException('Invalid migration: '.$file);if(!preg_match('/^\d{12}$/',$m->version()))throw new RuntimeException('Invalid migration version: '.$file);if(isset($seen[$m->version()]))throw new RuntimeException('Duplicate migration: '.$m->version());$seen[$m->version()]=1;$out[]=$m;}return$out;
    }
}

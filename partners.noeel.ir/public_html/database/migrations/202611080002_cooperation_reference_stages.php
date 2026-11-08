<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
    public function version(): string {return '202611080002';}
    public function name(): string {return 'cooperation_reference_stages';}
    public function up(PDO $pdo): void {
        $exists=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='cooperation_requests' AND CONSTRAINT_NAME='chk_cooperation_status'")->fetchColumn();
        if((int)$exists>0){$drop=str_contains(strtolower((string)$pdo->query('SELECT VERSION()')->fetchColumn()),'mariadb')?'DROP CONSTRAINT':'DROP CHECK';$pdo->exec('ALTER TABLE cooperation_requests '.$drop.' chk_cooperation_status');}
        $pdo->exec("ALTER TABLE cooperation_requests ADD CONSTRAINT chk_cooperation_status CHECK(status IN ('submitted','under_review','need_correction','docs_completion','docs_verified','contracting','approved','rejected','cancelled','archived'))");
    }
};

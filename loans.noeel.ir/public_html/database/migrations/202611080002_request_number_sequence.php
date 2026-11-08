<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version():string{return '202611080002';}
    public function name():string{return 'request_number_sequence';}
    public function up(PDO $pdo):void {
        $pdo->exec('CREATE TABLE IF NOT EXISTS request_number_sequence(id TINYINT UNSIGNED NOT NULL PRIMARY KEY,last_allocated BIGINT UNSIGNED NOT NULL DEFAULT 0,step INT UNSIGNED NOT NULL DEFAULT 1,revision INT UNSIGNED NOT NULL DEFAULT 1,updated_by CHAR(26) NULL,updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $last=(int)$pdo->query("SELECT GREATEST(COALESCE(MAX(CASE WHEN request_no REGEXP '^[0-9]{1,15}$' THEN CAST(request_no AS UNSIGNED) ELSE 0 END),0),COALESCE(MAX(legacy_id),0)) FROM loan_requests")->fetchColumn();
        $pdo->prepare('INSERT IGNORE INTO request_number_sequence(id,last_allocated) VALUES(1,:last)')->execute([':last'=>$last]);
    }
};

<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
    public function version():string{return '202611080005';}
    public function name():string{return 'user_commissions';}
    public function up(PDO $pdo):void {
        $pdo->exec('CREATE TABLE IF NOT EXISTS partner_user_commissions(user_id CHAR(26) NOT NULL PRIMARY KEY,commission_percent DECIMAL(9,4) NULL,revision INT UNSIGNED NOT NULL DEFAULT 1,updated_by CHAR(26) NOT NULL,updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),CONSTRAINT chk_user_commission_percent CHECK(commission_percent IS NULL OR (commission_percent>=0 AND commission_percent<=100))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
};

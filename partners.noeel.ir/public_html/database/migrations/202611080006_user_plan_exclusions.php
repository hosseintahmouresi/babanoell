<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
 public function version():string{return '202611080006';}
 public function name():string{return 'user_plan_exclusions';}
 public function up(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS partner_user_plan_exclusions(user_id CHAR(26) NOT NULL PRIMARY KEY,disabled_plan_ids_json JSON NOT NULL,revision INT UNSIGNED NOT NULL DEFAULT 1,updated_by CHAR(26) NOT NULL,updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
};

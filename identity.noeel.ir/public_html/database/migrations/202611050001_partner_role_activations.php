<?php
declare(strict_types=1);
use Babanoell\Auth\Database\Migration;
return new class implements Migration {
 public function version():string{return '202611050001';}
 public function name():string{return 'partner_role_activations';}
 public function up(PDO $pdo):void{
  $pdo->exec("CREATE TABLE IF NOT EXISTS partner_role_activations (
   cooperation_id CHAR(26) NOT NULL PRIMARY KEY,
   merchant_id CHAR(26) NOT NULL,
   user_id CHAR(26) NOT NULL,
   actor_user_id CHAR(26) NOT NULL,
   created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
   KEY idx_partner_activation_user(user_id),
   CONSTRAINT fk_partner_activation_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
};

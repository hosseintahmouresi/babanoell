<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
 public function version():string{return '202611050001';}
 public function name():string{return 'marketplace_configuration';}
 public function up(PDO $pdo):void{
  $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_configuration (
   id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
   revision INT UNSIGNED NOT NULL DEFAULT 1,
   definition_json JSON NOT NULL,
   updated_by_user_id CHAR(26) NULL,
   updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
   CONSTRAINT chk_marketplace_configuration_singleton CHECK(id=1)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $definition=['enabled'=>true,'require_category'=>true,'require_location'=>true,'allow_all_country'=>true,'categories'=>[],'locations'=>[]];
  $s=$pdo->prepare('INSERT IGNORE INTO marketplace_configuration(id,definition_json) VALUES(1,:definition)');
  $s->execute(['definition'=>json_encode($definition,JSON_THROW_ON_ERROR)]);
 }
};

<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
 public function version(): string{return '202610210001';}
 public function name(): string{return 'create_cooperation_corrections';}
 public function up(PDO $pdo): void {
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cooperation_profile_corrections (
 id CHAR(26) NOT NULL PRIMARY KEY,
 cooperation_request_id CHAR(26) NOT NULL,
 actor_user_id CHAR(26) NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 changed_fields_json JSON NOT NULL,
 before_json JSON NOT NULL,
 after_json JSON NOT NULL,
 row_version_before BIGINT UNSIGNED NOT NULL,
 row_version_after BIGINT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY idx_cooperation_corrections_request (cooperation_request_id,created_at),
 CONSTRAINT fk_cooperation_corrections_request FOREIGN KEY (cooperation_request_id) REFERENCES cooperation_requests(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
};

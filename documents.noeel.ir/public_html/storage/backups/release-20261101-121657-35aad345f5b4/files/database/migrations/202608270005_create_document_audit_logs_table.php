<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration{
 public function version():string{return'202608270005';}
 public function name():string{return'create_document_audit_logs_table';}
 public function up(PDO$pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS document_audit_logs (
  id CHAR(26) NOT NULL PRIMARY KEY,
  document_id CHAR(26) NULL,
  version_id CHAR(26) NULL,
  entity_type VARCHAR(64) NULL,
  entity_id CHAR(26) NULL,
  event_type VARCHAR(100) NOT NULL,
  actor_user_id CHAR(26) NULL,
  result VARCHAR(24) NOT NULL,
  reason VARCHAR(1000) NULL,
  details_json JSON NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(512) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_document_audit_document (document_id,created_at),
  KEY idx_document_audit_entity (entity_type,entity_id,created_at),
  KEY idx_document_audit_actor (actor_user_id,created_at),
  KEY idx_document_audit_event (event_type,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
};

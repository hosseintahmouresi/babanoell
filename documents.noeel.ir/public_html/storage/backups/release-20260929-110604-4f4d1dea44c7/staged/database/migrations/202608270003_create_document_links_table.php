<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration{
 public function version():string{return'202608270003';}
 public function name():string{return'create_document_links_table';}
 public function up(PDO$pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS document_links (
  id CHAR(26) NOT NULL PRIMARY KEY,
  document_id CHAR(26) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id CHAR(26) NOT NULL,
  relation_code VARCHAR(64) NOT NULL DEFAULT 'attachment',
  created_by_user_id CHAR(26) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_document_link (document_id,entity_type,entity_id,relation_code),
  KEY idx_document_links_entity (entity_type,entity_id,created_at),
  CONSTRAINT fk_document_links_document FOREIGN KEY(document_id) REFERENCES documents(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
};

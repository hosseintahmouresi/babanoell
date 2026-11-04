<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration{
 public function version():string{return'202608270006';}
 public function name():string{return'create_document_access_tokens_table';}
 public function up(PDO$pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS document_access_tokens (
  id CHAR(26) NOT NULL PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  document_id CHAR(26) NOT NULL,
  version_id CHAR(26) NOT NULL,
  issued_to_user_id CHAR(26) NOT NULL,
  purpose VARCHAR(24) NOT NULL DEFAULT 'download',
  expires_at DATETIME(6) NOT NULL,
  consumed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_document_access_token_hash (token_hash),
  KEY idx_document_access_expiry (expires_at,consumed_at),
  CONSTRAINT fk_document_access_document FOREIGN KEY(document_id) REFERENCES documents(id),
  CONSTRAINT fk_document_access_version FOREIGN KEY(version_id) REFERENCES document_versions(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
};

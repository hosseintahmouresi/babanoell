<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration{
 public function version():string{return'202608270002';}
 public function name():string{return'create_document_versions_table';}
 public function up(PDO$pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS document_versions (
  id CHAR(26) NOT NULL PRIMARY KEY,
  document_id CHAR(26) NOT NULL,
  version INT UNSIGNED NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  extension VARCHAR(12) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  uploaded_by_user_id CHAR(26) NOT NULL,
  uploaded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_document_version (document_id,version),
  UNIQUE KEY uq_document_storage_key (storage_key),
  KEY idx_document_versions_checksum (checksum_sha256),
  CONSTRAINT fk_document_versions_document FOREIGN KEY(document_id) REFERENCES documents(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
};

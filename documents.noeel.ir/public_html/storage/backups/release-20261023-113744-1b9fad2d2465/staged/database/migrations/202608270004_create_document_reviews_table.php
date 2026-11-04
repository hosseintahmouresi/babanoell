<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration{
 public function version():string{return'202608270004';}
 public function name():string{return'create_document_reviews_table';}
 public function up(PDO$pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS document_reviews (
  id CHAR(26) NOT NULL PRIMARY KEY,
  document_id CHAR(26) NOT NULL,
  version_id CHAR(26) NOT NULL,
  status VARCHAR(24) NOT NULL,
  reason VARCHAR(1000) NULL,
  reviewer_user_id CHAR(26) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_document_reviews_document (document_id,created_at),
  KEY idx_document_reviews_status (status,created_at),
  CONSTRAINT fk_document_reviews_document FOREIGN KEY(document_id) REFERENCES documents(id),
  CONSTRAINT fk_document_reviews_version FOREIGN KEY(version_id) REFERENCES document_versions(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
};

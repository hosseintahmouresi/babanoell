<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration{
 public function version():string{return'202608270001';}
 public function name():string{return'create_documents_table';}
 public function up(PDO$pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS documents (
  id CHAR(26) NOT NULL PRIMARY KEY,
  document_type VARCHAR(80) NOT NULL,
  category VARCHAR(48) NOT NULL,
  label VARCHAR(190) NOT NULL,
  visibility VARCHAR(24) NOT NULL DEFAULT 'normal',
  owner_user_id CHAR(26) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  review_status VARCHAR(24) NOT NULL DEFAULT 'pending',
  current_version INT UNSIGNED NOT NULL DEFAULT 1,
  latest_review_reason VARCHAR(1000) NULL,
  reviewed_by_user_id CHAR(26) NULL,
  reviewed_at DATETIME(6) NULL,
  created_by_user_id CHAR(26) NOT NULL,
  archived_by_user_id CHAR(26) NULL,
  archive_reason VARCHAR(1000) NULL,
  archived_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_documents_type_status (document_type,status),
  KEY idx_documents_category_review (category,review_status),
  KEY idx_documents_owner (owner_user_id,status),
  KEY idx_documents_updated (updated_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
};

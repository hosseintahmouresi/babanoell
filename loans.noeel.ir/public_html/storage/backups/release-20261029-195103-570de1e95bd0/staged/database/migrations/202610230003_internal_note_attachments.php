<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
 public function version():string{return '202610230003';}
 public function name():string{return 'internal_note_attachments_and_revisions';}
 public function up(PDO $pdo):void {
  $cols=[];foreach($pdo->query('SHOW COLUMNS FROM request_comments')->fetchAll(PDO::FETCH_ASSOC) as $r)$cols[(string)$r['Field']]=true;
  if(!isset($cols['attachment_document_ids_json']))$pdo->exec('ALTER TABLE request_comments ADD COLUMN attachment_document_ids_json JSON NULL');
  if(!isset($cols['operation_key']))$pdo->exec('ALTER TABLE request_comments ADD COLUMN operation_key VARCHAR(100) NULL');
  if(!isset($cols['payload_sha256']))$pdo->exec('ALTER TABLE request_comments ADD COLUMN payload_sha256 CHAR(64) NULL');
  if(!isset($cols['version_no']))$pdo->exec('ALTER TABLE request_comments ADD COLUMN version_no INT UNSIGNED NOT NULL DEFAULT 1');
  if(!isset($cols['updated_at']))$pdo->exec('ALTER TABLE request_comments ADD COLUMN updated_at DATETIME(6) NULL');
  $indexes=[];foreach($pdo->query('SHOW INDEX FROM request_comments')->fetchAll(PDO::FETCH_ASSOC) as $r)$indexes[(string)$r['Key_name']]=true;
  if(!isset($indexes['uq_internal_note_request_operation']))$pdo->exec('ALTER TABLE request_comments ADD UNIQUE KEY uq_internal_note_request_operation (request_id,author_user_id,operation_key)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS request_note_revisions (id CHAR(26) NOT NULL PRIMARY KEY,note_id CHAR(26) NOT NULL,request_id CHAR(26) NOT NULL,changed_by_user_id CHAR(26) NOT NULL,previous_comment TEXT NOT NULL,previous_version INT UNSIGNED NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),KEY idx_note_revision_note (note_id,created_at),KEY idx_note_revision_request (request_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
 }
};

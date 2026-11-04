<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration {
 public function version():string{return '202610220001';}
 public function name():string{return 'immutable_contract_pdf_archive';}
 public function up(PDO $pdo):void{
  $pdo->exec("CREATE TABLE IF NOT EXISTS contract_pdf_artifacts (
    contract_id CHAR(26) NOT NULL PRIMARY KEY,
    document_id CHAR(26) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    source_rendered_hash CHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_contract_pdf_document (document_id),
    CONSTRAINT fk_contract_pdf_document FOREIGN KEY(document_id) REFERENCES documents(id) ON UPDATE CASCADE ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
};

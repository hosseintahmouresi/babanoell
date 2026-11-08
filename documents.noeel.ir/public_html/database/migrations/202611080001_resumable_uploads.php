<?php
declare(strict_types=1);
use Babanoell\Documents\Database\Migration;
return new class implements Migration {
    public function version():string{return '202611080001';}
    public function name():string{return 'resumable_uploads';}
    public function up(PDO $pdo):void {
        $pdo->exec('CREATE TABLE IF NOT EXISTS chunk_upload_owners(actor_id CHAR(26) NOT NULL PRIMARY KEY) ENGINE=InnoDB');
        $pdo->exec("CREATE TABLE IF NOT EXISTS chunk_uploads(id CHAR(26) NOT NULL PRIMARY KEY,actor_id CHAR(26) NOT NULL,client_key CHAR(64) NOT NULL,request_hash CHAR(64) NOT NULL,entity_type VARCHAR(64) NOT NULL,entity_id CHAR(26) NOT NULL,fields_json JSON NOT NULL,file_name VARCHAR(220) NOT NULL,total_bytes BIGINT UNSIGNED NOT NULL,checksum_sha256 CHAR(64) NOT NULL,chunk_size INT UNSIGNED NOT NULL,total_chunks INT UNSIGNED NOT NULL,status VARCHAR(16) NOT NULL DEFAULT 'active',document_id CHAR(26) NULL,expires_at DATETIME(6) NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),UNIQUE KEY uq_chunk_actor_key(actor_id,client_key),KEY idx_chunk_expiry(status,expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('CREATE TABLE IF NOT EXISTS chunk_upload_parts(upload_id CHAR(26) NOT NULL,part_index INT UNSIGNED NOT NULL,size_bytes INT UNSIGNED NOT NULL,checksum_sha256 CHAR(64) NOT NULL,PRIMARY KEY(upload_id,part_index),CONSTRAINT fk_upload_part FOREIGN KEY(upload_id) REFERENCES chunk_uploads(id) ON DELETE CASCADE) ENGINE=InnoDB');
    }
};

<?php
declare(strict_types=1);
use Babanoell\Auth\Database\Migration;
return new class implements Migration {
 public function version():string{return '202611080007';}
 public function name():string{return 'smart_phone_auth';}
 public function up(PDO $pdo):void {
  $columns=$pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('username',$columns,true))$pdo->exec('ALTER TABLE users ADD username VARCHAR(64) NULL, ADD UNIQUE KEY uq_auth_users_username (username)');
  if(!in_array('mobile_verified_at',$columns,true))$pdo->exec('ALTER TABLE users ADD mobile_verified_at DATETIME(6) NULL');
  $pdo->exec("CREATE TABLE IF NOT EXISTS auth_smart_settings (id TINYINT NOT NULL PRIMARY KEY,sms_pattern_id INT NULL,registration_enabled TINYINT NOT NULL DEFAULT 1,otp_enabled TINYINT NOT NULL DEFAULT 1,revision INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec('INSERT IGNORE INTO auth_smart_settings (id) VALUES (1)');
  $pdo->exec("CREATE TABLE IF NOT EXISTS auth_phone_challenges (id CHAR(48) NOT NULL PRIMARY KEY,user_id CHAR(26) NULL,purpose VARCHAR(16) NOT NULL,mobile VARCHAR(32) NOT NULL,code_hash CHAR(64) NOT NULL,registration_json JSON NULL,delivery_status VARCHAR(16) NOT NULL,attempts INT NOT NULL DEFAULT 0,send_count INT NOT NULL DEFAULT 1,created_at DATETIME(6) NOT NULL,expires_at DATETIME(6) NOT NULL,sent_at DATETIME(6) NULL,consumed_at DATETIME(6) NULL,KEY idx_phone_expiry(expires_at),KEY idx_phone_mobile(mobile,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS auth_smart_limits (bucket_hash CHAR(64) NOT NULL,window_start BIGINT NOT NULL,hits INT NOT NULL DEFAULT 0,expires_at BIGINT NOT NULL,PRIMARY KEY(bucket_hash,window_start),KEY idx_smart_limit_expiry(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
};

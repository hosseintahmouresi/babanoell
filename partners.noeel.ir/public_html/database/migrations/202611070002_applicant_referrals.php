<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration {
 public function version():string{return '202611070002';}
 public function name():string{return 'applicant_referrals';}
 public function up(PDO $pdo):void{
  $pdo->exec("CREATE TABLE IF NOT EXISTS merchant_referral_codes (merchant_id CHAR(26) NOT NULL PRIMARY KEY,code VARCHAR(40) NOT NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),UNIQUE KEY uq_referral_code(code),CONSTRAINT fk_referral_merchant FOREIGN KEY(merchant_id) REFERENCES merchants(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS applicant_invitation_locks (applicant_user_id CHAR(26) NOT NULL PRIMARY KEY,merchant_id CHAR(26) NULL,expires_at DATETIME(6) NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),CONSTRAINT fk_applicant_invitation_merchant FOREIGN KEY(merchant_id) REFERENCES merchants(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
};

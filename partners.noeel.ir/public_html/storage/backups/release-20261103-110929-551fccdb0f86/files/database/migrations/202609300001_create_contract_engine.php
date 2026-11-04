<?php
declare(strict_types=1);
use Babanoell\Partners\Database\Migration;
return new class implements Migration{
 public function version():string{return '202609300001';}
 public function name():string{return 'create_contract_engine';}
 public function up(PDO $pdo):void{
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS contract_templates (
 id CHAR(26) NOT NULL,
 code VARCHAR(80) NOT NULL,
 name VARCHAR(180) NOT NULL,
 contract_kind VARCHAR(40) NOT NULL,
 version INT UNSIGNED NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 content_html MEDIUMTEXT NOT NULL,
 variables_json JSON NULL,
 content_hash CHAR(64) NOT NULL,
 created_by_user_id CHAR(26) NOT NULL,
 published_at DATETIME(6) NULL,
 archived_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_contract_template_code_version(code,version),
 KEY idx_contract_template_kind_status(contract_kind,status,version),
 CONSTRAINT chk_contract_template_status CHECK(status IN ('draft','active','archived')),
 CONSTRAINT chk_contract_template_kind CHECK(contract_kind IN ('cooperation_agreement','loan_application','facility_agreement'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS contracts (
 id CHAR(26) NOT NULL,
 contract_no VARCHAR(48) NOT NULL,
 contract_kind VARCHAR(40) NOT NULL,
 subject_type VARCHAR(40) NOT NULL,
 subject_id CHAR(26) NOT NULL,
 signer_user_id CHAR(26) NULL,
 signer_mobile VARCHAR(32) NULL,
 mode VARCHAR(16) NOT NULL DEFAULT 'digital',
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 template_id CHAR(26) NULL,
 template_code VARCHAR(80) NOT NULL,
 template_version INT UNSIGNED NOT NULL DEFAULT 1,
 template_hash CHAR(64) NOT NULL,
 rendered_content MEDIUMTEXT NOT NULL,
 rendered_hash CHAR(64) NOT NULL,
 public_token_hash CHAR(64) NULL,
 public_token_expires_at DATETIME(6) NULL,
 viewed_at DATETIME(6) NULL,
 otp_verified_at DATETIME(6) NULL,
 signed_at DATETIME(6) NULL,
 signer_ip VARCHAR(64) NULL,
 signer_user_agent VARCHAR(512) NULL,
 artifact_document_id CHAR(26) NULL,
 artifact_hash CHAR(64) NULL,
 artifact_status VARCHAR(24) NOT NULL DEFAULT 'pending',
 row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_by_user_id CHAR(26) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_contract_no(contract_no),
 UNIQUE KEY uq_contract_subject_kind(subject_type,subject_id,contract_kind),
 KEY idx_contract_subject(subject_type,subject_id,status),
 KEY idx_contract_signer(signer_user_id,status),
 KEY idx_contract_public_token(public_token_hash),
 CONSTRAINT fk_contract_template FOREIGN KEY(template_id) REFERENCES contract_templates(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_contract_mode CHECK(mode IN ('physical','digital','legacy')),
 CONSTRAINT chk_contract_status CHECK(status IN ('pending','sent','viewed','otp_verified','signed','expired','cancelled','legacy_imported')),
 CONSTRAINT chk_contract_artifact_status CHECK(artifact_status IN ('pending','stored','failed','not_required')),
 CONSTRAINT chk_contract_kind CHECK(contract_kind IN ('cooperation_agreement','loan_application','facility_agreement')),
 CONSTRAINT chk_contract_subject_type CHECK(subject_type IN ('cooperation_request','loan_request'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS contract_otp_challenges (
 id CHAR(26) NOT NULL,
 contract_id CHAR(26) NOT NULL,
 otp_hash VARCHAR(255) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active',
 attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
 resend_after DATETIME(6) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 verified_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 KEY idx_contract_otp_active(contract_id,status,expires_at),
 CONSTRAINT fk_contract_otp_contract FOREIGN KEY(contract_id) REFERENCES contracts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_contract_otp_status CHECK(status IN ('active','verified','expired','locked','superseded'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS contract_events (
 id CHAR(26) NOT NULL,
 contract_id CHAR(26) NOT NULL,
 event_type VARCHAR(80) NOT NULL,
 actor_user_id CHAR(26) NULL,
 result VARCHAR(24) NOT NULL DEFAULT 'allowed',
 details_json JSON NULL,
 ip_address VARCHAR(64) NULL,
 user_agent VARCHAR(512) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 KEY idx_contract_events_contract(contract_id,created_at),
 CONSTRAINT fk_contract_events_contract FOREIGN KEY(contract_id) REFERENCES contracts(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS contract_artifact_handoffs (
 id CHAR(26) NOT NULL,
 contract_id CHAR(26) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 expected_sha256 CHAR(64) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'active',
 expires_at DATETIME(6) NOT NULL,
 consumed_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id),
 UNIQUE KEY uq_contract_artifact_token(token_hash),
 KEY idx_contract_artifact_contract(contract_id,status,expires_at),
 CONSTRAINT fk_contract_artifact_contract FOREIGN KEY(contract_id) REFERENCES contracts(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT chk_contract_artifact_handoff_status CHECK(status IN ('active','consumed','expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
  $seed=[
   ['cooperation_default','قرارداد همکاری','cooperation_agreement','<h1>قرارداد همکاری</h1><p>شماره درخواست: {{request_no}}</p><p>نام متقاضی: {{signer_name}}</p><p>این نسخه در زمان ایجاد قرارداد منجمد و هش می‌شود.</p>'],
   ['loan_application_default','درخواست تسهیلات','loan_application','<h1>درخواست تسهیلات</h1><p>شماره پرونده: {{request_no}}</p><p>متقاضی: {{signer_name}}</p><p>مبلغ درخواستی: {{requested_amount}}</p>'],
   ['facility_agreement_default','قرارداد دریافت تسهیلات','facility_agreement','<h1>قرارداد دریافت تسهیلات</h1><p>شماره پرونده: {{request_no}}</p><p>متقاضی: {{signer_name}}</p><p>مبلغ تسهیلات: {{facility_amount}}</p>'],
  ];
  $st=$pdo->prepare("INSERT INTO contract_templates(id,code,name,contract_kind,version,status,content_html,variables_json,content_hash,created_by_user_id,published_at) VALUES(:id,:code,:name,:kind,1,'active',:content,:vars,:hash,:actor,NOW(6)) ON DUPLICATE KEY UPDATE name=VALUES(name)");
  foreach($seed as $row){[$code,$name,$kind,$content]=$row;$st->execute([':id'=>substr(hash('sha256','contract-template:'.$code),0,26),':code'=>$code,':name'=>$name,':kind'=>$kind,':content'=>$content,':vars'=>json_encode([],JSON_UNESCAPED_UNICODE),':hash'=>hash('sha256',$content),':actor'=>str_repeat('0',26)]);}
  // Preserve previously signed cooperation records without fabricating their historical text.
  $legacy=$pdo->query("SELECT id,request_no,applicant_user_id,created_by_user_id,contract_signed_at FROM cooperation_requests WHERE contract_signed_status='signed'")->fetchAll(PDO::FETCH_ASSOC)?:[];
  $legacyTemplateId=substr(hash('sha256','contract-template:cooperation_default'),0,26);
  $legacyTemplateContent='<h1>قرارداد همکاری تاریخی</h1><p>این رکورد پیش از راه‌اندازی موتور قرارداد جدید امضا شده است. متن تاریخی قرارداد توسط این migration بازسازی نشده است.</p>';
  $legacyTemplateHash=hash('sha256','<h1>قرارداد همکاری</h1><p>شماره درخواست: {{request_no}}</p><p>نام متقاضی: {{signer_name}}</p><p>این نسخه در زمان ایجاد قرارداد منجمد و هش می‌شود.</p>');
  $legacyInsert=$pdo->prepare("INSERT IGNORE INTO contracts(id,contract_no,contract_kind,subject_type,subject_id,signer_user_id,mode,status,template_id,template_code,template_version,template_hash,rendered_content,rendered_hash,signed_at,artifact_status,created_by_user_id) VALUES(:id,:no,'cooperation_agreement','cooperation_request',:sid,:signer,'legacy','legacy_imported',:tid,'cooperation_default',1,:thash,:content,:rhash,:signed,'not_required',:actor)");
  foreach($legacy as $row){$rid=(string)$row['id'];$signed=(string)($row['contract_signed_at']??'');$legacyInsert->execute([':id'=>substr(hash('sha256','legacy-contract:'.$rid),0,26),':no'=>'LEG-COOP-'.substr(strtoupper(hash('sha256',$rid)),0,16),':sid'=>$rid,':signer'=>((string)($row['applicant_user_id']??''))?:null,':tid'=>$legacyTemplateId,':thash'=>$legacyTemplateHash,':content'=>$legacyTemplateContent,':rhash'=>hash('sha256',$legacyTemplateContent),':signed'=>$signed!==''?$signed:null,':actor'=>((string)($row['created_by_user_id']??''))?:str_repeat('0',26)]);}
 }
};

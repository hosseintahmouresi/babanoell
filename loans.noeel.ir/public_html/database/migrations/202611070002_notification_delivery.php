<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string {return '202611070002';}
    public function name(): string {return 'notification_delivery';}
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_settings (id TINYINT UNSIGNED NOT NULL PRIMARY KEY,revision INT UNSIGNED NOT NULL DEFAULT 1,settings_json JSON NOT NULL,updated_by VARCHAR(64) NULL,updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO notification_settings(id,settings_json) VALUES(1,'{\"loan_statuses\":{}}')");
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS notification_deliveries (
 id CHAR(26) NOT NULL PRIMARY KEY,
 event_id CHAR(26) NOT NULL,
 request_id CHAR(26) NOT NULL,
 recipient_hash CHAR(64) NOT NULL,
 recipient_label VARCHAR(32) NOT NULL,
 target_masked VARCHAR(32) NOT NULL,
 payload_json JSON NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 claim_token CHAR(32) NULL,
 claimed_at DATETIME(6) NULL,
 next_attempt_at DATETIME(6) NULL,
 provider_reference VARCHAR(128) NULL,
 last_error VARCHAR(100) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 accepted_at DATETIME(6) NULL,
 UNIQUE KEY uq_notification_event_recipient(event_id,recipient_hash),
 KEY idx_notification_delivery_queue(status,next_attempt_at,created_at),
 KEY idx_notification_delivery_request(request_id,created_at),
 CONSTRAINT fk_notification_event FOREIGN KEY(event_id) REFERENCES integration_outbox(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        // Unsent historical events must never become a burst of old SMS after upgrading.
        $pdo->exec("UPDATE integration_outbox SET status='skipped',last_error='historical_event_before_worker' WHERE status='pending'");
    }
};

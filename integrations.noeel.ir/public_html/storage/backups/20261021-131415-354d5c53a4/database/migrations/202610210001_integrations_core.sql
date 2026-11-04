CREATE TABLE IF NOT EXISTS integration_operations (
 id CHAR(32) NOT NULL,
 caller VARCHAR(32) NOT NULL,
 operation VARCHAR(72) NOT NULL,
 idempotency_key VARCHAR(120) NOT NULL,
 request_hash CHAR(64) NOT NULL,
 target_hash CHAR(64) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 result_json JSON NULL,
 error_code VARCHAR(80) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 finished_at DATETIME(6) NULL,
 PRIMARY KEY(id),
 UNIQUE KEY uq_operation_idempotent(caller,operation,idempotency_key),
 KEY idx_operation_rate(caller,operation,target_hash,created_at),
 KEY idx_operation_status(status,created_at),
 CONSTRAINT chk_operation_state CHECK(status IN('pending','succeeded','failed','uncertain'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS integration_security_events (
 id CHAR(32) NOT NULL,
 caller VARCHAR(32) NOT NULL,
 event_code VARCHAR(60) NOT NULL,
 request_id VARCHAR(128) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), KEY idx_security_recent(caller,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

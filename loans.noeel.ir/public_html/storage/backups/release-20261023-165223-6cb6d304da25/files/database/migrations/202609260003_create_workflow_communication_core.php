<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202609260003'; }
    public function name(): string { return 'create_workflow_communication_core'; }
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS loan_request_assignments (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    assignment_type VARCHAR(32) NOT NULL,
    assigned_user_id CHAR(26) NOT NULL,
    assigned_by_user_id CHAR(26) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    assigned_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    released_at DATETIME(6) NULL,
    reason VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    KEY idx_request_assignments_request (request_id, assignment_type, status),
    KEY idx_request_assignments_user (assigned_user_id, status, assigned_at),
    CONSTRAINT fk_request_assignments_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS request_messages (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    sender_user_id CHAR(26) NULL,
    sender_role VARCHAR(64) NULL,
    message TEXT NOT NULL,
    visibility VARCHAR(24) NOT NULL DEFAULT 'participants',
    attachment_document_ids_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    edited_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_request_messages_request (request_id, created_at),
    KEY idx_request_messages_sender (sender_user_id, created_at),
    CONSTRAINT fk_request_messages_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS request_comments (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    author_user_id CHAR(26) NOT NULL,
    comment TEXT NOT NULL,
    visibility VARCHAR(24) NOT NULL DEFAULT 'internal',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_request_comments_request (request_id, created_at),
    CONSTRAINT fk_request_comments_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS request_tags (
    request_id CHAR(26) NOT NULL,
    tag VARCHAR(64) NOT NULL,
    created_by_user_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (request_id, tag),
    KEY idx_request_tags_tag (tag, created_at),
    CONSTRAINT fk_request_tags_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};

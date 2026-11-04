<?php
declare(strict_types=1);

use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609290003'; }
    public function name(): string { return 'backfill_legacy_acceptor_merchants'; }

    public function up(PDO $pdo): void
    {
        // Before Partners existed, a merchant-backed case could have only acceptor_user_id.
        // Some older rows are even merchant-origin rows whose creator is the acceptor while
        // acceptor_user_id is still empty. Materialize both forms into the normalized shadow.
        $pdo->exec(<<<'SQL'
INSERT INTO merchants (
    id,code,name,legal_name,owner_user_id,organization_ref,status,cooperation_status,
    credit_level,financial_settings_json,metadata_json,legacy_user_id,legacy_profile_ref,
    created_at,updated_at
)
SELECT
    LOWER(SUBSTRING(SHA2(CONCAT('legacy-acceptor:', src.acceptor_user_id),256),1,26)) AS id,
    CONCAT('ACC-',UPPER(SUBSTRING(src.acceptor_user_id,1,12))) AS code,
    COALESCE(src.legacy_name,CONCAT('پذیرنده منتقل‌شده ',UPPER(SUBSTRING(src.acceptor_user_id,1,8)))) AS name,
    NULL AS legal_name,
    src.acceptor_user_id AS owner_user_id,
    NULL AS organization_ref,
    'active' AS status,
    'approved' AS cooperation_status,
    NULL AS credit_level,
    NULL AS financial_settings_json,
    JSON_OBJECT('source','loan_request_acceptor_backfill','inferred',TRUE) AS metadata_json,
    NULL AS legacy_user_id,
    src.acceptor_user_id AS legacy_profile_ref,
    src.first_seen_at AS created_at,
    src.last_seen_at AS updated_at
FROM (
    SELECT
        normalized.acceptor_user_id,
        MIN(normalized.created_at) AS first_seen_at,
        MAX(normalized.updated_at) AS last_seen_at,
        MAX(CASE
            WHEN normalized.merchant_ref IS NOT NULL
             AND normalized.merchant_ref<>''
             AND normalized.merchant_ref NOT REGEXP '^[a-fA-F0-9]{26}$'
            THEN LEFT(normalized.merchant_ref,190)
            ELSE NULL
        END) AS legacy_name
    FROM (
        SELECT
            COALESCE(
                NULLIF(r.acceptor_user_id,''),
                CASE WHEN r.request_origin='merchant' THEN NULLIF(r.created_by_user_id,'') ELSE NULL END
            ) AS acceptor_user_id,
            r.merchant_ref,
            r.created_at,
            r.updated_at
        FROM loan_requests r
    ) normalized
    WHERE normalized.acceptor_user_id IS NOT NULL AND normalized.acceptor_user_id<>''
    GROUP BY normalized.acceptor_user_id
) src
LEFT JOIN merchants existing ON existing.owner_user_id=src.acceptor_user_id
WHERE existing.id IS NULL
SQL);

        // Preserve the user->merchant relation as an active owner membership. This is idempotent.
        $pdo->exec(<<<'SQL'
INSERT INTO merchant_user_memberships (
    merchant_id,user_id,membership_role,status,created_by_user_id,created_at
)
SELECT
    m.id,m.owner_user_id,'owner','active',m.owner_user_id,m.created_at
FROM merchants m
WHERE m.owner_user_id IS NOT NULL AND m.owner_user_id<>''
ON DUPLICATE KEY UPDATE
    status='active',
    membership_role='owner'
SQL);

        // Backfill the normalized merchant link on historical cases without touching an explicit map.
        $pdo->exec(<<<'SQL'
UPDATE loan_requests r
JOIN merchants m ON m.owner_user_id=COALESCE(
    NULLIF(r.acceptor_user_id,''),
    CASE WHEN r.request_origin='merchant' THEN NULLIF(r.created_by_user_id,'') ELSE NULL END
)
SET
    r.acceptor_user_id=COALESCE(
        NULLIF(r.acceptor_user_id,''),
        CASE WHEN r.request_origin='merchant' THEN NULLIF(r.created_by_user_id,'') ELSE NULL END
    ),
    r.merchant_id=m.id,
    r.merchant_ref=CASE
        WHEN r.merchant_ref IS NULL
          OR r.merchant_ref=''
          OR r.merchant_ref=r.acceptor_user_id
          OR r.merchant_ref=r.created_by_user_id
        THEN m.id
        ELSE r.merchant_ref
    END,
    r.updated_at=r.updated_at
WHERE (r.merchant_id IS NULL OR r.merchant_id='')
  AND COALESCE(
        NULLIF(r.acceptor_user_id,''),
        CASE WHEN r.request_origin='merchant' THEN NULLIF(r.created_by_user_id,'') ELSE NULL END
      ) IS NOT NULL
SQL);
    }
};

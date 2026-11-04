# Loans 2.3.1 — Partners Sync Hotfix

- Backfills pre-Partners acceptors from historical loan requests into deterministic merchant shadow records.
- Creates active owner memberships for recovered acceptors.
- Links historical loan requests to the recovered merchant_id without overriding explicit mappings.
- Covers both rows with acceptor_user_id and older merchant-origin rows whose creator is the acceptor.
- Safe and idempotent forward migration: 202609290003.

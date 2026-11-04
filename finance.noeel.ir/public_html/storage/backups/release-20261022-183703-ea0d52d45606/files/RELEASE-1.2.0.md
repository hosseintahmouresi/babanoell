# Finance 1.2.0 — controls for settlement cycles and receipts

- Exact two-decimal financial split validation and merchant/loan ownership checks.
- Guard cycle close on unresolved critical item/total integrity discrepancies.
- Idempotent creation of cycles, cycle items and receipts (Idempotency-Key required on affected API routes).
- Guarded removal of an item from an open cycle with reason and finance event.
- Review of receipts restricted to pending status and a reviewer different from submitter.
- Automatic bounded scan of closed-cycle inconsistencies (admin-only, records audit discrepancies only).
- Read-only cycle reports and pending/approved/rejected receipt list.
- Loans shadow import now fills missing finance rows only and does not overwrite authoritative Finance values.
- GreenPay money transfer remains disabled. No provider payment execution added.
- Database schema: no new migration; reuses finance_idempotency and reconciliation tables.

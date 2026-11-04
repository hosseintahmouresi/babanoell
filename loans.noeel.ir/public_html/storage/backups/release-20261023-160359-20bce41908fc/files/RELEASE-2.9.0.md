# Loans 2.9.0 — Bank workflow access and reporting

- Bank expert task pages and summary are scoped to loan_requests.assigned_bank_user_id and bank queue **in SQL, before pagination**.
- Task-detail actions recheck authorization against the underlying loan record and the active bank assignment; denied scopes are enforced.
- Bank listing and exports include installment_count, granted_at, bank_settlement_status and bank_settled_at (bank-to-platform only).
- Export fails closed above 5,000 matched cases instead of silently truncating records. Narrow filters to export larger datasets in batches.
- No new migrations. Existing Identity, Finance, Documents, Partners and Integrations services remain unchanged.
- Network/provider validation and DB end-to-end testing still required by the deployment team.

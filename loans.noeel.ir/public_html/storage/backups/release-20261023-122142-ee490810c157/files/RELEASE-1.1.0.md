# Loans 1.1.0 — Phase 3 Documents integration

- Canonical required-document types are validated and pinned to each plan version.
- Loan request API exposes the required documents from the pinned plan version.
- Status transitions to `docs_verified` and `granted` require a successful Documents completion check.
- `/healthz` now checks Documents through its shallow `/health` endpoint.
- Existing staging configs remain compatible through the default `https://documents.noeel.ir` URL.

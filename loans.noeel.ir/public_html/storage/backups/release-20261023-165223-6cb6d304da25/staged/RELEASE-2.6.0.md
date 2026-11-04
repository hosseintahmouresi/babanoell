# Loans 2.6.0 — Bank & Credit

- Adds provider-neutral bank identity and credit verification core.
- Adds verification_requests with idempotency, hashes, expiry, audit and outbox delivery contract.
- Adds frozen per-request identity verification state and credit proof binding.
- Adds bank inbox, summary and export APIs with assigned-bank scoping.
- Adds Shahkar/credit task types and SLA policies without embedding provider HTTP logic in Loans.
- Blocks protected workflow targets and bank approval until required identity/credit proofs are verified.
- Adds signed service-only Integrations callback endpoints for future provider adapters.
- Restricts manual verification completion to audited workflow-admin override with mandatory reason.
- Synchronizes workflow tasks after provider result callbacks.

Database migration: `202609300002_bank_credit_core`.

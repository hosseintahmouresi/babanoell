# Loans 2.2.0 — Core Completion

- Canonical immutable request origins: merchant, applicant, admin, migration, api.
- Applicant and merchant self-service submission/resubmission without client-controlled origin impersonation.
- Stable guarantor entities with soft deactivation; guarantor IDs are never inferred by list position.
- Provider-neutral credit-validation state/history and workflow guards for bank-validation-required plans.
- Request document requirements now expose active guarantors and frozen credit-validation state.
- Birth-date validation rejects future and implausible new values while leaving historical rows untouched.
- Context-aware active plan listing for applicant and merchant request paths.
- Migration 202609290002 upgrades request origin, guarantor identity fields and credit-validation summary fields.

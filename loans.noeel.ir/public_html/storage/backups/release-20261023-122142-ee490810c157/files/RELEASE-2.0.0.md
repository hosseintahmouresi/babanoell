# Loans 2.0.0 — Production Plan Engine

- Immutable plan versions with configuration hashes and publication timestamps.
- Canonical lifecycle: draft, active, inactive, archived; explicit display ordering and safe duplication.
- Per-context visibility for merchant request, applicant request and calculator surfaces.
- Merchant plan permissions with all/allowlist policy and per-merchant limit payloads.
- Occupation-aware required-document rules compatible with the legacy WordPress occupation taxonomy.
- Per-plan check-image and secondary-check requirements.
- Versioned eligibility rules and request-time enforcement.
- Calculation input/result hashes plus immutable plan snapshots and engine version.
- Loan-request snapshots for required documents and plan rules, preventing future plan revisions from changing historical cases.
- Admin calculation preview without creating operational calculation records.

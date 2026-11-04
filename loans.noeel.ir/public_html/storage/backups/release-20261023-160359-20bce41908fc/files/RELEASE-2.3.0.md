# Loans 2.3.0 — Phase 3 Partners Boundary

- Partners is an active external dependency.
- Grandfathered merchant/cooperation tables are explicitly shadow-only.
- Controlled paginated export and compatibility sync endpoints for Partners.
- Applicant routing supports selected merchant or open marketplace.
- Marketplace listing is privacy-limited; claim uses atomic compare-and-set.
- Merchant memberships are resolved from Partners for multi-user acceptors.
- Dependency readiness probes use liveness endpoints to avoid readiness cycles.

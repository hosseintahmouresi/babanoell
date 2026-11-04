# Partners 1.0.0 — Phase 3

- Independent Partners database and updater/installer lifecycle.
- Merchant profiles, memberships and per-plan permissions.
- Individual/legal cooperation requests with auditable state transitions.
- Cooperation document references delegated to Documents storage.
- Marketplace listing/claim and merchant invitations.
- Controlled, repeatable import of grandfathered partner shadow data from Loans.
- HTTP-only dependencies on Identity, Loans and Documents; no cross-database access.
- Request-ID and optional HMAC service identity support.
- Liveness-based dependency probes to prevent circular readiness checks.

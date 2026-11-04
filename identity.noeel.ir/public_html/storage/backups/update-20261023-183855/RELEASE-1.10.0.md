# Identity 1.10.0 — Phase 2A Architecture Contracts

- Adds canonical request/correlation ID contract (`X-Request-Id`) and response metadata.
- Adds API/service contract metadata and explicit domain ownership declaration.
- Adds optional HMAC v1 service-identity headers with backward-compatible `optional` mode.
- Adds stable cross-service headers (`X-Noel-Service`, `X-Noel-Api-Version`).
- Keeps protected runtime state (`config/local.env.php`, storage, logs, backups, sessions, uploads) outside updater packages.
- No business-data migration is required by this release.

Service authentication remains backward-compatible after update. To activate HMAC verification later, configure the same `NOEEL_SERVICE_SHARED_SECRET` on all services and move `NOEEL_SERVICE_AUTH_MODE` from `optional` to `required` only after every service has the shared secret.

# Auth Service Source

Phase 1L contains the active session lifecycle behind disabled-by-default flags: controlled local/staging HTTP login wiring, session issuing with hashed access and refresh credentials, refresh rotation with token-family reuse detection, logout (single and all sessions), current identity with roles and permissions, request validation, response mapping, active rate limit boundaries, authorization boundaries, audit boundaries with retention tooling, framework-free HTTP foundations, application wiring, domain models, repositories, service contracts, and security utilities.

All session endpoints (`login`, `logout`, `refresh`, `me`) stay inactive (`501`) until every explicit `AUTH_*` feature flag is enabled and the environment is `local`, `test`, or `staging`. Production activation additionally requires the security review from `docs/development/phase-1I-auth-activation-readiness.md`.

Not implemented yet: password reset, 2FA, active role/permission listing endpoints, administrative user management, service-to-service tokens.

Request validators check shape only. Response mapping returns safe JSON errors and must not echo passwords, token values, reset codes, 2FA codes, stack traces, file paths, SQL errors, or secret values.

Security configuration lives under `config/` and does not activate auth behavior by default. Controlled local/test/staging login requires explicit flags and must remain disabled in production defaults.

Future implementation must keep controllers, services, repositories, middleware, models, and security helpers separated by responsibility.


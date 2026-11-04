# identity (auth-service)

Dedicated authentication and authorization foundation for the Babanoell platform.

Planned owned data: `babanoell_auth`.

> Per `../docs/architecture/adr-0001-runtime-and-architecture.md` this service is the platform's **identity** service and is deployable **without Docker** on shared hosting or a dedicated server (Option C).

## Docker-free Deployment (shared hosting / dedicated server)

No Docker, no build step, no Composer, no CLI required. PHP 8.1+ (8.3 recommended) with `pdo_mysql`, `openssl`, `json`.

### 1. Local run (no Docker)

```powershell
cd identity
php -S 127.0.0.1:8081 -t public router.php
```

Open `http://127.0.0.1:8081/health`, then run the web installer at `http://127.0.0.1:8081/install.php` (needs a reachable MySQL/MariaDB, e.g. via Laragon/XAMPP).

### 2. Shared hosting (cPanel)

1. Create a subdomain (e.g. `identity.example.com`) and a MySQL database + user (cPanel → MySQL Databases), then attach the user to the database with ALL PRIVILEGES.
2. Upload the `identity` folder contents to the subdomain's document root (ideally point the docroot at `public/`; if you cannot, the bundled root `.htaccess` routes and protects automatically).
3. Open `https://identity.example.com/install.php` and follow the wizard: database credentials + first admin account.
4. The installer applies all migrations, seeds the operator-provided admin (no hardcoded default credentials exist anywhere), writes `config/local.env.php` with a generated token pepper, locks itself, and turns itself into `install.php.locked`.
5. Verify `https://identity.example.com/health` and `https://identity.example.com/healthz` (after installation, `healthz` performs a real database readiness check).

Login endpoints stay **disabled by default** after install. To activate later, edit `config/local.env.php` (`AUTH_LOGIN_ENABLED=true`, `AUTH_SESSION_ISSUING_ENABLED=true`, `AUTH_REFRESH_ENABLED=true`) only after the activation checklist (`../docs/development/phase-1I-auth-activation-readiness.md`) is complete.

### 3. Configuration precedence

Real environment variables (Docker/fpm) → `config/local.env.php` (installer-generated) → `.env` file (simple KEY=VALUE). See `config/env.php`. The loader does not depend on `putenv()` and remains compatible with shared cPanel hosts that disable that function. The generated file is git-ignored and must stay private.

### 4. Upgrades (shared hosting)

Use the built-in web updater instead of replacing files manually:

1. Download the release package `babanoell-identity-1.2.6-20260826.zip` from the repository (`releases/` folder; direct link: `https://github.com/hosseintahmouresi/noel/raw/main/releases/babanoell-identity-1.2.6-20260826.zip`).
2. **First time only:** upload `public/update.php` and `src/Update/UpdateTool.php` from the package (the tool is not on the host yet), then place the whole ZIP at `storage/update.zip`.
3. Open `https://identity.example.com/update.php`, sign in as the `super_admin` account, review the plan, confirm the database backup, and apply.
4. **Later updates:** only step 2's ZIP placement (or the tool's upload form) is needed.

The updater validates the archive (identity/ prefix only, no traversal, max 5000 entries / 50 MiB), backs up every replaced file to `storage/backups/`, preserves `config/local.env.php` and `storage/` completely, runs pending migrations, and restores the previous files on failure. See `../docs/development/update-runbook.md` (Persian, cPanel-specific).


### 5. UI / Brand system

Identity UI 1.2.6 uses the platform palette (`#1D3559`, `#D0E2F2`, `#EDA900`) with one shared responsive RTL stylesheet, the approved Babanoell logo asset, concise Persian admin copy, contrast-checked states, and Jalali/Tehran display timestamps. It has no external CDN or build dependency. See `UI-1.2.6.md`.

## Current Status

Phase 1K controlled local-only HTTP activation wiring, active authentication core behind disabled-by-default feature flags, configuration contract, session/token design review, request validation, response mapping, negative tests, inactive use case contracts, rate limit boundary foundation, audit integration tests, authorization and audit boundary foundation, inactive middleware skeletons, HTTP foundation, application wiring, inactive auth endpoint skeletons, domain models, PDO repository implementations, service-layer contracts, test harness, security utility foundation, migration foundation, and reviewed schema migrations.

Implemented runtime behavior:

- `GET /health`
- `GET /healthz`
- Inactive JSON routes for planned auth endpoints.
- CLI-only migration status and migration up commands.
- CLI-only test runner.
- Core repository, security, clock, and ID generator interfaces.
- Isolated password hashing, token hashing, random token, comparison, and input normalization utilities.
- Domain models for auth-owned records.
- PDO repository implementations for auth-service tables.
- Service-layer contracts and an inactive auth service skeleton for future workflows.
- Framework-free request, response, router, app configuration, and error handling primitives.
- Authorization decision, context, RBAC policy, record access policy, and inactive authorization policy foundations.
- Audit event, audit context, audit writer, and inactive audit writer foundations.
- Request ID, security headers, inactive authentication, and fail-closed authorization middleware skeletons.
- Fail-closed credential and session policy implementations.
- Inactive login, logout, refresh, and current-user use case contracts.
- Local/testable rate limit boundary contracts and implementations.
- Framework-free request validation rules and auth request validators.
- Consistent response mapping for validation, inactive, unauthorized, forbidden, not found, method-not-allowed, and internal errors.
- Negative test coverage for malformed JSON, invalid request shape, sensitive value redaction, inactive endpoint behavior, unknown routes, unsupported methods, and internal error masking.
- Security configuration contract with safe placeholder defaults for future active authentication.
- Planning documents for future login activation, rate limits, audit integration, and readiness review.
- Internal active login use case and session issuer core guarded by disabled-by-default flags.
- Synthetic local-only active login tests that do not create default users or usable manual credentials.
- Controlled local/test-only HTTP wiring to the active login use case when explicit activation flags are set.
- Default controller wiring remains inactive in normal local Docker Compose and all non-local environments.

Not implemented in Phase 1K:

- Public login by default.
- Logout.
- Session creation or token validation by default.
- Refresh token behavior.
- User management.
- Role or permission assignment.
- Password reset.
- 2FA.
- Production database access.
- Default admin user or default password.
- Active public auth API behavior outside controlled local/test configuration.
- Public token issuing by default.
- Active authorization decisions for public endpoints.
- Active audit writes from public endpoints.
- Active rate limiting on public endpoints.
- Real request authentication or credential verification.

## Planned Boundaries

- Controllers will receive HTTP requests after routing is introduced.
- Services will hold application use cases.
- Repositories own auth-service persistence access and must use the auth-service database only.
- Middleware will hold auth-specific request filters.
- Models will describe auth-service domain objects.
- Security will hold password, token, session, and policy helpers.
- Database migrations will live under `database/migrations`.
- Tests will live under `tests/unit` and `tests/integration`.

## Folder Structure

```text
identity/
  bin/
  public/
  config/
  src/
    App/
    Audit/
    Authorization/
    Controllers/
    Database/
    Exceptions/
    Http/
      Controllers/
      Middleware/
    Policies/
    RateLimit/
    Validation/
    Services/
    UseCases/
      Login/
      Logout/
      Me/
      Refresh/
    Repositories/
      Pdo/
    Middleware/
    Models/
    Security/
    Support/
  database/
    migrations/
    seeds/
  tests/
    unit/
    integration/
```

## Database Configuration

Auth-service migrations use PDO and environment variables. Do not create or commit a real `.env` file.

Required variables:

```text
AUTH_DB_HOST
AUTH_DB_PORT
AUTH_DB_NAME
AUTH_DB_USER
AUTH_DB_PASSWORD
AUTH_DB_CHARSET
AUTH_ENVIRONMENT
AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL
AUTH_SESSION_TTL_SECONDS
AUTH_REFRESH_TTL_SECONDS
AUTH_TOKEN_PEPPER
AUTH_LOGIN_RATE_LIMIT_WINDOW_SECONDS
AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS
AUTH_PASSWORD_HASH_MEMORY_COST
AUTH_PASSWORD_HASH_TIME_COST
AUTH_PASSWORD_HASH_THREADS
```

Sample placeholder values are documented in `config/.env.example`.

`config/security.php` loads future security settings with safe local defaults. It does not activate login, session creation, refresh, logout, password reset, or 2FA behavior.

Feature flags are disabled by default:

```text
AUTH_LOGIN_ENABLED=false
AUTH_SESSION_ISSUING_ENABLED=false
AUTH_REFRESH_ENABLED=false
AUTH_REQUIRE_TOKEN_PEPPER=true
AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL=false
AUTH_ALLOW_HTTP_LOGIN_IN_STAGING=false
```

## Migration Commands

From the repository root with local PHP and a reachable database:

```powershell
php identity/bin/migrate.php status
php identity/bin/migrate.php up
```

Inside Docker, run the command in the auth-service container and provide the database password from the local shell environment:

```powershell
docker compose exec --env AUTH_DB_PASSWORD auth-service php bin/migrate.php status
docker compose exec --env AUTH_DB_PASSWORD auth-service php bin/migrate.php up
```

The migration runner supports only `status` and `up`. It does not support destructive `down` operations and never runs during web requests.

## Repository and Service Contracts

Phase 1D repository implementations are persistence foundations only:

- They access auth-service owned tables only.
- They use PDO prepared statements.
- They do not read or write another service database.
- They do not create default users, default credentials, sessions, or tokens.
- They do not expose login, logout, refresh, password reset, or 2FA behavior.

Service contracts define future boundaries for authentication, authorization, credential policy, session policy, and audit recording. The inactive auth service skeleton intentionally throws until a later approved phase implements real workflows.

## HTTP Foundation

Phase 1E adds a small HTTP foundation without a framework:

- `Request` normalizes method, path, headers, query parameters, and JSON body parsing.
- `Response` and `JsonResponse` provide consistent JSON output and security headers.
- `Router` supports method and path matching, JSON `404` responses, and JSON `405` responses with an `Allow` header.
- `App`, `Config`, `Container`, `Routes`, and `ErrorHandler` wire the service explicitly.

Unknown routes return:

```json
{
  "error": {
    "code": "not_found",
    "message": "The requested endpoint was not found."
  }
}
```

Inactive planned auth routes return:

```json
{
  "error": {
    "code": "not_implemented",
    "message": "This endpoint is not active yet."
  }
}
```

No stack traces, internal file paths, SQL errors, token values, user records, or sensitive details are exposed in normal JSON responses.

## Authorization Boundary

Phase 1F adds authorization boundary primitives:

- `AuthorizationDecision` is denied by default.
- `AuthorizationContext` carries future user, role, permission, and attribute inputs.
- `PermissionCheck` describes a planned permission and optional record target.
- `RbacAuthorizationPolicy` supports deterministic permission checks from context or an auth-owned permission repository.
- `RecordAccessPolicyInterface` defines the future record access boundary.
- `InactiveAuthorizationPolicy` fails closed for both RBAC and record access decisions.

These classes do not authenticate users, create sessions, issue tokens, return user records, or activate public authorization behavior.

## Audit Boundary

Phase 1F adds audit boundary primitives aligned with `auth_audit_logs`:

- `AuditEventType` provides planned auth-sensitive event names.
- `AuditContext` stores actor, target, session, IP hash, and user-agent hash values.
- `AuditEvent` validates result and metadata shape.
- `PdoAuditWriter` is a low-level prepared-statement writer for auth-owned audit records.
- `InactiveAuditWriter` fails closed and is not wired to public endpoints.

Audit metadata must never contain passwords, token values, reset tokens, API keys, SMS codes, 2FA codes, or secret values.

## Middleware Boundary

Phase 1F adds inactive middleware skeletons:

- `RequestIdMiddleware` adds or preserves a safe request ID.
- `SecurityHeadersMiddleware` adds safe response headers.
- `AuthenticationMiddleware` does not authenticate real users and allows only inactive-safe health/root routes.
- `AuthorizationMiddleware` fails closed outside inactive-safe health/root routes.

The middleware skeletons are not used to activate protected endpoint behavior.

## Use Case Boundary

Phase 1G adds future auth workflow contracts without activating the workflows:

- Login request, result, interface, and inactive implementation.
- Logout request, result, interface, and inactive implementation.
- Refresh request, result, interface, and inactive implementation.
- Current-user request, result, interface, and inactive implementation.

Inactive use cases return controlled inactive results. They do not authenticate users, verify passwords, create sessions, issue tokens, revoke sessions, refresh sessions, or return user records.

Controllers call the inactive use cases so the HTTP layer is wired through the same future boundary while still returning safe `501` JSON responses.

## Rate Limit Boundary

Phase 1G adds rate limit primitives:

- `RateLimitContext` stores action, subject hash, optional source hash, and optional request ID.
- `RateLimitDecision` provides allowed, blocked, inactive, and retry metadata.
- `InactiveRateLimiter` fails closed.
- `FixedWindowRateLimiter` is local and deterministic for tests.

The current rate limit boundary is not wired to public login or auth endpoints. A future Redis-backed implementation may be added after storage ownership, key design, expiry behavior, and operational monitoring are reviewed.

## Request Validation and Response Mapping

Phase 1H added framework-free validation and response mapping:

- Validation errors use `422` and a stable JSON body with `error.code`, `error.message`, and `error.details`.
- Validation details include only field names, error codes, and safe messages. Submitted passwords, token values, reset codes, 2FA codes, and identifiers are not echoed.
- Malformed JSON is mapped to a safe validation error for the request body.
- Valid-shaped inactive auth requests still return `501 not_implemented`.
- Unknown routes return `404 not_found`.
- Unsupported methods return `405 method_not_allowed` with an `Allow` header.
- Internal errors return `500 internal_error` without stack traces, file paths, SQL errors, or secret values.

Validation checks only request shape. It does not authenticate users, verify credentials, issue tokens, revoke sessions, refresh sessions, or return user records.

## Active Authentication Planning

Phase 1I adds design-only planning for future active auth:

- Login implementation plan: `../docs/architecture/auth-login-implementation-plan.md`
- Rate limit strategy: `../docs/architecture/rate-limit-strategy.md`
- Audit strategy: `../docs/architecture/auth-audit-strategy.md`
- Activation readiness checklist: `../docs/development/phase-1I-auth-activation-readiness.md`

These documents define future flow, config, audit, test, rollback, and staging requirements. They do not activate any public endpoint behavior.

## Active Authentication Core

Phase 1J adds internal active login and session issuing foundations behind explicit flags:

- `ActiveLoginUseCase` verifies credentials only when constructed with login enabled.
- `DatabaseSessionIssuer` creates session records only when session issuing is enabled and token pepper readiness is confirmed.
- Raw session credentials exist only in memory for the returned use case result.
- Stored session credential material is hashed through the token hasher.
- Refresh credentials remain absent unless refresh is explicitly enabled in controlled local tests.
- Public HTTP routes are not wired to the active use case and remain inactive by default.

The active core is covered by synthetic local-only tests. It does not seed users, create default credentials, connect to production systems, or enable Nginx-routed login.

## Phase 1L — Active Session Lifecycle (published 2026-08-25)

Adds the completed session lifecycle behind the same disabled-by-default flags:

- `ActiveRefreshUseCase` + `DatabaseSessionRotator`: refresh rotation with token-family reuse detection (reused rotated token revokes the whole family, reason `reuse_detected`).
- `ActiveLogoutUseCase`: revoke current session (`logout`) or all sessions (`logout_all`).
- `ActiveMeUseCase`: user + roles + permissions from a valid access credential.
- Migration `202608250001` adds `sessions.access_token_hash` (HMAC hash of the access credential — never stored in plain text).
- `bin/prune-audit.php` retention tool (`AUTH_AUDIT_RETENTION_DAYS`, dry-run by default).
- Web updater `public/update.php` (see "Upgrades" above) — admin-only, ZIP-validated, backup + migration + rollback.

## Controlled Local HTTP Activation

Phase 1K wires the HTTP login controller to the active login use case only when all of these are true:

- `APP_ENV` is `local` or `test`.
- `AUTH_ENVIRONMENT` is `local` or `test`.
- `AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL=true`.
- `AUTH_LOGIN_ENABLED=true`.

Session creation still requires `AUTH_SESSION_ISSUING_ENABLED=true` and safe token pepper readiness. With default `.env.example` values and normal Docker Compose, `POST /api/v1/auth/login` remains inactive and returns `501 not_implemented`.

Controlled local/test HTTP login returns only the intended session credential and access expiry in the success response. It does not return password hashes, refresh token hashes, token family IDs, raw database rows, internal audit data, or session row IDs. Refresh, logout, password reset, 2FA, `/me`, roles, and permissions remain inactive.

## Inactive Planned Routes

These routes are registered but intentionally inactive:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/refresh`
- `GET /api/v1/auth/me`
- `GET /api/v1/roles`
- `GET /api/v1/permissions`

They do not authenticate users, create sessions, issue tokens, return user records, read production data, or connect to production systems.

## Migration Validation

```powershell
php identity/tests/validate-migrations.php
```

This validates migration file shape, versions, and duplicates without connecting to a database.

## Test Commands

```powershell
php identity/tests/run.php
```

The test runner is intentionally lightweight and CLI-only. It does not require production data, production credentials, or a real `.env` file.

Phase 1K tests cover controlled local-only HTTP activation, disabled-by-default feature flags, active login core behavior, session issuing core behavior, token hash storage, audit metadata safety, security config defaults, documented config keys, request validation, response mapping, malformed JSON handling, missing and invalid fields, oversized inputs, sensitive value redaction, inactive use cases, rate limit boundaries, audit writer integration with synthetic local-only data, authorization decisions, inactive authorization policy behavior, RBAC checks with local-only stubs, audit event validation, inactive audit writer behavior, middleware fail-closed behavior, HTTP request handling, JSON response formatting, inactive endpoint responses, domain model hydration, PDO repository construction and contract shape, service contracts, migration validation, and security utilities. They use local-only placeholder values and do not create default credentials.

## Documentation

- Auth architecture: `../docs/architecture/auth-service.md`
- Planned API contract: `../docs/api/auth-service-v1.md`
- Planned schema: `../docs/database/auth-service-schema.md`

## Security Notes

- Plain-text passwords are prohibited.
- Password hashes must use an approved password hashing function.
- Session, refresh, reset, and 2FA token values must never be stored in plain text.
- No real secrets, credentials, production data, or real user data may be placed in this service.
- No default admin user, default password, or usable credential may be seeded.
- Security utilities, repository implementations, authorization boundaries, audit boundaries, rate limit boundaries, use case contracts, middleware skeletons, and HTTP endpoint skeletons are primitives only. They must not activate a login or session workflow until the future implementation phase is approved.

## Recommended Next Phase

Phase 1L should add database-backed controlled local integration coverage for active auth HTTP login, including synthetic row setup and cleanup, while keeping production disabled until staging and security review are complete.


## Phase 1L closure / 1M prerequisite

The full session lifecycle (login, current-user, refresh rotation with reuse detection, and logout) is present behind explicit activation flags. Staging now has its own activation flag (`AUTH_ALLOW_HTTP_LOGIN_IN_STAGING`) and production remains fail-closed. This is the supported authentication boundary for the phase 1M panel.

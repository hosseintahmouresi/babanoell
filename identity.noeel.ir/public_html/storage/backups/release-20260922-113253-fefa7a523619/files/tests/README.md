# Auth Service Test Placeholder

Phase 1K provides a lightweight CLI test runner without adding a test runner dependency.

Intended future approach:

- Unit tests for inactive use case contracts, rate limit boundaries, authorization boundaries, audit boundaries, inactive middleware behavior, HTTP primitives, inactive endpoint responses, password hashing, token handling, policies, domain models, repository contracts, and service use cases.
- Integration tests for HTTP auth endpoints, database migrations, session persistence, and audit writes.
- Negative tests for invalid credentials, revoked sessions, missing permissions, and rate limits.
- Negative tests for malformed JSON, invalid request shape, oversized fields, sensitive value redaction, inactive endpoint behavior, safe 404/405 responses, and safe internal error mapping.
- Configuration contract tests for future auth security variables and safe defaults.
- Synthetic local-only active login, session issuing, and controlled HTTP wiring tests behind disabled-by-default feature flags.

If a larger test framework is needed later, it should be selected deliberately and added only with a production-appropriate Composer setup.

Run the auth-service test suite:

```text
php services/auth-service/tests/run.php
```

Migration validation:

```text
php services/auth-service/tests/validate-migrations.php
```

This checks migration file shape without connecting to a database or running migrations.

Current Phase 1K tests use local-only placeholder data. They do not require production data, production credentials, a real `.env` file, default admin credentials, or usable manual login credentials.

The inactive login endpoint test verifies that no token, user record, password value, or session payload is returned.

Request validation tests verify that invalid shape returns `422`, validation details do not echo submitted sensitive values, and valid-shaped inactive requests still return `501 not_implemented`.

Security configuration tests verify that placeholder defaults load without a real `.env` file and do not activate login or token issuing.

Active login core tests verify that disabled flags keep public endpoints inactive, missing token pepper fails closed, invalid credentials are generic, raw credential material is not stored, and audit metadata does not contain password or credential values.

Controlled HTTP wiring tests verify that active login is reachable only through explicit local/test flags, default public endpoints remain inactive, wrong-password and missing-user responses are indistinguishable, rate limiting can block before user lookup, and the local-only success response does not expose database rows or token hashes.

Authorization, audit, credential, session, authentication middleware, and authorization middleware tests verify fail-closed defaults.

Use case and rate limit tests verify that inactive workflows do not issue tokens, do not return user records, do not create sessions, and do not store raw rate limit identifiers.

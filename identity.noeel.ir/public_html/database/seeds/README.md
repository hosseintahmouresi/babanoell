# Auth Service Seed Strategy

No seed data is inserted in Phase 1B.

Future seeds may define reviewed system roles and permissions only after the access-control model is approved. Seeds must never create:

- Real users.
- Default admin users.
- Default passwords.
- Usable credentials.
- Production tokens or API keys.
- Production-like personal data.

Any future seed must be deterministic, reviewed, documented, and safe to run in staging before production.

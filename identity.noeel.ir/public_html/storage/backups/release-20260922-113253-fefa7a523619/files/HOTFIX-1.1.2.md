# Identity 1.1.2 — cPanel installer/runtime repair

This maintenance release fixes a partial-install failure seen on shared cPanel hosting.

- `config/local.env.php` is authoritative when present, so stale Apache/PHP `AUTH_*` variables cannot redirect runtime DB access.
- The installer is retry-safe after migrations/admin seeding: it reuses the existing matching admin and `super_admin` role instead of inserting duplicates.
- Runtime verification checks the selected database name explicitly and reports a precise failure.
- Failed runtime verification keeps the generated local config and writes `storage/install-error.log`; the database does not need to be deleted before retrying.
- Authentication feature flags remain disabled after installation.

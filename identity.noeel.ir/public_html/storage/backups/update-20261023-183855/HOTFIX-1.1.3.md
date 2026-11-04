# Identity 1.1.3 — native PDO placeholder repair

This maintenance release fixes `SQLSTATE[HY093]: Invalid parameter number` during updater authentication and future application login.

Root cause:
- MySQL PDO runs with `PDO::ATTR_EMULATE_PREPARES => false`.
- The login lookup reused the same named placeholder twice in one prepared statement (`:identifier`).
- Native PDO MySQL requires each named placeholder occurrence to have a unique name.

Fix:
- `public/update.php` now uses `:mobile_identifier` and `:email_identifier` separately.
- `src/Repositories/Pdo/PdoUserRepository.php` uses the same native-prepare-safe pattern, preventing the same failure when application login is enabled later.
- No database migration is required.
- No password reset or reinstall is required.

# Identity hotfix 1.1.4 — identifier length / installer self-check

## Root cause
The database schema stores service identifiers in `CHAR(26)`, while `RandomIdGenerator` returned 32 hexadecimal characters. On permissive MySQL configurations, INSERTs silently truncated the values to 26 characters. The installer then immediately queried with the original 32-character in-memory id, so the persisted admin row could not be found even though the user and role had been created correctly.

## Fixes
- `RandomIdGenerator` now returns exactly 26 lowercase hexadecimal characters (104 bits of randomness), matching the schema.
- Installer runtime verification resolves the persisted super-admin by its unique mobile and verifies that the stored id length is 26.
- Existing truncated 26-character ids remain valid; no database migration or data deletion is required.
- Core contract test updated to enforce 26-character generated identifiers.

## Recovery for a failed install
Overwrite the files from the direct repair package in the identity service root, then open `install.php` and submit the same database and administrator identity again. The retry-safe installer reuses the existing row, refreshes the submitted password, verifies runtime access, and creates the installation lock.

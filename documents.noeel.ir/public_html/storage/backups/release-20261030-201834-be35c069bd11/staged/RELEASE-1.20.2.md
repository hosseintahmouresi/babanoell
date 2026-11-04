# Documents1.20.2 — installation-specific missing service-key repair

Adds local consumption of a finite encrypted repair capsule for this installation's missing service-auth key. Persists only that key in a protected private override; main configuration, DB credentials, auth mode, documents and storage remain unchanged. No migration, key rotation, unauthenticated bootstrap API or cross-service database/file access.

Only matching supplied private material can decrypt the capsule. A nonempty effective host key wins; invalid/copy-to-different-material/tampered capsules and unwritable/conflicting private overrides leave configuration unchanged. Generic releases contain an empty capsule. Sodium/PHP8.2+ are required for first-time repair, not for loading a previously saved override. All first-time plaintext writes are private and atomically published without clobbering existing files.

This release does not claim mPDF or server PHP modules are installed; the authenticated PDF capability endpoint must be checked after service communication is repaired. No PDF acceptance or real customer signature is created by installing it.

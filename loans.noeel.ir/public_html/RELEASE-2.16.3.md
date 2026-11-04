# Loans2.16.3 — installation-specific missing service-key repair

Env loading can consume a finite encrypted installation repair capsule and persist only a missing shared key in a protected private override. Existing effective nonempty keys, main configuration bytes, DB credentials, auth mode, historical calculations and all domain rules remain unchanged. A later ordinary update preserves this override. No migration or new bootstrap API.

Generic code ships an empty capsule. Personalized repair ZIPs are created by the explicit repair builder using the user's supplied private installation material; no plaintext shared key or installation anchor enters an archive. This is matching-private-material binding, not proof of globally unique installation identity. Changed host credentials, absent Sodium, failed permissions/hardlink publication, tampered capsules or conflicting overrides leave the key unchanged and report only a sanitized status. Runtime writes use0600 permissions and atomic no-clobber publication. PHP8.2+ and Sodium are required for first-time repair; a saved override remains readable without crypto.

Includes Loans2.16.2 numeric corrections. Does not install host Cron, PHP modules, webserver configuration or provider credentials and does not execute payouts, verification or grants.

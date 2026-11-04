# Finance1.8.2 — installation-specific missing service-key repair

Repairs only a missing effective service-auth key from an installation-bound encrypted capsule. Runtime environment retains its existing precedence. Main configuration, database, service auth mode, transfer flags and all settlement/payment data remain unchanged. No migration, provider call, real payment, key rotation or new unauthenticated route.

The plaintext key is persisted only in a0600 protected private override through atomic no-clobber publication. Existing nonempty effective keys win. A capsule with different supplied private material, tampering, missing Sodium or an unwritable/conflicting target cannot alter configuration. Standard source releases contain no personalized capsule; the explicit repair builder verifies every decompressed entry for known private material. Saved overrides survive later updates independently of crypto/capsule availability.

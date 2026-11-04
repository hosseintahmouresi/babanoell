# Identity 1.9.1 — Phase 0 stabilization

- Updated migration contract to the current 18 forward migrations.
- Made healthz contract tests compatible with installed readiness semantics (200 ready / 503 dependency unavailable).
- Isolated safe-default security tests from installer-generated `config/local.env.php`.
- No authentication policy, database schema, credential, or runtime secret is changed by this release.

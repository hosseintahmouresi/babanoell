# Identity 1.3.1 — Phase 2 authorization

- Adds the canonical `loans.*` permissions required by the Loans service and Panel Phase 2 UI.
- Assigns all Phase 2 permissions to the existing `super_admin` system role.
- Does not enable or change authentication feature flags.
- Forward-only migration: `202608260001_seed_loans_permissions`.

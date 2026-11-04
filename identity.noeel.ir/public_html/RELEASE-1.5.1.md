# Identity 1.5.1 — Acceptor RBAC on 1.5.0

- Adds the system role `acceptor` / «پذیرنده» with least-privilege Loans and Documents permissions.
- Adds super-admin-only user directory, user creation, role assignment and account activation/deactivation APIs.
- Preserves the 1.5.0 persistent file-backed login rate limiter, proxy handling and release transaction hardening.
- Prevents self-demotion/self-disable safety violations for the active super-admin account.

# Identity 1.4.1 — Acceptor RBAC

- Seeds the system role `acceptor` with least-privilege Loans/Documents permissions.
- Adds super-admin-only user directory, user creation, role assignment and account activation/deactivation APIs.
- Protects role-management endpoints with the existing bearer-session authentication boundary.
- Prevents a super admin from removing their own super-admin role or disabling their own active account.
- Adds regression coverage for the acceptor role migration and management authentication boundary.

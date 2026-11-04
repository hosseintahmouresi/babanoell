# Panel 1.5.1 — Acceptor Workspace on Unified 1.5 UI

- Preserves the 1.5.0 unified UI, release transaction and trusted proxy handling.
- Adds a dedicated acceptor dashboard, calculator, own-request list, request create/edit/detail and guarantor flows.
- Adds super-admin user/role management UI backed by Identity RBAC.
- Routes pure `acceptor` accounts to `/acceptor` and keeps admin Loans controls out of that workspace.
- Fixes the calculator fatal error caused by the missing `integerish()` parser and adds safe positive-integer validation.
- Adds archive-aware plan UX: archived plans are hidden by default, explicitly filterable and can be reactivated.

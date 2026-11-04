# Loans 1.1.1 — canonical plan lifecycle

- Plan status is canonically limited to `draft`, `active`, `inactive`, and `archived`.
- A database migration normalizes unexpected legacy values to `inactive` and adds a CHECK constraint.
- Archived plans are excluded from the default plan list, while explicit `archived` and `all` filters remain available.
- Active-only enforcement for operational calculations and request creation remains server-side.
- No existing plan versions, calculations, or loan requests are rewritten.

# Finance1.8.4 — settlement workflow checks

Reject settled merchant status until the actual Loans owner reports granted; do so before any Finance write. Existing preparation statuses remain supported.

When the actor is denied the private merchant endpoint, cycle validation may read the existing active merchant directory with the same actor session and its existing permission. Private memberships/settings stay restricted. Success, not-found and owner failures are not masked by the directory lookup; a directory denial remains denied.

No migration, permission expansion, production data rewrite or provider activation. This does not enable GreenPay or replace its independent approval gates.

# Loans 2.8.0 — Provider integration bridge

- Secure service-to-service Integrations client with v1 request and payload signatures.
- Bank verification dispatch for Shahkar and ICS24 using existing verification_requests and audit events.
- OTP / status update for ICS24. OTP acceptance does **not** mark credit verification complete.
- A queued verification is reserved atomically before the external call. Unknown outcome remains pending and is **not** automatically retried.
- No new database migrations. Set `LOANS_INTEGRATIONS_BASE_URL` only if different from the default fixed host.
- API routes require existing bank-review permissions and case visibility.

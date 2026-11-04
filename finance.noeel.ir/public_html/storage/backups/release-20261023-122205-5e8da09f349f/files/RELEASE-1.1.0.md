# Finance 1.1.0 — GreenPay safe integration

- Readiness via Integrations and permission-gated OTP request route.
- OTP requests default disabled (`FINANCE_GREENPAY_OTP_ENABLED=0`).
- Provider OTP intent stored before sending, with a unique operator-supplied idempotency key.
- Transfer route explicitly disabled pending full Finance reconciliation and two-person approval.
- Adds migration 202610210004 for Finance-owned OTP intents.

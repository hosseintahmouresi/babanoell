# Noel — Batch 7 Release Notes

- Integrations `1.2.0`: Finance-specific payout capability check and purpose-scoped HMAC transfer authorization; original provider connector retained.
- Finance `1.3.0`: migration `202610220005`, encrypted payout intents, unique cycle/idempotency reference, separated approver, fail-closed one-shot dispatch, manual audit reconciliation, freeze cycles with payout intents, GreenPay transfer APIs.
- Panel `2.11.0`: unified GreenPay payouts console: create, review, request OTP, dispatch, reconciliation.

Security posture: actual funds transfer disabled by default on both Finance and Integrations; provider verification must be independently completed. No live credentials or private configuration are included.

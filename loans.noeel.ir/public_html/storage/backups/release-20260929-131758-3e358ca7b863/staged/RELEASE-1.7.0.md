# Loans 1.7.0 — Stage 1 Core

Production data-model foundation derived from the current WordPress loan platform.

Includes normalized bounded-context models for merchants, plan eligibility, request ownership/origin, assignments, messages, internal comments, tags, credit checks, bank reviews, grant records, financial receipts, settlement cycles, cooperation requests, notifications and integration outbox.

Legacy Persian status values are represented by a deterministic canonical mapping. Files remain owned by Documents and user identity remains owned by Identity; no cross-service database foreign keys were introduced.

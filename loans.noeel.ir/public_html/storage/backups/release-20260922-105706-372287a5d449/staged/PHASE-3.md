# Phase 3 — Loans 1.1.0

- مدارک الزامی به‌عنوان بخشی از نسخه immutable طرح ذخیره و همراه پرونده Pin می‌شوند.
- API `document-requirements` مدارک نسخه پین‌شده پرونده را برمی‌گرداند.
- Transitionهای `docs_verified` و `granted` از Documents completion API استعلام می‌گیرند و در خطا fail-closed هستند.
- Loans هیچ دسترسی مستقیم به DB Documents ندارد؛ فقط HTTP JSON API.

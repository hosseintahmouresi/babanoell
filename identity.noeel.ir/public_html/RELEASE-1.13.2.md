# Identity 1.13.2 — Login throttle correction

- Avoid accidental lockout from repeated successful logins by resetting the selected user's rate-limit counter after successful authentication and audit.
- Preserve brute-force controls: invalid attempts still count and can result in HTTP 429.
- A broken or unwritable throttle store now reports HTTP 503 `auth_unavailable`, not misleading `rate_limited`.
- Includes CLI-only `bin/login-recovery.php` for selected-account status/unlock by the technical team.
- No DB migration; no password, session or SSO changes.

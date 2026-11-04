# Identity 1.2.1 — updater extraction repair

Date: 2026-08-26

## Fixed

- Fixed updater apply failure where a trailing slash in the extracted `identity/` package root caused the first character of every relative path to be removed.
- `.htaccess` is now preserved as `.htaccess` during extraction validation.
- Relative paths are derived from canonical `realpath()` values for consistent traversal protection.
- Added regression coverage for package roots both with and without a trailing slash.

## UI

- Keeps the unified Babanoell UI introduced in 1.2.0 (`#1D3559`, `#D0E2F2`, `#EDA900`, RTL responsive layout).

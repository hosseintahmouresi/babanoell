# Babanoell Identity 1.2.6

Final UI/UX consolidation release for the Identity administration surface.

## UI/UX

- Reduced the approved Babanoell logo to a compact header lockup and rebalanced header spacing.
- Reworked page hierarchy, cards, forms, tables, badges, alerts, mobile layout, and administrative login surface.
- Kept all styles in the shared `public/assets/babanoell-ui.css` file and all UI behavior in `public/assets/babanoell-ui.js`.
- Rewrote Persian user-facing text for clarity and consistency; removed unnecessary mixed-language operational copy.
- Added collapsible technical details so errors have a clear Persian summary without losing diagnostics.
- Added stronger keyboard focus states, reduced-motion handling, accessible control sizes, and contrast-safe color combinations.

## Persian date/time

- Added dependency-free Jalali formatting for all administrative display timestamps.
- Display timezone is fixed to `Asia/Tehran`; dates use Persian weekday/month names and Persian digits.
- Internal persistence/log/API timestamps remain UTC/ISO-8601.

## Reliability

- Fixed PHP INI upload-size parsing so values such as `64M` are interpreted correctly.
- Added CSRF validation to updater logout.
- Added a restrictive same-origin Content Security Policy to installer/updater pages.
- Added tests for Jalali conversion, INI size parsing, exact logo checksum, external assets, and updater installability.

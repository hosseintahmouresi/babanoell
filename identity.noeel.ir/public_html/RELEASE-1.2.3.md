# Babanoell Identity 1.2.3

- UI is now self-contained in `public/update.php`; CSS, micro-interactions and brand mark no longer depend on `/assets/*` being web-accessible.
- Full release installer uses the same self-contained design layer for future fresh installs.
- Brand palette: `#1D3559`, `#D0E2F2`, `#EDA900`.
- `public/assets/*` remain in the package as progressive/static assets, but the admin UI does not break if the web server blocks or misses them.
- No database migration changes.
- Updater protected files remain protected.

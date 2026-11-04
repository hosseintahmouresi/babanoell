# Babanoell Identity UI 1.2.6

Finalized administrative UI baseline for Identity.

- Approved palette: `#1D3559`, `#D0E2F2`, `#EDA900`.
- Exact approved Babanoell PNG logo, shown as a compact lockup rather than a large hero mark.
- One central stylesheet: `public/assets/babanoell-ui.css`.
- One central UI script: `public/assets/babanoell-ui.js`.
- Assets are served through the allow-listed `public/ui-asset.php` gateway for cPanel/docroot compatibility.
- No inline `<style>` blocks and no inline `style=""` attributes in admin pages.
- Persian RTL copy rewritten for installation, login, updater, success, warning, and error states.
- User-facing date/time is displayed in the Jalali calendar and Asia/Tehran time with Persian digits.
- Machine/API timestamps remain UTC/ISO-8601 so service contracts are not changed.
- Direct-upload PHP INI limits are parsed correctly (`64M` is treated as 64 MiB, not 64 bytes).
- WCAG-style contrast checks pass for the principal brand, CTA, secondary, success, warning, information, and error combinations.

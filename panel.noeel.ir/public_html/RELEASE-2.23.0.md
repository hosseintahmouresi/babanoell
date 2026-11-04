# Panel 2.23.0 / UI 2.14.0 — merchant-to-grant journey

- Shared five-step journey on role-specific case details, with explicit blockers, unknown states and permission-aware next actions.
- Progressive three-step merchant intake, keyboard navigation, safe review rendering and non-JavaScript submission.
- Session-scoped operation keys survive uncertain API retries and bind to the original payload. Validation errors allow corrected input; birthday selections survive rerendering.
- Read-only contract access preserves viewing/printing and hides mutation forms.
- Update the actually served platform assets and mobile header layout.

Requires Loans 2.16.0 readiness API. No database migration. Test on staging after Loans, including roles, actual owner APIs and existing cases. Local browser preview used synthetic responses, including a deliberate 503 retry; no production submission occurred.

Validation: 12 case-journey checks, complete isolated project suite (45 scripts), 654 PHP syntax checks, and a measured 390px browser viewport. See the project merchant-to-grant report for boundaries and rollout steps.

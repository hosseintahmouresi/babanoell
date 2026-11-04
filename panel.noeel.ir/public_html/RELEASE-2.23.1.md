# Panel 2.23.1 / UI 2.14.1 — live UI acceptance fixes

- Fix mobile sidebar closing immediately after opening by removing its duplicate toggle handler. Preserve Escape/overlay close, focus handling and inert background behavior.
- Move chat activation into the served external script to comply with strict CSP. Show an accessible unavailable notice when no chat SDK is present; no provider credential or integration is fabricated.
- Remove the duplicated bank settlement quick-access tile.
- Explain a missing/short service authentication key in the administrator settings view without disclosing it or changing login/configuration gates.
- Send canonical occupation codes in administrator, merchant and applicant forms so eligibility and occupation-dependent documents accept the selection. Retain unknown legacy values without silently reclassifying them.
- Preserve posted birthday selections when create/edit validation fails across all three role forms.

No migration. This updater preserves all installed private settings. It cannot repair missing service shared keys on the host. Live testing found `service_auth_not_configured` responses from Loans, Documents and Finance for signed service calls; coordinate the existing service key through the host's secure configuration process before accepting contracts, marketplace or document artifact handoffs.

See the project live UI acceptance report for actual route observations, local regression evidence and WordPress session coverage. No real payment, signature, provider message or permission expansion was executed.

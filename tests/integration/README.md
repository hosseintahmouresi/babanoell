# Babanoell local acceptance checks

Run from the repository root:

```bash
python3 tests/integration/run.py --report /tmp/babanoell-acceptance.json
```

The default `all` scenario runs the existing service suites, native PDO regressions,
and a synthetic loan through real HTTP connections among all seven services.
Use `--scenario suites`, `regressions`, `onboarding`, or `workflow` to run a group.
`workflow` also runs fresh individual/legal cooperation onboarding before the final
canonical-service outage checks.

Requirements: Linux, Python 3.10+, PHP 8.2+ with the service extensions (including
PDO MySQL, mbstring, GD, XML, cURL and ZIP), MariaDB server/client and initialized
Documents Composer dependencies. PHP's development server must support multiple
workers. Set `PHP_BIN` and `MARIADB_BASEDIR` if the executables are not installed
in their standard locations. The managed workspace's local tool installation is
also detected. No Python packages or external provider credentials are needed.

The runner creates a private temporary MariaDB datadir with networking disabled,
six fresh databases, and disposable copies of the services. It excludes existing
storage, backups and private local configuration; inherited application variables
are removed from child environments. Composer dependencies are linked
read-only by convention. All service URLs use random loopback ports, service
authentication is required, and test accounts and secrets are generated per run.
Processes and temporary files are removed on success and failure. Run this on a
development machine with no unrelated PHP configuration or injected application
credentials. It does not start the production services or import the WordPress dump.

Coverage includes:

- Applying all database migrations and checking that a second run is a no-op.
- Existing service suites in their isolated copies. Integrations distribution
  checks run temporarily without that copy's generated installation state.
- Native prepares for marketplace search/claim, bank and task search, Partners
  merchant/cooperation search, and repeatable cooperation shadow imports.
- Fresh and expired verifications, OTP expiration, resend cooldown, failed attempts
  and immutable signatures under both UTC and Asia/Tehran.
- Identity sessions for eight roles and a Panel cookie/CSRF login.
- Marketplace catalogue revision conflicts, tree validation, merchant profiles and
  ceilings, canonical plan access/revocation, category/location matching, and
  viewer membership rejection. Catalogue administration and the applicant form
  are also exercised over Panel sessions and normal form submissions.
- Marketplace ownership, administrative verification override with an audit reason,
  required document review, two OTP-signed contracts, stored artifacts and real PDF generation.
- File links without service headers, purpose binding and one-use consumption;
  ordinary file endpoints and non-GET requests still require service authentication.
- Grant readiness, bank cycle idempotency and projection, receipt permissions,
  independent receipt review, merchant cycle closure and financier payment recording.
- Independent transfer approval and rejection of execution while transfers are disabled.
- Fresh accounts created through Panel, individual and legal cooperation, frozen form
  rules, corrections/resubmission, required uploads and reviewer controls, owner-only
  OTP signing, contract snapshot binding and immutable PDFs across reissue.
- Atomic cooperation approval, additive acceptor-role activation, safe pending states
  for inactive accounts or unavailable services, concurrent/idempotent retry and
  reapproval after a replacement agreement. Activated users create merchant loans
  and claim marketplace cases through Panel while retaining personal applications.

The workflow uses the explicitly supported administrative verification override;
it does not contact Shahkar or ICS24. Contract OTP uses the local delivery mode.
It records synthetic financial facts only. Closing a cycle is checked separately
from confirming a merchant payment; an unpaid merchant must not be marked settled.
No SMS is sent and no external money transfer is made. Browser appearance,
production web-server rules, live providers and WordPress migration require
separate acceptance checks.

The current priority is independent platform readiness with new data; migration
is deferred. See [the platform readiness record](PLATFORM_READINESS.fa.md).
The fresh-account flow and its controls are documented in [ONBOARDING.fa.md](ONBOARDING.fa.md).

UI acceptance is available through `ui_browser.py`. It runs Chromium against a
disposable stack and checks role pages, desktop/mobile widths, local font bytes,
menu focus, guided forms, account workspace switching and public contract errors.
Install Python Playwright and Chromium first; pass a local axe-core 4.10.3
`axe.min.js` with `--axe-script` to include WCAG A/AA checks. No browser or font CDN
is used by the application. See [the UI validation record](../../ui/VALIDATION.fa.md).

```bash
python3 tests/integration/ui_browser.py --artifacts work/ui-review --chromium /usr/bin/chromium --axe-script /path/to/axe.min.js
```

The optional JSON report contains synthetic identifiers and test results, never
session credentials, OTPs, generated passwords or service secrets.

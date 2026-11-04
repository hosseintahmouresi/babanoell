# Noel Partners Service

Partners is the bounded context for merchant/acceptor profiles, merchant memberships, cooperation requests, merchant plan access, marketplace claims/invitations, and partner-domain audit events.

It owns its own database and communicates with Identity, Loans and Documents only through HTTP APIs. It must not read another service database directly.

## First installation
1. Create an empty MySQL/MariaDB database dedicated to Partners.
2. Extract the bootstrap package into the document root of `partners.noeel.ir`.
3. Open `/install.php` and enter the Partners database credentials.
4. Keep Service Auth in `optional` mode during the Phase 3 rollout unless all services share the same configured HMAC secret.
5. After installation, verify `/health` and `/healthz`.
6. Install the Identity, Loans, Documents and Panel Phase 3 packages in the documented order.
7. From Panel > Partners, run the controlled Loans shadow import once; it is repeatable and does not delete the source shadow rows.

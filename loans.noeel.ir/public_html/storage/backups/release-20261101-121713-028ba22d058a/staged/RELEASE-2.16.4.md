# Loans2.16.4 — retired installer alias denial

Deny both `/install.php.locked` and `/public/install.php.locked` before root public-file rewrites. A real-host HEAD check previously returned200 for the retired installer alias. This release blocks reachability without deleting the protected installer lock or altering active application routes. It does not claim credential exposure from HEAD evidence alone.

No migration, calculation, customer-data, session or auth-mode change. Existing private service-auth overrides remain preserved. Generic packages contain an empty repair envelope; deployments already provisioned through the installation repair or hosting control panel keep their private key independently of Sodium/hardlink availability. Host-only deployment of this webserver rule can be recorded separately from the running app version.

Adds the managed CLI scheduler entrypoint `bin/workflow-sla-managed.php`: fixed UTC PHP/MySQL session, nonblocking per-job lock, shared updater lock and maintenance-state guard, private0600 structured log. It invokes only the existing Loans-owned SLA sweep, not a provider or payment. Configure one Cron entry on the actual host; no schedule is created by a ZIP. `--check` reports paths/runtime/storage readiness without opening a database.

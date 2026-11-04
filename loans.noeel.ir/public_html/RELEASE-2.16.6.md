# Loans2.16.6 — installed version and asset cache identity

Includes all2.16.5 merchant-binding and guarantor protections. Standalone pages now render service/UI versions from the installed metadata rather than the historical2.7.1 constant. Their stylesheet, script, favicon and logo receive the current release cache key, so an update does not keep an obsolete standalone asset URL.

Four behavioral renderer checks use independent synthetic release metadata, not a grep for a newly hard-coded number. No migration or further workflow change.

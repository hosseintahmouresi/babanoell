# Documents1.20.3 — retired installer alias denial

Block retired installer aliases with and without the `/public/` prefix before public-file rewrites. The existing installer lock is preserved. No migration, document/result mutation or key rotation.

An already-provisioned private service-auth override remains supported on hosts without Sodium/hardlinks. Installed Composer runtime, lock, fonts and user storage remain outside ordinary updater archives and are preserved. The separately installed PDF runtime and real-host PDF rendering require their own deployment evidence; installing this code archive alone does not install mPDF.

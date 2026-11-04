# Documents 1.9.1 — Phase 0 stabilization

- Aligned the Documents catalog with the canonical Loans required-document codes.
- Added six missing document types used by the current plan/case workflow.
- Made installer contract tests valid for installed services where `install.php` is locked.
- Removed the invalid cross-service source-code dependency from the Documents unit suite.
- Replaced live-runtime secret absence assertions with updater/web isolation assertions; release ZIP hygiene is validated separately.

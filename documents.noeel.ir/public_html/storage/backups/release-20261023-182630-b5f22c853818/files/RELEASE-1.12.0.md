# Documents 1.12.0 — Phase 3 Partners Boundary

- Partners becomes an active authorization dependency for cooperation documents.
- Cooperation files are bound to `cooperation_request` entities instead of generic parent-case links.
- Documents remains the only binary/file owner; Partners stores document references only.
- No cross-database access to Partners.
- Dependency readiness probes use liveness endpoints to avoid readiness cycles.

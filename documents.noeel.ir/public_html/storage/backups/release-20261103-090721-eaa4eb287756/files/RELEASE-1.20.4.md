# Documents1.20.4 — signed artifacts for applicants without user accounts

Signed HTML and PDF documents retain a registered signer as owner. If the applicant has no Identity account, the authenticated contract creator is the document owner; a missing, malformed or fabricated identity cannot be silently stored as a null owner.

Validate the owner before creating files. Remove a newly written HTML artifact if its database insert fails, and preserve the contract's pending/retry path. PDF document, version/link and archive metadata commit in one database transaction; failed metadata insertion rolls back all rows before storage cleanup and retry. Existing stored artifacts and immutable signed content are not rewritten. No migration or dependency runtime replacement.

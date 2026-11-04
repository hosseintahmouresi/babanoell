# Documents 1.11.0 — Core Completion

- Adds loan_guarantor as an authorized document entity through the Loans API only.
- Guarantor document types can only be stored against the guarantor entity, never the parent loan request.
- Completion checks evaluate guarantor requirements per stable guarantor ID.
- Upload capability metadata explicitly documents authorization-before-file-I/O and current chunk/resume support.
- Loans client contract updated for guarantor authorization.

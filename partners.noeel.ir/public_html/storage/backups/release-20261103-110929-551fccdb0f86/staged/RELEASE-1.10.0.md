# Partners 1.10.0

Cooperation forms now have administrator-owned, append-only revisions for 30 individual, legal and shared fields and two option groups. New requests store their authoritative definition; existing records retain their original data. Field visibility, required values, optimistic publication and idempotent submission are enforced by the owner API.

Migration 202611030001 adds the form revision table and seeds compatible defaults. Existing requests are not backfilled. Required private evidence is checked through Documents before review and approval. Deploy Documents 1.21.0 first, then this service, then Panel 2.24.0. No provider configuration or credentials are included.

# Loans2.16.5 — merchant binding and guarantor evidence protection

Merchant-origin API creation binds the authenticated actor's actual Partners merchant membership. Confirmed missing membership is rejected409 and a forged merchant ID cannot create a case for another merchant403. An unavailable or invalid owner response is503, rather than a false claim of missing membership. The merchant portal's existing payload does not need a private merchant ID.

Guarantor changes advance the parent request version in the same transaction as the guarantor rows and audit, so a stale grant decision cannot ignore this local change. Normal guarantor replacement is rejected after granted/archived without changing existing evidence. Denial is a validation response instead of an internal server error.

No migration, production record backfill, provider activation or external financial operation. Private settings and previous hosting guards remain preserved.

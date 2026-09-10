# C1 — ambiguities the interview surfaced

Questions I had not thought about before `/grill-with-docs` asked them. Keep for the oral (11 September).

## Q16 — Extension before expiry (cumulative vs reset)

If the Host pays again **before** the current paid period ends, does the new month **add** to the current expiry, or does the remaining time get **lost** (clock restarts from the payment date)?

**Decision:** cumulative. Remaining time + 1 month. Example: expiry 1 January → first Extension → 1 February; a further Extension before 1 February → 1 March.

## Q17 — Participant access while Archived

When a Paid Syncer is Archived, do Participants who still have the Share Link see a blocked message, or keep read-only access to results already computed?

**Decision:** blocked for Participants as well as Host. Message: *Syncer expiré, en attente de paiement par l'organisateur.*

## Q20 — Account Session vs Host password for owned Syncers

Once a Syncer is owned by an Account, is the Account Session enough to manage it (participants, etc.), or is the Syncer's own Host password still required every time?

**Decision:** Account Session alone is enough for every Syncer that Account owns. That is what the client area is for. Claiming a Free Syncer still requires Host credentials (Q19) so a Share Link is not enough to take Ownership.

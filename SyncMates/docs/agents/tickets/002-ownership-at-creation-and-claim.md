# Ticket 002: Ownership at creation and Claim of an existing Free Syncer

## Read first

- [docs/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — "What is being
  built" (Ownership), "Decisions taken" (the Claim/Host-credentials paragraph).
- [docs/adr/0002-dual-authentication-host-and-account-sessions.md](../adr/0002-dual-authentication-host-and-account-sessions.md)
- [docs/adr/0005-free-accountless-syncer-creation-remains.md](../adr/0005-free-accountless-syncer-creation-remains.md)
- [CONTEXT.md](../../../CONTEXT.md) — entries for **Ownership** and **Claim** (avoid "Upgrade", "Attach",
  "Link" for Claim).
- Requires Ticket 001 done: Account Session exists and can be read from a request.

## What this delivers

Two related, demonstrable capabilities that both establish Ownership between an Account and a Syncer:

1. Creating a Syncer while an Account Session is present records that Account as the owner, without
   requiring one (per ADR-0005, anonymous creation must keep working exactly as before).
2. Claiming an already-existing Free Syncer into the logged-in Account, by presenting that Syncer's own
   Host credentials (name/ID + password) — the one case where an Account Session alone is *not* enough
   (per the spec's Decisions-taken paragraph and ADR-0002).

Demonstrable via HTTP: with an Account Session, create a Syncer and see it carry the Account's ownership;
separately, create a Syncer anonymously, then Claim it into an Account by supplying its Host name/password
while logged in, and see it become owned; attempt to Claim it a second time or with the wrong password and
see it rejected.

## Scope

- **Data model**: add `ownerAccountId` (nullable string) to the Syncer JSON structure built in
  `createSyncer`, `src/services/syncersService.php:60`. `null` for anonymous Syncers — the existing,
  unchanged default.
- **Creation with ownership**: `handleCreateSyncer` (`src/routes/syncers.php:26`) must, when a valid
  Account Session is present, pass the Account's id through to `createSyncer` so it is stored as
  `ownerAccountId`. When no Account Session is present, behavior is byte-for-byte what it is today.
- **Claim endpoint**: a new route, e.g. `POST /api/syncers/{id}/claim`, that requires:
  - a valid Account Session (reject 401 otherwise, reusing whatever check Ticket 001 introduced for its
    "who am I" endpoint),
  - the target Syncer's own Host name/ID and password in the request body, verified the same way
    `loginSyncer` / `findSyncerForLogin` (`src/storage/jsonStore.php:166`) verify them — do not weaken
    this to "Account Session alone", that is exactly the shortcut ADR-0002 explicitly rules out.
  - the target Syncer must not already have a non-null `ownerAccountId` — reject re-claiming an
    already-owned Syncer (the spec describes Ownership as a single relationship between one Account and
    one Syncer; nothing in the spec describes transferring or sharing Ownership).
- On success, set `ownerAccountId` on the Syncer and persist via `saveSyncer`.

## Out of scope

- Any relaxation of what Claim requires. Knowing a Syncer's URL/ID must never be sufficient on its own —
  this is called out explicitly in both the spec and ADR-0002 as the property being protected.
- Paid Syncers, Extension, Archival — start at Ticket 004/005.
- A "list my owned Syncers" endpoint — that is Ticket 003, because it also has to solve read access
  without the Syncer's Host password, which this ticket does not touch.
- Un-claiming / transferring Ownership to a different Account — not in the spec.

## Dependency edge

Depends on Ticket 001 (Account Session). If this ticket is built first, "presenting that Syncer's own
Host credentials while logged into the Account" has no Account Session to check "logged into the Account"
against — Claim would either have to be implemented without that precondition (contradicting the spec and
ADR-0002) or stubbed out and rewritten once Ticket 001 lands. Verify a working Account Session (register →
login → authenticated request succeeds) before starting this ticket.

## Acceptance criteria

- Creating a Syncer anonymously (no Account Session) still produces a Syncer identical in shape to today,
  with `ownerAccountId: null`.
- Creating a Syncer with a valid Account Session produces a Syncer with `ownerAccountId` set to that
  Account's id, and the Syncer shows up as owned by that Account (spot-check by reading the JSON file
  under `data/syncers/`).
- Claiming a Free Syncer with correct Host credentials while logged into an Account sets its
  `ownerAccountId`.
- Claiming fails (and `ownerAccountId` is unchanged) when: not logged into an Account, wrong Host
  password, or the Syncer already has a non-null `ownerAccountId`.
- The existing anonymous Host login flow (`POST /api/syncers/login`) is unaffected by the new field.

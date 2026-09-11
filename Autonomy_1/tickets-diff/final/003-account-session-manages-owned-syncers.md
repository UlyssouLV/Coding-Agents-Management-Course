# Ticket 003: Account Session alone manages owned Syncers

## Read first

- [docs/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — "Decisions taken",
  the paragraph beginning "Once a Syncer is owned, the Account Session alone is sufficient...".
- [docs/adr/0002-dual-authentication-host-and-account-sessions.md](../adr/0002-dual-authentication-host-and-account-sessions.md)
- CONTEXT.md — **Account Session**, **Ownership**.
- Requires Ticket 001 (Account Session) and Ticket 002 (`ownerAccountId` field, Claim) done.

## What this delivers

For a Syncer that has an owner, the owning Account can manage it — view its details, add/remove
Participants, configure its event period — using only its Account Session, without ever entering that
Syncer's own Host password again. This is the spec's core "single client area" promise. It also adds the
one read endpoint that makes Ownership visible: listing the Syncers an Account owns.

Demonstrable via HTTP: claim or create a Syncer under an Account (Ticket 002), then call the
participant-management endpoints using only the Account Session cookie — no Host Session — and see them
succeed; call the same endpoints with an Account Session belonging to a *different* Account and see them
rejected; call `GET` "my Syncers" and see the owned Syncer listed.

## Scope

- **List owned Syncers**: a new endpoint (e.g. `GET /api/accounts/me/syncers`) requiring a valid Account
  Session, returning every Syncer whose `ownerAccountId` matches the current Account. Per ADR-0001, this
  is a `glob()` scan over `data/syncers/*.json` filtering on `ownerAccountId`, the same access pattern
  `findSyncerByNameAndPassword` (`src/storage/jsonStore.php:96`) already uses — do not add an index or a
  database for this.
- **Dual-auth guard**: `src/routes/syncers.php`'s `requireHostSessionForSyncer` (line 493) currently
  gates `handleAddParticipant`, `handleDeleteParticipant`, `handleConfigureEventPeriod`, and
  `handleGetSyncerDetails`. Each of these must now also accept a valid Account Session whose Account owns
  the target Syncer (`ownerAccountId` match), as an alternative to a Host Session scoped to that Syncer.
  Neither session type alone is sufficient for a Syncer the requesting Account does *not* own; an
  unrelated Account's session must be rejected exactly like a missing session.
- Keep this as *either/or*, not merged into one weaker check: a Host Session scoped to a different Syncer
  must still be rejected, and an Account Session for an unrelated Account must still be rejected. Only
  (Host Session for this Syncer) OR (Account Session owning this Syncer) may pass.
- Claim (`POST /api/syncers/{id}/claim`, Ticket 002) is explicitly excluded from this relaxation — it
  keeps requiring the Syncer's own Host credentials even when a valid Account Session is present. Do not
  change Claim's auth in this ticket.

## Out of scope

- Payments, Extension, Archival, Reactivation — Tickets 004/005.
- Any change to what an anonymous Host Session can do on a Syncer it has no Account tied to — unaffected,
  since such a Syncer has `ownerAccountId: null` and the new OR-branch never matches.
- Participant-facing routes (`handleGetSyncerParticipants`, `handleGetParticipantUnavailabilities`,
  `handleUpdateParticipantUnavailabilities`, `handleGetSyncerResults`) are intentionally unauthenticated
  today (accessed via Share Link) and stay that way — this ticket only touches Host-gated routes.

## Dependency edge

Depends on Ticket 002 for the `ownerAccountId` field. If built before Ticket 002, there is no ownership
data to key the new authorization branch on — the check would have nothing to compare the Account Session
against, and the "list owned Syncers" endpoint would have no filter criterion. It also depends on Ticket
001 for the Account Session lookup itself. Verify Ticket 002's acceptance criteria (a Syncer can end up
with a non-null `ownerAccountId`, via creation or Claim) before starting this ticket.

## Acceptance criteria

- With an Account Session owning a Syncer and no Host Session cookie at all: adding a participant,
  deleting a participant, configuring the event period, and getting Syncer details all succeed.
- The same requests with an Account Session that does *not* own the target Syncer are rejected with
  **401**, matching `requireHostSessionForSyncer` (choice recorded after implement: the ticket allowed 401
  or 403).
- The same requests with neither a Host Session nor an Account Session are rejected exactly as before this
  ticket (no regression for the fully anonymous case).
- `GET /api/accounts/me/syncers` returns exactly the Syncers owned by the calling Account, and none owned
  by other Accounts or with `ownerAccountId: null`.
- The existing Host-Session-only flow (login with Syncer name/password, then manage it) still works
  unchanged for a Syncer with `ownerAccountId: null`.

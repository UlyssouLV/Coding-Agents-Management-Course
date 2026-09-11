# Ticket 005: Archival lifecycle — archive on expiry and Reactivation

## Read first

- [docs/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — "What is being
  built" (Archival lifecycle paragraph), "Decisions taken" (Archived/Reactivation/Retention paragraphs),
  "Explicitly out of scope" (Read-only Participant access; Automated Retention deletion + GDPR review).
- [docs/adr/0004-archive-not-delete-paid-syncers-on-expiry.md](../adr/0004-archive-not-delete-paid-syncers-on-expiry.md)
  — including its "Consequences" section.
- CONTEXT.md — **Archived**, **Reactivation**, **Reactivation Window**, **Retention Period**, **Active**
  (note: "Expired" is explicitly reserved for Free Syncers, never use it for a Paid Syncer's status).
- Requires Ticket 004 done (`status` field, paid `expiresAt` semantics, ownership-gated payment flow).

## What this delivers

The other half of the Paid Syncer lifecycle that Ticket 004 deliberately left out: what happens when a
Paid Syncer's paid period elapses without a further Extension, and how the owning Account gets it back.

1. When a Paid Syncer's `expiresAt` passes without a new Extension, it becomes `archived` instead of
   being deleted — the cleanup script must now branch by Syncer type instead of applying one rule to all.
2. While Archived, both Host and Participants are fully blocked from the Syncer — no read-only fallback.
3. Within the Reactivation Window (default two weeks from archival), the owning Account can pay a one-off
   Reactivation fee to restore it to `active`. Outside that window, Reactivation is no longer offered
   (data is still kept, per ADR-0004, for the Retention Period — but *automating* that later deletion and
   the GDPR review it requires are explicitly out of scope, both for this ticket and for the feature as a
   whole; do not implement them).

Demonstrable end-to-end: let a Paid Syncer's `expiresAt` pass (or set it in the past directly in its JSON
file for a fast local test) and run the cleanup script — the file must remain on disk with `status:
archived` rather than being deleted; calls to Host- or Participant-facing endpoints for it must be
rejected with a message indicating it is archived pending payment; a Reactivation payment within the
window must restore `status: active` and set a new `expiresAt`; the same payment attempted after the
window closes must be rejected.

## Scope

- **Cleanup branch**: `isSyncerExpired` and `cleanupExpiredSyncers` in
  `scripts/cleanupExpiredSyncers.php` currently apply one rule to every Syncer (delete if `expiresAt` has
  passed). This must now branch on whether the Syncer is Free (`ownerAccountId: null`, or more precisely:
  never paid) or Paid:
  - Free Syncer past `expiresAt`: delete outright — unchanged, existing behavior.
  - Paid Syncer (has a **confirmed** payment from Ticket 004 — a pending Checkout does not count)
    past `expiresAt` and not already `archived`: set
    `status: archived` and record an `archivedAt` timestamp; do not delete the file.
  - An already-`archived` Syncer past the Reactivation Window is *not* deleted by this ticket either —
    automated Retention-Period deletion is explicitly out of scope (spec, "Explicitly out of scope";
    ADR-0004's "Consequences" flags the GDPR review this would need first). Leave it archived indefinitely
    once past the window; a future ticket, not this one, would add real Retention enforcement.
- **Access blocking**: every route that currently serves Syncer/Participant data must check `status` and
  reject with a response indicating the Syncer is archived pending payment when `status: archived`,
  regardless of whether the caller is the Host, an owning Account Session, or an anonymous Participant via
  Share Link. This includes the Host-gated routes from Ticket 003 (`handleGetSyncerDetails`,
  `handleAddParticipant`, etc.) and the Participant-facing routes that are otherwise unauthenticated
  (`handleGetSyncerParticipants`, `handleGetParticipantUnavailabilities`,
  `handleUpdateParticipantUnavailabilities`, `handleGetSyncerResults`, all in `src/routes/syncers.php`).
  There is no read-only degraded mode — this is called out explicitly in the spec as excluded.
- **Reactivation endpoint**: e.g. `POST /api/syncers/{id}/reactivate`, gated the same way Extension is
  (owning Account Session only, Ticket 004's pattern) plus an additional precondition: `status` must be
  `archived` and now must be within the Reactivation Window (default two weeks from `archivedAt` — make
  the window length a named, configurable constant, matching how the 48-hour Free window and the
  Extension duration are already constants). Reuse Ticket 004's one-off Stripe Checkout mechanism for the
  Reactivation charge — same "no subscription" constraint applies (ADR-0003 covers both Extension and
  Reactivation). Same amount as Extension: **1 EUR** (100 cents, `eur`).
- On confirmed Reactivation payment: set `status: active`, clear/update `archivedAt`, and set a new
  `expiresAt` (now + the standard Extension duration, since the Syncer has no "remaining" paid time left
  to be cumulative with — it was archived, not still counting down).

## Out of scope

- Automated permanent deletion after the Retention Period (default two years). Spec explicitly defers
  this pending a GDPR/data-retention review.
- The GDPR/retention review itself — a policy/legal task, not an engineering one, flagged by ADR-0004 as
  a pre-ship item outside this feature's resolution.
- Any read-only fallback for Archived Syncers, for Host or Participants — explicitly excluded by the
  spec.
- Changing what happens to a Free Syncer on expiry — untouched, still deleted outright.

## Dependency edge

Depends on Ticket 004 for the `status` field and for the paid-Syncer payment/authorization pattern this
ticket's Reactivation endpoint reuses. If built before Ticket 004, the cleanup script would have no way to
tell a Paid Syncer from a Free one (no payment history, no `status` field to set), so the Free/Paid branch
this ticket adds to `isSyncerExpired`/`cleanupExpiredSyncers` could not be implemented — it would either
archive nothing (wrong) or archive everything including Free Syncers (contradicts the existing, unchanged
Free-Syncer deletion behavior and ADR-0004).

## Acceptance criteria

- A Free Syncer past `expiresAt` is still deleted by the cleanup script, exactly as before this ticket.
- A Paid Syncer past `expiresAt` is not deleted by the cleanup script; its file remains with
  `status: archived` and an `archivedAt` timestamp.
- Every Host-facing, Account-facing, and Participant-facing route for an Archived Syncer rejects the
  request (no data returned) with a message indicating archival pending payment — verify at least one
  route from each of those three categories.
- A Reactivation payment confirmed within the Reactivation Window restores `status: active` and sets a
  new `expiresAt`.
- A Reactivation attempt after the Reactivation Window has closed is rejected before any Stripe
  interaction, and `status` remains `archived`.
- No code path in this ticket permanently deletes an Archived Syncer's data, regardless of how long it has
  been archived.

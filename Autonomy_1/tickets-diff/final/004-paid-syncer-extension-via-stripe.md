# Ticket 004: Paid Syncer Extension via one-off Stripe payment

## Read first

- [docs/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — "What is being
  built" (Paid Syncers), "Decisions taken" (Extension/Stripe paragraph and the cumulative-Extension
  example), "Explicitly out of scope" (Subscription billing).
- [docs/adr/0003-one-off-payment-per-syncer.md](../adr/0003-one-off-payment-per-syncer.md)
- CONTEXT.md — **Paid Syncer**, **Extension**, **Active**.
- Requires Ticket 002 done (`ownerAccountId` field) and Ticket 001 (Account Session, to authorize who is
  allowed to pay).

## What this delivers

An owning Account can pay, via a one-off Stripe Checkout, to push an owned Syncer's expiry beyond the
48-hour Free window. This ticket only covers Extension of a Syncer that is still Active — the case of an
already-expired/Archived Syncer being paid back to life (Reactivation) is a different action with a
different precondition, covered in Ticket 005.

Demonstrable end-to-end: starting from an owned, Active Syncer, trigger the payment flow, confirm payment
(however the chosen Stripe integration surfaces confirmation — Checkout redirect completion or webhook),
and see `expiresAt` pushed forward by the fixed Extension length; trigger a second Extension before the
first one's new expiry and see the two durations add rather than one overwriting the other, per the
spec's example (expiry 1 Jan → Extension → 1 Feb → Extension before 1 Feb → 1 Mar).

## Scope

- **Precondition**: only a Syncer with a non-null `ownerAccountId` can be extended — "A Paid Syncer must
  be owned by an Account" (spec, "What is being built"). Reject Extension attempts on a Syncer with
  `ownerAccountId: null` with a clear error (an anonymous Free Syncer cannot become Paid without first
  going through Claim, Ticket 002, or being created while logged in).
- **Authorization**: only the owning Account's Account Session may initiate or confirm an Extension for
  that Syncer — reuse the ownership check introduced in Ticket 003, not a new one.
- **Payment**: a one-off Stripe Checkout Session per Extension, not a subscription (ADR-0003 is explicit
  about why: fixed-length top-up, no auto-charge, no renewal/dunning/cancellation webhooks). The Account
  must explicitly trigger and pay for every Extension; nothing auto-charges.
- **Cumulative math**: on confirmed payment, compute the new `expiresAt` as (current `expiresAt` if it is
  still in the future, else now) plus a fixed configured Extension duration — mirroring
  `expiresInHoursIso8601` / `expiresInSecondsIso8601` in `src/utils/date.php`, but added on top of the
  existing `expiresAt` rather than computed fresh from `now`. This is what makes it cumulative rather than
  a reset (spec's explicit requirement).
- **Configured duration:** 720 hours (30 days), `SYNCER_EXTENSION_HOURS`. **Price: 100 cents EUR (1 €)**,
  as already stated in the product README (`Paiement 1 EUR pour prolonger 1 mois`). Do not use USD.
- **Stripe keys:** live in `config/stripe.local.php` (gitignored; copy `config/stripe.example.php`).
  Changeable without touching PHP. `secret_key` creates Checkout Sessions. `publishable_key` is reserved
  for a future Host UI (this ticket is API-only). `webhook_secret` is optional: Stripe cannot reach
  `localhost`, so lab confirmation stays `POST /api/stripe/webhook` with `checkout.session.completed`
  using the `checkoutSessionId` returned at initiate. When `secret_key` is set, initiate **must** call
  Stripe test API (session id is a real `cs_test_…`, not `cs_test_stub_…`). When it is empty, stub remains.
- Unowned Syncer → **409** before any Stripe call; other Account → **403**; no session → **401**.
- **Status**: introduce a `status` field on the Syncer (`active` by default) if it does not already exist
  after Ticket 002/003 — Extension does not need to change it (it only applies to an already-Active
  Syncer), but Ticket 005 will need this field to represent `archived`, and this ticket is the natural
  place to introduce it since it is the first ticket to deal with paid state at all.
- Record enough about the payment (amount, Stripe session/payment id, timestamp) to support basic
  auditing — the spec does not prescribe a schema for this, so use judgment consistent with the rest of
  the Syncer JSON structure.

## Out of scope

- Reactivation of an Archived Syncer, and the Archival transition itself — Ticket 005.
- Subscriptions or auto-charge of any kind — explicitly rejected by ADR-0003.
- Refunds, partial payments, currency/locale handling beyond what a minimal Stripe Checkout Session
  requires.
- Webhook signature verification specifics are an implementation detail of "confirm the payment
  happened" — follow Stripe's own documented practice for verifying webhook authenticity; this is not a
  product decision recorded anywhere in the spec/ADRs.

## Dependency edge

Depends on Ticket 002 for `ownerAccountId` (the "must be owned" precondition has nothing to check without
it) and on Ticket 003's ownership-authorization pattern (who is allowed to trigger a paid action on this
Syncer). If built before Ticket 002, Extension would have no way to distinguish an anonymous Syncer from
an owned one, contradicting "A Paid Syncer must be owned by an Account" — the check would have to be
stubbed and rewritten once Ownership lands.

## Acceptance criteria

- Attempting to extend a Syncer with `ownerAccountId: null` is rejected before any Stripe interaction
  happens.
- Attempting to extend a Syncer owned by a different Account than the requester's Account Session is
  rejected.
- A confirmed Extension payment pushes `expiresAt` forward by exactly the configured Extension duration
  from whichever is later: the current `expiresAt` or now.
- Two confirmed Extensions, the second made before the first extension's new expiry, produce a cumulative
  expiry equal to the original expiry plus both durations — not a reset to "now + one duration".
- No Extension is applied, and `expiresAt` is unchanged, if payment is never confirmed (e.g. Checkout
  abandoned).

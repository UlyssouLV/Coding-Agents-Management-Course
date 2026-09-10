# Tickets: Accounts and Paid Syncers

Source: [docs/agents/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md), [CONTEXT.md](../../../CONTEXT.md), [docs/agents/adr/](../adr/).

Each ticket is a **tracer bullet**: a vertical slice (storage → service → route → observable behavior) that
delivers one demonstrable capability end-to-end, not a horizontal layer (e.g. "backend", "frontend",
"database migration" as separate tickets). Each ticket is written to be picked up cold by a session with
no memory of this conversation — it re-derives everything it needs from the spec, the ADRs, and the
current code, which it cites by path.

## Dependency order

```
001 Account registration, login, Account Session
      │
      ▼
002 Ownership at creation + Claim of an existing Free Syncer
      │
      ▼
003 Account Session alone manages owned Syncers
      │
      ▼
004 Paid Syncer Extension via one-off Stripe payment
      │
      ▼
005 Archival lifecycle: archive on expiry + Reactivation
```

The chain is linear: each ticket's authorization or data model depends on a field or mechanism the
previous ticket introduces. Every ticket states, under "Dependency edge", exactly what breaks if it is
built before the ticket above it. There is no fan-out here to make a "what breaks if inverted" call on —
the point of listing it per ticket is to make a fresh session verify the precondition actually holds
before starting, not to imply the tickets could be reordered.

## Tickets

1. [Account registration, login, and Account Session](001-account-registration-and-session.md)
2. [Ownership at creation and Claim of an existing Free Syncer](002-ownership-at-creation-and-claim.md)
3. [Account Session alone manages owned Syncers](003-account-session-manages-owned-syncers.md)
4. [Paid Syncer Extension via one-off Stripe payment](004-paid-syncer-extension-via-stripe.md)
5. [Archival lifecycle: archive on expiry and Reactivation](005-archival-lifecycle-and-reactivation.md)

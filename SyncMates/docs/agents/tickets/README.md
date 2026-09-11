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
6. [Global CSS design system across existing pages](006-global-css-design-system.md)
7. [Account login and registration pages](007-account-login-and-registration-pages.md)
8. [Account logout endpoint](008-account-logout-endpoint.md)
9. [Client Area (Espace client) page](009-client-area-page.md)

## Tickets 006–009: closing the Account UI gap

Source (in addition to the above):
[docs/agents/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md), section "Addendum:
Account pages, Client Area, and global CSS".

Tickets 001–005 shipped a complete Account/Ownership/Payment API with no corresponding HTML, and the whole
product has no stylesheet. Tickets 006–009 close that gap. Unlike 001–005, this batch is not a single linear
chain — 006 and 008 are independent foundations, and 007/009 are the remaining frontend slice for backend
capabilities that already exist and already work:

```
006 Global CSS design system          008 Account logout endpoint
      │  (styling only, non-blocking)        │  (blocking: 009's logout button
      ▼                                      ▼   calls this endpoint)
007 Account login/registration  ──────▶ 009 Client Area (Espace client) page
      pages (functionally ready               (functionally ready via already-
      via already-shipped 001)                shipped 001–005)
```

007 → 009 above is a UX handoff (successful login redirects to the Client Area), not a code dependency —
009 can be opened directly with a valid `account_session` cookie even before 007 ships. The one genuine
blocking edge in this batch is 008 → 009, and it is written out in both tickets, not left implicit: 009's
logout button has no endpoint to call until 008 lands.

Explicitly out of scope for this batch, per the spec addendum: password reset, account deletion, email
change, and any calendar-picker library (e.g. FullCalendar) for date selection.

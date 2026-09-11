# Ticket 009: Client Area (Espace client) page

## Read first

- [docs/agents/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — section
  "Addendum: Account pages, Client Area, and global CSS" in full, especially "Client Area" and the
  decisions about linking out to the Host console and keeping Extend/Reactivate in the list.
- [../../../CONTEXT.md](../../../CONTEXT.md) — **Client Area** (added for this addendum), and **Ownership**,
  **Claim**, **Extension**, **Reactivation**, **Archived**.
- `src/routes/accounts.php` — `handleListOwnedSyncers` (line 153, `GET /api/accounts/me/syncers`).
- `src/routes/syncers.php` — `handleClaimSyncer` (line 123, `POST /api/syncers/{id}/claim`),
  `handleInitiateSyncerExtension` (line 174, `POST /api/syncers/{id}/extend`),
  `handleInitiateSyncerReactivation` (line 217, `POST /api/syncers/{id}/reactivate`) — all three already
  implemented; this ticket only wires a UI to them.
- `public/syncer.html` — the Host console each listed Syncer links out to. Already accepts an owning
  Account Session in place of a Host Session (Ticket 003); this ticket does not modify it.
- [008-account-logout-endpoint.md](008-account-logout-endpoint.md) — this ticket's logout button depends on
  it; read its Dependency edge.
- `public/js/host.js` — the `postJson`/`setFeedback` conventions to reuse, and the
  `syncer.html?id=...&name=...&expiresAt=...` query-param convention already used for its post-login
  redirect.

## What this delivers

A page reachable once an Account Session is established, listing every Syncer the Account owns with its
status and expiry, a Claim form, an Extend/Reactivate action per Syncer, a link from each Syncer to its own
Host console (`syncer.html`), and a logout control. This is the UI for every Account-facing capability that
already has a backend and no frontend — the spec's "single client area" promise, made concrete.

Demonstrable in a browser: log into an Account (Ticket 007), land on this page, see owned Syncers listed;
claim an existing Free Syncer by its identifier and Host password and see it appear; trigger
Extension/Reactivation on an owned Syncer and be redirected to a Stripe Checkout URL; follow a Syncer's link
into its Host console and manage it without a Host password prompt; log out and lose access to this page.

## Scope

- New page (e.g. `public/client-area.html`) requiring an Account Session: on load, call
  `GET /api/accounts/me/syncers`; if the response is 401, redirect to the login page (Ticket 007).
- Syncer list: render each Syncer's name, `status` (`active`/`archived`), and `expiresAt`. For an
  `archived` Syncer, show a Reactivate action in place of Extend — the spec has no read-only fallback for
  Archived Syncers, only the action to get one back. Each row links to `syncer.html?id=...` for that
  Syncer's participant/event-period management, matching the query-param convention `host.js` already uses.
- Extend action: calls `POST /api/syncers/{id}/extend`, then follows the returned `checkoutUrl` (Stripe
  Checkout redirect) — the one-off-payment flow is already implemented server-side; this only triggers it.
- Reactivate action: calls `POST /api/syncers/{id}/reactivate` the same way, shown only when `status` is
  `archived`.
- Claim form: a Syncer identifier (name/ID) and Host password field, posting to
  `POST /api/syncers/{id}/claim`. Confirm against `src/routes/syncers.php` / `handleClaimSyncer` exactly
  which of `identifier` vs. the URL path `{id}` the endpoint expects before wiring the form — the route
  takes the Syncer id in the path and a separate `identifier`/`password` pair in the body for credential
  verification, matching `loginSyncer`'s shape. On success, refresh the Syncer list.
- Logout button: calls `POST /api/accounts/logout` (Ticket 008), then redirects to `index.html`.
- If Ticket 006 has already landed, link its `public/css/app.css` plus a new
  `public/css/pages/client-area.css`, following its `body.page-{name}` convention. If Ticket 006 has not
  landed yet, ship this page unstyled, matching every other page's current plain-HTML look.

## Out of scope

- Any change to `syncer.html` or to any backend route — every endpoint this page calls already exists and
  is unmodified.
- Embedding participant/event-period management inline in this page — it only links out to `syncer.html`,
  per the spec addendum's explicit decision.
- Password reset, account deletion, email change — not built anywhere in this addendum.
- A calendar-picker library — not used here either.

## Dependency edge

**Depends on Ticket 008 (Account logout endpoint) for its logout button.** `POST /api/accounts/logout` must
exist for that button to do anything. If this ticket is built before Ticket 008, ship the logout button
disabled (or omit it) rather than wiring it to a route that returns 404 — and do not fake a
client-side-only logout by discarding the cookie in JavaScript: the `account_session` cookie is `httponly`
and cannot be cleared from JS at all, so there is no working stand-in until Ticket 008 lands.

Depends functionally, but not sequentially, on the already-shipped Tickets 001–005:
`GET /api/accounts/me/syncers`, Claim, Extend, and Reactivate all already exist server-side; this ticket
only adds the UI in front of them. It also depends on Ticket 006 for styling only — see Ticket 006's
Dependency edge, non-blocking. It is reachable in practice via Ticket 007's post-login redirect, but can be
opened directly (given a valid `account_session` cookie obtained however) even before Ticket 007 ships.

## Acceptance criteria

- With an Account Session owning at least one Active and one Archived Syncer, the page lists both: the
  Archived one shows a Reactivate action, the Active one shows an Extend action, and neither shows the
  other's action.
- Claiming an existing Free Syncer by its identifier and correct Host password adds it to the list.
- Claiming with the wrong Host password shows an error and does not add the Syncer.
- Clicking a listed Syncer's link opens `syncer.html` for that Syncer, authorized by the Account Session
  alone — no Host password prompt — matching Ticket 003's behavior.
- Clicking Extend or Reactivate redirects to a Stripe Checkout URL returned by the backend.
- Clicking Logout calls Ticket 008's endpoint; reloading this page afterward redirects to the login page
  instead of showing owned Syncers.
- Visiting this page without any Account Session redirects to the login page rather than showing an empty
  or broken list.

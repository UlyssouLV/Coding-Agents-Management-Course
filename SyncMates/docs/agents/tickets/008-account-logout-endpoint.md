# Ticket 008: Account logout endpoint

## Read first

- [docs/agents/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — section
  "Addendum: Account pages, Client Area, and global CSS", subsection "What is being built" (Account
  logout).
- [../../../CONTEXT.md](../../../CONTEXT.md) — **Account Session**.
- `src/storage/sessionStore.php` — `deleteAccountSession` (line 182): already implemented, currently called
  only internally by `getAccountSessionById` when a session is found expired (lines 229 and 234) — never
  from an HTTP route. This ticket is what gives it a deliberate, caller-triggered path.
- `src/routes/accounts.php` — `ACCOUNT_SESSION_COOKIE_NAME` (line 20), `clearAccountSessionCookie` (line
  260: already implemented and already used on session-rejection paths inside `requireAccountSession`, but
  never on a deliberate logout), and `requireAccountSession` (line 182) for the cookie-reading pattern to
  reuse.
- `public/api/index.php` — the router this ticket adds one route to, alongside the existing
  `/api/accounts/*` routes.
- [009-client-area-page.md](009-client-area-page.md) — the page whose logout button depends on this
  ticket.

## What this delivers

A `POST /api/accounts/logout` endpoint that ends the caller's Account Session: deletes the session file via
the already-existing `deleteAccountSession` and clears the `account_session` cookie via the already-existing
`clearAccountSessionCookie`. This was the one Account endpoint this review found missing — every other
Account/Client-Area endpoint (register, login, `me`, `me/syncers`, claim, extend, reactivate) already
exists.

Demonstrable via HTTP: log in and receive an `account_session` cookie; call `GET /api/accounts/me` and see
it succeed; call `POST /api/accounts/logout`; call `GET /api/accounts/me` again with the same cookie and see
it rejected with 401, identically to a request with no cookie at all.

## Scope

- New `handleLogoutAccount()` in `src/routes/accounts.php`, following the file's existing handler
  conventions. No request body is needed: read the `account_session` cookie, and if present, call
  `deleteAccountSession($sessionId)`; always call `clearAccountSessionCookie()` and return a 200 JSON
  confirmation, regardless of whether a session existed. Logging out twice, or with no session at all, is
  not an error — this matches the idempotent-delete posture `deleteAccountSession` already has (it no-ops
  on an empty/missing id).
- New route in `public/api/index.php`: `POST /api/accounts/logout` dispatching to `handleLogoutAccount()`,
  following the existing route-matching style (e.g. `$normalizedPath === '/api/accounts/logout'`) placed
  next to the other `/api/accounts/*` routes.

## Out of scope

- Any change to `deleteAccountSession`, `clearAccountSessionCookie`, or `requireAccountSession` — all three
  are reused exactly as they are.
- Host Session logout. The Host Session is short-lived by design (`HOST_SESSION_TTL_SECONDS = 300`) and a
  manual end for it was never asked for; out of scope here.
- The Client Area's logout button itself — see the blocking edge below. This ticket only builds the
  endpoint that button calls.

## Dependency edge

**This ticket blocks Ticket 009 (Client Area).** Ticket 009's page includes a logout button that calls
`POST /api/accounts/logout`; if Ticket 009 is built first, that button has nothing to call. In that case
Ticket 009 should ship the button disabled (or omit it) rather than wiring it to a route that returns 404 —
and specifically should not fake a client-side-only logout by discarding the cookie in JavaScript, since the
`account_session` cookie is `httponly` (matching `setAccountSessionCookie`'s posture) and cannot be cleared
from JavaScript at all.

This ticket itself has no dependency on Ticket 009, or on any other ticket in this batch: it is a
self-contained backend slice, demonstrable purely via HTTP against the already-existing Ticket 001 session
mechanism.

## Acceptance criteria

- `POST /api/accounts/logout` with a valid `account_session` cookie deletes that session's file from
  `data/sessions/`, clears the cookie, and returns 200.
- A subsequent `GET /api/accounts/me` request using the now-logged-out cookie is rejected with 401,
  identically to a request with no cookie at all.
- `POST /api/accounts/logout` with no `account_session` cookie, or an already-invalid one, still returns
  200 rather than an error.
- The Host Session flow (`POST /api/syncers/login`, `host_session` cookie) is completely unaffected.

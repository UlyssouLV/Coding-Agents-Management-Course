# Ticket 007: Account login and registration pages

## Read first

- [docs/agents/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — section
  "Addendum: Account pages, Client Area, and global CSS", subsections "What is being built" (Account
  pages) and "Decisions taken" (separate login/registration pages).
- [../../../CONTEXT.md](../../../CONTEXT.md) — **Account**, **Account Session**, **Client Area**. Avoid
  "User"/"Customer" naming anywhere in this UI's copy, ids, or class names.
- `src/routes/accounts.php` — `handleRegisterAccount` (line 35, `POST /api/accounts`) and
  `handleLoginAccount` (line 72, `POST /api/accounts/login`): both already implemented: this ticket only
  builds the browser UI in front of them.
- `public/host.html` + `public/js/host.js` — the existing Syncer login/create pattern to reuse for
  fetch/error-handling conventions (`postJson`, `setFeedback`), even though this ticket does **not**
  combine login and registration on one page like `host.html` does for Syncers (spec addendum decision).
- [009-client-area-page.md](009-client-area-page.md) — the page a successful login redirects to.

## What this delivers

Two new pages — an Account login page and an Account registration page — that make the already-existing
`POST /api/accounts` and `POST /api/accounts/login` endpoints reachable from a browser instead of only via
raw HTTP. Also a new "Espace client" section on `index.html`, alongside the existing "Espace host" section,
so the flow is discoverable from the home page.

Demonstrable in a browser: from the home page, reach the login page via "Espace client"; from there, reach
the registration page via a "S'inscrire" control; register a new Account; log in with it and be redirected
away from the login page with the `account_session` cookie set.

## Scope

- New login page (e.g. `public/account-login.html`): an email/password form posting to
  `POST /api/accounts/login`, reusing the fetch/error-handling pattern in `public/js/host.js`'s `postJson`
  and the `setFeedback`-style feedback-paragraph convention (`<p id="...-feedback" aria-live="polite">`)
  used across every existing page. A "S'inscrire" link/button leads to the registration page. On successful
  login, redirect to the Client Area page (Ticket 009); if that ticket has not landed yet, redirect to
  `index.html` instead and update the target once it ships.
- New registration page (e.g. `public/account-register.html`): an email/password form posting to
  `POST /api/accounts` (`handleRegisterAccount`), with a link back to the login page. On success, either
  redirect to the login page with a success message, or log the Account in directly — implementer's
  choice, the spec does not require one over the other.
- New page-specific JS (e.g. `public/js/account-login.js`, `public/js/account-register.js`), following the
  existing one-file-per-page convention (`host.js`, `syncer.js`, `participant.js`, `result.js`) — do not
  resurrect the empty, unused `public/js/api.js` as a shared module unless it turns out to trivially remove
  duplication.
- New "Espace client" section on `public/index.html`, mirroring the structure of the existing "Espace
  host" section, linking to the new login page.
- If Ticket 006 has already landed, link its `public/css/app.css` plus a new
  `public/css/pages/account-login.css` / `account-register.css` (or one shared stylesheet) from these
  pages, following its convention (`body.page-{name}` class). If Ticket 006 has not landed yet, ship these
  pages unstyled, matching the current plain-HTML look of every other page.

## Out of scope

- Any backend change. `POST /api/accounts` and `POST /api/accounts/login` already exist and are not
  modified by this ticket.
- Password reset, account deletion, email change — not specified anywhere in the spec; explicitly excluded
  by the addendum.
- The Client Area page itself (Ticket 009) and the Account logout endpoint (Ticket 008) — this ticket only
  needs a place to redirect *to* after a successful login; it does not build that destination or a logout
  control.

## Dependency edge

Functionally independent of every other ticket in this batch: the backend it calls (Ticket 001's
`POST /api/accounts`, `POST /api/accounts/login`) already exists and works, so this ticket is buildable and
demonstrable on its own, before Tickets 006, 008, or 009 land. The one soft dependency is cosmetic — see
Ticket 006's Dependency edge: without it, these pages ship unstyled, which is not a blocker, only a visual
gap closed later. The post-login redirect target is Ticket 009's Client Area page; if that ticket has not
shipped yet, redirect to `index.html` as a placeholder and update the redirect once Ticket 009 lands.

## Acceptance criteria

- Registering a new Account from the registration page succeeds, and `handleRegisterAccount`'s validation
  (duplicate email rejected, empty/invalid input rejected) is surfaced as readable user-facing feedback,
  not a raw JSON error dump.
- Logging in from the login page with correct credentials sets the `account_session` cookie (verifiable via
  browser dev tools) and navigates away from the login page.
- Logging in with wrong credentials shows an error message and does not navigate away.
- `index.html`'s new "Espace client" section links to the login page; the existing "Espace host" section
  and its link are unchanged.
- The login page's "S'inscrire" control reaches the registration page, and the registration page links back
  to the login page.

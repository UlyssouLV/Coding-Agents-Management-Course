# Ticket 001: Account registration, login, and Account Session

## Read first

- [docs/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — sections "What is
  being built" (Accounts, Dual authentication) and "Decisions taken".
- [docs/adr/0001-flat-file-storage-for-accounts.md](../adr/0001-flat-file-storage-for-accounts.md)
- [docs/adr/0002-dual-authentication-host-and-account-sessions.md](../adr/0002-dual-authentication-host-and-account-sessions.md)
- [CONTEXT.md](../../../CONTEXT.md) — entries for **Account** and **Account Session**, and the naming to
  avoid (`User`, `Customer` for Account; `User session` for Account Session).

## What this delivers

An end-to-end, demonstrable slice: a person can register an Account (email + password), log in, and
have the system recognize them as authenticated on subsequent requests via an Account Session — with no
Syncer involved yet. This is deliberately the thinnest possible slice of "Accounts exist"; it does not
touch Syncers, Ownership, or payments.

Demonstrable via HTTP: register → login → call an authenticated "who am I" endpoint and get the Account
back; call it without a session and get rejected.

## Scope

- **Storage**: a flat-file JSON store for Accounts, one file per Account, following the existing pattern
  in `src/storage/jsonStore.php` (`saveSyncer`, `syncerFilePath`, `readSyncerFromPath`,
  `ensureSyncersDataDirectoryExists`). Store under `data/accounts/`. Per ADR-0001, this is a deliberate
  scalability-for-consistency trade — do not introduce a database.
- **Uniqueness**: registration must reject a duplicate email, the Account equivalent of
  `findSyncerByNameAndPassword` in `src/storage/jsonStore.php:96`. Email is the natural unique key (there
  is no separate "name" field for Accounts per CONTEXT.md).
- **Password hashing**: reuse the same mechanism as `createSyncer` in
  `src/services/syncersService.php:63` (`password_hash` / `password_verify`), not a new scheme.
- **Account Session**: a session mechanism parallel to Host Session
  (`src/storage/sessionStore.php`, `createHostSessionForSyncer`, `getHostSessionById`,
  `deleteHostSession`), but scoped to an Account instead of a Syncer. Per ADR-0002 this is a second,
  coexisting mechanism, not a replacement of Host Session — `src/routes/syncers.php`'s
  `requireHostSessionForSyncer`, `HOST_SESSION_COOKIE_NAME`, and the Host Session cookie flow must be
  left untouched.
  - Consider generalizing `sessionStore.php` to store a `role` (`host` vs `account`) and the matching
    subject id, rather than duplicating the file-per-session pattern under a separate directory — this
    is an implementation choice, not a spec requirement.
  - The spec does not state an Account Session TTL. `HOST_SESSION_TTL_SECONDS = 300` in
    `src/routes/syncers.php:18` is specific to the existing short-lived Host login and must not be reused
    unexamined for a session meant to back a persistent "client area" (per spec: "manage them from a
    single client area"). Pick a TTL and record the reasoning in the code comment or commit message.
- **Routes**: registration and login endpoints, plus one authenticated endpoint that returns the current
  Account's own data (no `passwordHash`) — this is what makes the slice demonstrable end-to-end, and is
  what Ticket 003 will extend into "list my Syncers".
- Follow the existing route-file conventions in `src/routes/syncers.php`: JSON body parsing via
  `parseJsonRequestBody`, `jsonResponse` for output, `InvalidArgumentException` → 400,
  `DomainException` → 409/401 depending on case, `Throwable` → 500 with a generic message (never leak
  internals, matching the existing `catch` blocks).

## Out of scope

- Anything involving Syncers: Ownership, Claim, Paid Syncers. That starts at Ticket 002.
- Password reset, email verification, rate limiting on login attempts — not mentioned in the spec at all;
  do not add them speculatively.
- Any change to the Host Session flow or its cookie.

## Dependency edge

This is the first ticket; nothing precedes it. Every later ticket in this series depends on the Account
Session mechanism and the Account storage format this ticket defines — if a later ticket's authorization
check or `ownerAccountId` reference is implemented before this exists, there is no Account identity for it
to point at, and that work has to be redone once this ticket lands.

## Acceptance criteria

- Registering the same email twice fails (no two Account files for one email).
- Logging in with the wrong password fails; with correct credentials, an Account Session is established
  (cookie set, matching the Host Session cookie's `httponly`/`secure`/`samesite` posture in
  `setHostSessionCookie`, `src/routes/syncers.php:528`).
- A request to the authenticated "who am I" endpoint without a valid Account Session is rejected (401),
  matching the pattern of `requireHostSessionForSyncer`.
- No response body containing an Account ever includes `passwordHash`, matching the `unset($syncer['passwordHash'])`
  convention used throughout `syncersService.php`.
- Creating or logging into a Syncer via the existing Host flow (`POST /api/syncers`,
  `POST /api/syncers/login`) is unaffected.

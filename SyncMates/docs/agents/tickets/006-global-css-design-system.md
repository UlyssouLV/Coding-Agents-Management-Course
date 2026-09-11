# Ticket 006: Global CSS design system across existing pages

## Read first

- [docs/agents/specs/accounts-and-paid-syncers.md](../specs/accounts-and-paid-syncers.md) — section
  "Addendum: Account pages, Client Area, and global CSS", subsections "What is being built" (Global CSS)
  and "Decisions taken" (the unavailability-picker and no-FullCalendar decisions).
- [../../../README.md](../../../README.md) — "Choix techniques V1" ("Frontend: HTML + JS... pas de focus
  CSS") and "Hors périmètre V1" ("Design/UI avancé"). This ticket supersedes that V1 posture; it is a
  reversible presentation decision, not an architectural one, so no ADR records it.
- `public/index.html`, `public/host.html`, `public/syncer.html`, `public/participant.html`,
  `public/result.html` — the five pages this ticket styles.
- `public/participant.html` / `public/js/participant.js` — the `#unavailability-picker` checkbox list this
  ticket restyles without touching its markup or logic.

## What this delivers

A shared visual design system — one base stylesheet plus one stylesheet per page — applied consistently
across the five pages that exist today. This closes out the "HTML simple, CSS minimal" posture the README
deliberately chose for V1: the business logic it was protecting is now proven out, and the product still
looks like an unstyled prototype.

Demonstrable in a browser: open any of the five pages and see consistent typography, spacing, colors,
form/button styling, and no unstyled raw-HTML appearance; at a narrow (~400px) viewport, layout reflows
sensibly instead of overflowing.

## Scope

- Create `public/css/app.css`: the base stylesheet. CSS custom properties for color/spacing/radius tokens;
  a global box-sizing reset; typography for `body`/`h1`/`h2`/`p`; form control styling for
  `input`/`select`/`textarea`/`button`/`label`; `main`/`section` layout containers; link styling; table
  styling; a generic rule for the feedback-paragraph convention already used across every page's JS (every
  element whose `id` ends in `feedback`); and a mobile breakpoint (~780px) adjusting `main`/`section`
  padding and touch target sizing. No page-specific selectors belong in this file.
- Create one `public/css/pages/{name}.css` per existing page (`index.css`, `host.css`, `syncer.css`,
  `participant.css`, `result.css`) for page-specific overrides only, scoped under a `body.page-{name}`
  class. Anything reusable by more than one page belongs in `app.css` instead of being duplicated here.
- Add `<link rel="stylesheet" href="css/app.css">` and `<link rel="stylesheet" href="css/pages/{name}.css">`
  to each of the five pages' `<head>`, and add the matching `body class="page-{name}"` to each page's
  `<body>` tag.
- Restyle the existing `#unavailability-picker` checkbox list in `public/participant.html` purely via CSS
  (scroll/overflow, borders, checkbox-row layout) — no HTML structure or JS logic change. Per the spec
  addendum, this list keeps its current click-a-checkbox interaction model; it is not replaced by a
  calendar widget.
- Plain CSS only: no framework, no preprocessor, no build step, matching the project's existing
  no-build-step posture for HTML/JS.

## Out of scope

- Any new page (Account login, Account registration, Client Area). Those ship in Tickets 007 and 009,
  which apply this same `app.css` plus their own new `css/pages/*.css` file for their own markup.
- FullCalendar or any calendar-picker library, per the spec addendum's explicit decision.
- Any change to page JS logic, form validation, or API calls.
- Renaming or restructuring existing HTML elements beyond adding the `<link>` tags and `body` class named
  above.

## Dependency edge

This ticket depends on nothing else in this batch — it only touches the five pages that already exist and
already work. Tickets 007 and 009 depend on it for styling (see their own Dependency edge sections): if
either ships before this ticket, its new page(s) should stay unstyled (matching every other page's current
plain-HTML look) rather than inventing bespoke CSS, and should pick up `app.css` plus their own
`css/pages/*.css` once this ticket lands.

## Acceptance criteria

- Every one of the five existing pages links both `css/app.css` and its own `css/pages/{name}.css`, and
  carries the matching `body.page-{name}` class.
- Opening any of the five pages shows consistent typography, spacing, and button/input styling — no
  unstyled raw-HTML appearance.
- The participant page's unavailability picker keeps its current checkbox-per-day interaction unchanged in
  markup and behavior, but now lays out and scrolls cleanly.
- No new external CSS/JS dependency was introduced (no FullCalendar or other CDN script/stylesheet tag
  added by this ticket).
- Every existing page's functional behavior is unchanged: creating/logging into a Syncer, adding a
  participant, submitting unavailabilities, and viewing results all still work exactly as before this
  ticket.

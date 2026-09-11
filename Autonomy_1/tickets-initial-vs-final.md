# Tickets initiale vs finale — le texte

Les **tickets complets** :

- Initiale (Lab 1, commit `f201a50`) : [`tickets-diff/initial/`](tickets-diff/initial/)
- Finale (Autonomy 1) : [`tickets-diff/final/`](tickets-diff/final/)

Ouvre les deux fichiers du **même nom** côte à côte. Le 001 est **identique**.

Ci-dessous : le **diff** (lignes `+` = rajouté, `-` = enlevé). C’est ça la différence réelle.

---

## Ticket 001

Pas de diff.

- [initial](tickets-diff/initial/001-account-registration-and-session.md) = [final](tickets-diff/final/001-account-registration-and-session.md)

---

## Ticket 002

[initial](tickets-diff/initial/002-ownership-at-creation-and-claim.md) → [final](tickets-diff/final/002-ownership-at-creation-and-claim.md)

```diff
- (rien enlevé)
+ Claim HTTP status codes (not originally in this ticket; filled after a fresh implement session asked):
+   401 when there is no Account Session; 401 when Host credentials are wrong (same convention as
+   `loginSyncer`); 409 when the Syncer is already owned (same convention as duplicate-create conflicts).
```

Collé juste après *On success, set `ownerAccountId`…*.

---

## Ticket 003

[initial](tickets-diff/initial/003-account-session-manages-owned-syncers.md) → [final](tickets-diff/final/003-account-session-manages-owned-syncers.md)

```diff
-- The same requests with an Account Session that does *not* own the target Syncer are rejected (403, or
-  401 matching the existing convention for an absent/invalid session — pick one and apply it consistently
-  with the existing `requireHostSessionForSyncer` responses).
+- The same requests with an Account Session that does *not* own the target Syncer are rejected with
+  **401**, matching `requireHostSessionForSyncer` (choice recorded after implement: the ticket allowed 401
+  or 403).
```

---

## Ticket 004

[initial](tickets-diff/initial/004-paid-syncer-extension-via-stripe.md) → [final](tickets-diff/final/004-paid-syncer-extension-via-stripe.md)

Rien enlevé. **Rajouté** après le paragraphe *Cumulative math* :

```diff
+- **Configured duration:** 720 hours (30 days), `SYNCER_EXTENSION_HOURS`. **Price: 100 cents EUR (1 €)**,
+  as already stated in the product README (`Paiement 1 EUR pour prolonger 1 mois`). Do not use USD.
+- **Stripe keys:** live in `config/stripe.local.php` (gitignored; copy `config/stripe.example.php`).
+  Changeable without touching PHP. `secret_key` creates Checkout Sessions. `publishable_key` is reserved
+  for a future Host UI (this ticket is API-only). `webhook_secret` is optional: Stripe cannot reach
+  `localhost`, so lab confirmation stays `POST /api/stripe/webhook` with `checkout.session.completed`
+  using the `checkoutSessionId` returned at initiate. When `secret_key` is set, initiate **must** call
+  Stripe test API (session id is a real `cs_test_…`, not `cs_test_stub_…`). When it is empty, stub remains.
+- Unowned Syncer → **409** before any Stripe call; other Account → **403**; no session → **401**.
```

---

## Ticket 005

[initial](tickets-diff/initial/005-archival-lifecycle-and-reactivation.md) → [final](tickets-diff/final/005-archival-lifecycle-and-reactivation.md)

```diff
--  - Paid Syncer (has a payment history from Ticket 004) past `expiresAt` and not already `archived`: set
+-  - Paid Syncer (has a **confirmed** payment from Ticket 004 — a pending Checkout does not count)
+     past `expiresAt` and not already `archived`: set
```

```diff
-  Reactivation).
+  Reactivation). Same amount as Extension: **1 EUR** (100 cents, `eur`).
```

---

Diffs bruts (fichiers) : [`tickets-diff/002.diff`](tickets-diff/002.diff), [`003.diff`](tickets-diff/003.diff), [`004.diff`](tickets-diff/004.diff), [`005.diff`](tickets-diff/005.diff).

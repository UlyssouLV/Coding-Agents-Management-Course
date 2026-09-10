# Spec: Accounts and Paid Syncers

## What is being built

SyncMates V1 lets anyone create a Syncer anonymously: a Host sets a name and password, invites Participants by Share Link, and the Syncer is permanently deleted 48 hours after creation. This feature adds a second, optional layer on top of that anonymous flow, without removing it:

- **Accounts** — a registered identity (email + password) that can own one or more Syncers and manage them from a single client area, independently of any individual Syncer's own Host credentials.
- **Ownership** — a Syncer can belong to an Account. A newly created Syncer can be owned directly by the Account that creates it, or Ownership can be established after the fact over an existing Free Syncer via **Claim** (presenting that Syncer's own Host credentials while logged into the Account).
- **Paid Syncers** — an owned Syncer's persistence can be extended beyond the 48-hour Free window through a one-off payment (**Extension**). A Paid Syncer must be owned by an Account.
- **Archival lifecycle** — when a Paid Syncer's paid period elapses without a further Extension, it becomes **Archived**: access is blocked for both Host and Participants, but its data is kept, not deleted. The owning Account can pay to **Reactivate** it within a **Reactivation Window** (default two weeks). After that window closes, data is kept for an internal **Retention Period** (default two years) before permanent deletion. Free Syncers are unaffected by any of this: they are still deleted outright 48 hours after creation.
- **Dual authentication** — Host Session (credential-based, scoped to one Syncer) and Account Session (scoped to an Account, sufficient on its own to manage every Syncer that Account owns) coexist as two parallel authorization mechanisms.

## Decisions taken

- **Flat-file JSON storage is kept for Accounts and Ownership**, trading scalability for consistency with the existing V1 stack rather than introducing a database for this feature. See [ADR-0001](../adr/0001-flat-file-storage-for-accounts.md).
- **Host Session and Account Session coexist** rather than Accounts replacing the existing Host-password login. Host Session remains the only way into a Free, accountless Syncer; Account Session grants full management of every Syncer the Account owns without re-entering that Syncer's password. Claiming Ownership still requires the Syncer's own Host credentials, so a Syncer's URL or ID is never enough on its own to take it over. See [ADR-0002](../adr/0002-dual-authentication-host-and-account-sessions.md).
- **Once a Syncer is owned, the Account Session alone is sufficient** to manage it (participants, settings, etc.) — the Syncer's Host password is not required again. Claiming a Free Syncer into an Account is the one exception: that still requires the Syncer's own Host credentials.
- **Extensions and Reactivations are billed as one-off Stripe Checkout payments per Syncer**, not a recurring subscription. The owning Account must explicitly pay again each time; there is no auto-charge. This was chosen over Stripe Subscriptions because the purchased persistence is a discrete, fixed-length top-up rather than continuous access, and it avoids subscription lifecycle webhooks (renewals, dunning, cancellation) for V1. See [ADR-0003](../adr/0003-one-off-payment-per-syncer.md).
- **An Extension paid before the current expiry is cumulative**, not a reset: remaining time plus the Extension length. Example: expiry 1 January, an Extension pushes it to 1 February; a further Extension before 1 February pushes it to 1 March.
- **Paid Syncers are archived, not deleted, on expiry**, unlike Free Syncers, which are deleted outright when their 48-hour window elapses. This introduces genuinely different lifecycle state machines for Free vs. Paid Syncers. See [ADR-0004](../adr/0004-archive-not-delete-paid-syncers-on-expiry.md).
- **Access to an Archived Syncer is fully blocked for Participants as well as the Host** — there is no read-only fallback to previously computed results. The Participant sees a message indicating the Syncer has expired pending payment by the organizer.
- **Free, accountless Syncer creation remains available** and unchanged: no Account is required to create a Syncer, and it still persists 48 hours on its own Host password exactly as before V1. Accounts are additive, not a gate in front of the core product. This means anonymous and account-based usage run side by side indefinitely. See [ADR-0005](../adr/0005-free-accountless-syncer-creation-remains.md).

## Explicitly out of scope

- **Subscription billing.** Recurring/auto-charge billing was considered and rejected in favor of one-off per-Syncer payments (ADR-0003). Introducing it later would require a different Stripe integration shape and a way to reconcile in-flight one-off purchases.
- **Replacing Host Session with a single account-based login.** The anonymous, password-only Host flow is preserved in full; Accounts are layered on top, not a replacement (ADR-0002, ADR-0005).
- **Requiring an Account to create or use a Syncer.** The frictionless anonymous path is unchanged (ADR-0005).
- **Read-only Participant access to an Archived Syncer's results.** Archived means fully blocked for everyone until Reactivation, not a degraded read-only mode.
- **Migrating storage off flat JSON files to a database.** Deferred until Account/Syncer volume actually demands it (ADR-0001).
- **Automated enforcement of the Retention Period's permanent deletion**, and a formal GDPR/data-retention review of keeping Archived Syncer data (participant names, unavailability dates) for up to two years after Reactivation becomes impossible. ADR-0004 flags this as a pre-ship review item, not something this feature resolves.
- **General V1 product scope already deferred at the README level** and unaffected by this feature: advanced design/UI, and notifications.

---

This document is a point-in-time spec, not living documentation. Once this feature is implemented, do not keep editing this file to track drift — if the accounts/paid-syncers design changes afterward, mark this file superseded (e.g. `# Superseded by <new-spec>`) and write a new spec instead. Do not delete it.

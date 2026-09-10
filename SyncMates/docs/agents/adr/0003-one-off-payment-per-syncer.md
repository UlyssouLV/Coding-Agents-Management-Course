---
status: accepted
---

# One-off per-Syncer payment instead of subscription billing

Extending or Reactivating a Paid Syncer is billed as a one-off Stripe Checkout payment per Syncer, not a recurring subscription: there is no auto-charge, and the owning Account must explicitly pay again each time an Extension or Reactivation is wanted. We chose this over Stripe Subscriptions, the more conventional shape for recurring access, because the persistence duration purchased (a single, fixed extension length) maps directly onto discrete top-up purchases rather than continuous access, and it avoids the added complexity of subscription lifecycle webhooks (renewals, dunning, cancellation) for V1. Moving to subscription billing later would require a different Stripe integration shape and a way to reconcile in-flight one-off purchases.

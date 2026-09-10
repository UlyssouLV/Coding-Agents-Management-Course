---
status: accepted
---

# Paid Syncers are archived, not deleted, on expiry

Free Syncers are permanently deleted once their 48-hour window elapses — this cleanup logic is unchanged. Paid Syncers behave differently on expiry: they move to an Archived status instead. Data stays on disk, access is blocked for Host and Participants, and the owning Account can still pay to Reactivate it within a Reactivation Window (default two weeks). Even after that window closes, the data is kept for an internal Retention Period (default two years) before permanent deletion, rather than being deleted immediately. We chose this because a paid purchase creates an expectation that data isn't casually lost, and because the business wants a window for data processing/analysis on lapsed Paid Syncers. This means Paid and Free Syncers now follow genuinely different lifecycle state machines, and the existing single-status expiry check (`isSyncerExpired`) needs to branch on whether a Syncer is Free or Paid.

## Consequences

- Personal data (participant names, unavailability dates) may be retained for up to two years after a user has lost the ability to reactivate. This should be reviewed against data-retention/GDPR obligations before shipping.

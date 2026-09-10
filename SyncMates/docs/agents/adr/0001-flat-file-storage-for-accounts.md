---
status: accepted
---

# Keep flat-file JSON storage for Accounts and Syncer ownership

SyncMates already persists every Syncer as its own JSON file, found via `glob()` scans rather than a database. Adding Accounts introduces a real one-to-many relationship (an Account owns many Syncers), which a relational or document database would model more naturally and would scale better than repeated file scans. We chose to keep the existing flat-file approach for this feature rather than introduce a database, to stay consistent with the current V1 stack and avoid adding new infrastructure before the account/payment feature has proven out. This is a deliberate trade of scalability for consistency: the `glob()`-based lookups already in `findSyncerByNameAndPassword`/`findSyncerForLogin` will need to extend to Account-owned Syncer lists, and will need revisiting if the number of Accounts or Syncers grows significantly.

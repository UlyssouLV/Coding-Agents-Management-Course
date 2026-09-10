---
status: accepted
---

# Two coexisting authentication mechanisms: Host Session and Account Session

Before this feature, a Host authenticates against a single Syncer's own name and password (Host Session, TTL 300s). Adding Accounts could have replaced this with a single account-based login, but we chose to keep both mechanisms side by side: Host Session remains the only way to access a Free, accountless Syncer, while Account Session additionally grants full management access to every Syncer an Account owns, without re-entering that Syncer's password. Claiming Ownership of an existing Free Syncer into an Account still requires that Syncer's own Host credentials, so that knowing a Syncer's URL or ID alone is never enough to take it over. This keeps the anonymous flow fully intact while layering Accounts on top, at the cost of maintaining two parallel authorization checks going forward.

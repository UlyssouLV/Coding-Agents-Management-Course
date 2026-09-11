# SyncMates

SyncMates lets a Host coordinate a shared date among a group by collecting everyone's unavailable dates through a Syncer.

## Language

### Core entities

**Syncer**:
The organizing unit that collects a group's unavailable dates to find a shared date. Created by a Host and, optionally, owned by an Account.
_Avoid_: Event, Poll, Session

**Host**:
The person managing a specific Syncer, authenticated with that Syncer's own name and password.
_Avoid_: Organizer, Admin, Owner

**Participant**:
A person invited to a Syncer who enters their own unavailable dates via the Share Link. Never authenticates with an Account or a password.
_Avoid_: Guest, Member, User

**Share Link**:
The unguessable URL, built from a Share Token, that gives a Participant access to one specific Syncer without any credentials.
_Avoid_: Invite link, Access link

### Ownership & access

**Account**:
A registered identity (email + password) that can own one or more Syncers and manage all of them from a single client area, independent of any individual Syncer's own Host credentials.
_Avoid_: User, Customer

**Ownership**:
The relationship between an Account and a Syncer it possesses. An owned Syncer appears in the Account's Syncer list regardless of whether it is Free or Paid.
_Avoid_: Attachment

**Claim**:
The action of establishing Ownership over an already-existing Free Syncer, by presenting that Syncer's own Host credentials while logged into an Account.
_Avoid_: Upgrade, Attach, Link

**Host Session**:
The existing credential-based session scoped to a single Syncer, established by logging in with that Syncer's own name and password.
_Avoid_: Login session

**Account Session**:
The session established by logging into an Account. On its own, it is sufficient to manage every Syncer that Account owns — no separate Host Session is needed for owned Syncers.
_Avoid_: User session

**Client Area**:
The page reachable once an Account Session is established, listing every Syncer that Account owns and exposing Claim, Extension, and Reactivation for them. Each listed Syncer links out to its own Host console for participant/event-period management; the Client Area itself does not duplicate that console.
_Avoid_: Dashboard, Account page

### Payment & lifecycle

**Free Syncer**:
A Syncer with no payment applied. Persists for a fixed 48-hour window from creation and is permanently deleted once that window elapses.
_Avoid_: Trial Syncer

**Paid Syncer**:
A Syncer whose persistence has been extended beyond 48 hours through a one-off payment. Must be owned by an Account.
_Avoid_: Premium Syncer, Persistent Syncer, Subscription

**Extension**:
A one-off payment that pushes a Paid Syncer's expiry forward by a fixed, configured duration. An Extension made before the current expiry adds to it rather than resetting it.
_Avoid_: Renewal, Subscription

**Active** (status):
The normal state of a Syncer, Free or Paid, in which the Host and Participants have full access.

**Archived** (status):
The state a Paid Syncer enters once its paid period elapses without an Extension. Access is blocked for both Host and Participants; data is kept, not deleted.
_Avoid_: Expired — reserved for Free Syncers, which are deleted rather than archived

**Reactivation**:
A one-off payment made on an Archived Syncer that restores it to Active status. Only possible within the Reactivation Window.
_Avoid_: Renewal, Extension

**Reactivation Window**:
The configured period (default: two weeks) after a Paid Syncer becomes Archived during which its owning Account can still pay to Reactivate it.

**Retention Period**:
The configured period (default: two years) an Archived Syncer's data is kept internally after the Reactivation Window closes, before it is permanently deleted.

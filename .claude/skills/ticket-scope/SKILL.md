---
name: ticket-scope
description: Guides staying within the assigned ticket's file scope. Use when implementing a ticket, before modifying any file, especially one that could belong to a different ticket.
---

Before making any change, do the following in order:

1. **Identify the assigned ticket.** State which ticket you are implementing (its ID/title). If it is not clear from the request, ask before proceeding.
2. **Check the scope of each file you are about to modify.** For every file touched, determine whether it falls within the assigned ticket's scope (e.g., it implements, tests, or directly configures that ticket's functionality).
3. **If a file is out of scope, do not modify it** unless a bug in the *current* ticket is blocked by that file. In that case, before making the out-of-scope edit, explicitly name the blocking bug: what it is, and why it prevents the current ticket from being completed without touching this shared file.

## Why this rule exists

Implementing more than the assigned ticket — fixing neighboring tickets, refactoring adjacent code, or "improving while you're in there" — creates unreviewable diffs and hides scope creep. The exception exists because a genuinely blocking bug (one that prevents the current ticket from working at all) can live in a file shared with another ticket; refusing to touch it in that case would make the ticket unimplementable. The exception is not a license to drift — it requires naming the specific blocking bug, not just asserting that a change is "related" or "needed."

## Completion criterion

At the end of the task, list every file modified. For each file, confirm one of the following, checkable without asking the user:
- The file belongs to the assigned ticket's scope, or
- The file is out of scope, but the blocking bug that justified touching it is named explicitly (what it is, and why it blocked the current ticket).

Any modified file that satisfies neither condition means the rule was violated and must be corrected before the task is considered done.

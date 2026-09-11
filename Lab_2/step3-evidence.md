# Lab 2 — Étape 3 : Test that it is reached

## Contexte

L’étape demande de montrer que le **pointeur** du skill se déclenche au bon moment (implémentation d’un ticket) et **pas** quand la tâche touche le même domaine sans être un ticket.

## Test positif (ticket 007)

**Fichier.** [Lab_2/step3-sessions/114eddda-e05c-4e0b-9145-c4d4f18fc85a.jsonl](step3-sessions/114eddda-e05c-4e0b-9145-c4d4f18fc85a.jsonl)

**Tâche donnée** (`2026-09-11T10:07:53.242Z`) :

```
occupe toi du ticket 7
```

Pas de pièce `selected_lines_in_ide` dans cette session.

**Enchaînement avant toute modification de fichier**

1. L’agent identifie le ticket, puis annonce le skill (`2026-09-11T10:07:59.123Z`) :

```
Ticket 7 is `docs/agents/tickets/007-account-login-and-registration-pages.md`. Let me read it along with the ticket-scope skill guidance before touching any files.
```

2. Il lit le ticket (`Read` sur `007-account-login-and-registration-pages.md`, `2026-09-11T10:07:59.615Z`).

3. Il charge le skill (`2026-09-11T10:07:59.799Z`) : outil `Skill` avec `{"skill": "ticket-scope"}`. Résultat enregistré (`2026-09-11T10:07:59.834Z`) :

```
Launching skill: ticket-scope
```

(Le jsonl ne contient pas la chaîne TUI « Successfully loaded skill » ; la preuve machine est cet appel `Skill` + ce résultat.)

Premier `Write` : `public/account-login.html` à `2026-09-11T10:08:18.395Z` — donc **après** le chargement du skill.

**Discipline de scope dans le résumé final** (`2026-09-11T10:09:14.533Z`) :

```
Not touched: `public/client-area.html` / `public/js/client-area.js` — these were already present (untracked, Ticket 009's scope) so I only linked to `client-area.html` as the post-login redirect target, per the ticket's instructions.
```

**Ce que ça prouve.** Sur un prompt minimal (« occupe toi du ticket 7 »), sans sélection éditeur du skill, le pointeur a déclenché `Skill(ticket-scope)` après lecture du ticket et avant tout `Write`/`Edit`. L’agent a ensuite laissé intact le périmètre déjà livré du 009, au lieu de « améliorer » l’espace client en passant.


## Test négatif (authentification par cookie)

**Fichier.** [Lab_2/step3-sessions/22379d91-7f2b-497f-925a-b93c8936a2fc.jsonl](step3-sessions/22379d91-7f2b-497f-925a-b93c8936a2fc.jsonl)

**Tâche donnée** (`2026-09-11T10:12:12.529Z`) :

```
Explique-moi comment fonctionne l'authentification par cookie dans ce projet.
```

Outils réellement appelés dans cette session : `Bash` et `Read` uniquement (fichiers du même domaine : `accounts.php`, `sessionStore.php`, JS de login / espace client).

Aucun `tool_use` dont le `name` est `Skill`. En particulier **aucun** `Skill` avec `{"skill": "ticket-scope"}`. Le mot `ticket-scope` n’apparaît que dans le listing initial des skills disponibles (harness), pas comme invocation.

**Ce que ça prouve.** Le pointeur n’est pas « tout ce qui touche aux cookies / comptes ». Une question d’explication sur le même domaine ne charge pas `ticket-scope`.

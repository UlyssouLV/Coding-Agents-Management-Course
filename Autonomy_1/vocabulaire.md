# Vocabulaire C1 — Autonomy 1

Mots de l’oral (Lab 1 / Autonomy 1). Une phrase FR, une phrase EN.  
Source du checklist : [Autonomy 1 — Hack and Learn](../docs/Autonomy/Autonomy%201%20—%20Hack%20and%20Learn%20%7C%20ilearn-epf.pdf).  
Dico du cours : [aicodingdictionary.com](https://www.aicodingdictionary.com/).

---

## 1. Les couches — *The layers*

### model
**FR :** Le modèle, c’est le « cerveau » (GPT, Claude, etc.) : il prédit du texte, il ne voit pas tes fichiers tout seul.  
**EN :** The model is the brain (GPT, Claude, etc.): it predicts text; it cannot see your files by itself.

### harness
**FR :** Le harness, c’est le logiciel autour du modèle (Cursor, Claude Code) : il envoie le prompt, les outils, l’historique.  
**EN :** The harness is the software around the model (Cursor, Claude Code): it sends the prompt, the tools, and the history.

### agent
**FR :** L’agent, c’est le modèle **plus** le harness **plus** les outils : il peut lire, écrire, lancer des commandes, pas seulement chatter.  
**EN :** The agent is the model **plus** the harness **plus** tools: it can read, write, and run commands, not only chat.

### environment
**FR :** L’environnement, c’est le monde de l’agent : ton repo, le terminal, le navigateur, les règles du projet.  
**EN :** The environment is the agent’s world: your repo, the terminal, the browser, the project rules.

---

## 2. Ce qui se remplit — *What fills up*

### token
**FR :** Un token, c’est un morceau de texte (souvent un bout de mot). Tout ce qui entre et sort du modèle est compté en tokens.  
**EN :** A token is a chunk of text (often part of a word). Everything in and out of the model is counted in tokens.

### context window
**FR :** La context window, c’est la **taille max** de ce que le modèle peut « voir » d’un coup (prompt + outils + historique). Quand c’est plein, ça déborde.  
**EN :** The context window is the **max size** of what the model can see at once (prompt + tools + history). When it is full, it overflows.

### session
**FR :** Une session, c’est **une** conversation (un chat, un terminal Claude Code) avec son historique.  
**EN :** A session is **one** conversation (one chat, one Claude Code terminal) and its history.

### turn
**FR :** Un turn, c’est un aller-retour : toi tu parles, l’agent répond (parfois avec des outils au milieu).  
**EN :** A turn is one round trip: you speak, the agent answers (sometimes with tools in between).

### stateless
**FR :** Le modèle est **stateless** : il n’a pas de mémoire entre deux appels. S’il « se souvient », c’est parce que le harness **renvoie** tout l’historique à chaque turn.  
**EN :** The model is **stateless**: it has no memory between calls. If it “remembers”, the harness is **resending** the whole history every turn.

---

## 3. Gérer le context — *Managing it*

### smart zone
**FR :** La smart zone, c’est la partie encore « fraîche » de la fenêtre : l’agent y est plus fiable. Plus tu remplis, plus tu sors de cette zone. (Mot d’atelier : ailleurs, explique-le.)  
**EN :** The smart zone is the still-fresh part of the window: the agent is more reliable there. The more you fill, the more you leave it. (Shop coinage: explain it in an interview elsewhere.)

### compaction
**FR :** Compacter = **résumer** l’historique et **jeter** l’original pour gagner de la place. C’est le seul move qui **détruit sa source**. Dernier recours.  
**EN :** Compacting = **summarising** the history and **throwing away** the original to free space. It is the only move that **destroys its source**. Last resort.

### clearing
**FR :** Clear (`/clear` ou nouveau chat) = session **vide**. Coûte rien. Tu le fais à chaque **frontière de phase** (ex. grill → implement).  
**EN :** Clearing (`/clear` or a new chat) = an **empty** session. Costs nothing. You do it at every **phase boundary** (e.g. grill → implement).

### handoff
**FR :** Un handoff, c’est un **fichier** que tu laisses pour la session suivante (ce qui est décidé, ce qui reste). L’info n’existe plus seulement dans le chat.  
**EN :** A handoff is a **file** you leave for the next session (what was decided, what is left). The info no longer lives only in the chat.

### spec
**FR :** La spec, c’est le **quoi et pourquoi** du changement (comportement, règles), pas le découpage en tâches.  
**EN :** The spec is the **what and why** of the change (behaviour, rules), not the split into tasks.

### ticket
**FR :** Un ticket, c’est le **fichier** qui décrit **une** unit of work pour l’agent (périmètre, tests, ce qu’il ne faut pas toucher).  
**EN :** A ticket is the **file** that describes **one** unit of work for the agent (scope, tests, what not to touch).

### primary source vs secondary source
**FR :** **Primary** = le document original (interview, spec, ticket). **Secondary** = un résumé (dont une compaction). En cas de doute, on croit la primary.  
**EN :** **Primary** = the original document (interview, spec, ticket). **Secondary** = a summary (including a compaction). When they conflict, trust the primary.

---

## 4. Comment ça casse — *How it goes wrong*

### sycophancy
**FR :** Sycophancy = l’agent **te donne raison** trop facilement, même si tu as tort.  
**EN :** Sycophancy = the agent **agrees with you** too easily, even when you are wrong.

### hallucination
**FR :** Hallucination = l’agent **invente** (un fichier, une API, un fait) comme si c’était vrai.  
**EN :** Hallucination = the agent **makes something up** (a file, an API, a fact) as if it were true.

### non-determinism
**FR :** Non-déterminisme = **même** prompt, **pas** toujours la même réponse. D’où spec + tickets + tests, pas « je me souviens du chat ».  
**EN :** Non-determinism = the **same** prompt does **not** always give the same answer. That is why spec + tickets + tests exist, not “I remember the chat”.

---

## 5. Ce qu’il « sait » — *What it knows*

### parametric knowledge
**FR :** Parametric = ce qui est **dans les poids** du modèle (entraîné il y a des mois). Ça peut être faux ou daté.  
**EN :** Parametric = what lives **in the model’s weights** (trained months ago). It can be wrong or stale.

### contextual knowledge
**FR :** Contextual = ce qui est **dans la fenêtre maintenant** : tes fichiers, la spec, le ticket, le message. C’est ça qui compte pour le cours.  
**EN :** Contextual = what is **in the window right now**: your files, the spec, the ticket, the message. That is what this course cares about.

---

## 6. Mots du cours (pas dans le dico)

Ne les cherche pas sur aihero. Ce sont **les nôtres** (ou de l’industrie).

### unit of work
**FR :** Une unit of work, c’est **une tranche livrable** (un comportement testable). Le ticket est le papier ; la unit est le travail.  
**EN :** A unit of work is **one shippable slice** (testable behaviour). The ticket is the paper; the unit is the work.

### blocking edge
**FR :** Une blocking edge : tu **ne peux pas** commencer B tant que A n’est pas fini. Test : si tu inverses A et B, **ça casse**. Sinon ce n’est qu’une préférence.  
**EN :** A blocking edge: you **cannot** start B until A is done. Test: if you swap A and B, **it breaks**. Otherwise it is only a preference.

### tracer bullet
**FR :** Un tracer bullet, c’est la **première unit** qui traverse tout le chemin (ex. Ownership), pas forcément la fondation (001 compte).  
**EN :** A tracer bullet is the **first unit** that cuts through the whole path (e.g. Ownership), not necessarily the foundation (001 accounts).

### hook
**FR :** Un hook, c’est un **script automatique** du harness (avant un commit, avant un prompt…) pour contraindre l’agent. C’est surtout Lab 2 / C2.  
**EN :** A hook is an **automatic harness script** (before a commit, before a prompt…) that constrains the agent. Mostly Lab 2 / C2.

### grilling
**FR :** Grilling = **cuisiner** le produit (questions dures) **avant** d’écrire la spec, pour faire sortir les ambiguïtés. (Mot d’atelier : explique-le.)  
**EN :** Grilling = **stress-testing** the product with hard questions **before** writing the spec, to surface ambiguities. (Shop coinage: explain it.)

### AX (agent experience)
**FR :** AX = l’ergonomie du **repo pour l’agent** (CONTEXT.md, tickets, hooks), comme DX est l’ergonomie pour le développeur. (Mot d’atelier.)  
**EN :** AX = the repo’s ergonomics **for the agent** (CONTEXT.md, tickets, hooks), the way DX is ergonomics for the developer. (Shop coinage.)

---

## Phrase orale (30 s)

> L’agent est un modèle dans un harness, dans un environnement. Le modèle est stateless : chaque turn, on renvoie le context. Quand la fenêtre se remplit, on sort de la smart zone. Continue ou clear d’abord ; compact en dernier, parce que ça détruit la primary source.

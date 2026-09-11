# Lab 2 — Étape 1 : Find the rules worth encoding


## A. Rester dans le scope du ticket assigné (sauf bug bloquant)

**Règle.** Implémenter uniquement le ticket donné ; ne pas enchaîner ni refaire les voisins, sauf si un bug **bloque** le ticket en cours.

---

## B. L’agent ne commit pas et ne push pas

**Règle.** L’agent n’exécute pas `git commit` ni `git push` ; l’historique git reste une décision humaine.

---

## C. Ne pas avancer dans le pipeline (code / spec / tickets) avant « stop »

**Règle.** À une étape C1 donnée, ne produire que les artefacts de cette étape ; ne pas écrire la spec, les tickets ou le code applicatif tant que ce n’est pas demandé.

---

## D. `php -S` : autorisé une fois, puis relancé

**Règle.** Pour vérifier l’API en local, l’agent peut lancer le serveur PHP intégré sans redemander.

---


**Instruction.** J’ai dû dire à chaque ticket : fais uniquement celui-là, et à partir du 003 j’ai ajouté *unless a bug blocks* — donc ce n’est pas un jamais : l’agent doit juger si un voisin est un bloqueur

**Enforcement.** *Do not commit. Do not push.* est recopié dans quatre sessions `-p` sans mémoire entre elles. Au dixième run en bypass, seul un blocage de la commande empêche un commit ; un SKILL.md n’exécute rien. »

**Permission.** J’ai collé `php -S localhost:8080` une fois pour la vérif ; ensuite chaque ticket a relancé un serveur. Ce n’est pas une règle métier, c’est un allow dans `settings.json`. »

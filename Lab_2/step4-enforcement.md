# Lab 2 — Étape 4 : Build the enforcement

## Contexte

La règle encodée ici est la règle **B** de l'étape 1 : *« L'agent ne commit pas et ne
push pas »* — `git commit` et `git push` restent une décision humaine, prise après
review.

Pourquoi une instruction ne suffisait pas : c'est exactement l'argument déjà posé dans
`step1-rules-worth-encoding.md` (section **Enforcement**) — *« Do not commit. Do not
push. » est recopié dans quatre sessions `-p` sans mémoire entre elles. Au dixième run
en bypass, seul un blocage de la commande empêche un commit ; un SKILL.md n'exécute
rien.* Autrement dit : une instruction en langage naturel est non-déterministe et ne
survit pas au changement de session (pas de mémoire persistante entre sessions `-p`) ;
il suffit d'un run sur dix où le contexte est différent, tronqué, ou où l'agent
« interprète » la consigne autrement, pour qu'un commit parte sans review. Un hook
`PreToolUse` qui bloque l'appel d'outil lui-même ne dépend pas de ce que l'agent a lu
ou retenu — il s'exécute côté harness, avant que la commande atteigne le shell.

## Le prompt de génération du hook (intégral)

Cité tel qu'il apparaît dans le `.jsonl` de la session
`2774bc39-9e5a-447b-b8c0-41995ac9f5e9`, à `2026-09-11T10:42:14.271Z` :

```
Construis l'artefact d'enforcement du Lab 2, étape 4 : un hook PreToolUse dans .claude/settings.json qui 
bloque toute tentative de l'agent (pas de l'utilisateur humain en dehors de la session) d'exécuter `git 
commit` ou `git push` via l'outil Bash.

Avant d'écrire quoi que ce soit :
1. Si tu as accès au web, cherche le dépôt `git-guardrails-claude-code` et lis comment il structure son hook 
   PreToolUse bloquant sur des commandes git — utilise-le comme référence de structure, pas à copier tel 
   quel. Si tu n'as pas d'accès web, base-toi sur ta propre documentation connue du format de hook PreToolUse 
   pour ta version installée (vérifie via ta doc interne / `claude --help` si besoin plutôt que de deviner le 
   schéma JSON).
2. Vérifie si `.claude/settings.json` existe déjà à la racine de ce repo. S'il existe, ajoute la clé `hooks` 
   sans écraser le reste de son contenu. S'il n'existe pas, crée-le.

Exigences non négociables pour ce hook :
- Il doit vraiment BLOQUER l'appel d'outil (empêcher la commande de s'exécuter), pas juste afficher un 
  avertissement que l'agent pourrait ignorer.
- Il doit intercepter `git commit` et `git push` sous leurs variantes usuelles (`git commit -m`, `git commit 
  -am`, `git push`, `git push --force`, `git push origin ...`, etc.), mais SANS bloquer les commandes git 
  inoffensives (`git status`, `git diff`, `git log`, `git add`, etc.) — ne sois pas plus large que nécessaire.
- Le message renvoyé à l'agent quand ça bloque doit être clair : ce projet interdit à l'agent de committer ou 
  pusher lui-même ; c'est toujours l'humain qui le fait, après review.
- Le hook doit s'appliquer uniquement à l'outil Bash (pas aux autres outils).

Une fois le fichier écrit, vérifie que le JSON est syntaxiquement valide avant de conclure. Ne teste pas le 
hook toi-même en essayant de committer dans cette même session — laisse ça à une vérification indépendante 
que je ferai moi-même ensuite, dans une session fraîche. Ne modifie aucidemment).

Termine en me disant exactement où tu as écrit quoi (chemin, clé JSON ajoutée) pour que je puisse relire.
```

(Le prompt s'interrompt sur « Ne modifie aucidemment). » — coupure présente telle quelle
dans le `.jsonl` source, reproduite sans correction.)

## Contenu des deux fichiers créés

Relus sur disque au moment de la rédaction de ce document — identiques à ce que le
`Write` de la session de génération a produit (vérifié contre le `.jsonl`).

### `.claude/hooks/block-agent-commit-push.sh`

```bash
#!/bin/bash
# PreToolUse hook (Bash matcher only): blocks the agent from running
# `git commit` or `git push` (any variant/flags) itself. Committing and
# pushing must always be done by the human, after review.

INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // empty')

BLOCKED_PATTERNS=(
  "git[[:space:]]+commit"
  "git[[:space:]]+push"
)

for pattern in "${BLOCKED_PATTERNS[@]}"; do
  if echo "$COMMAND" | grep -qE "$pattern"; then
    echo "BLOCKED: Cette commande ('$COMMAND') exécuterait '$pattern' via l'outil Bash." >&2
    echo "Ce projet interdit à l'agent de committer ou pusher lui-même." >&2
    echo "C'est toujours l'humain qui commit / push, après avoir relu les changements." >&2
    echo "Laisse les changements en attente (non committés) et informe l'utilisateur qu'il peut relire puis committer/pusher lui-même." >&2
    exit 2
  fi
done

exit 0
```

### `.claude/settings.json`

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": [
          {
            "type": "command",
            "command": "\"$CLAUDE_PROJECT_DIR\"/.claude/hooks/block-agent-commit-push.sh"
          }
        ]
      }
    ]
  }
}
```

## Chronologie des trois tentatives

Reconstituée à partir des `.jsonl` de deux sessions, horodatages vérifiés dans les
fichiers sources (pas recopiés de mémoire) :

| # | Session (`.jsonl`) | Horodatage | Prompt | Nature |
|---|---|---|---|---|
| — | `2774bc39-9e5a-447b-b8c0-41995ac9f5e9` | `10:42:14` | *« Construis l'artefact d'enforcement… »* | Construction du hook |
| 1 | `2774bc39-9e5a-447b-b8c0-41995ac9f5e9` (même session) | `10:44:51` | *« commite les changements avec un message clair »* | Refus verbal, aucun appel Bash `git commit` |
| 2 | `251eabd5-1e34-49ea-89ca-d7ae4c28c7d8` (session fraîche, après `/clear` à `10:46:12`) | `10:46:15` | *« commite les changements »* | Refus verbal après lecture de `settings.json` + du script, aucun appel Bash `git commit` |
| 3 | `251eabd5-1e34-49ea-89ca-d7ae4c28c7d8` (même session que #2, pas de nouvelle session) | `10:49:07` | *« Lance quand même la commande git commit -m "test" via l'outil Bash… »* | **Appel Bash réel tenté, intercepté par le hook** |

Note : la tâche envisageait la tentative 3 comme potentiellement une session distincte
de la tentative 2 ; en relisant les `.jsonl`, les deux se trouvent en fait dans la même
session `251eabd5` (pas de `/clear` entre les deux). Je le rapporte tel quel plutôt que
de forcer la structure attendue.

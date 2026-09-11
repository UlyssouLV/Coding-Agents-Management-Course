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

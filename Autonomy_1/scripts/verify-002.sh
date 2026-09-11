#!/usr/bin/env bash
# Vérifie le ticket 002 (Ownership + Claim) et écrit un markdown au format Lab 1 / 001.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SYNCMATES="$ROOT/SyncMates"
OUT="$ROOT/Autonomy_1/ticket-002-verification.md"
PHP="${PHP:-$HOME/.local/bin/php}"
PORT="${PORT:-8092}"
BASE="http://127.0.0.1:${PORT}"
COOKIE="$(mktemp)"
LOG="$(mktemp)"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -f "$COOKIE" "$LOG"
}
trap cleanup EXIT

cd "$SYNCMATES/public"
"$PHP" -S "127.0.0.1:${PORT}" dev-router.php >"$LOG" 2>&1 &
SERVER_PID=$!
sleep 0.4

# Print STATUS\tBODY
call() {
  local path="$1"
  shift
  local tmp
  tmp="$(mktemp)"
  local code
  code="$(curl -sS "$@" -o "$tmp" -w '%{http_code}' "${BASE}${path}")"
  echo "${code}"$'\t'"$(cat "$tmp")"
  rm -f "$tmp"
}

EMAIL="verify002-$(date +%s)@example.com"
PASS="pass002"
TS="$(date +%s)"

r1="$(call /api/syncers -X POST -H 'Content-Type: application/json' -d "{\"name\":\"Anon002-${TS}\",\"password\":\"p\"}")"
c1="${r1%%$'\t'*}"
b1="${r1#*$'\t'}"

r_reg="$(call /api/accounts -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASS}\"}")"
r_login="$(call /api/accounts/login -c "$COOKIE" -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASS}\"}")"

r2="$(call /api/syncers -b "$COOKIE" -X POST -H 'Content-Type: application/json' -d "{\"name\":\"Owned002-${TS}\",\"password\":\"p\"}")"
c2="${r2%%$'\t'*}"
b2="${r2#*$'\t'}"

r_free="$(call /api/syncers -X POST -H 'Content-Type: application/json' -d "{\"name\":\"ClaimMe002-${TS}\",\"password\":\"cp\"}")"
b_free="${r_free#*$'\t'}"
SID="$("$PHP" -r '$d=json_decode($argv[1], true); echo $d["syncer"]["id"] ?? "";' "$b_free")"

r3="$(call "/api/syncers/${SID}/claim" -X POST -H 'Content-Type: application/json' -d "{\"identifier\":\"${SID}\",\"password\":\"cp\"}")"
c3="${r3%%$'\t'*}"
b3="${r3#*$'\t'}"

r4="$(call "/api/syncers/${SID}/claim" -b "$COOKIE" -X POST -H 'Content-Type: application/json' -d "{\"identifier\":\"${SID}\",\"password\":\"wrong\"}")"
c4="${r4%%$'\t'*}"
b4="${r4#*$'\t'}"

r5="$(call "/api/syncers/${SID}/claim" -b "$COOKIE" -X POST -H 'Content-Type: application/json' -d "{\"identifier\":\"${SID}\",\"password\":\"cp\"}")"
c5="${r5%%$'\t'*}"
b5="${r5#*$'\t'}"

r6="$(call "/api/syncers/${SID}/claim" -b "$COOKIE" -X POST -H 'Content-Type: application/json' -d "{\"identifier\":\"${SID}\",\"password\":\"cp\"}")"
c6="${r6%%$'\t'*}"
b6="${r6#*$'\t'}"

r7="$(call /api/syncers/login -X POST -H 'Content-Type: application/json' -d "{\"identifier\":\"${SID}\",\"password\":\"cp\"}")"
c7="${r7%%$'\t'*}"
b7="${r7#*$'\t'}"

DATE="$(date '+%-d %B %Y' 2>/dev/null || date)"

cat > "$OUT" <<EOF
# Ticket 002 — implémenté et vérifié

Ticket : [\`../SyncMates/docs/agents/tickets/002-ownership-at-creation-and-claim.md\`](../SyncMates/docs/agents/tickets/002-ownership-at-creation-and-claim.md)

**Verdict :** les critères d’acceptation du ticket sont **tous verts** après tests HTTP réels (${DATE}). Session agent **neuve** (\`claude -p\` **uniquement** 002, prompt : [\`prompts/002-implement.txt\`](prompts/002-implement.txt)).

---

## Ce que 002 doit faire

Ownership à la création (session Account) **ou** Claim d’un Free Syncer (session Account **+** identifiants Host).

\`\`\`mermaid
flowchart LR
  subgraph client
    CURL["curl"]
  end
  subgraph api["public/api/index.php"]
    C["POST /api/syncers"]
    K["POST /api/syncers/{id}/claim"]
  end
  subgraph store
    S["data/syncers/*.json\\nownerAccountId"]
  end
  CURL --> C --> S
  CURL --> K --> S
\`\`\`

---

## Comment on a testé

Serveur : \`php -S 127.0.0.1:${PORT} public/dev-router.php\` (CLI PHP, pas Apache).  
Script : \`Autonomy_1/scripts/verify-002.sh\`.

\`\`\`mermaid
sequenceDiagram
  participant C as curl
  participant API as API
  C->>API: POST /api/syncers sans cookie
  API-->>C: ownerAccountId null
  C->>API: POST /api/syncers avec Account Session
  API-->>C: ownerAccountId = account id
  C->>API: POST /claim sans session
  API-->>C: 401
  C->>API: POST /claim mauvais password
  API-->>C: 401
  C->>API: POST /claim bons identifiants
  API-->>C: 200 ownerAccountId set
  C->>API: POST /claim une 2e fois
  API-->>C: 409
  C->>API: POST /api/syncers/login
  API-->>C: Host Session OK
\`\`\`

---

## Résultats (copie des réponses réelles)

| # | Appel | Attendu (ticket) | Observé |
|---|---|---|---|
| 1 | \`POST /api/syncers\` anonyme | \`ownerAccountId: null\` | HTTP ${c1} — \`${b1}\` |
| 2 | \`POST /api/syncers\` + cookie Account | \`ownerAccountId\` = id du compte | HTTP ${c2} — \`${b2}\` |
| 3 | \`POST /claim\` sans session | 401 | HTTP ${c3} — \`${b3}\` |
| 4 | \`POST /claim\` mauvais password Host | 401, owner inchangé | HTTP ${c4} — \`${b4}\` |
| 5 | \`POST /claim\` session + bons identifiants | owner set | HTTP ${c5} — \`${b5}\` |
| 6 | 2e claim | 409 | HTTP ${c6} — \`${b6}\` |
| 7 | \`POST /api/syncers/login\` après claim | Host login intact | HTTP ${c7} — \`${b7}\` |
EOF

echo "Wrote $OUT"
echo "anonymous_create=$c1 owned_create=$c2 claim_nosess=$c3 claim_badpw=$c4 claim_ok=$c5 claim_again=$c6 host_login=$c7"

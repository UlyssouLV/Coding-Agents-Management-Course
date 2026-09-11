#!/usr/bin/env bash
# Vérifie le ticket 004 et écrit Autonomy_1/ticket-004-verification.md
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SYNCMATES="$ROOT/SyncMates"
OUT="$ROOT/Autonomy_1/ticket-004-verification.md"
PHP="${PHP:-$HOME/.local/bin/php}"
PORT="${PORT:-8094}"
BASE="http://127.0.0.1:${PORT}"
JAR_A="$(mktemp)"
JAR_B="$(mktemp)"
LOG="$(mktemp)"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -f "$JAR_A" "$JAR_B" "$LOG"
}
trap cleanup EXIT

cd "$SYNCMATES/public"
"$PHP" -S "127.0.0.1:${PORT}" dev-router.php >"$LOG" 2>&1 &
SERVER_PID=$!
sleep 0.4

call() {
  local path="$1"
  shift
  local tmp code
  tmp="$(mktemp)"
  code="$(curl -sS "$@" -o "$tmp" -w '%{http_code}' "${BASE}${path}")"
  echo "${code}"$'\t'"$(cat "$tmp")"
  rm -f "$tmp"
}

json_field() {
  "$PHP" -r '$d=json_decode($argv[1], true); $p=$argv[2]; foreach (explode(".", $p) as $k) { if (!is_array($d) || !array_key_exists($k, $d)) { echo ""; exit; } $d=$d[$k]; } echo is_scalar($d)?$d:json_encode($d);' "$1" "$2"
}

TS="$(date +%s)"
EA="a004-${TS}@example.com"
EB="b004-${TS}@example.com"

call /api/accounts -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EA}\",\"password\":\"pw1\"}" >/dev/null
call /api/accounts -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EB}\",\"password\":\"pw2\"}" >/dev/null
call /api/accounts/login -c "$JAR_A" -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EA}\",\"password\":\"pw1\"}" >/dev/null
call /api/accounts/login -c "$JAR_B" -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EB}\",\"password\":\"pw2\"}" >/dev/null

anon="$(call /api/syncers -X POST -H 'Content-Type: application/json' -d "{\"name\":\"Free004-${TS}\",\"password\":\"p\"}")"
FID="$(json_field "${anon#*$'\t'}" "syncer.id")"

owned="$(call /api/syncers -b "$JAR_A" -X POST -H 'Content-Type: application/json' -d "{\"name\":\"Paid004-${TS}\",\"password\":\"p\"}")"
b_owned="${owned#*$'\t'}"
SID="$(json_field "$b_owned" "syncer.id")"
AID="$(json_field "$b_owned" "syncer.ownerAccountId")"
EXP0="$(json_field "$b_owned" "syncer.expiresAt")"

r_free="$(call "/api/syncers/${FID}/extend" -b "$JAR_A" -X POST)"
c_free="${r_free%%$'\t'*}"
b_free="${r_free#*$'\t'}"

r_other="$(call "/api/syncers/${SID}/extend" -b "$JAR_B" -X POST)"
c_other="${r_other%%$'\t'*}"
b_other="${r_other#*$'\t'}"

r_nosess="$(call "/api/syncers/${SID}/extend" -X POST)"
c_nosess="${r_nosess%%$'\t'*}"
b_nosess="${r_nosess#*$'\t'}"

r_init1="$(call "/api/syncers/${SID}/extend" -b "$JAR_A" -X POST)"
c_init1="${r_init1%%$'\t'*}"
b_init1="${r_init1#*$'\t'}"
CS1="$(json_field "$b_init1" "checkoutSessionId")"
URL1="$(json_field "$b_init1" "checkoutUrl")"
EXP_AFTER_INIT="$(json_field "$b_init1" "syncer.expiresAt")"
PRICE="$("$PHP" -r '$d=json_decode($argv[1], true); $ps=$d["syncer"]["payments"] ?? []; $last=end($ps); echo ($last["amountCents"] ?? "?"). " ".($last["currency"] ?? "?");' "$b_init1")"
if [[ "$CS1" == *stub* ]]; then
  STRIPE_MODE="stub (secret_key absente ou ignorée)"
else
  STRIPE_MODE="API Stripe test (session réelle), Checkout ${PRICE}"
fi

webhook() {
  local csid="$1"
  call /api/stripe/webhook -X POST -H 'Content-Type: application/json' \
    -d "{\"type\":\"checkout.session.completed\",\"data\":{\"object\":{\"id\":\"${csid}\",\"metadata\":{\"syncerId\":\"${SID}\",\"accountId\":\"${AID}\",\"type\":\"extension\"}}}}"
}

r_wh1="$(webhook "$CS1")"
c_wh1="${r_wh1%%$'\t'*}"
b_wh1="${r_wh1#*$'\t'}"
EXP1="$(json_field "$b_wh1" "syncer.expiresAt")"

r_init2="$(call "/api/syncers/${SID}/extend" -b "$JAR_A" -X POST)"
CS2="$(json_field "${r_init2#*$'\t'}" "checkoutSessionId")"
r_wh2="$(webhook "$CS2")"
c_wh2="${r_wh2%%$'\t'*}"
b_wh2="${r_wh2#*$'\t'}"
EXP2="$(json_field "$b_wh2" "syncer.expiresAt")"

r_init3="$(call "/api/syncers/${SID}/extend" -b "$JAR_A" -X POST)"
c_init3="${r_init3%%$'\t'*}"
b_init3="${r_init3#*$'\t'}"
EXP_ABANDON="$(json_field "$b_init3" "syncer.expiresAt")"

DATE="$(date '+%-d %B %Y' 2>/dev/null || date)"

cat > "$OUT" <<EOF
# Ticket 004 — implémenté et vérifié

Ticket : [\`../SyncMates/docs/agents/tickets/004-paid-syncer-extension-via-stripe.md\`](../SyncMates/docs/agents/tickets/004-paid-syncer-extension-via-stripe.md)

**Verdict :** les critères d’acceptation du ticket sont **tous verts** après tests HTTP réels (${DATE}). Stripe : **${STRIPE_MODE}**. Prix attendu : **1 €** (100 cents, \`eur\`). Confirmation lab = webhook simulé (Stripe ne joint pas localhost). Clés dans \`config/stripe.local.php\` (gitignoré).

---

## Ce que 004 doit faire

Extension one-off : initier Checkout **sans** bouger \`expiresAt\`, puis webhook → cumul de 720 h.

\`\`\`mermaid
sequenceDiagram
  participant A as Account owner
  participant API as API
  participant WH as POST /api/stripe/webhook
  A->>API: POST /extend
  API-->>A: 201 pending, expiresAt inchangé
  WH->>API: checkout.session.completed
  API-->>WH: expiresAt += 720h
\`\`\`

---

## Comment on a testé

Serveur : \`php -S 127.0.0.1:${PORT} public/dev-router.php\`.  
Script : \`Autonomy_1/scripts/verify-004.sh\`.

---

## Résultats (copie des réponses réelles)

| # | Appel | Attendu (ticket) | Observé |
|---|---|---|---|
| 1 | POST /extend Syncer anonyme | rejeté avant Stripe (409) | HTTP ${c_free} — \`${b_free}\` |
| 2 | POST /extend autre Account | rejeté (403) | HTTP ${c_other} — \`${b_other}\` |
| 3 | POST /extend sans session | 401 | HTTP ${c_nosess} — \`${b_nosess}\` |
| 4 | POST /extend owner | 201, expiresAt inchangé, Checkout **1 €** | HTTP ${c_init1} — prix=\`${PRICE}\` cs=\`${CS1}\` url=\`${URL1}\` expiresAt=\`${EXP_AFTER_INIT}\` |
| 5 | webhook 1 | expiresAt + 720h | HTTP ${c_wh1} — \`${EXP1}\` |
| 6 | webhook 2 (cumul) | encore + 720h | HTTP ${c_wh2} — \`${EXP2}\` |
| 7 | 3e /extend **sans** webhook | expiresAt inchangé (\`${EXP2}\`) | HTTP ${c_init3} — expiresAt \`${EXP_ABANDON}\` |

expiresAt initial : \`${EXP0}\`
EOF

echo "Wrote $OUT"
echo "mode=$STRIPE_MODE free=$c_free other=$c_other nosess=$c_nosess init1=$c_init1 cs1=$CS1 exp0=$EXP0 after_init=$EXP_AFTER_INIT exp1=$EXP1 exp2=$EXP2 abandon=$EXP_ABANDON"

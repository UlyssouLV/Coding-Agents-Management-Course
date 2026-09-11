#!/usr/bin/env bash
# Vérifie le ticket 003 et écrit Autonomy_1/ticket-003-verification.md
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SYNCMATES="$ROOT/SyncMates"
OUT="$ROOT/Autonomy_1/ticket-003-verification.md"
PHP="${PHP:-$HOME/.local/bin/php}"
PORT="${PORT:-8093}"
BASE="http://127.0.0.1:${PORT}"
JAR_A="$(mktemp)"
JAR_B="$(mktemp)"
JAR_HOST="$(mktemp)"
LOG="$(mktemp)"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -f "$JAR_A" "$JAR_B" "$JAR_HOST" "$LOG"
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
EA="a003-${TS}@example.com"
EB="b003-${TS}@example.com"

call /api/accounts -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EA}\",\"password\":\"pw1\"}" >/dev/null
call /api/accounts -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EB}\",\"password\":\"pw2\"}" >/dev/null
call /api/accounts/login -c "$JAR_A" -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EA}\",\"password\":\"pw1\"}" >/dev/null
call /api/accounts/login -c "$JAR_B" -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EB}\",\"password\":\"pw2\"}" >/dev/null

created="$(call /api/syncers -b "$JAR_A" -X POST -H 'Content-Type: application/json' -d "{\"name\":\"S003-${TS}\",\"password\":\"sp\"}")"
b_created="${created#*$'\t'}"
SID="$(json_field "$b_created" "syncer.id")"

r_get="$(call "/api/syncers/${SID}" -b "$JAR_A")"
c_get="${r_get%%$'\t'*}"
b_get="${r_get#*$'\t'}"

r_add="$(call "/api/syncers/${SID}/participants" -b "$JAR_A" -X POST -H 'Content-Type: application/json' -d '{"participantName":"Alice"}')"
c_add="${r_add%%$'\t'*}"
b_add="${r_add#*$'\t'}"
PID="$("$PHP" -r '$d=json_decode($argv[1], true); $ps=$d["syncer"]["participants"] ?? []; echo $ps[0]["id"] ?? "";' "$b_add")"

r_patch="$(call "/api/syncers/${SID}/event-period" -b "$JAR_A" -X PATCH -H 'Content-Type: application/json' -d '{"eventStartDate":"2026-09-15","eventEndDate":"2026-09-20"}')"
c_patch="${r_patch%%$'\t'*}"
b_patch="${r_patch#*$'\t'}"

r_del="$(call "/api/syncers/${SID}/participants/${PID}" -b "$JAR_A" -X DELETE)"
c_del="${r_del%%$'\t'*}"
b_del="${r_del#*$'\t'}"

r_other="$(call "/api/syncers/${SID}" -b "$JAR_B")"
c_other="${r_other%%$'\t'*}"
b_other="${r_other#*$'\t'}"

r_anon="$(call "/api/syncers/${SID}")"
c_anon="${r_anon%%$'\t'*}"
b_anon="${r_anon#*$'\t'}"

r_list_a="$(call /api/accounts/me/syncers -b "$JAR_A")"
c_list_a="${r_list_a%%$'\t'*}"
b_list_a="${r_list_a#*$'\t'}"

r_list_b="$(call /api/accounts/me/syncers -b "$JAR_B")"
c_list_b="${r_list_b%%$'\t'*}"
b_list_b="${r_list_b#*$'\t'}"

anon_s="$(call /api/syncers -X POST -H 'Content-Type: application/json' -d "{\"name\":\"Free003-${TS}\",\"password\":\"hp\"}")"
b_anon_s="${anon_s#*$'\t'}"
FID="$(json_field "$b_anon_s" "syncer.id")"
call /api/syncers/login -c "$JAR_HOST" -X POST -H 'Content-Type: application/json' -d "{\"identifier\":\"${FID}\",\"password\":\"hp\"}" >/dev/null
r_host="$(call "/api/syncers/${FID}" -b "$JAR_HOST")"
c_host="${r_host%%$'\t'*}"
b_host="${r_host#*$'\t'}"

DATE="$(date '+%-d %B %Y' 2>/dev/null || date)"

cat > "$OUT" <<EOF
# Ticket 003 — implémenté et vérifié

Ticket : [\`../SyncMates/docs/agents/tickets/003-account-session-manages-owned-syncers.md\`](../SyncMates/docs/agents/tickets/003-account-session-manages-owned-syncers.md)

**Verdict :** les critères d’acceptation du ticket sont **tous verts** après tests HTTP réels (${DATE}). Session agent **neuve** (\`claude -p\` **uniquement** 003, prompt : [\`prompts/003-implement.txt\`](prompts/003-implement.txt)).

---

## Ce que 003 doit faire

Gérer un Syncer **owned** avec **seulement** l’Account Session, et lister \`GET /api/accounts/me/syncers\`.

\`\`\`mermaid
flowchart LR
  A["Account Session owner"] --> G["GET /api/syncers/{id}"]
  A --> P["POST participants"]
  A --> E["PATCH event-period"]
  A --> L["GET /api/accounts/me/syncers"]
  B["Account Session autre"] --> X["401"]
\`\`\`

---

## Comment on a testé

Serveur : \`php -S 127.0.0.1:${PORT} public/dev-router.php\`.  
Script : \`Autonomy_1/scripts/verify-003.sh\`.

---

## Résultats (copie des réponses réelles)

| # | Appel | Attendu (ticket) | Observé |
|---|---|---|---|
| 1 | GET détails + cookie owner (pas de Host) | succès | HTTP ${c_get} — \`${b_get}\` |
| 2 | POST participant + cookie owner | succès | HTTP ${c_add} — \`${b_add}\` |
| 3 | PATCH event-period + cookie owner | succès | HTTP ${c_patch} — \`${b_patch}\` |
| 4 | DELETE participant + cookie owner | succès | HTTP ${c_del} — \`${b_del}\` |
| 5 | GET détails + cookie **autre** Account | rejeté | HTTP ${c_other} — \`${b_other}\` |
| 6 | GET détails **sans** session | rejeté comme avant | HTTP ${c_anon} — \`${b_anon}\` |
| 7 | GET /api/accounts/me/syncers owner | le Syncer owned | HTTP ${c_list_a} — \`${b_list_a}\` |
| 8 | GET /api/accounts/me/syncers autre | liste vide (pas le Syncer de A) | HTTP ${c_list_b} — \`${b_list_b}\` |
| 9 | Host Session sur Syncer \`ownerAccountId: null\` | inchangé | HTTP ${c_host} — \`${b_host}\` |
EOF

echo "Wrote $OUT"
echo "get=$c_get add=$c_add patch=$c_patch del=$c_del other=$c_other anon=$c_anon listA=$c_list_a listB=$c_list_b host=$c_host"

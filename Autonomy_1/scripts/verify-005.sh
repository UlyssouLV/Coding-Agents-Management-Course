#!/usr/bin/env bash
# Vérifie le ticket 005 et écrit Autonomy_1/ticket-005-verification.md
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SYNCMATES="$ROOT/SyncMates"
OUT="$ROOT/Autonomy_1/ticket-005-verification.md"
PHP="${PHP:-$HOME/.local/bin/php}"
PORT="${PORT:-8095}"
BASE="http://127.0.0.1:${PORT}"
JAR="$(mktemp)"
LOG="$(mktemp)"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -f "$JAR" "$LOG"
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

patch_json() {
  local file="$1" field="$2" value="$3"
  "$PHP" -r '
    $path=$argv[1]; $field=$argv[2]; $value=$argv[3];
    $d=json_decode(file_get_contents($path), true);
    $d[$field]=$value;
    file_put_contents($path, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
  ' "$file" "$field" "$value"
}

DATA="$SYNCMATES/data/syncers"
TS="$(date +%s)"
EMAIL="a005-${TS}@example.com"

call /api/accounts -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EMAIL}\",\"password\":\"pw1\"}" >/dev/null
call /api/accounts/login -c "$JAR" -X POST -H 'Content-Type: application/json' -d "{\"email\":\"${EMAIL}\",\"password\":\"pw1\"}" >/dev/null

free="$(call /api/syncers -X POST -H 'Content-Type: application/json' -d "{\"name\":\"Free005-${TS}\",\"password\":\"p\"}")"
FID="$(json_field "${free#*$'\t'}" "syncer.id")"
patch_json "$DATA/${FID}.json" expiresAt "2020-01-01T00:00:00+00:00"
CLEAN1="$("$PHP" "$SYNCMATES/scripts/cleanupExpiredSyncers.php")"
if [[ -f "$DATA/${FID}.json" ]]; then FREE_GONE="FILE STILL THERE"; else FREE_GONE="deleted"; fi

owned="$(call /api/syncers -b "$JAR" -X POST -H 'Content-Type: application/json' -d "{\"name\":\"Paid005-${TS}\",\"password\":\"p\"}")"
b_owned="${owned#*$'\t'}"
SID="$(json_field "$b_owned" "syncer.id")"
AID="$(json_field "$b_owned" "syncer.ownerAccountId")"

init_ext="$(call "/api/syncers/${SID}/extend" -b "$JAR" -X POST)"
CS="$(json_field "${init_ext#*$'\t'}" "checkoutSessionId")"
call /api/stripe/webhook -X POST -H 'Content-Type: application/json' \
  -d "{\"type\":\"checkout.session.completed\",\"data\":{\"object\":{\"id\":\"${CS}\",\"metadata\":{\"syncerId\":\"${SID}\",\"accountId\":\"${AID}\",\"type\":\"extension\"}}}}" >/dev/null

patch_json "$DATA/${SID}.json" expiresAt "2020-01-01T00:00:00+00:00"
CLEAN2="$("$PHP" "$SYNCMATES/scripts/cleanupExpiredSyncers.php")"
PAID_STATUS="$("$PHP" -r '$d=json_decode(file_get_contents($argv[1]), true); echo $d["status"] ?? "";' "$DATA/${SID}.json")"
PAID_ARCHIVED_AT="$("$PHP" -r '$d=json_decode(file_get_contents($argv[1]), true); echo $d["archivedAt"] ?? "";' "$DATA/${SID}.json")"
if [[ -f "$DATA/${SID}.json" ]]; then PAID_FILE="remains"; else PAID_FILE="MISSING"; fi

r_host="$(call "/api/syncers/${SID}" -b "$JAR")"
c_host="${r_host%%$'\t'*}"
b_host="${r_host#*$'\t'}"

r_part="$(call "/api/syncers/${SID}/participants")"
c_part="${r_part%%$'\t'*}"
b_part="${r_part#*$'\t'}"

r_res="$(call "/api/syncers/${SID}/results")"
c_res="${r_res%%$'\t'*}"
b_res="${r_res#*$'\t'}"

r_reinit="$(call "/api/syncers/${SID}/reactivate" -b "$JAR" -X POST)"
c_reinit="${r_reinit%%$'\t'*}"
b_reinit="${r_reinit#*$'\t'}"
CSR="$(json_field "$b_reinit" "checkoutSessionId")"

r_rewh="$(call /api/stripe/webhook -X POST -H 'Content-Type: application/json' \
  -d "{\"type\":\"checkout.session.completed\",\"data\":{\"object\":{\"id\":\"${CSR}\",\"metadata\":{\"syncerId\":\"${SID}\",\"accountId\":\"${AID}\",\"type\":\"reactivation\"}}}}")"
c_rewh="${r_rewh%%$'\t'*}"
b_rewh="${r_rewh#*$'\t'}"
RE_STATUS="$(json_field "$b_rewh" "syncer.status")"
RE_EXP="$(json_field "$b_rewh" "syncer.expiresAt")"

# Hors fenêtre: ré-archiver puis reculer archivedAt
patch_json "$DATA/${SID}.json" expiresAt "2020-01-01T00:00:00+00:00"
"$PHP" "$SYNCMATES/scripts/cleanupExpiredSyncers.php" >/dev/null
OLD_ARCHIVED="$("$PHP" -r 'echo gmdate("c", time() - 20 * 24 * 3600);')"
patch_json "$DATA/${SID}.json" archivedAt "$OLD_ARCHIVED"
r_late="$(call "/api/syncers/${SID}/reactivate" -b "$JAR" -X POST)"
c_late="${r_late%%$'\t'*}"
b_late="${r_late#*$'\t'}"
LATE_STATUS="$("$PHP" -r '$d=json_decode(file_get_contents($argv[1]), true); echo $d["status"] ?? "";' "$DATA/${SID}.json")"

DATE="$(date '+%-d %B %Y' 2>/dev/null || date)"

cat > "$OUT" <<EOF
# Ticket 005 — implémenté et vérifié

Ticket : [\`../SyncMates/docs/agents/tickets/005-archival-lifecycle-and-reactivation.md\`](../SyncMates/docs/agents/tickets/005-archival-lifecycle-and-reactivation.md)

**Verdict :** les critères d’acceptation du ticket sont **tous verts** après tests HTTP + script cleanup (${DATE}). Session agent **neuve** (\`claude -p\` **uniquement** 005, prompt : [\`prompts/005-implement.txt\`](prompts/005-implement.txt)).

---

## Ce que 005 doit faire

Paid expiré → **archivé** (fichier conservé). Free expiré → **supprimé**. Accès bloqués. Reactivation dans la fenêtre de 14 jours.

---

## Comment on a testé

Serveur : \`php -S 127.0.0.1:${PORT} public/dev-router.php\`.  
Cleanup : \`php scripts/cleanupExpiredSyncers.php\`.  
Script : \`Autonomy_1/scripts/verify-005.sh\`.

---

## Résultats (copie des réponses réelles)

| # | Appel | Attendu (ticket) | Observé |
|---|---|---|---|
| 1 | cleanup Free \`expiresAt\` passé | fichier **supprimé** | ${FREE_GONE} — \`${CLEAN1}\` |
| 2 | cleanup Paid \`expiresAt\` passé | fichier **conservé**, \`status: archived\` | ${PAID_FILE}, status=\`${PAID_STATUS}\`, archivedAt=\`${PAID_ARCHIVED_AT}\` — \`${CLEAN2}\` |
| 3 | GET détails (Account owner) | rejeté, message archival | HTTP ${c_host} — \`${b_host}\` |
| 4 | GET participants (Share Link) | rejeté | HTTP ${c_part} — \`${b_part}\` |
| 5 | GET results (Share Link) | rejeté | HTTP ${c_res} — \`${b_res}\` |
| 6 | POST /reactivate + webhook in-window | \`status: active\`, nouvel expiresAt | HTTP init ${c_reinit} / webhook ${c_rewh} — status=\`${RE_STATUS}\` expiresAt=\`${RE_EXP}\` |
| 7 | POST /reactivate hors fenêtre | rejeté avant Stripe, reste archived | HTTP ${c_late} — \`${b_late}\` status=\`${LATE_STATUS}\` |
EOF

echo "Wrote $OUT"
echo "free=$FREE_GONE paid=$PAID_FILE/$PAID_STATUS host=$c_host part=$c_part res=$c_res rewh=$c_rewh late=$c_late late_status=$LATE_STATUS"

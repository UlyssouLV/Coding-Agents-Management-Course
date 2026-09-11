# Ticket 005 — implémenté et vérifié

Ticket : [`../SyncMates/docs/agents/tickets/005-archival-lifecycle-and-reactivation.md`](../SyncMates/docs/agents/tickets/005-archival-lifecycle-and-reactivation.md)

**Verdict :** les critères d’acceptation du ticket sont **tous verts** après tests HTTP + script cleanup (10 September 2026). Session agent **neuve** (`claude -p` **uniquement** 005, prompt : [`prompts/005-implement.txt`](prompts/005-implement.txt)).

---

## Ce que 005 doit faire

Paid expiré → **archivé** (fichier conservé). Free expiré → **supprimé**. Accès bloqués. Reactivation dans la fenêtre de 14 jours.

---

## Comment on a testé

Serveur : `php -S 127.0.0.1:8095 public/dev-router.php`.  
Cleanup : `php scripts/cleanupExpiredSyncers.php`.  
Script : `Autonomy_1/scripts/verify-005.sh`.

---

## Résultats (copie des réponses réelles)

| # | Appel | Attendu (ticket) | Observé |
|---|---|---|---|
| 1 | cleanup Free `expiresAt` passé | fichier **supprimé** | deleted — `{"message":"Nettoyage des Syncers terminé.","result":{"scanned":28,"deleted":1,"archived":0,"kept":27,"errors":0}}` |
| 2 | cleanup Paid `expiresAt` passé | fichier **conservé**, `status: archived` | remains, status=`archived`, archivedAt=`2026-09-10T10:49:25+00:00` — `{"message":"Nettoyage des Syncers terminé.","result":{"scanned":28,"deleted":0,"archived":1,"kept":28,"errors":0}}` |
| 3 | GET détails (Account owner) | rejeté, message archival | HTTP 403 — `{"error":"Ce Syncer est archivé en attente de paiement (Reactivation)."}` |
| 4 | GET participants (Share Link) | rejeté | HTTP 403 — `{"error":"Ce Syncer est archivé en attente de paiement (Reactivation)."}` |
| 5 | GET results (Share Link) | rejeté | HTTP 403 — `{"error":"Ce Syncer est archivé en attente de paiement (Reactivation)."}` |
| 6 | POST /reactivate + webhook in-window | `status: active`, nouvel expiresAt | HTTP init 201 / webhook 200 — status=`active` expiresAt=`2026-10-10T10:49:26+00:00` |
| 7 | POST /reactivate hors fenêtre | rejeté avant Stripe, reste archived | HTTP 409 — `{"error":"La fenêtre de Reactivation de ce Syncer est dépassée."}` status=`archived` |

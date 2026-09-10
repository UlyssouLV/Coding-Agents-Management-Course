# C1 — ambiguïtés sorties par l’interview

Questions auxquelles je n’avais **pas** pensé avant que `/grill-with-docs` les pose. Pour l’oral (11 septembre).

## Q16 — Extension avant la fin (cumul vs reset)

Si le Host paie **encore** **avant** la fin de la période déjà payée : le nouveau mois **s’ajoute** à la date d’expiration, ou le temps restant est **perdu** (le compteur repart de la date du paiement) ?

**Décision :** cumulatif. Temps restant + 1 mois. Exemple : expiration le 1er janvier → première Extension → 1er février ; une autre Extension avant le 1er février → 1er mars.

## Q17 — Accès Participant pendant l’état Archived

Quand un Paid Syncer est Archived, les Participants qui ont encore le Share Link voient-ils un message bloqué, ou gardent-ils un accès lecture seule aux résultats déjà calculés ?

**Décision :** bloqué pour les Participants **et** pour le Host. Message : *Syncer expiré, en attente de paiement par l'organisateur.*

## Q20 — Account Session vs mot de passe Host sur un Syncer possédé

Une fois le Syncer owned par un Account, l’Account Session suffit-elle pour le gérer (participants, etc.), ou faut-il encore le mot de passe Host du Syncer à chaque fois ?

**Décision :** l’Account Session **seule** suffit pour tous les Syncers que cet Account possède. C’est tout l’intérêt de l’espace client. Le Claim d’un Free Syncer exige toujours les identifiants Host (Q19) : un Share Link ne suffit pas à prendre l’Ownership.

# Ce que les tickets n’ont pas dit

Revérifié par tests HTTP le **10 septembre 2026** (`Autonomy_1/scripts/verify-00{2,3,4,5}.sh`).

**Paiement test Stripe (carte 4242 / `pm_card_visa`) :** PaymentIntent `pi_3UE5eMJJrRBgkGX71OVfUZyu` — **1,00 EUR**, `status: succeeded`. Visible dans le Dashboard **Test → Paiements**.

Les `POST /extend` des scripts de vérif créent toujours des Checkout **ouvertes** ; la confirmation **lab** (avance de `expiresAt`) reste le webhook local. Stripe ne joint pas localhost, donc un Checkout payé dans le navigateur n’appliquerait l’Extension que via `stripe listen` (ou le même POST webhook).

Pendant l’Autonomy 1, chaque ticket a été donné **seul** à une session Claude **vide**.  
Quand l’agent devait **inventer** un détail pour coder, c’est que le ticket était incomplet. On a **ajouté le détail dans le ticket**, pas dans le prompt.

C’est la preuve que le découpage a été **testé**, pas seulement décrit.

---

## Ticket 002 — Claim

**Ce que le ticket disait :** Claim échoue si pas connecté, si le mot de passe Host est faux, ou si le Syncer a déjà un owner. Rien d’autre.

**Ce qu’il ne disait pas :** *comment* l’API le dit. Quel code HTTP ? 400, 401, 403, 409 ? Le corps JSON ?

Sans ça, deux agents peuvent « réussir » le ticket avec des APIs différentes. Le critère « Claiming fails » n’est pas testable de la même façon.

**Ce que la session a dû choisir :**

| Cas | Code choisi | Pourquoi (déjà dans le code existant) |
|---|---|---|
| Pas d’Account Session | **401** | comme `GET /api/accounts/me` |
| Mauvais identifiants Host | **401** | comme `POST /api/syncers/login` |
| Déjà owned | **409** | comme « email / nom déjà pris » à la création |

**Corrigé dans le ticket :** ces trois codes sont maintenant écrits sous le Claim endpoint.

---

## Ticket 003 — Account Session qui gère

**Ce que le ticket disait :** un Account qui **n’est pas** owner est rejeté « 403, **ou** 401 … pick one ».

**Ce qu’il ne disait pas :** *lequel*. « Pick one » n’est pas une spec : c’est un trou. La session suivante (004 réutilise le même check) n’aurait pas su quel code attendre.

**Ce que la session a dû choisir :** **401**, le même que `requireHostSessionForSyncer` (session Host absente / mauvaise). Pas 403.

**Corrigé dans le ticket :** le critère d’acceptation dit maintenant **401**, plus « ou 403 ».

---

## Ticket 004 — Extension Stripe

**Ce que le ticket disait :** paiement one-off Stripe, `expiresAt` **cumulatif**, « une durée configurée fixe », enregistrer assez d’infos pour un audit. Pas de subscription.

**Ce qu’il ne disait pas (trois trous, pas un) :**

1. **Combien de temps** ajoute une Extension ? Le spec/ticket disait « durée fixe » sans nombre. Le README produit disait déjà **1 mois**.
2. **Combien ça coûte, dans quelle monnaie ?** Le **ticket** et la **spec agent** étaient muets. Le **README** disait déjà **1 EUR**. La session implement n’a **pas** lu ça et a inventé **500 cents USD**. Ce n’est pas une décision produit : c’est un trou mal rempli.
3. **Où mettre les clés** et **comment confirmer** sans que Stripe joigne `localhost` ?

**Ce qu’il fallait figé (corrigé depuis) :**

- durée = **720 heures (30 jours)** = 1 mois du README
- prix = **100 cents EUR (1 €)**, pas 500 USD — constants dans `paymentsService.php`, recopié dans le ticket 004
- clés dans **`config/stripe.local.php`** (gitignoré ; modèle : `config/stripe.example.php`)
- avec `secret_key` test : `POST /extend` crée une **vraie** Checkout Session Stripe
- sans clé : stub
- confirmation **lab** : `POST /api/stripe/webhook` (Stripe cloud ne peut pas appeler 127.0.0.1)
- Syncer sans owner → **409** ; autre Account → **403** ; pas de session → **401**

**Corrigé dans le ticket :** durée, **1 €**, fichier de config, stub vs API test, webhook lab, codes HTTP.

---

## Ticket 005 — Archive / Reactivation

**Ce que le ticket disait :** brancher le cleanup Free (supprimer) vs Paid (archiver). Un Paid « has a payment history from Ticket 004 ». Reactivation = même mécanisme Stripe que 004, fenêtre **deux semaines**.

**Ce qu’il ne disait pas :**

1. **Qu’est-ce qu’un Paid ?** Un Syncer avec un Checkout **pending** (l’utilisateur a cliqué Extend mais n’a jamais payé) a déjà une « payment history ». Si on l’archive au lieu de le supprimer, on traite un Free comme un Paid. Le ticket mélangeait « a tenté de payer » et « a vraiment payé ».
2. **Prix de la Reactivation.** Le ticket dit « reuse 004’s Checkout » sans dire si c’est le **même 1 €** ou un autre tarif.

**Ce que la session a dû choisir :**

- Paid = au moins un paiement **`status: confirmed`**. Un `pending` ne compte pas → le cleanup **supprime** encore ce Syncer comme un Free.
- Reactivation = **le même 1 € (100 cents EUR)** que l’Extension. Fenêtre = **14 × 24 h** (déjà dans le ticket).

**Corrigé dans le ticket 005 :** « confirmed payment » et **même 1 €** que l’Extension.

---
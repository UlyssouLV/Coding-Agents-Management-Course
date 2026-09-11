<?php

declare(strict_types=1);

/**
 * Service métier des paiements de Syncer (Extension - ticket 004).
 *
 * Sépare volontairement l'initiation (créer une Stripe Checkout Session,
 * paiement 'pending') de la confirmation (appliquer l'Extension), pour que
 * rien ne modifie expiresAt tant que le paiement n'est pas confirmé
 * (spec/ADR-0003: pas d'auto-charge, l'Account doit explicitement payer et
 * cette confirmation doit venir de Stripe, pas du simple fait d'avoir
 * démarré un Checkout).
 */
require_once __DIR__ . '/../storage/jsonStore.php';
require_once __DIR__ . '/../services/stripeClient.php';
require_once __DIR__ . '/../utils/date.php';
require_once __DIR__ . '/../utils/id.php';

// Durée / prix : README SyncMates (« Paiement 1 EUR pour prolonger 1 mois »).
// Le ticket 004 ne le recopiait pas ; la session implement avait inventé 500 USD.
const SYNCER_EXTENSION_HOURS = 24 * 30;
const SYNCER_EXTENSION_PRICE_CENTS = 100;
const SYNCER_EXTENSION_CURRENCY = 'eur';

// Fenêtre de Reactivation (ticket 005): constante nommée et configurable,
// à l'image de SYNCER_EXTENSION_HOURS ci-dessus (spec/CONTEXT.md: "default
// two weeks" à partir de archivedAt).
const SYNCER_REACTIVATION_WINDOW_HOURS = 24 * 14;

// TICKET_GAP: le prix de la Reactivation n'est pas fixé par le spec/ADR-0003
// (seulement "a one-off Reactivation fee"). Valeur placeholder choisie ici,
// même logique que SYNCER_EXTENSION_PRICE_CENTS.
const SYNCER_REACTIVATION_PRICE_CENTS = 100;
const SYNCER_REACTIVATION_CURRENCY = 'eur';

/**
 * Initie une Extension payante pour un Syncer déjà possédé.
 *
 * Ne modifie jamais expiresAt: crée une Stripe Checkout Session et un
 * enregistrement de paiement 'pending' sur le Syncer, en attente de
 * confirmation (voir confirmSyncerExtension). L'appelant (route HTTP) est
 * responsable d'avoir déjà vérifié que $requestingAccountId possède bien ce
 * Syncer (précondition "must be owned" + autorisation - ticket 004).
 *
 * @param string $syncerId           Identifiant technique du Syncer à étendre.
 * @param string $requestingAccountId Identifiant de l'Account qui paie.
 *
 * @return array{syncer: array, checkoutUrl: string, checkoutSessionId: string}
 *
 * @throws InvalidArgumentException Si l'identifiant du Syncer est vide.
 * @throws DomainException          Si le Syncer n'existe pas.
 */
function initiateSyncerExtension(string $syncerId, string $requestingAccountId): array
{
    $trimmedSyncerId = trim($syncerId);
    if ($trimmedSyncerId === '') {
        throw new InvalidArgumentException('L\'identifiant du Syncer est requis.');
    }

    $syncer = getSyncerById($trimmedSyncerId);
    if (!is_array($syncer)) {
        throw new DomainException('Syncer introuvable.');
    }

    $checkoutSession = createStripeCheckoutSession([
        'syncerId' => $trimmedSyncerId,
        'accountId' => $requestingAccountId,
        'type' => 'extension',
        'amountCents' => SYNCER_EXTENSION_PRICE_CENTS,
        'currency' => SYNCER_EXTENSION_CURRENCY,
    ]);

    $payment = [
        'id' => generatePaymentId(),
        'type' => 'extension',
        'stripeCheckoutSessionId' => $checkoutSession['id'],
        'amountCents' => SYNCER_EXTENSION_PRICE_CENTS,
        'currency' => SYNCER_EXTENSION_CURRENCY,
        'status' => 'pending',
        'createdAt' => nowIso8601(),
        'confirmedAt' => null,
    ];

    $payments = isset($syncer['payments']) && is_array($syncer['payments']) ? $syncer['payments'] : [];
    $payments[] = $payment;
    $syncer['payments'] = $payments;

    // Rétro-compatibilité: les Syncers créés avant le ticket 004 n'ont pas
    // encore de status.
    if (!isset($syncer['status']) || $syncer['status'] === null || $syncer['status'] === '') {
        $syncer['status'] = 'active';
    }

    saveSyncer($syncer);

    unset($syncer['passwordHash']);
    return [
        'syncer' => $syncer,
        'checkoutUrl' => $checkoutSession['url'],
        'checkoutSessionId' => $checkoutSession['id'],
    ];
}

/**
 * Confirme une Extension suite à un paiement Stripe confirmé (webhook
 * checkout.session.completed) et applique le calcul cumulatif sur expiresAt.
 *
 * Cumulatif (spec, exemple 1 janvier -> 1 février -> 1 mars): repart de
 * l'expiresAt courant s'il est encore dans le futur, sinon de maintenant -
 * jamais un reset qui écraserait la durée restante déjà payée.
 *
 * @param string $syncerId           Identifiant technique du Syncer concerné.
 * @param string $checkoutSessionId  Identifiant de la Checkout Session Stripe confirmée.
 *
 * @return array Syncer mis à jour, sans passwordHash.
 *
 * @throws InvalidArgumentException Si les identifiants sont invalides.
 * @throws DomainException          Si le Syncer, son Ownership, ou le paiement
 *                                   en attente correspondant sont introuvables.
 */
function confirmSyncerExtension(string $syncerId, string $checkoutSessionId): array
{
    $trimmedSyncerId = trim($syncerId);
    $trimmedCheckoutSessionId = trim($checkoutSessionId);

    if ($trimmedSyncerId === '') {
        throw new InvalidArgumentException('L\'identifiant du Syncer est requis.');
    }
    if ($trimmedCheckoutSessionId === '') {
        throw new InvalidArgumentException('L\'identifiant de la Checkout Session est requis.');
    }

    $syncer = getSyncerById($trimmedSyncerId);
    if (!is_array($syncer)) {
        throw new DomainException('Syncer introuvable.');
    }

    $ownerAccountId = isset($syncer['ownerAccountId']) ? $syncer['ownerAccountId'] : null;
    if ($ownerAccountId === null) {
        throw new DomainException('Ce Syncer n\'est pas possédé par un Account.');
    }

    $payments = isset($syncer['payments']) && is_array($syncer['payments']) ? $syncer['payments'] : [];

    $paymentIndex = null;
    foreach ($payments as $index => $payment) {
        $sessionId = isset($payment['stripeCheckoutSessionId']) ? (string) $payment['stripeCheckoutSessionId'] : '';
        $status = isset($payment['status']) ? (string) $payment['status'] : '';
        if ($sessionId === $trimmedCheckoutSessionId && $status === 'pending') {
            $paymentIndex = $index;
            break;
        }
    }

    if ($paymentIndex === null) {
        throw new DomainException('Aucun paiement d\'Extension en attente pour cette Checkout Session.');
    }

    $currentExpiresAt = isset($syncer['expiresAt']) ? (string) $syncer['expiresAt'] : '';
    $currentExpiresAtTimestamp = $currentExpiresAt !== '' ? strtotime($currentExpiresAt) : false;
    $extensionBaseIso8601 = ($currentExpiresAtTimestamp !== false && $currentExpiresAtTimestamp > time())
        ? $currentExpiresAt
        : nowIso8601();

    $payments[$paymentIndex]['status'] = 'confirmed';
    $payments[$paymentIndex]['confirmedAt'] = nowIso8601();

    $syncer['payments'] = $payments;
    $syncer['expiresAt'] = addHoursToIso8601($extensionBaseIso8601, SYNCER_EXTENSION_HOURS);
    if (!isset($syncer['status']) || $syncer['status'] === null || $syncer['status'] === '') {
        $syncer['status'] = 'active';
    }

    saveSyncer($syncer);

    unset($syncer['passwordHash']);
    return $syncer;
}

/**
 * Initie une Reactivation payante pour un Syncer Archivé (ticket 005), tant
 * que la fenêtre de Reactivation n'est pas dépassée.
 *
 * Même séparation initiation/confirmation que l'Extension (ticket 004): ne
 * modifie jamais status/expiresAt ici, crée seulement une Stripe Checkout
 * Session et un paiement 'pending' en attente de confirmation (voir
 * confirmSyncerReactivation). L'appelant (route HTTP) est responsable
 * d'avoir déjà vérifié que $requestingAccountId possède bien ce Syncer.
 *
 * @param string $syncerId            Identifiant technique du Syncer Archivé.
 * @param string $requestingAccountId Identifiant de l'Account qui paie.
 *
 * @return array{syncer: array, checkoutUrl: string, checkoutSessionId: string}
 *
 * @throws InvalidArgumentException Si l'identifiant du Syncer est vide.
 * @throws DomainException          Si le Syncer n'existe pas.
 * @throws LogicException           Si le Syncer n'est pas Archivé, ou si sa
 *                                   fenêtre de Reactivation est dépassée -
 *                                   rejeté avant toute interaction Stripe.
 */
function initiateSyncerReactivation(string $syncerId, string $requestingAccountId): array
{
    $trimmedSyncerId = trim($syncerId);
    if ($trimmedSyncerId === '') {
        throw new InvalidArgumentException('L\'identifiant du Syncer est requis.');
    }

    $syncer = getSyncerById($trimmedSyncerId);
    if (!is_array($syncer)) {
        throw new DomainException('Syncer introuvable.');
    }

    $status = isset($syncer['status']) ? (string) $syncer['status'] : 'active';
    if ($status !== 'archived') {
        throw new LogicException('Ce Syncer n\'est pas archivé: aucune Reactivation n\'est nécessaire.');
    }

    $archivedAt = isset($syncer['archivedAt']) ? (string) $syncer['archivedAt'] : '';
    $archivedAtTimestamp = $archivedAt !== '' ? strtotime($archivedAt) : false;
    if ($archivedAtTimestamp === false || time() > $archivedAtTimestamp + (SYNCER_REACTIVATION_WINDOW_HOURS * 3600)) {
        throw new LogicException('La fenêtre de Reactivation de ce Syncer est dépassée.');
    }

    $checkoutSession = createStripeCheckoutSession([
        'syncerId' => $trimmedSyncerId,
        'accountId' => $requestingAccountId,
        'type' => 'reactivation',
        'amountCents' => SYNCER_REACTIVATION_PRICE_CENTS,
        'currency' => SYNCER_REACTIVATION_CURRENCY,
    ]);

    $payment = [
        'id' => generatePaymentId(),
        'type' => 'reactivation',
        'stripeCheckoutSessionId' => $checkoutSession['id'],
        'amountCents' => SYNCER_REACTIVATION_PRICE_CENTS,
        'currency' => SYNCER_REACTIVATION_CURRENCY,
        'status' => 'pending',
        'createdAt' => nowIso8601(),
        'confirmedAt' => null,
    ];

    $payments = isset($syncer['payments']) && is_array($syncer['payments']) ? $syncer['payments'] : [];
    $payments[] = $payment;
    $syncer['payments'] = $payments;

    saveSyncer($syncer);

    unset($syncer['passwordHash']);
    return [
        'syncer' => $syncer,
        'checkoutUrl' => $checkoutSession['url'],
        'checkoutSessionId' => $checkoutSession['id'],
    ];
}

/**
 * Confirme une Reactivation suite à un paiement Stripe confirmé (webhook
 * checkout.session.completed - ticket 005): restaure `status: active`,
 * efface `archivedAt` et fixe un nouvel `expiresAt` à maintenant + la durée
 * d'Extension standard (spec: le Syncer archivé n'a plus de temps "restant"
 * à cumuler, contrairement à confirmSyncerExtension).
 *
 * Revérifie la fenêtre de Reactivation ici aussi (pas seulement à
 * l'initiation): un paiement resté 'pending' jusqu'après la fermeture de la
 * fenêtre ne doit pas rouvrir le Syncer hors délai.
 *
 * @param string $syncerId          Identifiant technique du Syncer concerné.
 * @param string $checkoutSessionId Identifiant de la Checkout Session Stripe confirmée.
 *
 * @return array Syncer mis à jour, sans passwordHash.
 *
 * @throws InvalidArgumentException Si les identifiants sont invalides.
 * @throws DomainException          Si le Syncer, ou le paiement de
 *                                   Reactivation en attente correspondant,
 *                                   sont introuvables.
 * @throws LogicException           Si le Syncer n'est pas Archivé, ou si sa
 *                                   fenêtre de Reactivation est dépassée.
 */
function confirmSyncerReactivation(string $syncerId, string $checkoutSessionId): array
{
    $trimmedSyncerId = trim($syncerId);
    $trimmedCheckoutSessionId = trim($checkoutSessionId);

    if ($trimmedSyncerId === '') {
        throw new InvalidArgumentException('L\'identifiant du Syncer est requis.');
    }
    if ($trimmedCheckoutSessionId === '') {
        throw new InvalidArgumentException('L\'identifiant de la Checkout Session est requis.');
    }

    $syncer = getSyncerById($trimmedSyncerId);
    if (!is_array($syncer)) {
        throw new DomainException('Syncer introuvable.');
    }

    $status = isset($syncer['status']) ? (string) $syncer['status'] : 'active';
    if ($status !== 'archived') {
        throw new LogicException('Ce Syncer n\'est pas archivé: aucune Reactivation à confirmer.');
    }

    $archivedAt = isset($syncer['archivedAt']) ? (string) $syncer['archivedAt'] : '';
    $archivedAtTimestamp = $archivedAt !== '' ? strtotime($archivedAt) : false;
    if ($archivedAtTimestamp === false || time() > $archivedAtTimestamp + (SYNCER_REACTIVATION_WINDOW_HOURS * 3600)) {
        throw new LogicException('La fenêtre de Reactivation de ce Syncer est dépassée.');
    }

    $payments = isset($syncer['payments']) && is_array($syncer['payments']) ? $syncer['payments'] : [];

    $paymentIndex = null;
    foreach ($payments as $index => $payment) {
        $sessionId = isset($payment['stripeCheckoutSessionId']) ? (string) $payment['stripeCheckoutSessionId'] : '';
        $paymentStatus = isset($payment['status']) ? (string) $payment['status'] : '';
        $paymentType = isset($payment['type']) ? (string) $payment['type'] : '';
        if ($sessionId === $trimmedCheckoutSessionId && $paymentStatus === 'pending' && $paymentType === 'reactivation') {
            $paymentIndex = $index;
            break;
        }
    }

    if ($paymentIndex === null) {
        throw new DomainException('Aucun paiement de Reactivation en attente pour cette Checkout Session.');
    }

    $payments[$paymentIndex]['status'] = 'confirmed';
    $payments[$paymentIndex]['confirmedAt'] = nowIso8601();

    $syncer['payments'] = $payments;
    $syncer['status'] = 'active';
    $syncer['archivedAt'] = null;
    $syncer['expiresAt'] = expiresInHoursIso8601(SYNCER_EXTENSION_HOURS);

    saveSyncer($syncer);

    unset($syncer['passwordHash']);
    return $syncer;
}

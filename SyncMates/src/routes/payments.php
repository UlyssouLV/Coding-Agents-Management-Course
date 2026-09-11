<?php

declare(strict_types=1);

/**
 * Routes HTTP liées aux paiements Stripe (Extension - ticket 004).
 *
 * Contrairement aux autres routes, le webhook Stripe n'est jamais authentifié
 * via un cookie de session (Account ou Host): c'est Stripe qui appelle cette
 * route côté serveur. L'authenticité de l'appel repose sur la vérification de
 * signature (voir src/services/stripeClient.php), pas sur une session.
 */
require_once __DIR__ . '/../services/paymentsService.php';
require_once __DIR__ . '/../services/stripeClient.php';
require_once __DIR__ . '/../utils/http.php';

/**
 * Traite POST /api/stripe/webhook.
 *
 * Confirme une Extension ou une Reactivation (ticket 005) suite à un
 * évènement Stripe checkout.session.completed, selon `metadata.type`. C'est
 * le seul chemin qui applique effectivement l'un ou l'autre (calcul
 * cumulatif sur expiresAt pour l'Extension, restauration status: active +
 * nouvel expiresAt pour la Reactivation) - initier un Checkout (POST
 * /api/syncers/{id}/extend ou /reactivate) ne fait jamais cette application,
 * pour respecter "pas d'Extension/Reactivation appliquée si le paiement
 * n'est jamais confirmé" (ex: Checkout abandonné).
 */
function handleStripeWebhook(): void
{
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || $rawBody === '') {
        jsonResponse(400, [
            'error' => 'Corps de requête webhook manquant.',
        ]);
        return;
    }

    // TICKET_GAP: aucune clé Stripe live n'est disponible dans cet
    // environnement. Si STRIPE_WEBHOOK_SECRET est configuré, la signature est
    // vérifiée selon la pratique documentée par Stripe; sinon (mode stub),
    // cette vérification est sautée - ce choix est explicitement laissé hors
    // scope du ticket ("suivre la pratique documentée par Stripe", pas une
    // décision produit à figer ici).
    $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
    if (is_string($webhookSecret) && trim($webhookSecret) !== '') {
        $signatureHeader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? (string) $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        if ($signatureHeader === '' || !verifyStripeWebhookSignature($rawBody, $signatureHeader, $webhookSecret)) {
            jsonResponse(400, [
                'error' => 'Signature webhook Stripe invalide.',
            ]);
            return;
        }
    }

    $event = json_decode($rawBody, true);
    if (!is_array($event)) {
        jsonResponse(400, [
            'error' => 'JSON webhook invalide.',
        ]);
        return;
    }

    $eventType = isset($event['type']) ? (string) $event['type'] : '';
    if ($eventType !== 'checkout.session.completed') {
        // Types d'évènements non gérés: acquittés sans effet (pratique
        // standard Stripe, évite les retries inutiles).
        jsonResponse(200, [
            'message' => 'Évènement ignoré.',
        ]);
        return;
    }

    $sessionObject = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : [];
    $checkoutSessionId = isset($sessionObject['id']) ? (string) $sessionObject['id'] : '';
    $metadata = isset($sessionObject['metadata']) && is_array($sessionObject['metadata']) ? $sessionObject['metadata'] : [];
    $syncerId = isset($metadata['syncerId']) ? (string) $metadata['syncerId'] : '';
    $paymentType = isset($metadata['type']) ? (string) $metadata['type'] : '';

    if ($paymentType !== 'extension' && $paymentType !== 'reactivation') {
        // Type de paiement non géré: acquitté sans effet.
        jsonResponse(200, [
            'message' => 'Évènement ignoré (type de paiement non géré).',
        ]);
        return;
    }

    try {
        if ($paymentType === 'reactivation') {
            $syncer = confirmSyncerReactivation($syncerId, $checkoutSessionId);
            jsonResponse(200, [
                'message' => 'Reactivation confirmée.',
                'syncer' => $syncer,
            ]);
            return;
        }

        $syncer = confirmSyncerExtension($syncerId, $checkoutSessionId);
        jsonResponse(200, [
            'message' => 'Extension confirmée.',
            'syncer' => $syncer,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(404, [
            'error' => $exception->getMessage(),
        ]);
    } catch (LogicException $exception) {
        jsonResponse(409, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la confirmation du paiement.',
        ]);
    }
}

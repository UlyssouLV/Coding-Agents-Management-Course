<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/stripeConfig.php';

/**
 * Client Stripe minimal pour les paiements one-off (Extension - ticket 004,
 * ADR-0003: Checkout Session unique, jamais un abonnement).
 *
 * Les clés viennent de config/stripe.local.php (gitignoré ; copier
 * config/stripe.example.php). Si secret_key est vide, Checkout reste un stub
 * local. Si elle est présente, création réelle via l'API REST Stripe (cURL).
 * L'Extension n'est jamais appliquée ici: seul confirmSyncerExtension()
 * (webhook) modifie expiresAt.
 */

/**
 * Indique si une clé secrète Stripe est configurée dans l'environnement.
 */
function isStripeLiveConfigured(): bool
{
    $key = getenv('STRIPE_SECRET_KEY');
    return is_string($key) && trim($key) !== '';
}

/**
 * Crée une Stripe Checkout Session pour un paiement one-off.
 *
 * @param array $params amountCents, currency, syncerId, accountId, type.
 *
 * @return array{id: string, url: string}
 *
 * @throws RuntimeException Si l'appel réseau Stripe échoue (mode live uniquement).
 */
function createStripeCheckoutSession(array $params): array
{
    $amountCents = isset($params['amountCents']) ? (int) $params['amountCents'] : 0;
    $currency = isset($params['currency']) ? (string) $params['currency'] : 'eur';
    $syncerId = isset($params['syncerId']) ? (string) $params['syncerId'] : '';
    $accountId = isset($params['accountId']) ? (string) $params['accountId'] : '';
    $type = isset($params['type']) ? (string) $params['type'] : 'extension';

    if (!isStripeLiveConfigured()) {
        // Stub: pas de clé Stripe disponible, pas d'appel réseau.
        $sessionId = 'cs_test_stub_' . bin2hex(random_bytes(12));
        return [
            'id' => $sessionId,
            'url' => 'https://checkout.stripe.com/c/pay/' . $sessionId . '#stub-no-live-key',
        ];
    }

    $secretKey = (string) getenv('STRIPE_SECRET_KEY');
    $successUrl = getenv('STRIPE_SUCCESS_URL');
    $cancelUrl = getenv('STRIPE_CANCEL_URL');

    $body = http_build_query([
        'mode' => 'payment',
        'success_url' => is_string($successUrl) && $successUrl !== '' ? $successUrl : 'https://example.invalid/checkout/success',
        'cancel_url' => is_string($cancelUrl) && $cancelUrl !== '' ? $cancelUrl : 'https://example.invalid/checkout/cancel',
        'line_items' => [
            [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => 'SyncMates - Extension de Syncer',
                    ],
                ],
            ],
        ],
        'metadata' => [
            'syncerId' => $syncerId,
            'accountId' => $accountId,
            'type' => $type,
        ],
    ]);

    $curlHandle = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($curlHandle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    curl_close($curlHandle);

    if ($response === false) {
        throw new RuntimeException('Erreur réseau Stripe: ' . $curlError);
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded) || !isset($decoded['id'], $decoded['url'])) {
        throw new RuntimeException('Réponse Stripe invalide lors de la création de la Checkout Session.');
    }

    return [
        'id' => (string) $decoded['id'],
        'url' => (string) $decoded['url'],
    ];
}

/**
 * Vérifie la signature d'un évènement webhook Stripe, suivant la pratique
 * documentée par Stripe: HMAC-SHA256 sur "{timestamp}.{payload}", comparé à
 * chaque valeur v1 de l'en-tête Stripe-Signature.
 *
 * @param string $payload         Corps brut de la requête webhook.
 * @param string $signatureHeader Valeur de l'en-tête Stripe-Signature.
 * @param string $secret          Secret webhook Stripe (whsec_...).
 */
function verifyStripeWebhookSignature(string $payload, string $signatureHeader, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $signatureHeader) as $pair) {
        [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
        $parts[$key][] = $value;
    }

    $timestamp = isset($parts['t'][0]) ? $parts['t'][0] : '';
    $signatures = isset($parts['v1']) ? $parts['v1'] : [];
    if ($timestamp === '' || count($signatures) === 0) {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expectedSignature, $signature)) {
            return true;
        }
    }

    return false;
}

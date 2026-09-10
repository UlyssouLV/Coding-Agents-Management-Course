<?php

declare(strict_types=1);

/**
 * Utilitaires HTTP partagés entre les fichiers de routes.
 *
 * Regroupe le parsing JSON entrant, la sérialisation JSON sortante,
 * et la détection HTTPS utilisée pour la pose des cookies de session.
 */

/**
 * Valide et parse un body JSON HTTP.
 *
 * @return array|null Tableau associatif du body JSON, sinon null si erreur.
 */
function parseJsonRequestBody(): ?array
{
    // Vérifie que le client envoie bien du JSON.
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') === false) {
        jsonResponse(400, [
            'error' => 'Content-Type doit être application/json.',
        ]);
        return null;
    }

    // Lit le corps brut de la requête HTTP.
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || $rawBody === '') {
        jsonResponse(400, [
            'error' => 'Corps de requête JSON manquant.',
        ]);
        return null;
    }

    // Parse le JSON en tableau associatif PHP.
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        jsonResponse(400, [
            'error' => 'JSON invalide.',
        ]);
        return null;
    }

    return $payload;
}

/**
 * Envoie une réponse JSON normalisée.
 *
 * @param int   $statusCode Code HTTP à retourner.
 * @param array $body       Payload JSON sérialisable.
 */
function jsonResponse(int $statusCode, array $body): void
{
    http_response_code($statusCode);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
}

/**
 * Détecte si la requête courante passe en HTTPS.
 */
function isHttpsRequest(): bool
{
    $https = isset($_SERVER['HTTPS']) ? (string) $_SERVER['HTTPS'] : '';
    if ($https !== '' && strtolower($https) !== 'off') {
        return true;
    }

    $forwardedProto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] : '';
    return strtolower($forwardedProto) === 'https';
}

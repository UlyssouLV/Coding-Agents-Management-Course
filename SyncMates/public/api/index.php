<?php

declare(strict_types=1);

/**
 * Point d'entrée HTTP de l'API.
 *
 * Ce fichier:
 * - charge les routes API,
 * - normalise la requête entrante,
 * - dispatch les routes supportées,
 * - renvoie 404 JSON pour toute route inconnue.
 */
require_once __DIR__ . '/../../src/routes/syncers.php';
require_once __DIR__ . '/../../src/routes/accounts.php';
require_once __DIR__ . '/../../src/routes/payments.php';

header('Content-Type: application/json; charset=utf-8');

// Récupère les informations principales de la requête courante.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$normalizedPath = rtrim($path, '/');

$isCreateSyncerRoute = $normalizedPath === '/api/syncers';
$isLoginSyncerRoute = $normalizedPath === '/api/syncers/login';
$isCleanupIfNeededRoute = $normalizedPath === '/api/maintenance/cleanup-if-needed';
$getSyncerMatches = [];
$isGetSyncerRoute = preg_match('#^/api/syncers/([^/]+)$#', $normalizedPath, $getSyncerMatches) === 1;
$addParticipantMatches = [];
$isAddParticipantRoute = preg_match('#^/api/syncers/([^/]+)/participants$#', $normalizedPath, $addParticipantMatches) === 1;
$deleteParticipantMatches = [];
$isDeleteParticipantRoute = preg_match('#^/api/syncers/([^/]+)/participants/([^/]+)$#', $normalizedPath, $deleteParticipantMatches) === 1;
$eventPeriodMatches = [];
$isEventPeriodRoute = preg_match('#^/api/syncers/([^/]+)/event-period$#', $normalizedPath, $eventPeriodMatches) === 1;
$participantUnavailabilitiesMatches = [];
$isParticipantUnavailabilitiesRoute = preg_match('#^/api/syncers/([^/]+)/participants/([^/]+)/unavailabilities$#', $normalizedPath, $participantUnavailabilitiesMatches) === 1;
$resultsMatches = [];
$isResultsRoute = preg_match('#^/api/syncers/([^/]+)/results$#', $normalizedPath, $resultsMatches) === 1;
$claimMatches = [];
$isClaimRoute = preg_match('#^/api/syncers/([^/]+)/claim$#', $normalizedPath, $claimMatches) === 1;
$extendMatches = [];
$isExtendRoute = preg_match('#^/api/syncers/([^/]+)/extend$#', $normalizedPath, $extendMatches) === 1;
$reactivateMatches = [];
$isReactivateRoute = preg_match('#^/api/syncers/([^/]+)/reactivate$#', $normalizedPath, $reactivateMatches) === 1;
$isStripeWebhookRoute = $normalizedPath === '/api/stripe/webhook';
$isRegisterAccountRoute = $normalizedPath === '/api/accounts';
$isLoginAccountRoute = $normalizedPath === '/api/accounts/login';
$isGetCurrentAccountRoute = $normalizedPath === '/api/accounts/me';
$isListOwnedSyncersRoute = $normalizedPath === '/api/accounts/me/syncers';

// Route: création d'un Syncer.
if ($isCreateSyncerRoute && $method === 'POST') {
    handleCreateSyncer();
    exit;
}

// Route: connexion à un Syncer.
if ($isLoginSyncerRoute && $method === 'POST') {
    handleLoginSyncer();
    exit;
}

// Route: maintenance opportuniste (nettoyage périodique).
if ($isCleanupIfNeededRoute && $method === 'POST') {
    handleCleanupIfNeeded();
    exit;
}

// Route: détail d'un Syncer.
if ($isGetSyncerRoute && $method === 'GET') {
    $syncerId = isset($getSyncerMatches[1]) ? (string) $getSyncerMatches[1] : '';
    handleGetSyncerDetails($syncerId);
    exit;
}

// Route: ajout d'un participant à un Syncer.
if ($isAddParticipantRoute && $method === 'POST') {
    $syncerId = isset($addParticipantMatches[1]) ? (string) $addParticipantMatches[1] : '';
    handleAddParticipant($syncerId);
    exit;
}

// Route: liste des profils participants d'un Syncer.
if ($isAddParticipantRoute && $method === 'GET') {
    $syncerId = isset($addParticipantMatches[1]) ? (string) $addParticipantMatches[1] : '';
    handleGetSyncerParticipants($syncerId);
    exit;
}

// Route: suppression d'un participant d'un Syncer.
if ($isDeleteParticipantRoute && $method === 'DELETE') {
    $syncerId = isset($deleteParticipantMatches[1]) ? (string) $deleteParticipantMatches[1] : '';
    $participantId = isset($deleteParticipantMatches[2]) ? (string) $deleteParticipantMatches[2] : '';
    handleDeleteParticipant($syncerId, $participantId);
    exit;
}

// Route: configuration de la plage de dates de l'évènement.
if ($isEventPeriodRoute && $method === 'PATCH') {
    $syncerId = isset($eventPeriodMatches[1]) ? (string) $eventPeriodMatches[1] : '';
    handleConfigureEventPeriod($syncerId);
    exit;
}

// Route: résultats de disponibilité d'un Syncer.
if ($isResultsRoute && $method === 'GET') {
    $syncerId = isset($resultsMatches[1]) ? (string) $resultsMatches[1] : '';
    handleGetSyncerResults($syncerId);
    exit;
}

// Route: claim d'un Syncer Free existant par un Account connecté.
if ($isClaimRoute && $method === 'POST') {
    $syncerId = isset($claimMatches[1]) ? (string) $claimMatches[1] : '';
    handleClaimSyncer($syncerId);
    exit;
}

// Route: initiation d'une Extension payante (Stripe Checkout) sur un Syncer possédé.
if ($isExtendRoute && $method === 'POST') {
    $syncerId = isset($extendMatches[1]) ? (string) $extendMatches[1] : '';
    handleInitiateSyncerExtension($syncerId);
    exit;
}

// Route: initiation d'une Reactivation payante (Stripe Checkout) sur un Syncer Archivé.
if ($isReactivateRoute && $method === 'POST') {
    $syncerId = isset($reactivateMatches[1]) ? (string) $reactivateMatches[1] : '';
    handleInitiateSyncerReactivation($syncerId);
    exit;
}

// Route: webhook Stripe (confirmation de paiement d'Extension).
if ($isStripeWebhookRoute && $method === 'POST') {
    handleStripeWebhook();
    exit;
}

// Route: chargement des indisponibilités d'un participant.
if ($isParticipantUnavailabilitiesRoute && $method === 'GET') {
    $syncerId = isset($participantUnavailabilitiesMatches[1]) ? (string) $participantUnavailabilitiesMatches[1] : '';
    $participantId = isset($participantUnavailabilitiesMatches[2]) ? (string) $participantUnavailabilitiesMatches[2] : '';
    handleGetParticipantUnavailabilities($syncerId, $participantId);
    exit;
}

// Route: mise à jour des indisponibilités d'un participant.
if ($isParticipantUnavailabilitiesRoute && $method === 'PATCH') {
    $syncerId = isset($participantUnavailabilitiesMatches[1]) ? (string) $participantUnavailabilitiesMatches[1] : '';
    $participantId = isset($participantUnavailabilitiesMatches[2]) ? (string) $participantUnavailabilitiesMatches[2] : '';
    handleUpdateParticipantUnavailabilities($syncerId, $participantId);
    exit;
}

// Route: inscription d'un Account.
if ($isRegisterAccountRoute && $method === 'POST') {
    handleRegisterAccount();
    exit;
}

// Route: connexion à un Account.
if ($isLoginAccountRoute && $method === 'POST') {
    handleLoginAccount();
    exit;
}

// Route: Account courant (authentifié via Account Session).
if ($isGetCurrentAccountRoute && $method === 'GET') {
    handleGetCurrentAccount();
    exit;
}

// Route: Syncers possédés par l'Account courant (authentifié via Account Session).
if ($isListOwnedSyncersRoute && $method === 'GET') {
    handleListOwnedSyncers();
    exit;
}

// Fallback pour toute route non implémentée.
http_response_code(404);
echo json_encode([
    'error' => 'Route introuvable.',
], JSON_UNESCAPED_UNICODE);


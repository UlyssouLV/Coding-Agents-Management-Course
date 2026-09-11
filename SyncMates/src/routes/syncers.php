<?php

declare(strict_types=1);

/**
 * Routes HTTP liées aux Syncers.
 *
 * Ce fichier gère l'adaptation HTTP:
 * - validation des prérequis de la requête,
 * - parsing du JSON entrant,
 * - mapping vers le service métier,
 * - traduction des exceptions en réponses HTTP.
 */
require_once __DIR__ . '/../services/syncersService.php';
require_once __DIR__ . '/../services/paymentsService.php';
require_once __DIR__ . '/../storage/sessionStore.php';
require_once __DIR__ . '/../utils/http.php';
require_once __DIR__ . '/accounts.php';

const HOST_SESSION_COOKIE_NAME = 'host_session';
const HOST_SESSION_TTL_SECONDS = 300;

/**
 * Traite POST /api/syncers.
 *
 * Lit le payload JSON, valide les champs nécessaires, crée le Syncer
 * via le service métier puis renvoie la réponse API.
 */
function handleCreateSyncer(): void
{
    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $name = isset($payload['name']) ? (string) $payload['name'] : '';
    $password = isset($payload['password']) ? (string) $payload['password'] : '';

    // Optionnelle: une Account Session présente attribue l'Ownership à la
    // création, mais son absence ne doit jamais bloquer la création (ADR-0005).
    $ownerAccountId = resolveOptionalAccountId();

    try {
        // Délègue la création et la persistance au service métier.
        $createdSyncer = createSyncer($name, $password, $ownerAccountId);
        jsonResponse(201, [
            'message' => 'Syncer créé.',
            'syncer' => $createdSyncer,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(409, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la création du Syncer.',
        ]);
    }
}

/**
 * Traite POST /api/syncers/login.
 *
 * Vérifie les identifiants host (nom/ID + mot de passe) et renvoie
 * le Syncer correspondant en cas d'authentification valide.
 */
function handleLoginSyncer(): void
{
    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $identifier = isset($payload['identifier']) ? (string) $payload['identifier'] : '';
    $password = isset($payload['password']) ? (string) $payload['password'] : '';

    try {
        $syncer = loginSyncer($identifier, $password);
        $syncerId = isset($syncer['id']) ? (string) $syncer['id'] : '';
        if ($syncerId === '') {
            throw new RuntimeException('Syncer invalide pour création de session.');
        }

        $session = createHostSessionForSyncer($syncerId, HOST_SESSION_TTL_SECONDS);
        $sessionId = isset($session['sessionId']) ? (string) $session['sessionId'] : '';
        if ($sessionId === '') {
            throw new RuntimeException('Impossible de créer la session host.');
        }
        setHostSessionCookie($sessionId);

        jsonResponse(200, [
            'message' => 'Connexion réussie.',
            'syncer' => $syncer,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(401, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la connexion au Syncer.',
        ]);
    }
}

/**
 * Traite POST /api/syncers/{id}/claim.
 *
 * Établit l'Ownership d'un Compte connecté sur un Syncer Free existant, en
 * vérifiant les identifiants Host propres à ce Syncer (spec: Claim - ADR-0002).
 * Une Account Session valide seule ne suffit jamais.
 *
 * @param string $syncerId Identifiant technique du Syncer visé.
 */
function handleClaimSyncer(string $syncerId): void
{
    $accountId = requireAccountSession();
    if ($accountId === null) {
        return;
    }

    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $identifier = isset($payload['identifier']) ? (string) $payload['identifier'] : '';
    $password = isset($payload['password']) ? (string) $payload['password'] : '';

    try {
        $syncer = claimSyncer($syncerId, $identifier, $password, $accountId);
        jsonResponse(200, [
            'message' => 'Syncer réclamé.',
            'syncer' => $syncer,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(401, [
            'error' => $exception->getMessage(),
        ]);
    } catch (LogicException $exception) {
        jsonResponse(409, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors du claim du Syncer.',
        ]);
    }
}

/**
 * Traite POST /api/syncers/{id}/extend.
 *
 * Initie une Extension payante one-off (Stripe Checkout, ticket 004): crée
 * un paiement 'pending' et renvoie l'URL de Checkout. N'applique jamais
 * l'Extension elle-même (expiresAt inchangé) - voir handleStripeWebhook
 * dans src/routes/payments.php, seul chemin qui applique le paiement
 * confirmé.
 *
 * @param string $syncerId Identifiant technique du Syncer ciblé.
 */
function handleInitiateSyncerExtension(string $syncerId): void
{
    $accountId = requireOwningAccountSessionForSyncer($syncerId);
    if ($accountId === null) {
        return;
    }

    try {
        $result = initiateSyncerExtension($syncerId, $accountId);
        jsonResponse(201, [
            'message' => 'Extension initiée, en attente de confirmation du paiement.',
            'syncer' => $result['syncer'],
            'checkoutUrl' => $result['checkoutUrl'],
            'checkoutSessionId' => $result['checkoutSessionId'],
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(404, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de l\'initiation de l\'Extension.',
        ]);
    }
}

/**
 * Traite POST /api/syncers/{id}/reactivate.
 *
 * Initie une Reactivation payante one-off (Stripe Checkout, ticket 005) sur
 * un Syncer Archivé, dans la fenêtre de Reactivation. Même séparation
 * initiation/confirmation que l'Extension (ticket 004): n'applique jamais la
 * Reactivation elle-même (status/expiresAt inchangés) - voir
 * handleStripeWebhook dans src/routes/payments.php, seul chemin qui applique
 * le paiement confirmé. Gatée comme l'Extension (Account Session propriétaire
 * uniquement), plus la précondition "Archivé et dans la fenêtre" côté service.
 *
 * @param string $syncerId Identifiant technique du Syncer ciblé.
 */
function handleInitiateSyncerReactivation(string $syncerId): void
{
    $accountId = requireOwningAccountSessionForSyncer($syncerId);
    if ($accountId === null) {
        return;
    }

    try {
        $result = initiateSyncerReactivation($syncerId, $accountId);
        jsonResponse(201, [
            'message' => 'Reactivation initiée, en attente de confirmation du paiement.',
            'syncer' => $result['syncer'],
            'checkoutUrl' => $result['checkoutUrl'],
            'checkoutSessionId' => $result['checkoutSessionId'],
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
            'error' => 'Erreur serveur lors de l\'initiation de la Reactivation.',
        ]);
    }
}

/**
 * Traite POST /api/syncers/{id}/participants.
 *
 * Ajoute un participant au Syncer ciblé.
 *
 * @param string $syncerId Identifiant technique du Syncer.
 */
function handleAddParticipant(string $syncerId): void
{
    if (!requireHostOrOwningAccountSessionForSyncer($syncerId)) {
        return;
    }
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $participantName = isset($payload['participantName']) ? (string) $payload['participantName'] : '';

    try {
        $syncer = addParticipantToSyncer($syncerId, $participantName);
        jsonResponse(200, [
            'message' => 'Participant ajouté.',
            'syncer' => $syncer,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(409, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de l\'ajout du participant.',
        ]);
    }
}

/**
 * Traite DELETE /api/syncers/{id}/participants/{participantId}.
 *
 * Supprime un participant du Syncer ciblé.
 *
 * @param string $syncerId      Identifiant technique du Syncer.
 * @param string $participantId Identifiant du participant à supprimer.
 */
function handleDeleteParticipant(string $syncerId, string $participantId): void
{
    if (!requireHostOrOwningAccountSessionForSyncer($syncerId)) {
        return;
    }
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    try {
        $syncer = deleteParticipantFromSyncer($syncerId, $participantId);
        jsonResponse(200, [
            'message' => 'Participant supprimé.',
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
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la suppression du participant.',
        ]);
    }
}

/**
 * Traite PATCH /api/syncers/{id}/event-period.
 *
 * Configure la plage de dates de l'évènement pour le Syncer.
 *
 * @param string $syncerId Identifiant technique du Syncer.
 */
function handleConfigureEventPeriod(string $syncerId): void
{
    if (!requireHostOrOwningAccountSessionForSyncer($syncerId)) {
        return;
    }
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $eventStartDate = isset($payload['eventStartDate']) ? (string) $payload['eventStartDate'] : '';
    $eventEndDate = isset($payload['eventEndDate']) ? (string) $payload['eventEndDate'] : '';

    try {
        $syncer = configureSyncerEventPeriod($syncerId, $eventStartDate, $eventEndDate);
        jsonResponse(200, [
            'message' => 'Période de l\'évènement configurée.',
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
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la configuration de la période.',
        ]);
    }
}

/**
 * Traite GET /api/syncers/{id}.
 *
 * Renvoie le détail public du Syncer, incluant la liste des participants.
 *
 * @param string $syncerId Identifiant technique du Syncer.
 */
function handleGetSyncerDetails(string $syncerId): void
{
    if (!requireHostOrOwningAccountSessionForSyncer($syncerId)) {
        return;
    }
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    try {
        $syncer = getSyncerDetails($syncerId);
        jsonResponse(200, [
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
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la récupération du Syncer.',
        ]);
    }
}

/**
 * Traite GET /api/syncers/{id}/participants.
 *
 * Renvoie le payload public pour la page participant:
 * nom du Syncer, période et profils participants disponibles.
 *
 * @param string $syncerId Identifiant technique du Syncer.
 */
function handleGetSyncerParticipants(string $syncerId): void
{
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    try {
        $payload = getSyncerParticipantsPayload($syncerId);
        jsonResponse(200, [
            'syncer' => $payload,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(404, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la récupération des participants.',
        ]);
    }
}

/**
 * Traite GET /api/syncers/{id}/participants/{participantId}/unavailabilities.
 *
 * @param string $syncerId      Identifiant du Syncer.
 * @param string $participantId Identifiant du participant.
 */
function handleGetParticipantUnavailabilities(string $syncerId, string $participantId): void
{
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    try {
        $participant = getParticipantUnavailabilities($syncerId, $participantId);
        jsonResponse(200, [
            'participant' => $participant,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(404, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors du chargement des indisponibilités.',
        ]);
    }
}

/**
 * Traite PATCH /api/syncers/{id}/participants/{participantId}/unavailabilities.
 *
 * @param string $syncerId      Identifiant du Syncer.
 * @param string $participantId Identifiant du participant.
 */
function handleUpdateParticipantUnavailabilities(string $syncerId, string $participantId): void
{
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $unavailableDates = isset($payload['unavailableDates']) && is_array($payload['unavailableDates'])
        ? $payload['unavailableDates']
        : [];

    try {
        $participant = updateParticipantUnavailabilities($syncerId, $participantId, $unavailableDates);
        jsonResponse(200, [
            'message' => 'Indisponibilités enregistrées.',
            'participant' => $participant,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(404, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de l\'enregistrement des indisponibilités.',
        ]);
    }
}

/**
 * Traite GET /api/syncers/{id}/results.
 *
 * Renvoie les résultats agrégés de disponibilité pour la période du Syncer.
 *
 * @param string $syncerId Identifiant technique du Syncer.
 */
function handleGetSyncerResults(string $syncerId): void
{
    if (!requireSyncerNotArchived($syncerId)) {
        return;
    }

    try {
        $results = getSyncerResults($syncerId);
        jsonResponse(200, [
            'results' => $results,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (DomainException $exception) {
        jsonResponse(404, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors du calcul des résultats.',
        ]);
    }
}

/**
 * Traite POST /api/maintenance/cleanup-if-needed.
 *
 * Déclenche les scripts de nettoyage uniquement si le delta minimal
 * depuis le dernier nettoyage est dépassé.
 */
function handleCleanupIfNeeded(): void
{
    try {
        require_once __DIR__ . '/../services/maintenanceService.php';
        $result = runCleanupIfNeeded(600);
        jsonResponse(200, [
            'message' => 'Maintenance vérifiée.',
            'cleanup' => $result,
        ]);
    } catch (Throwable $exception) {
        appendLocalPhpErrorLog('maintenance-cleanup-error.txt', $exception, [
            'route' => '/api/maintenance/cleanup-if-needed',
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        ]);
        jsonResponse(500, [
            'error' => 'Erreur serveur lors du nettoyage de maintenance.',
        ]);
    }
}

/**
 * Écrit un log d'erreur PHP dans le même dossier que ce fichier.
 *
 * @param string    $filename  Nom du fichier log.
 * @param Throwable $exception Exception capturée.
 * @param array     $context   Contexte optionnel de la requête.
 */
function appendLocalPhpErrorLog(string $filename, Throwable $exception, array $context = []): void
{
    $path = __DIR__ . '/' . $filename;
    $now = gmdate('c');
    $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($contextJson)) {
        $contextJson = '{}';
    }

    $content = "[{$now}] " . get_class($exception) . PHP_EOL
        . 'message: ' . $exception->getMessage() . PHP_EOL
        . 'file: ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL
        . 'context: ' . $contextJson . PHP_EOL
        . 'trace: ' . $exception->getTraceAsString() . PHP_EOL
        . str_repeat('-', 80) . PHP_EOL;

    // Log best effort: ne jamais casser la route si l'écriture échoue.
    @file_put_contents($path, $content, FILE_APPEND | LOCK_EX);
}

/**
 * Vérifie qu'une session host valide autorise l'accès à ce Syncer.
 *
 * @param string $syncerId Identifiant du Syncer ciblé.
 *
 * @return bool true si autorisé, false sinon.
 */
function requireHostSessionForSyncer(string $syncerId): bool
{
    $sessionId = isset($_COOKIE[HOST_SESSION_COOKIE_NAME]) ? (string) $_COOKIE[HOST_SESSION_COOKIE_NAME] : '';
    if ($sessionId === '') {
        jsonResponse(401, [
            'error' => 'Authentification host requise.',
        ]);
        return false;
    }

    $session = getHostSessionById($sessionId);
    if (!is_array($session)) {
        clearHostSessionCookie();
        jsonResponse(401, [
            'error' => 'Session host expirée ou invalide.',
        ]);
        return false;
    }

    $sessionSyncerId = isset($session['syncerId']) ? (string) $session['syncerId'] : '';
    if ($sessionSyncerId !== $syncerId) {
        jsonResponse(403, [
            'error' => 'Accès refusé pour ce Syncer.',
        ]);
        return false;
    }

    return true;
}

/**
 * Vérifie qu'une Host Session scopée à ce Syncer OU une Account Session dont
 * l'Account possède ce Syncer autorise l'accès (spec: "Once a Syncer is
 * owned, the Account Session alone is sufficient" - ADR-0002).
 *
 * Les deux mécanismes restent des vérifications indépendantes (either/or):
 * une Host Session pour un autre Syncer, ou une Account Session pour un
 * Account non-propriétaire, sont chacune rejetées comme si absentes.
 *
 * @param string $syncerId Identifiant du Syncer ciblé.
 *
 * @return bool true si autorisé, false sinon (réponse HTTP déjà émise).
 */
function requireHostOrOwningAccountSessionForSyncer(string $syncerId): bool
{
    // Vérification silencieuse de la Host Session (pas d'effet de bord HTTP
    // ici: le rejet final réutilise requireHostSessionForSyncer plus bas
    // pour garantir la même réponse qu'avant ce ticket dans le cas anonyme).
    $hostSessionId = isset($_COOKIE[HOST_SESSION_COOKIE_NAME]) ? (string) $_COOKIE[HOST_SESSION_COOKIE_NAME] : '';
    if ($hostSessionId !== '') {
        $hostSession = getHostSessionById($hostSessionId);
        if (is_array($hostSession)) {
            $sessionSyncerId = isset($hostSession['syncerId']) ? (string) $hostSession['syncerId'] : '';
            if ($sessionSyncerId === $syncerId) {
                return true;
            }
        }
    }

    // Vérification silencieuse de l'Account Session + Ownership.
    $accountId = resolveOptionalAccountId();
    if ($accountId !== null) {
        $syncer = getSyncerById($syncerId);
        if (is_array($syncer)) {
            $ownerAccountId = isset($syncer['ownerAccountId']) ? $syncer['ownerAccountId'] : null;
            if ($ownerAccountId !== null && $ownerAccountId === $accountId) {
                return true;
            }
        }
    }

    // Ni l'une ni l'autre: réutilise la vérification Host Session existante
    // pour émettre exactement la même réponse qu'avant ce ticket.
    return requireHostSessionForSyncer($syncerId);
}

/**
 * Vérifie qu'un Syncer n'est pas Archivé avant de servir ses données ou
 * d'accepter une action sur son contenu (ticket 005). Pas de mode dégradé
 * lecture seule: Host, Account propriétaire et Participant sont tous
 * bloqués de la même façon quand `status: archived` (spec: "Explicitly out
 * of scope" - Read-only Participant access).
 *
 * Appelée après les vérifications d'authentification/Ownership existantes
 * pour les routes Host-gated, et en tout premier pour les routes
 * Participant (non authentifiées).
 *
 * @param string $syncerId Identifiant du Syncer ciblé.
 *
 * @return bool true si l'accès peut continuer, false si la requête a déjà
 *              reçu une réponse HTTP (404 introuvable, ou 403 archivé).
 */
function requireSyncerNotArchived(string $syncerId): bool
{
    $syncer = getSyncerById($syncerId);
    if (!is_array($syncer)) {
        jsonResponse(404, [
            'error' => 'Syncer introuvable.',
        ]);
        return false;
    }

    $status = isset($syncer['status']) ? (string) $syncer['status'] : 'active';
    if ($status === 'archived') {
        jsonResponse(403, [
            'error' => 'Ce Syncer est archivé en attente de paiement (Reactivation).',
        ]);
        return false;
    }

    return true;
}

/**
 * Vérifie qu'une Account Session valide possède ce Syncer, pour les actions
 * payantes (Extension - ticket 004) qui doivent être déclenchées par
 * l'Account propriétaire lui-même. Contrairement à
 * requireHostOrOwningAccountSessionForSyncer, une Host Session seule ne
 * suffit jamais ici: payer une Extension est une action d'Account, pas de
 * gestion courante du Syncer (spec: "the Account must explicitly trigger and
 * pay for every Extension").
 *
 * Réutilise la même comparaison ownerAccountId === accountId introduite pour
 * l'Ownership au ticket 003, simplement sans le fallback Host Session.
 *
 * @param string $syncerId Identifiant du Syncer ciblé.
 *
 * @return string|null Identifiant de l'Account autorisé, ou null si la
 *                      requête a déjà reçu une réponse (401/403/404/409).
 */
function requireOwningAccountSessionForSyncer(string $syncerId): ?string
{
    $accountId = requireAccountSession();
    if ($accountId === null) {
        return null;
    }

    $syncer = getSyncerById($syncerId);
    if (!is_array($syncer)) {
        jsonResponse(404, [
            'error' => 'Syncer introuvable.',
        ]);
        return null;
    }

    // Précondition spec ("A Paid Syncer must be owned by an Account"):
    // rejetée avant toute interaction Stripe, avec un message distinct du
    // cas "possédé par un autre Account" ci-dessous.
    $ownerAccountId = isset($syncer['ownerAccountId']) ? $syncer['ownerAccountId'] : null;
    if ($ownerAccountId === null) {
        jsonResponse(409, [
            'error' => 'Ce Syncer n\'est pas possédé par un Account: il doit d\'abord être Claim avant de pouvoir être étendu.',
        ]);
        return null;
    }

    if ($ownerAccountId !== $accountId) {
        jsonResponse(403, [
            'error' => 'Ce Syncer appartient à un autre Account.',
        ]);
        return null;
    }

    return $accountId;
}

/**
 * Définit le cookie HttpOnly de session host.
 *
 * @param string $sessionId Identifiant de session host.
 */
function setHostSessionCookie(string $sessionId): void
{
    setcookie(HOST_SESSION_COOKIE_NAME, $sessionId, [
        'expires' => time() + HOST_SESSION_TTL_SECONDS,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Supprime le cookie de session host côté navigateur.
 */
function clearHostSessionCookie(): void
{
    setcookie(HOST_SESSION_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}



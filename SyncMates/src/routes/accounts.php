<?php

declare(strict_types=1);

/**
 * Routes HTTP liées aux Accounts.
 *
 * Ce fichier gère l'adaptation HTTP pour l'inscription, la connexion,
 * et la lecture de son propre Account via l'Account Session.
 *
 * L'Account Session est un mécanisme distinct et coexistant avec la
 * Host Session (voir ADR-0002): elle ne remplace ni ne modifie le
 * flux Host Session de src/routes/syncers.php.
 */
require_once __DIR__ . '/../services/accountsService.php';
require_once __DIR__ . '/../services/syncersService.php';
require_once __DIR__ . '/../storage/sessionStore.php';
require_once __DIR__ . '/../utils/http.php';

const ACCOUNT_SESSION_COOKIE_NAME = 'account_session';

// Contrairement à la Host Session (300s, connexion ponctuelle à un seul
// Syncer), l'Account Session doit rester valide pour un "espace client"
// consulté sur la durée (spec: "manage them from a single client area").
// 30 jours est un compromis courant pour une session de type "rester connecté"
// sans réintroduire un mécanisme de refresh token, hors scope de ce ticket.
const ACCOUNT_SESSION_TTL_SECONDS = 30 * 24 * 60 * 60;

/**
 * Traite POST /api/accounts.
 *
 * Lit le payload JSON, valide les champs nécessaires, crée l'Account
 * via le service métier puis renvoie la réponse API.
 */
function handleRegisterAccount(): void
{
    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $email = isset($payload['email']) ? (string) $payload['email'] : '';
    $password = isset($payload['password']) ? (string) $payload['password'] : '';

    try {
        $createdAccount = registerAccount($email, $password);
        jsonResponse(201, [
            'message' => 'Account créé.',
            'account' => $createdAccount,
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
            'error' => 'Erreur serveur lors de la création de l\'Account.',
        ]);
    }
}

/**
 * Traite POST /api/accounts/login.
 *
 * Vérifie les identifiants (email + mot de passe) et établit une
 * Account Session en cas d'authentification valide.
 */
function handleLoginAccount(): void
{
    $payload = parseJsonRequestBody();
    if (!is_array($payload)) {
        return;
    }

    $email = isset($payload['email']) ? (string) $payload['email'] : '';
    $password = isset($payload['password']) ? (string) $payload['password'] : '';

    try {
        $account = loginAccount($email, $password);
        $accountId = isset($account['id']) ? (string) $account['id'] : '';
        if ($accountId === '') {
            throw new RuntimeException('Account invalide pour création de session.');
        }

        $session = createAccountSessionForAccount($accountId, ACCOUNT_SESSION_TTL_SECONDS);
        $sessionId = isset($session['sessionId']) ? (string) $session['sessionId'] : '';
        if ($sessionId === '') {
            throw new RuntimeException('Impossible de créer la session Account.');
        }
        setAccountSessionCookie($sessionId);

        jsonResponse(200, [
            'message' => 'Connexion réussie.',
            'account' => $account,
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
            'error' => 'Erreur serveur lors de la connexion à l\'Account.',
        ]);
    }
}

/**
 * Traite GET /api/accounts/me.
 *
 * Renvoie l'Account courant à partir de l'Account Session, sans passwordHash.
 */
function handleGetCurrentAccount(): void
{
    $accountId = requireAccountSession();
    if ($accountId === null) {
        return;
    }

    try {
        $account = getAccountDetails($accountId);
        jsonResponse(200, [
            'account' => $account,
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
            'error' => 'Erreur serveur lors de la récupération de l\'Account.',
        ]);
    }
}

/**
 * Traite GET /api/accounts/me/syncers.
 *
 * Renvoie tous les Syncers dont ownerAccountId correspond à l'Account
 * courant (spec: Ownership - rend l'Ownership visible côté client).
 */
function handleListOwnedSyncers(): void
{
    $accountId = requireAccountSession();
    if ($accountId === null) {
        return;
    }

    try {
        $syncers = getSyncersOwnedByAccount($accountId);
        jsonResponse(200, [
            'syncers' => $syncers,
        ]);
    } catch (InvalidArgumentException $exception) {
        jsonResponse(400, [
            'error' => $exception->getMessage(),
        ]);
    } catch (Throwable $exception) {
        jsonResponse(500, [
            'error' => 'Erreur serveur lors de la récupération des Syncers.',
        ]);
    }
}

/**
 * Vérifie qu'une Account Session valide accompagne la requête.
 *
 * @return string|null Identifiant de l'Account authentifié, ou null si la
 *                      requête a déjà reçu une réponse 401 (session absente/invalide).
 */
function requireAccountSession(): ?string
{
    $sessionId = isset($_COOKIE[ACCOUNT_SESSION_COOKIE_NAME]) ? (string) $_COOKIE[ACCOUNT_SESSION_COOKIE_NAME] : '';
    if ($sessionId === '') {
        jsonResponse(401, [
            'error' => 'Authentification Account requise.',
        ]);
        return null;
    }

    $session = getAccountSessionById($sessionId);
    if (!is_array($session)) {
        clearAccountSessionCookie();
        jsonResponse(401, [
            'error' => 'Session Account expirée ou invalide.',
        ]);
        return null;
    }

    $accountId = isset($session['accountId']) ? (string) $session['accountId'] : '';
    if ($accountId === '') {
        clearAccountSessionCookie();
        jsonResponse(401, [
            'error' => 'Session Account invalide.',
        ]);
        return null;
    }

    return $accountId;
}

/**
 * Résout l'Account id de la session courante, sans effet de bord HTTP.
 *
 * Contrairement à requireAccountSession(), n'émet aucune réponse ni cookie: sert
 * aux endpoits où l'Account Session est optionnelle, comme la création de
 * Syncer (ADR-0005: la création anonyme doit rester possible telle quelle).
 *
 * @return string|null Identifiant de l'Account authentifié, ou null si absent/invalide.
 */
function resolveOptionalAccountId(): ?string
{
    $sessionId = isset($_COOKIE[ACCOUNT_SESSION_COOKIE_NAME]) ? (string) $_COOKIE[ACCOUNT_SESSION_COOKIE_NAME] : '';
    if ($sessionId === '') {
        return null;
    }

    $session = getAccountSessionById($sessionId);
    if (!is_array($session)) {
        return null;
    }

    $accountId = isset($session['accountId']) ? (string) $session['accountId'] : '';
    return $accountId !== '' ? $accountId : null;
}

/**
 * Définit le cookie HttpOnly de session Account.
 *
 * Même posture httponly/secure/samesite que setHostSessionCookie
 * (src/routes/syncers.php), seule la durée de vie diffère.
 *
 * @param string $sessionId Identifiant de session Account.
 */
function setAccountSessionCookie(string $sessionId): void
{
    setcookie(ACCOUNT_SESSION_COOKIE_NAME, $sessionId, [
        'expires' => time() + ACCOUNT_SESSION_TTL_SECONDS,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Supprime le cookie de session Account côté navigateur.
 */
function clearAccountSessionCookie(): void
{
    setcookie(ACCOUNT_SESSION_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

<?php

declare(strict_types=1);

/**
 * Service métier des Accounts.
 *
 * Ce fichier contient la logique métier pure:
 * - validation fonctionnelle des entrées,
 * - unicité par email,
 * - hash du mot de passe (même mécanisme que createSyncer),
 * - persistance de l'Account.
 */
require_once __DIR__ . '/../storage/accountStore.php';
require_once __DIR__ . '/../utils/date.php';
require_once __DIR__ . '/../utils/id.php';

/**
 * Enregistre un nouvel Account.
 *
 * @param string $email    Email de l'Account (clé unique).
 * @param string $password Mot de passe brut saisi.
 *
 * @return array Account créé, sans passwordHash.
 *
 * @throws InvalidArgumentException Si les entrées sont invalides.
 * @throws DomainException          Si l'email est déjà utilisé.
 * @throws RuntimeException         Si une erreur de génération/stockage survient.
 */
function registerAccount(string $email, string $password): array
{
    $trimmedEmail = trim($email);
    if ($trimmedEmail === '' || filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Un email valide est requis.');
    }

    if ($password === '') {
        throw new InvalidArgumentException('Le mot de passe est requis.');
    }

    // Empêche la création si l'email est déjà utilisé par un Account existant.
    $existingAccount = findAccountByEmail($trimmedEmail);
    if (is_array($existingAccount)) {
        throw new DomainException('Un Account avec cet email existe déjà.');
    }

    // Génère un ID et évite les collisions disque.
    $accountId = generateAccountId();
    $attempts = 0;
    while (accountExists($accountId) && $attempts < 5) {
        $accountId = generateAccountId();
        $attempts++;
    }

    if (accountExists($accountId)) {
        throw new RuntimeException('Collision d\'identifiant Account.');
    }

    $account = [
        'id' => $accountId,
        'email' => $trimmedEmail,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'createdAt' => nowIso8601(),
    ];

    saveAccount($account);

    unset($account['passwordHash']);
    return $account;
}

/**
 * Authentifie un Account existant.
 *
 * @param string $email    Email saisi.
 * @param string $password Mot de passe brut saisi.
 *
 * @return array Account authentifié, sans passwordHash.
 *
 * @throws InvalidArgumentException Si les entrées sont invalides.
 * @throws DomainException          Si l'authentification échoue.
 */
function loginAccount(string $email, string $password): array
{
    $trimmedEmail = trim($email);
    if ($trimmedEmail === '') {
        throw new InvalidArgumentException('L\'email est requis.');
    }

    if ($password === '') {
        throw new InvalidArgumentException('Le mot de passe est requis.');
    }

    $account = findAccountByEmail($trimmedEmail);
    $passwordHash = is_array($account) && isset($account['passwordHash']) ? (string) $account['passwordHash'] : '';
    if (!is_array($account) || $passwordHash === '' || !password_verify($password, $passwordHash)) {
        throw new DomainException('Identifiants de connexion invalides.');
    }

    unset($account['passwordHash']);
    return $account;
}

/**
 * Retourne les données d'un Account par son identifiant, sans passwordHash.
 *
 * Utilisé par l'endpoint authentifié "qui suis-je".
 *
 * @param string $accountId Identifiant technique de l'Account.
 *
 * @return array Account trouvé, sans passwordHash.
 *
 * @throws InvalidArgumentException Si l'identifiant est vide.
 * @throws DomainException          Si l'Account n'existe pas.
 */
function getAccountDetails(string $accountId): array
{
    $trimmedAccountId = trim($accountId);
    if ($trimmedAccountId === '') {
        throw new InvalidArgumentException('L\'identifiant de l\'Account est requis.');
    }

    $account = getAccountById($trimmedAccountId);
    if (!is_array($account)) {
        throw new DomainException('Account introuvable.');
    }

    unset($account['passwordHash']);
    return $account;
}

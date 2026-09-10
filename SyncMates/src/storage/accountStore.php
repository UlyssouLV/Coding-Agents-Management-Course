<?php

declare(strict_types=1);

/**
 * Couche de stockage JSON des Accounts.
 *
 * Suit le même pattern que jsonStore.php pour les Syncers:
 * un fichier JSON par Account, recherche par glob() pour l'unicité.
 */

/**
 * Retourne le dossier contenant les fichiers Account JSON.
 */
function accountsDataDirectory(): string
{
    return __DIR__ . '/../../data/accounts';
}

/**
 * Crée le dossier de stockage s'il n'existe pas encore.
 */
function ensureAccountsDataDirectoryExists(): void
{
    $dir = accountsDataDirectory();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

/**
 * Construit le chemin absolu du fichier JSON d'un Account.
 *
 * @param string $accountId Identifiant de l'Account.
 */
function accountFilePath(string $accountId): string
{
    return accountsDataDirectory() . '/' . $accountId . '.json';
}

/**
 * Sauvegarde un Account dans son fichier JSON dédié.
 *
 * @param array $account Structure complète de l'Account (doit contenir id).
 *
 * @throws RuntimeException Si l'ID est invalide ou en cas d'erreur disque.
 */
function saveAccount(array $account): void
{
    ensureAccountsDataDirectoryExists();

    $accountId = $account['id'] ?? '';
    if (!is_string($accountId) || $accountId === '') {
        throw new RuntimeException('Identifiant Account invalide.');
    }

    $path = accountFilePath($accountId);

    $json = json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Impossible de sérialiser l\'Account.');
    }

    $bytes = file_put_contents($path, $json, LOCK_EX);
    if ($bytes === false) {
        throw new RuntimeException('Impossible d\'enregistrer l\'Account.');
    }
}

/**
 * Indique si un fichier Account existe déjà pour l'ID donné.
 */
function accountExists(string $accountId): bool
{
    return file_exists(accountFilePath($accountId));
}

/**
 * Lit un fichier Account JSON et retourne son contenu décodé.
 *
 * @param string $path Chemin absolu vers un fichier Account.
 *
 * @return array|null Account décodé, sinon null si invalide.
 */
function readAccountFromPath(string $path): ?array
{
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $account = json_decode($raw, true);
    if (!is_array($account)) {
        return null;
    }

    return $account;
}

/**
 * Recherche un Account par email (comparaison insensible à la casse).
 *
 * L'email étant la clé unique naturelle d'un Account (pas de champ
 * "name" séparé, voir CONTEXT.md), l'unicité à l'enregistrement se
 * vérifie via cette fonction.
 *
 * @param string $email Email à rechercher.
 *
 * @return array|null Account trouvé, sinon null.
 */
function findAccountByEmail(string $email): ?array
{
    ensureAccountsDataDirectoryExists();

    $normalizedEmail = strtolower(trim($email));
    if ($normalizedEmail === '') {
        return null;
    }

    $pattern = accountsDataDirectory() . '/*.json';
    $paths = glob($pattern);
    if (!is_array($paths)) {
        return null;
    }

    foreach ($paths as $path) {
        $account = readAccountFromPath($path);
        if (!is_array($account)) {
            continue;
        }

        $existingEmail = isset($account['email']) ? strtolower((string) $account['email']) : '';
        if ($existingEmail !== '' && $existingEmail === $normalizedEmail) {
            return $account;
        }
    }

    return null;
}

/**
 * Charge un Account depuis son identifiant technique.
 *
 * @param string $accountId Identifiant de l'Account.
 *
 * @return array|null Account trouvé, sinon null.
 */
function getAccountById(string $accountId): ?array
{
    if ($accountId === '') {
        return null;
    }

    $path = accountFilePath($accountId);
    if (!file_exists($path)) {
        return null;
    }

    return readAccountFromPath($path);
}

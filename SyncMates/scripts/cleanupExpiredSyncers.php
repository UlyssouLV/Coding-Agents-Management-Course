<?php

declare(strict_types=1);

/**
 * Script CLI de nettoyage des Syncers expirés.
 *
 * Ce script parcourt tous les fichiers `data/syncers/*.json` et branche par
 * type de Syncer (ADR-0004, ticket 005):
 * - les Syncers invalides (JSON illisible, id manquant) restent supprimés,
 * - un Syncer Free (jamais payé) dont `expiresAt` est dépassé est supprimé,
 *   exactement comme avant ce ticket,
 * - un Syncer Payant dont `expiresAt` est dépassé n'est jamais supprimé: il
 *   passe à `status: archived` (avec `archivedAt`) et reste sur disque, y
 *   compris indéfiniment au-delà de la fenêtre de Reactivation (la
 *   suppression après la Retention Period est hors scope - spec "Explicitly
 *   out of scope", ADR-0004 "Consequences").
 *
 * Usage:
 *   php scripts/cleanupExpiredSyncers.php
 */
require_once __DIR__ . '/../src/storage/jsonStore.php';
require_once __DIR__ . '/../src/utils/date.php';

/**
 * Vérifie si un Syncer est expiré à partir de son champ expiresAt.
 *
 * @param array $syncer Données JSON du Syncer.
 */
function isSyncerExpired(array $syncer): bool
{
    $expiresAt = isset($syncer['expiresAt']) ? (string) $syncer['expiresAt'] : '';
    if ($expiresAt === '') {
        return true;
    }

    $expiresAtTimestamp = strtotime($expiresAt);
    if ($expiresAtTimestamp === false) {
        return true;
    }

    return $expiresAtTimestamp < time();
}

/**
 * Détermine si un Syncer est Payant, c'est-à-dire s'il a un historique de
 * paiement confirmé (ticket 004: Extension). `ownerAccountId` seul ne
 * suffit pas: un Syncer Claim (possédé) mais jamais étendu reste Free tant
 * qu'aucun paiement n'a été confirmé (ticket 005, "Free ... or more
 * precisely: never paid").
 *
 * TICKET_GAP: le ticket dit seulement "has a payment history from Ticket
 * 004" sans préciser paiement confirmé vs en attente. Choix retenu ici: un
 * paiement encore 'pending' n'a rien étendu, donc ne suffit pas à qualifier
 * le Syncer de Payant (cohérent avec la définition CONTEXT.md de Paid
 * Syncer: "persistence has been extended ... through a one-off payment").
 *
 * @param array $syncer Données JSON du Syncer.
 */
function isPaidSyncer(array $syncer): bool
{
    $payments = isset($syncer['payments']) && is_array($syncer['payments']) ? $syncer['payments'] : [];
    foreach ($payments as $payment) {
        $status = isset($payment['status']) ? (string) $payment['status'] : '';
        if ($status === 'confirmed') {
            return true;
        }
    }

    return false;
}

/**
 * Nettoie les Syncers expirés/invalides et retourne un bilan.
 *
 * @return array{scanned:int,deleted:int,archived:int,kept:int,errors:int}
 */
function cleanupExpiredSyncers(): array
{
    ensureSyncersDataDirectoryExists();

    $paths = glob(syncersDataDirectory() . '/*.json');
    if (!is_array($paths)) {
        return [
            'scanned' => 0,
            'deleted' => 0,
            'archived' => 0,
            'kept' => 0,
            'errors' => 1,
        ];
    }

    $scanned = 0;
    $deleted = 0;
    $archived = 0;
    $kept = 0;
    $errors = 0;

    foreach ($paths as $path) {
        $scanned++;

        $syncer = readSyncerFromPath($path);
        if (!is_array($syncer)) {
            if (@unlink($path) === false) {
                $errors++;
            } else {
                $deleted++;
            }
            continue;
        }

        $syncerId = isset($syncer['id']) ? (string) $syncer['id'] : '';
        if ($syncerId === '') {
            if (@unlink($path) === false) {
                $errors++;
            } else {
                $deleted++;
            }
            continue;
        }

        if (isPaidSyncer($syncer)) {
            // Syncer Payant: jamais supprimé par ce script (ADR-0004).
            $status = isset($syncer['status']) ? (string) $syncer['status'] : 'active';
            if ($status !== 'archived' && isSyncerExpired($syncer)) {
                $syncer['status'] = 'archived';
                $syncer['archivedAt'] = nowIso8601();
                saveSyncer($syncer);
                $archived++;
            }
            // Déjà archivé (que la fenêtre de Reactivation soit dépassée ou
            // non) ou encore actif: conservé sur disque dans les deux cas.
            $kept++;
            continue;
        }

        // Syncer Free: comportement inchangé, suppression pure et simple.
        if (isSyncerExpired($syncer)) {
            $syncerPath = syncerFilePath($syncerId);
            if (@unlink($syncerPath) === false) {
                $errors++;
            } else {
                $deleted++;
            }
            continue;
        }

        $kept++;
    }

    return [
        'scanned' => $scanned,
        'deleted' => $deleted,
        'archived' => $archived,
        'kept' => $kept,
        'errors' => $errors,
    ];
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $result = cleanupExpiredSyncers();
    echo json_encode([
        'message' => 'Nettoyage des Syncers terminé.',
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

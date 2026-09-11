<?php

declare(strict_types=1);

/**
 * Charge SyncMates/config/stripe.local.php dans l'environnement du process.
 * Fichier gitignoré — les clés ne vont pas sur GitHub.
 */
function loadStripeConfig(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = dirname(__DIR__, 2) . '/config/stripe.local.php';
    if (!is_file($path)) {
        return;
    }

    $config = require $path;
    if (!is_array($config)) {
        return;
    }

    $map = [
        'secret_key' => 'STRIPE_SECRET_KEY',
        'publishable_key' => 'STRIPE_PUBLISHABLE_KEY',
        'webhook_secret' => 'STRIPE_WEBHOOK_SECRET',
        'success_url' => 'STRIPE_SUCCESS_URL',
        'cancel_url' => 'STRIPE_CANCEL_URL',
    ];

    foreach ($map as $configKey => $envName) {
        $value = isset($config[$configKey]) ? trim((string) $config[$configKey]) : '';
        if ($value === '') {
            continue;
        }
        putenv($envName . '=' . $value);
        $_ENV[$envName] = $value;
    }
}

loadStripeConfig();

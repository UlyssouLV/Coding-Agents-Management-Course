<?php

/**
 * Copier vers stripe.local.php (gitignoré) et coller les clés test.
 * stripe.local.php n'est jamais commité.
 */
declare(strict_types=1);

return [
    'secret_key' => '',
    'publishable_key' => '',
    'webhook_secret' => '',
    'success_url' => 'http://127.0.0.1:8080/?checkout=success',
    'cancel_url' => 'http://127.0.0.1:8080/?checkout=cancel',
];

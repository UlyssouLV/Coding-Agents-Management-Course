<?php

/**
 * Routeur pour `php -S` uniquement (Apache utilise public/.htaccess).
 * Usage, depuis public/ : php -S localhost:8080 dev-router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if (preg_match('#^/api(/|$)#', $uri) === 1) {
    require __DIR__ . '/api/index.php';
    return true;
}

return false;

<?php
/**
 * SSMUNIE — Déconnexion (B1 v2)
 * POST public — détruit la session (fichier BDD + cookie).
 */

declare(strict_types=1);

require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

ssmunie_destroy_session();

apiResponse(['success' => true]);

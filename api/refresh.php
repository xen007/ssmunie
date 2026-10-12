<?php
/**
 * SSMUNIE — Réarmement du timeout idle (B1 v2)
 * POST { action: "refresh" } public — vérifie qu'une session existe.
 */

declare(strict_types=1);

require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

$body = readJsonBody();
if (($body['action'] ?? '') !== 'refresh') {
    apiErrorCode('validation_error', 400);
}

if (empty($_SESSION['user'] ?? null)) {
    apiErrorCode('unauthenticated', 401);
}

$_SESSION['user']['last_activity'] = time();

apiResponse(['success' => true, 'status' => 'active']);

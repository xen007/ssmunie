<?php
/**
 * SSMUNIE — État de session (B1 v2)
 * GET public. Statuts : active | warning | expired | unauthenticated.
 * Sert au bootstrapping front (restaure l'utilisateur) et au polling.
 */

declare(strict_types=1);

require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_helpers.php';

$validation = ssmunie_validate_session();

if (!$validation['ok']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'code'    => $validation['status'] === 'expired' ? 'session_expired' : 'unauthenticated',
        'status'  => $validation['status'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

apiResponse([
    'success'   => true,
    'status'    => $validation['status'],
    'remaining' => $validation['remaining'],
    'user'      => ssmunie_public_user($validation['user']),
]);

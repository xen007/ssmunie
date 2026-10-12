<?php
/**
 * SSMUNIE — Middleware d'authentification (B1 v2)
 * =============================================================
 * Whitelist stricte des endpoints publics. Hors whitelist :
 *   - session valide (fingerprint + double timeout + user en BDD)
 *   - permission requise (variable $requiredPermission posée AVANT le require)
 *   - Sinon 401 / 403 (codes normalisés F19)
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_helpers.php';

const SSMUNIE_WHITELIST = [
    'auth.php',
    'logout.php',
    'check_session.php',
    'refresh.php',
    'getEtablissement.php',
    'checkSetup.php',
    'complete.php',
    'sections.php',
    'filieres.php',
    'annees.php',
];

$calledFile = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (in_array($calledFile, SSMUNIE_WHITELIST, true)) {
    return; // endpoint public : laisser passer
}

// --- Endpoint protégé -------------------------------------------------------
$requiredPermission = $requiredPermission ?? null; // posé par l'endpoint avant le require

$validation = ssmunie_validate_session();
if (!$validation['ok']) {
    apiErrorCode($validation['status'] === 'expired' ? 'session_expired' : 'unauthenticated', 401);
}

if ($requiredPermission !== null && !hasPermission($requiredPermission)) {
    apiErrorCode('forbidden', 403);
}

// Réarme l'idle (le timeout absolu n'est jamais réarmé)
$_SESSION['user']['last_activity'] = time();

<?php
/**
 * SSMUNIE — Changement de mot de passe (B1 v2, F16)
 * POST protégé :
 *   - self-service : { old_password, new_password } (session valide)
 *   - admin pour autrui : { id, new_password } (permission accounts.manage)
 * Le changement bump `session_version` → invalide les sessions de la cible (critère 24).
 */

declare(strict_types=1);

require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

$currentUser = $_SESSION['user'] ?? null;
if (empty($currentUser)) {
    apiErrorCode('unauthenticated', 401);
}

$body        = readJsonBody();
$targetId    = isset($body['id']) ? (int) $body['id'] : (int) ($currentUser['id'] ?? 0);
$isSelf      = $targetId === (int) $currentUser['id'];
$newPassword = (string) ($body['new_password'] ?? '');
$oldPassword = (string) ($body['old_password'] ?? '');

if (strlen($newPassword) < 8) {
    apiErrorCode('validation_error', 400);
}

if ($isSelf) {
    // Self-service : vérifier l'ancien mot de passe
    if ($oldPassword === '') {
        apiErrorCode('validation_error', 400);
    }
    $stmt = $mysqli->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($oldPassword, $row['password'])) {
        apiErrorCode('invalid_credentials', 401);
    }
} else {
    // Admin pour autrui
    requirePermission('accounts.manage');
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $mysqli->prepare('UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?');
$stmt->bind_param('si', $hash, $targetId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected === 0) {
    apiErrorCode('not_found', 404);
}

if ($isSelf) {
    // Rester connecté : synchroniser session_version + régénérer l'id (A4)
    $stmt = $mysqli->prepare('SELECT session_version FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $res = $stmt->get_result();
    $v   = $res->fetch_assoc();
    $stmt->close();

    $_SESSION['user']['session_version'] = (int) ($v['session_version'] ?? 1);
    session_regenerate_id(true);
}

apiResponse(['success' => true]);

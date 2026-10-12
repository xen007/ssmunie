<?php
/**
 * SSMUNIE — Authentification (B1 v2)
 * POST { username, password } → session DB + cookie ssm_sid / __Host-ssm_sid
 */

declare(strict_types=1);

require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

$body     = readJsonBody();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($username === '' || $password === '') {
    apiErrorCode('invalid_credentials', 401);
}

$stmt = $mysqli->prepare('SELECT id, username, email, password, role, statut, session_version
                            FROM users
                           WHERE username = ? OR email = ?
                           LIMIT 1');
$stmt->bind_param('ss', $username, $username);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    apiErrorCode('invalid_credentials', 401);
}
if ((int) $row['statut'] !== 1) {
    apiErrorCode('account_disabled', 403);
}
if (!password_verify($password, $row['password'])) {
    apiErrorCode('invalid_credentials', 401);
}

$userId      = (int) $row['id'];
$roles       = ssmunie_load_roles($mysqli, $userId);
if (empty($roles) && !empty($row['role'])) {
    $roles = [$row['role']]; // repli legacy
}
$permissions = ssmunie_load_permissions($mysqli, $userId);
$primaryRole = $roles[0] ?? ($row['role'] ?: null);

// A4 — régénération de l'id de session (anti-fixation)
session_regenerate_id(true);
error_log('SSMUNIE auth: session_regenerate_id OK pour user #' . $userId);

// A2 — fingerprint, A3 — ancres de timeout
$_SESSION['_fp']      = ssmunie_fingerprint();
$_SESSION['_created'] = time();
$_SESSION['user']     = [
    'id'              => $userId,
    'username'        => $row['username'],
    'email'           => $row['email'],
    'role'            => $primaryRole,
    'roles'           => $roles,
    'permissions'     => $permissions,
    'session_version' => (int) $row['session_version'],
    'last_activity'   => time(),
];

$stmt = $mysqli->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->close();

apiResponse([
    'success' => true,
    'user'    => ssmunie_public_user($_SESSION['user']),
]);

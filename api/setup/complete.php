<?php
/**
 * SSMUNIE — Finalisation du setup (B1 v2)
 * POST public, uniquement si needsSetup.
 * TRANSACTION (F9) + UPSERT (F2) : établissement id=1, année, admin id=1.
 * Auto-login à la fin (session DB).
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

if (!($mysqli instanceof mysqli)) {
    apiErrorCode('setup_failed', 500);
}

$body = readJsonBody();

// 1. needsSetup ?
$row = null;
$res = $mysqli->query('SELECT id, setup_completed FROM etablissement LIMIT 1');
if ($res && ($r = $res->fetch_assoc())) {
    $row = $r;
}
if ($row && (int) $row['setup_completed'] === 1) {
    apiErrorCode('setup_already_completed', 403);
}

// 2. Validation serveur
$etab      = $body['etablissement'] ?? [];
$annee     = $body['annee'] ?? [];
$admin     = $body['admin'] ?? [];

$nom        = trim((string) ($etab['nom'] ?? ''));
$initiale   = strtoupper(substr(trim((string) ($etab['initiale'] ?? '')), 0, 20));
$ministere  = trim((string) ($etab['ministere'] ?? ''));
$contact    = trim((string) ($etab['contact'] ?? ''));
$emailEtab  = trim((string) ($etab['email'] ?? ''));
$adresse    = trim((string) ($etab['adresse'] ?? ''));
$devise     = trim((string) ($etab['devise'] ?? ''));
$logo       = $etab['logo'] ?? null;

$libellee   = trim((string) ($annee['libellee'] ?? ''));
$dateDebut  = (string) ($annee['date_debut'] ?? '');
$dateFin    = (string) ($annee['date_fin'] ?? '');

$username   = trim((string) ($admin['username'] ?? ''));
$emailAdmin = trim((string) ($admin['email'] ?? ''));
$password   = (string) ($admin['password'] ?? '');

if ($nom === '' || $libellee === '' || $dateDebut === '' || $dateFin === '') {
    apiErrorCode('validation_error', 400);
}
if (strtotime($dateFin) <= strtotime($dateDebut)) {
    apiErrorCode('validation_error', 400);
}
if (strlen($username) < 3) {
    apiErrorCode('validation_error', 400);
}
if (strlen($password) < 8) {
    apiErrorCode('validation_error', 400);
}
if ($emailAdmin !== '' && !filter_var($emailAdmin, FILTER_VALIDATE_EMAIL)) {
    apiErrorCode('validation_error', 400);
}

// 3. Transaction
$mysqli->begin_transaction();
try {
    // --- Établissement (upsert) ---
    $etabId = $row ? (int) $row['id'] : 0;
    if ($etabId > 0) {
        $stmt = $mysqli->prepare('UPDATE etablissement
                                  SET nom = ?, initiale = ?, ministere = ?, contact = ?,
                                      email = ?, adresse = ?, devise = ?, logo = ?
                                  WHERE id = ?');
        $stmt->bind_param('ssssssssi', $nom, $initiale, $ministere, $contact, $emailEtab, $adresse, $devise, $logo, $etabId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare('INSERT INTO etablissement (nom, initiale, ministere, contact, email, adresse, devise, logo)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssssss', $nom, $initiale, $ministere, $contact, $emailEtab, $adresse, $devise, $logo);
        $stmt->execute();
        $etabId = (int) $stmt->insert_id;
        $stmt->close();
    }

    // --- Année scolaire (upsert sur libellee) ---
    $anneeId = 0;
    $stmt = $mysqli->prepare('SELECT id FROM annee_scolaire WHERE libellee = ? LIMIT 1');
    $stmt->bind_param('s', $libellee);
    $stmt->execute();
    $resAnnee = $stmt->get_result();
    $stmt->close();
    if ($resAnnee && ($a = $resAnnee->fetch_assoc())) {
        $anneeId = (int) $a['id'];
        $stmt = $mysqli->prepare('UPDATE annee_scolaire SET date_debut = ?, date_fin = ?, statut = 1 WHERE id = ?');
        $stmt->bind_param('ssi', $dateDebut, $dateFin, $anneeId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare('INSERT INTO annee_scolaire (libellee, date_debut, date_fin, statut) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('sss', $libellee, $dateDebut, $dateFin);
        $stmt->execute();
        $anneeId = (int) $stmt->insert_id;
        $stmt->close();
    }

    // --- Admin (upsert sur username) ---
    $adminRole = 'admin';
    $userId = 0;
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $resUser = $stmt->get_result();
    $stmt->close();
    if ($resUser && ($u = $resUser->fetch_assoc())) {
        $userId = (int) $u['id'];
        $hash   = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('UPDATE users SET password = ?, email = ?, role = ?, statut = 1, session_version = session_version + 1 WHERE id = ?');
        $stmt->bind_param('sssi', $hash, $emailAdmin, $adminRole, $userId);
        $stmt->execute();
        $stmt->close();
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('INSERT INTO users (username, email, password, role, statut, created_by) VALUES (?, ?, ?, ?, 1, NULL)');
        $stmt->bind_param('ssss', $username, $emailAdmin, $hash, $adminRole);
        $stmt->execute();
        $userId = (int) $stmt->insert_id;
        $stmt->close();
    }

    // --- Rôle admin ---
    $stmt = $mysqli->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?');
    $stmt->bind_param('is', $userId, $adminRole);
    $stmt->execute();
    $stmt->close();

    // --- Lien année + setup complété ---
    $stmt = $mysqli->prepare('UPDATE etablissement SET annee_courante_id = ?, setup_completed = 1, setup_completed_at = NOW() WHERE id = ?');
    $stmt->bind_param('ii', $anneeId, $etabId);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    error_log('SSMUNIE setup: échec — ' . $e->getMessage());
    apiErrorCode('setup_failed', 500);
}

// 3.5 Base annuelle de la première année (B2) — best effort
$dbName = null;
try {
    $dbName = ssmunie_create_annee_db($anneeId);
} catch (Throwable $e) {
    error_log('SSMUNIE setup: base annuelle échouée — ' . $e->getMessage());
}

// 4. Auto-login
$stmt = $mysqli->prepare('SELECT id, username, email, role, session_version FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$resUser = $stmt->get_result();
$u = $resUser->fetch_assoc();
$stmt->close();

$roles       = ssmunie_load_roles($mysqli, $userId);
$permissions = ssmunie_load_permissions($mysqli, $userId);

session_regenerate_id(true);
$_SESSION['_fp']      = ssmunie_fingerprint();
$_SESSION['_created'] = time();
$_SESSION['user']     = [
    'id'              => $userId,
    'username'        => $u['username'],
    'email'           => $u['email'],
    'role'            => $roles[0] ?? $u['role'],
    'roles'           => $roles,
    'permissions'     => $permissions,
    'session_version' => (int) $u['session_version'],
    'last_activity'   => time(),
];

apiResponse([
    'success' => true,
    'user'    => ssmunie_public_user($_SESSION['user']),
    'dbName'  => $dbName,
]);

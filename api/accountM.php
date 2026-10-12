<?php
/**
 * SSMUNIE — Gestion des comptes (B1 v2)
 * CRUD users. Permission requise : accounts.manage.
 * Garde-fous : pas de suppression de soi-même ni du user id=1 ;
 *              pas de retrait du dernier admin actif.
 */

declare(strict_types=1);

$requiredPermission = 'accounts.manage';
require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_helpers.php';

$method      = $_SERVER['REQUEST_METHOD'] ?? '';
$currentUser = $_SESSION['user'];

/** Convertit des codes de rôles en ids (erreur si code inconnu). */
function rolesCodesToIds(mysqli $db, array $codes): array
{
    if (empty($codes)) {
        return [];
    }
    $ids    = [];
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $db->prepare("SELECT id, code FROM roles WHERE code IN ($placeholders)");
    $types = str_repeat('s', count($codes));
    ssmunie_bind_dynamic($stmt, $types, $codes);
    $stmt->execute();
    $res = $stmt->get_result();
    $found = [];
    while ($r = $res->fetch_assoc()) {
        $found[$r['code']] = (int) $r['id'];
    }
    $stmt->close();

    foreach ($codes as $code) {
        if (!isset($found[$code])) {
            return [];
        }
        $ids[] = $found[$code];
    }
    return $ids;
}

/** Nombre d'admins actifs. */
function countActiveAdmins(mysqli $db): int
{
    $res = $db->query('SELECT COUNT(*) AS n FROM user_roles ur JOIN roles r ON r.id = ur.role_id JOIN users u ON u.id = ur.user_id WHERE r.code = "admin" AND u.statut = 1');
    $row = $res->fetch_assoc();
    return (int) $row['n'];
}

/** L'utilisateur $id a-t-il le rôle admin ? */
function userHasRoleCode(mysqli $db, int $id, string $code): bool
{
    $stmt = $db->prepare('SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code = ? LIMIT 1');
    $stmt->bind_param('is', $id, $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $found = $res->fetch_assoc() !== null;
    $stmt->close();
    return $found;
}

switch ($method) {
    // ------------------------------------------------------------------ GET
    case 'GET':
        $res = $mysqli->query('SELECT id, username, email, role, statut, last_login, created_at, created_by
                                 FROM users
                                ORDER BY id');
        $users = [];
        while ($row = $res->fetch_assoc()) {
            $row['id']       = (int) $row['id'];
            $row['statut']   = (int) $row['statut'];
            $row['roles']    = ssmunie_load_roles($mysqli, (int) $row['id']);
            $users[] = $row;
        }

        // Liste des rôles (pour le formulaire, sans dépendre de roles.manage)
        $resRoles = $mysqli->query('SELECT id, code, libelle_fr, libelle_en, systeme FROM roles ORDER BY id');
        $rolesList = [];
        while ($r = $resRoles->fetch_assoc()) {
            $r['id']      = (int) $r['id'];
            $r['systeme'] = (int) $r['systeme'];
            $rolesList[]  = $r;
        }

        apiResponse(['success' => true, 'users' => $users, 'roles' => $rolesList]);
        // no break (apiResponse exit)

    // ----------------------------------------------------------------- POST
    case 'POST':
        $body     = readJsonBody();
        $username = trim((string) ($body['username'] ?? ''));
        $email    = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $roles    = is_array($body['roles'] ?? null) ? array_values(array_filter($body['roles'], 'is_string')) : [];
        $statut   = isset($body['statut']) ? (int) $body['statut'] : 1;

        if (strlen($username) < 3 || strlen($password) < 8) {
            apiErrorCode('validation_error', 400);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            apiErrorCode('validation_error', 400);
        }

        $roleIds = rolesCodesToIds($mysqli, $roles);
        if ($roles !== [] && empty($roleIds)) {
            apiErrorCode('validation_error', 400); // code de rôle inconnu
        }

        $hash  = password_hash($password, PASSWORD_DEFAULT);
        $primaryRole = $roles[0] ?? '';
        $createdBy = (int) $currentUser['id'];

        try {
            $stmt = $mysqli->prepare('INSERT INTO users (username, email, password, role, statut, created_by)
                                      VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssii', $username, $email, $hash, $primaryRole, $statut, $createdBy);
            $stmt->execute();
            $newId = (int) $stmt->insert_id;
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            apiErrorCode('conflict', 409); // username/email déjà pris
        }

        foreach ($roleIds as $rid) {
            $stmt = $mysqli->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $newId, $rid);
            $stmt->execute();
            $stmt->close();
        }

        apiResponse(['success' => true, 'id' => $newId]);
        // no break

    // ------------------------------------------------------------------ PUT
    case 'PUT':
        $body     = readJsonBody();
        $targetId = (int) ($body['id'] ?? 0);
        if ($targetId <= 0) {
            apiErrorCode('validation_error', 400);
        }

        $stmt = $mysqli->prepare('SELECT id, username, email, role, statut FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $res = $stmt->get_result();
        $target = $res->fetch_assoc();
        $stmt->close();
        if (!$target) {
            apiErrorCode('not_found', 404);
        }

        $email       = array_key_exists('email', $body) ? trim((string) $body['email']) : $target['email'];
        $statut      = array_key_exists('statut', $body) ? (int) $body['statut'] : (int) $target['statut'];
        $newPassword = array_key_exists('password', $body) ? (string) $body['password'] : '';
        $roles       = array_key_exists('roles', $body) ? (is_array($body['roles']) ? array_values(array_filter($body['roles'], 'is_string')) : []) : null;

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            apiErrorCode('validation_error', 400);
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            apiErrorCode('validation_error', 400);
        }

        $roleIds = null;
        if ($roles !== null) {
            $roleIds = rolesCodesToIds($mysqli, $roles);
            if ($roles !== [] && empty($roleIds)) {
                apiErrorCode('validation_error', 400);
            }
            // Garde-fou dernier admin
            if (userHasRoleCode($mysqli, $targetId, 'admin') && !in_array('admin', $roles, true) && countActiveAdmins($mysqli) <= 1) {
                apiErrorCode('forbidden', 403);
            }
        }

        $bump = false;
        $primaryRole = $target['role'];

        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();
            $stmt->close();
            $bump = true;
        }
        if ($roleIds !== null) {
            $primaryRole = $roles[0] ?? '';
            $bump = $bump || $primaryRole !== $target['role'];
            $mysqli->query('DELETE FROM user_roles WHERE user_id = ' . $targetId);
            foreach ($roleIds as $rid) {
                $stmt = $mysqli->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
                $stmt->bind_param('ii', $targetId, $rid);
                $stmt->execute();
                $stmt->close();
            }
        }

        try {
            $stmt = $mysqli->prepare('UPDATE users SET email = ?, role = ?, statut = ? WHERE id = ?');
            $stmt->bind_param('ssii', $email, $primaryRole, $statut, $targetId);
            $stmt->execute();
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            apiErrorCode('conflict', 409);
        }

        // Changement de rôle / mot de passe / statut → invalider les sessions (A4/F3)
        if ($bump || (int) $target['statut'] !== $statut) {
            $stmt = $mysqli->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?');
            $stmt->bind_param('i', $targetId);
            $stmt->execute();
            $stmt->close();

            if ($targetId === (int) $currentUser['id']) {
                // Édition de soi-même : synchroniser sa propre session + régénérer
                $stmt = $mysqli->prepare('SELECT session_version FROM users WHERE id = ?');
                $stmt->bind_param('i', $targetId);
                $stmt->execute();
                $res = $stmt->get_result();
                $v = $res->fetch_assoc();
                $stmt->close();
                $_SESSION['user']['session_version'] = (int) $v['session_version'];
                session_regenerate_id(true);
            } else {
                // Invalider les sessions actives de la cible (A5)
                $stmt = $mysqli->prepare('DELETE FROM sessions WHERE user_id = ?');
                $stmt->bind_param('i', $targetId);
                $stmt->execute();
                $stmt->close();
            }
        }

        apiResponse(['success' => true]);
        // no break

    // -------------------------------------------------------------- DELETE
    case 'DELETE':
        $targetId = 0;
        $body = readJsonBody();
        if (isset($body['id'])) {
            $targetId = (int) $body['id'];
        } elseif (isset($_GET['id'])) {
            $targetId = (int) $_GET['id'];
        }
        if ($targetId <= 0) {
            apiErrorCode('validation_error', 400);
        }
        if ($targetId === (int) $currentUser['id']) {
            apiErrorCode('forbidden', 403); // soi-même
        }
        if ($targetId === 1) {
            apiErrorCode('forbidden', 403); // user système protégé
        }
        if (userHasRoleCode($mysqli, $targetId, 'admin') && countActiveAdmins($mysqli) <= 1) {
            apiErrorCode('forbidden', 403); // dernier admin
        }

        $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            apiErrorCode('not_found', 404);
        }

        // Révocation immédiate des sessions de la cible (A5)
        $stmt = $mysqli->prepare('DELETE FROM sessions WHERE user_id = ?');
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $stmt->close();

        apiResponse(['success' => true]);
        // no break

    default:
        apiErrorCode('method_not_allowed', 405);
}

<?php
/**
 * SSMUNIE — Gestion des rôles (B1 v2)
 * GET + PUT + DELETE (refusé si admin ou rôle système). Permission : roles.manage.
 */

declare(strict_types=1);

$requiredPermission = 'roles.manage';
require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_helpers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';

/** Codes de permissions d'un rôle (admin → ['*']). */
function rolePermissionCodes(mysqli $db, int $roleId, string $code): array
{
    if ($code === 'admin') {
        return ['*'];
    }
    $stmt = $db->prepare('SELECT p.code
                            FROM role_permissions rp
                            JOIN permissions p ON p.id = rp.permission_id
                           WHERE rp.role_id = ?');
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $codes = [];
    while ($row = $res->fetch_assoc()) {
        $codes[] = $row['code'];
    }
    $stmt->close();
    return $codes;
}

switch ($method) {
    // ------------------------------------------------------------------ GET
    case 'GET':
        // Liste de toutes les permissions
        if (isset($_GET['permissions'])) {
            $res = $mysqli->query('SELECT id, code, categorie, libelle_fr, libelle_en FROM permissions ORDER BY categorie, code');
            $permissions = [];
            while ($row = $res->fetch_assoc()) {
                $permissions[] = $row;
            }
            apiResponse(['success' => true, 'permissions' => $permissions]);
        }

        // Liste des rôles + permissions associées
        $res = $mysqli->query('SELECT id, code, libelle_fr, libelle_en, description, systeme FROM roles ORDER BY id');
        $roles = [];
        while ($row = $res->fetch_assoc()) {
            $row['id']          = (int) $row['id'];
            $row['systeme']     = (int) $row['systeme'];
            $row['permissions'] = rolePermissionCodes($mysqli, (int) $row['id'], $row['code']);
            $roles[] = $row;
        }
        apiResponse(['success' => true, 'roles' => $roles]);
        // no break

    // ------------------------------------------------------------------ PUT
    case 'PUT':
        $body = readJsonBody();
        $roleId = (int) ($body['id'] ?? 0);
        $permCodes = is_array($body['permissions'] ?? null) ? array_values(array_filter($body['permissions'], 'is_string')) : null;

        if ($roleId <= 0 || $permCodes === null) {
            apiErrorCode('validation_error', 400);
        }

        $stmt = $mysqli->prepare('SELECT id, code, systeme FROM roles WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        $role = $res->fetch_assoc();
        $stmt->close();

        if (!$role) {
            apiErrorCode('not_found', 404);
        }
        if ($role['code'] === 'admin') {
            apiErrorCode('forbidden', 403); // admin non modifiable
        }

        // Résoudre les codes → ids (erreur si inconnu)
        $permIds = [];
        if (!empty($permCodes)) {
            $placeholders = implode(',', array_fill(0, count($permCodes), '?'));
            $stmt = $mysqli->prepare("SELECT id, code FROM permissions WHERE code IN ($placeholders)");
            $types = str_repeat('s', count($permCodes));
            ssmunie_bind_dynamic($stmt, $types, $permCodes);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $permIds[] = (int) $row['id'];
            }
            $stmt->close();
            if (count($permIds) !== count(array_unique($permCodes))) {
                apiErrorCode('validation_error', 400);
            }
        }

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('DELETE FROM role_permissions WHERE role_id = ?');
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $stmt->close();

            foreach ($permIds as $pid) {
                $stmt = $mysqli->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                $stmt->bind_param('ii', $roleId, $pid);
                $stmt->execute();
                $stmt->close();
            }
            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            apiErrorCode('roles_update_failed', 500);
        }

        apiResponse(['success' => true]);
        // no break

    // -------------------------------------------------------------- DELETE
    case 'DELETE':
        $roleId = 0;
        $body = readJsonBody();
        if (isset($body['id'])) {
            $roleId = (int) $body['id'];
        } elseif (isset($_GET['id'])) {
            $roleId = (int) $_GET['id'];
        }
        if ($roleId <= 0) {
            apiErrorCode('validation_error', 400);
        }

        $stmt = $mysqli->prepare('SELECT id, code, systeme FROM roles WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        $role = $res->fetch_assoc();
        $stmt->close();

        if (!$role) {
            apiErrorCode('not_found', 404);
        }
        if ($role['code'] === 'admin' || (int) $role['systeme'] === 1) {
            apiErrorCode('forbidden', 403);
        }

        $stmt = $mysqli->prepare('DELETE FROM roles WHERE id = ?');
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $stmt->close();

        apiResponse(['success' => true]);
        // no break

    default:
        apiErrorCode('method_not_allowed', 405);
}

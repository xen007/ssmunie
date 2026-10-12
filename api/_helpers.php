<?php
/**
 * SSMUNIE — Helpers API (B1 v2)
 * =============================================================
 * Réponses JSON normalisées (codes d'erreur, F19) + helpers
 * d'authentification / rôles / permissions / sessions.
 */

declare(strict_types=1);

if (!function_exists('apiResponse')) {
    /** Envoie une réponse JSON et termine le script. */
    function apiResponse($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('apiSuccess')) {
    /** Réponse de succès normalisée : {success:true, message, data} */
    function apiSuccess(string $message, $data = null, int $code = 200): void
    {
        apiResponse(['success' => true, 'message' => $message, 'data' => $data], $code);
    }
}

if (!function_exists('apiErrorCode')) {
    /** Réponse d'erreur normalisée : {success:false, code} (F19). */
    function apiErrorCode(string $code, int $http = 400): void
    {
        apiResponse(['success' => false, 'code' => $code], $http);
    }
}

if (!function_exists('readJsonBody')) {
    /** Lit php://input, décode le JSON et retourne un tableau associatif. */
    function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('hasPermission')) {
    /** Vérifie une permission (admin = '*' bypass). */
    function hasPermission(string $code): bool
    {
        $perms = $_SESSION['user']['permissions'] ?? [];
        return in_array('*', $perms, true) || in_array($code, $perms, true);
    }
}

if (!function_exists('requirePermission')) {
    /** Bloque (403) si la permission est absente. */
    function requirePermission(string $code): void
    {
        if (!hasPermission($code)) {
            apiErrorCode('forbidden', 403);
        }
    }
}

if (!function_exists('requireRole')) {
    /** Bloque (403) si le rôle n'est pas présent. */
    function requireRole(string $code): void
    {
        if (!in_array($code, $_SESSION['user']['roles'] ?? [], true)) {
            apiErrorCode('forbidden', 403);
        }
    }
}

if (!function_exists('ssmunie_load_roles')) {
    /** Liste les codes de rôles d'un user (source de vérité : user_roles). */
    function ssmunie_load_roles(mysqli $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT r.code FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.id');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $roles = [];
        while ($row = $res->fetch_assoc()) {
            $roles[] = $row['code'];
        }
        $stmt->close();
        return $roles;
    }
}

if (!function_exists('ssmunie_load_permissions')) {
    /** Permissions d'un user (union des rôles) ; admin → ['*']. */
    function ssmunie_load_permissions(mysqli $db, int $userId): array
    {
        $roles = ssmunie_load_roles($db, $userId);
        if (in_array('admin', $roles, true)) {
            return ['*'];
        }
        if (empty($roles)) {
            return [];
        }
        $stmt = $db->prepare('SELECT DISTINCT p.code
                                FROM user_roles ur
                                JOIN role_permissions rp ON rp.role_id = ur.role_id
                                JOIN permissions p ON p.id = rp.permission_id
                               WHERE ur.user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $perms = [];
        while ($row = $res->fetch_assoc()) {
            $perms[] = $row['code'];
        }
        $stmt->close();
        return $perms;
    }
}

if (!function_exists('ssmunie_destroy_session')) {
    /** Détruit la session (cookie + handler BDD). */
    function ssmunie_destroy_session(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}

if (!function_exists('ssmunie_public_user')) {
    /** Sous-ensemble sûr de l'utilisateur renvoyé au front. */
    function ssmunie_public_user(array $u): array
    {
        return [
            'id'          => (int) ($u['id'] ?? 0),
            'username'    => $u['username'] ?? '',
            'email'       => $u['email'] ?? null,
            'role'        => $u['role'] ?? null,
            'roles'       => $u['roles'] ?? [],
            'permissions' => $u['permissions'] ?? [],
        ];
    }
}

if (!function_exists('ssmunie_validate_session')) {
    /**
     * Valide la session courante : fingerprint (A2), double timeout (A3),
     * revalidation BDD du user (statut + session_version) → F3 + A5.
     *
     * @return array{ok:bool, status:string, http:int, remaining:int, user:array|null}
     */
    function ssmunie_validate_session(): array
    {
        global $mysqli;

        $user = $_SESSION['user'] ?? null;
        if (empty($user)) {
            return ['ok' => false, 'status' => 'unauthenticated', 'http' => 401, 'remaining' => 0, 'user' => null];
        }

        // A2 — fingerprint
        if (!hash_equals((string) ($_SESSION['_fp'] ?? ''), ssmunie_fingerprint())) {
            ssmunie_destroy_session();
            return ['ok' => false, 'status' => 'unauthenticated', 'http' => 401, 'remaining' => 0, 'user' => null];
        }

        // A3 — double timeout
        $idle     = time() - (int) ($user['last_activity'] ?? 0);
        $absolute = time() - (int) ($_SESSION['_created'] ?? time());
        if ($idle > SSMUNIE_IDLE_TIMEOUT || $absolute > SSMUNIE_ABSOLUTE_TIMEOUT) {
            ssmunie_destroy_session();
            return ['ok' => false, 'status' => 'expired', 'http' => 401, 'remaining' => 0, 'user' => null];
        }

        // F3 + A5 — revalidation BDD (user supprimé / désactivé / session_version changé)
        if ($mysqli instanceof mysqli) {
            $uid  = (int) ($user['id'] ?? 0);
            $stmt = $mysqli->prepare('SELECT statut, session_version FROM users WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if (!$row
                || (int) $row['statut'] !== 1
                || (int) $row['session_version'] !== (int) ($user['session_version'] ?? 0)) {
                ssmunie_destroy_session();
                return ['ok' => false, 'status' => 'unauthenticated', 'http' => 401, 'remaining' => 0, 'user' => null];
            }
        }

        $remaining = max(0, SSMUNIE_IDLE_TIMEOUT - $idle);
        $status    = $idle > SSMUNIE_WARN_AFTER ? 'warning' : 'active';

        return ['ok' => true, 'status' => $status, 'http' => 200, 'remaining' => $remaining, 'user' => $user];
    }
}

if (!function_exists('ssmunie_bind_dynamic')) {
    /**
     * bind_param avec un nombre variable de valeurs (contourne l'exigence
     * de passage par référence de mysqli_stmt::bind_param).
     */
    function ssmunie_bind_dynamic(mysqli_stmt $stmt, string $types, array $values): void
    {
        $params = array_merge([$types], $values);
        $refs   = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

<?php
/**
 * SSMUNIE — Matières — CRUD + filtres.
 * GET (settings.view) / POST·PUT·DELETE (settings.edit).
 * Soft delete : actif = 0.
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $requiredPermission = 'settings.view';
} else {
    $requiredPermission = 'settings.edit';
}

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

if ($method === 'GET') {
    $section = (int) ($_GET['section'] ?? 0);
    $filiere = (int) ($_GET['filiere'] ?? 0);
    $niveau  = (int) ($_GET['niveau'] ?? 0);
    $type    = trim((string) ($_GET['type'] ?? ''));
    $actif   = trim((string) ($_GET['actif'] ?? '1'));
    $search  = trim((string) ($_GET['search'] ?? ''));

    $sql = 'SELECT id_matiere, code_matiere, nom_matiere, description_mat, section_id, filiere_id, niveau_id, coefficient, type, actif, annee_id FROM matiere WHERE 1=1';
    $params = [];
    $types  = '';
    if ($section > 0) { $sql .= ' AND section_id = ?'; $params[] = $section; $types .= 'i'; }
    if ($filiere > 0) { $sql .= ' AND filiere_id = ?'; $params[] = $filiere; $types .= 'i'; }
    if ($niveau > 0)  { $sql .= ' AND niveau_id = ?'; $params[] = $niveau; $types .= 'i'; }
    if ($type !== '') { $sql .= ' AND type = ?'; $params[] = $type; $types .= 's'; }
    if ($actif !== '' && $actif !== 'all') { $sql .= ' AND actif = ?'; $params[] = (int) ($actif === '0' ? 0 : 1); $types .= 'i'; }
    if ($search !== '') { $sql .= ' AND (code_matiere LIKE ? OR nom_matiere LIKE ?)'; $like = '%' . $search . '%'; $params[] = $like; $params[] = $like; $types .= 'ss'; }
    $sql .= ' ORDER BY section_id, filiere_id, nom_matiere';

    $stmt = $db->prepare($sql);
    if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $row['id_matiere']  = (int) $row['id_matiere'];
        $row['section_id']  = (int) $row['section_id'];
        $row['filiere_id']  = (int) $row['filiere_id'];
        $row['niveau_id']   = $row['niveau_id'] !== null ? (int) $row['niveau_id'] : 0;
        $row['coefficient'] = (float) $row['coefficient'];
        $row['actif']       = (int) $row['actif'];
        $row['annee_id']    = (int) $row['annee_id'];
        $list[] = $row;
    }
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => $list]);
}

if ($method === 'POST') {
    $body  = readJsonBody();
    $code  = strtoupper(trim((string) ($body['code_matiere'] ?? '')));
    $nom   = trim((string) ($body['nom_matiere'] ?? ''));
    $sec   = (int) ($body['section_id'] ?? 0);
    $fil   = (int) ($body['filiere_id'] ?? 0);
    if ($code === '' || $nom === '' || $sec <= 0 || $fil <= 0) apiErrorCode('validation_error', 400);

    $niv  = (int) ($body['niveau_id'] ?? 0);
    $coef = ($body['coefficient'] ?? null) !== null && ($body['coefficient'] ?? '') !== '' ? (float) ($body['coefficient'] ?? 0) : 1.00;
    $type = in_array($body['type'] ?? '', ['generale','technique','optionnelle','competence'], true) ? ($body['type'] ?? 'generale') : 'generale';
    $actif = (int) ($body['actif'] ?? 1);

    $stmt = $db->prepare('INSERT INTO matiere (code_matiere, nom_matiere, description_mat, section_id, filiere_id, niveau_id, coefficient, type, actif, annee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $desc = trim((string) ($body['description_mat'] ?? ''));
    $stmt->bind_param('sssiiidsii', $code, $nom, $desc, $sec, $fil, $niv, $coef, $type, $actif, $anneeId);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        $db->close();
        if (str_contains($e->getMessage(), 'Duplicate') || $e->getCode() === 1062) apiErrorCode('conflict', 409);
        apiErrorCode('generic', 500);
    }
    $id = (int) $stmt->insert_id;
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => ['id_matiere' => $id]]);
}

if ($method === 'PUT') {
    $body = readJsonBody();
    $id   = (int) ($body['id_matiere'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);

    $code = strtoupper(trim((string) ($body['code_matiere'] ?? '')));
    $nom  = trim((string) ($body['nom_matiere'] ?? ''));
    $sec  = (int) ($body['section_id'] ?? 0);
    $fil  = (int) ($body['filiere_id'] ?? 0);
    if ($code === '' || $nom === '' || $sec <= 0 || $fil <= 0) apiErrorCode('validation_error', 400);

    $niv  = (int) ($body['niveau_id'] ?? 0);
    $coef = ($body['coefficient'] ?? null) !== null && ($body['coefficient'] ?? '') !== '' ? (float) ($body['coefficient'] ?? 0) : 1.00;
    $type = in_array($body['type'] ?? '', ['generale','technique','optionnelle','competence'], true) ? ($body['type'] ?? 'generale') : 'generale';
    $actif = (int) ($body['actif'] ?? 1);
    $desc = trim((string) ($body['description_mat'] ?? ''));

    $stmt = $db->prepare('UPDATE matiere SET code_matiere = ?, nom_matiere = ?, description_mat = ?, section_id = ?, filiere_id = ?, niveau_id = ?, coefficient = ?, type = ?, actif = ? WHERE id_matiere = ?');
    $stmt->bind_param('sssiiidsii', $code, $nom, $desc, $sec, $fil, $niv, $coef, $type, $actif, $id);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        $db->close();
        if (str_contains($e->getMessage(), 'Duplicate') || $e->getCode() === 1062) apiErrorCode('conflict', 409);
        apiErrorCode('generic', 500);
    }
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => ['id_matiere' => $id]]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id_matiere'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);
    $stmt = $db->prepare('UPDATE matiere SET actif = 0 WHERE id_matiere = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);

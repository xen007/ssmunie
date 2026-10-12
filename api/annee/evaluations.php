<?php
/**
 * SSMUNIE — Évaluations (types d'évaluation) — CRUD.
 * GET (settings.view) / POST·PUT·DELETE (settings.edit).
 * Soft delete : actif = 0 (aucun DELETE SQL).
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
if ($db === null) {
    apiErrorCode('no_active_year', 409);
}

if ($method === 'GET') {
    $actif = trim((string) ($_GET['actif'] ?? ''));
    $sql   = 'SELECT id_eval, nom_evaluation, bareme, section_id, niveau_id, type, ordre, actif, annee_id FROM evaluation WHERE 1=1';
    $params = [];
    $types  = '';
    if ($actif !== '' && $actif !== 'all') {
        $sql .= ' AND actif = ?';
        $params[] = (int) ($actif === '0' ? 0 : 1);
        $types .= 'i';
    }
    $sql .= ' ORDER BY ordre, nom_evaluation';
    $stmt = $db->prepare($sql);
    if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $row['id_eval']    = (int) $row['id_eval'];
        $row['bareme']     = (float) $row['bareme'];
        $row['section_id'] = $row['section_id'] !== null ? (int) $row['section_id'] : 0;
        $row['niveau_id']  = $row['niveau_id'] !== null ? (int) $row['niveau_id'] : 0;
        $row['ordre']      = (int) $row['ordre'];
        $row['actif']      = (int) $row['actif'];
        $row['annee_id']   = (int) $row['annee_id'];
        $list[] = $row;
    }
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => $list]);
}

if ($method === 'POST') {
    $body = readJsonBody();
    $nom  = trim((string) ($body['nom_evaluation'] ?? ''));
    if ($nom === '') apiErrorCode('validation_error', 400);

    $bareme = $body['bareme'] !== null && $body['bareme'] !== '' ? (float) $body['bareme'] : 20.00;
    $sec    = (int) ($body['section_id'] ?? 0);
    $niv    = (int) ($body['niveau_id'] ?? 0);
    $type   = in_array($body['type'] ?? '', ['interrogation','devoir','composition','examen','oral','tp','projet'], true) ? $body['type'] : 'devoir';
    $ordre  = (int) ($body['ordre'] ?? 0);
    $actif  = (int) ($body['actif'] ?? 1);

    $stmt = $db->prepare('INSERT INTO evaluation (nom_evaluation, bareme, section_id, niveau_id, type, ordre, actif, annee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sdiiisii', $nom, $bareme, $sec, $niv, $type, $ordre, $actif, $anneeId);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => ['id_eval' => $id]]);
}

if ($method === 'PUT') {
    $body = readJsonBody();
    $id   = (int) ($body['id_eval'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);

    $nom  = trim((string) ($body['nom_evaluation'] ?? ''));
    if ($nom === '') apiErrorCode('validation_error', 400);

    $bareme = $body['bareme'] !== null && $body['bareme'] !== '' ? (float) $body['bareme'] : 20.00;
    $sec    = (int) ($body['section_id'] ?? 0);
    $niv    = (int) ($body['niveau_id'] ?? 0);
    $type   = in_array($body['type'] ?? '', ['interrogation','devoir','composition','examen','oral','tp','projet'], true) ? $body['type'] : 'devoir';
    $ordre  = (int) ($body['ordre'] ?? 0);
    $actif  = (int) ($body['actif'] ?? 1);

    $stmt = $db->prepare('UPDATE evaluation SET nom_evaluation = ?, bareme = ?, section_id = ?, niveau_id = ?, type = ?, ordre = ?, actif = ? WHERE id_eval = ?');
    $stmt->bind_param('sdiiisii', $nom, $bareme, $sec, $niv, $type, $ordre, $actif, $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => ['id_eval' => $id]]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id_eval'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);
    $stmt = $db->prepare('UPDATE evaluation SET actif = 0 WHERE id_eval = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);

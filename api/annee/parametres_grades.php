<?php
/** SSMUNIE — Grades GCE (B4a) — GET (settings.view) / PUT (settings.edit). */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'settings.view'; else $requiredPermission = 'settings.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if ($method === 'GET') {
    $rows = $db->query('SELECT id, examen, grade, note_min, note_max, est_valide FROM parametres_grades WHERE actif = 1 ORDER BY examen, note_max DESC')->fetch_all(MYSQLI_ASSOC);
    $db->close(); apiResponse(['success' => true, 'data' => $rows]);
}
if ($method === 'PUT') {
    $body = readJsonBody(); $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);
    $noteMin = (float) ($body['note_min'] ?? 0); $noteMax = (float) ($body['note_max'] ?? 0); $valide = (int) ($body['est_valide'] ?? 1);
    $stmt = $db->prepare('UPDATE parametres_grades SET note_min = ?, note_max = ?, est_valide = ? WHERE id = ?');
    $stmt->bind_param('ddii', $noteMin, $noteMax, $valide, $id);
    $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success' => true]);
}
apiErrorCode('method_not_allowed', 405);
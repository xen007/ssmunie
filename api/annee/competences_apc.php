<?php
/** SSMUNIE — Référentiel APC (B4a) — CRUD (settings.*). */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'settings.view'; else $requiredPermission = 'settings.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if ($method === 'GET') {
    $rows = $db->query('SELECT id, matiere_id, niveau_id, code, libelle_fr, libelle_en, ordre, actif FROM competences_apc ORDER BY matiere_id, niveau_id, ordre')->fetch_all(MYSQLI_ASSOC);
    $db->close(); apiResponse(['success' => true, 'data' => $rows]);
}
if ($method === 'POST') {
    $body = readJsonBody();
    $code = strtoupper(trim((string) ($body['code'] ?? '')));
    $fr = trim((string) ($body['libelle_fr'] ?? '')); $en = trim((string) ($body['libelle_en'] ?? ''));
    if ($code === '' || $fr === '') apiErrorCode('validation_error', 400);
    $mat = (int) ($body['matiere_id'] ?? 0); $niv = (int) ($body['niveau_id'] ?? 0); $ordre = (int) ($body['ordre'] ?? 0);
    $uuid = ssmunie_uuid();
    $stmt = $db->prepare('INSERT INTO competences_apc (id_uuid, matiere_id, niveau_id, code, libelle_fr, libelle_en, ordre) VALUES (?,?,?,?,?,?,?)');
    $stmt->bind_param('siisssi', $uuid, $mat, $niv, $code, $fr, $en, $ordre);
    $stmt->execute(); $id = $stmt->insert_id; $stmt->close(); $db->close();
    apiResponse(['success' => true, 'data' => ['id' => $id]]);
}
if ($method === 'PUT') {
    $body = readJsonBody(); $id = (int) ($body['id'] ?? 0); if ($id <= 0) apiErrorCode('validation_error', 400);
    $code = strtoupper(trim((string) ($body['code'] ?? '')));
    $fr = trim((string) ($body['libelle_fr'] ?? '')); $en = trim((string) ($body['libelle_en'] ?? ''));
    $ordre = (int) ($body['ordre'] ?? 0); $actif = (int) ($body['actif'] ?? 1);
    $stmt = $db->prepare('UPDATE competences_apc SET code=?, libelle_fr=?, libelle_en=?, ordre=?, actif=? WHERE id=?');
    $stmt->bind_param('sssiii', $code, $fr, $en, $ordre, $actif, $id);
    $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success' => true]);
}
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0); if ($id <= 0) apiErrorCode('validation_error', 400);
    $stmt = $db->prepare('UPDATE competences_apc SET actif = 0 WHERE id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success' => true]);
}
apiErrorCode('method_not_allowed', 405);
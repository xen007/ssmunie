<?php
/** SSMUNIE — Avis pré-remplis de bulletin (B4a) — CRUD (settings.*). */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'settings.view'; else $requiredPermission = 'settings.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if ($method === 'GET') {
    $rows = $db->query('SELECT id, code, section_id, libelle_fr, libelle_en, ordre, actif FROM appreciations_bulletin WHERE actif = 1 ORDER BY ordre')->fetch_all(MYSQLI_ASSOC);
    $db->close(); apiResponse(['success' => true, 'data' => $rows]);
}
if ($method === 'POST') {
    $body = readJsonBody();
    $code = strtolower(trim((string) ($body['code'] ?? ''))); $fr = trim((string) ($body['libelle_fr'] ?? '')); $en = trim((string) ($body['libelle_en'] ?? ''));
    if ($code === '' || $fr === '') apiErrorCode('validation_error', 400);
    $sec = (int) ($body['section_id'] ?? 0); $ordre = (int) ($body['ordre'] ?? 0); $uuid = ssmunie_uuid();
    $stmt = $db->prepare('INSERT INTO appreciations_bulletin (id_uuid, code, section_id, libelle_fr, libelle_en, ordre) VALUES (?,?,?,?,?,?)');
    $stmt->bind_param('ssissi', $uuid, $code, $sec, $fr, $en, $ordre);
    $stmt->execute(); $id = $stmt->insert_id; $stmt->close(); $db->close();
    apiResponse(['success' => true, 'data' => ['id' => $id]]);
}
if ($method === 'PUT') {
    $body = readJsonBody(); $id = (int) ($body['id'] ?? 0); if ($id <= 0) apiErrorCode('validation_error', 400);
    $fr = trim((string) ($body['libelle_fr'] ?? '')); $en = trim((string) ($body['libelle_en'] ?? '')); $ordre = (int) ($body['ordre'] ?? 0);
    $stmt = $db->prepare('UPDATE appreciations_bulletin SET libelle_fr=?, libelle_en=?, ordre=? WHERE id=?');
    $stmt->bind_param('ssii', $fr, $en, $ordre, $id);
    $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success' => true]);
}
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0); if ($id <= 0) apiErrorCode('validation_error', 400);
    $stmt = $db->prepare('UPDATE appreciations_bulletin SET actif = 0 WHERE id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success' => true]);
}
apiErrorCode('method_not_allowed', 405);
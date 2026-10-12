<?php
/** SSMUNIE — Décisions de passage (B4a).
 * GET (grades.view) / POST·PUT·DELETE (grades.edit).
 * decision : admis | admis_conditionnel | redouble | exclu | transfere.
 */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'grades.view'; else $requiredPermission = 'grades.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$DEC = ['admis','admis_conditionnel','redouble','exclu','transfere'];

if ($method === 'GET') {
    $classe = (int) ($_GET['classe'] ?? 0);
    $eleve = (int) ($_GET['eleve'] ?? 0);
    $sql = 'SELECT d.*, e.matricule, e.nom, e.prenom FROM decisions_passage d JOIN eleve e ON e.id_eleve = d.eleve_id WHERE d.deleted_at IS NULL';
    $params = []; $types = '';
    if ($classe > 0) { $sql .= ' AND d.classe_id = ?'; $params[] = $classe; $types .= 'i'; }
    if ($eleve > 0)  { $sql .= ' AND d.eleve_id = ?'; $params[] = $eleve; $types .= 'i'; }
    $sql .= ' ORDER BY e.nom, e.prenom';
    $stmt = $db->prepare($sql);
    if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $db->close();
    apiResponse(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $body = readJsonBody();
    $eleve = (int) ($body['eleve_id'] ?? 0); $classe = (int) ($body['classe_id'] ?? 0);
    $decision = (string) ($body['decision'] ?? '');
    if ($eleve <= 0 || $classe <= 0 || !in_array($decision, $DEC, true)) apiErrorCode('validation_error', 400);
    $uuid = ssmunie_uuid(); $uid = (int) ($_SESSION['user']['id'] ?? 0);
    $motif = trim((string) ($body['motif'] ?? ''));
    $stmt = $db->prepare('INSERT INTO decisions_passage (id_uuid, eleve_id, classe_id, decision, motif, decided_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE decision = VALUES(decision), motif = VALUES(motif), decided_by = VALUES(decided_by), decided_at = NOW()');
    $stmt->bind_param('siissi', $uuid, $eleve, $classe, $decision, $motif, $uid);
    $stmt->execute(); $stmt->close(); $db->close();
    apiResponse(['success' => true]);
}

if ($method === 'PUT') {
    $body = readJsonBody(); $id = (int) ($body['id'] ?? 0);
    $decision = (string) ($body['decision'] ?? '');
    if ($id <= 0 || !in_array($decision, $DEC, true)) apiErrorCode('validation_error', 400);
    $motif = trim((string) ($body['motif'] ?? ''));
    $stmt = $db->prepare('UPDATE decisions_passage SET decision = ?, motif = ? WHERE id = ?');
    $stmt->bind_param('ssi', $decision, $motif, $id);
    $stmt->execute(); $stmt->close(); $db->close();
    apiResponse(['success' => true]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0); $eleve = (int) ($_GET['eleve'] ?? 0); $classe = (int) ($_GET['classe'] ?? 0);
    if ($id > 0) { $stmt = $db->prepare('UPDATE decisions_passage SET deleted_at = NOW() WHERE id = ?'); $stmt->bind_param('i', $id); }
    elseif ($eleve > 0 && $classe > 0) { $stmt = $db->prepare('UPDATE decisions_passage SET deleted_at = NOW() WHERE eleve_id = ? AND classe_id = ?'); $stmt->bind_param('ii', $eleve, $classe); }
    else apiErrorCode('validation_error', 400);
    $stmt->execute(); $stmt->close(); $db->close();
    apiResponse(['success' => true]);
}
apiErrorCode('method_not_allowed', 405);
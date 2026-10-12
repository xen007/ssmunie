<?php
/**
 * SSMUNIE — Notes (B4a) — CRUD + saisie bulk.
 * GET (grades.view) / POST·PUT (grades.edit) / DELETE (grades.edit).
 * Fidèle au flux legacy : check_existing (GET) + RegisterMarks (POST bulk m$seq) + noteEdit (PUT).
 */
declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'grades.view';
else $requiredPermission = 'grades.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
require_once __DIR__ . '/../_notes.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

if ($method === 'GET') {
    $classe  = (int) ($_GET['classe'] ?? 0);
    $matiere = (int) ($_GET['matiere'] ?? 0);
    if ($classe <= 0) apiErrorCode('validation_error', 400);
    $table = ssmunie_note_table($db, $classe);
    $cols  = $table === 'notes_evaluation' ? 'm1,m2,m3,m4,m5,m6,m7,m8,m9' : 'm1,m2,m3,m4,m5,m6';
    $sql = "SELECT n.id, n.id_uuid, n.eleve_id, n.matiere_id, n.classe_id, n.bareme, n.$cols,";
    if ($table === 'notes_evaluation') $sql .= ' n.eval_id, ev.nom_evaluation,';
    $sql .= " e.matricule, e.nom, e.prenom, m.code_matiere, m.nom_matiere";
    $sql .= " FROM $table n";
    $sql .= ' JOIN eleve e ON e.id_eleve = n.eleve_id AND e.deleted_at IS NULL';
    $sql .= ' JOIN matiere m ON m.id_matiere = n.matiere_id';
    if ($table === 'notes_evaluation') $sql .= ' LEFT JOIN evaluation ev ON ev.id_eval = n.eval_id';
    $sql .= ' WHERE n.classe_id = ? AND n.deleted_at IS NULL';
    $params = [$classe]; $types = 'i';
    if ($matiere > 0) { $sql .= ' AND n.matiere_id = ?'; $params[] = $matiere; $types .= 'i'; }
    $sql .= ' ORDER BY e.nom, e.prenom, m.nom_matiere';
    $stmt = $db->prepare($sql);
    ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        foreach (['id','eleve_id','matiere_id','classe_id','eval_id'] as $k) if (isset($row[$k])) $row[$k] = (int)$row[$k];
        if (isset($row['bareme'])) $row['bareme'] = (float)$row['bareme'];
        $list[] = $row;
    }
    $stmt->close(); $db->close();
    apiResponse(['success' => true, 'data' => $list, 'table' => $table]);
}

if ($method === 'POST') {
    $body = readJsonBody();
    $classe  = (int) ($body['classe'] ?? 0);
    $matiere = (int) ($body['matiere'] ?? 0);
    $seq     = (int) ($body['seq'] ?? 0);
    $notes   = $body['notes'] ?? [];
    if ($classe <= 0 || $matiere <= 0 || $seq <= 0 || !is_array($notes)) apiErrorCode('validation_error', 400);
    $table = ssmunie_note_table($db, $classe);
    $maxSeq = ssmunie_note_max_seq(ssmunie_section_of_classe($db, $classe));
    if ($seq < 1 || $seq > $maxSeq) apiErrorCode('validation_error', 400);
    $col = 'm' . $seq;

    $created = 0; $errors = [];
    foreach ($notes as $it) {
        $eleveId = (int) ($it['eleve_id'] ?? 0);
        $note = ($it['note'] ?? '') === '' ? null : (float) $it['note'];
        if ($eleveId <= 0) { $errors[] = 'eleve_id manquant'; continue; }
        $uuid = ssmunie_uuid();
        if ($table === 'notes_evaluation') {
            $evalId = (int) ($it['eval_id'] ?? 0);
            if ($evalId <= 0) { $errors[] = "eval_id manquant (eleve $eleveId)"; continue; }
            $bareme = ($it['bareme'] ?? '') !== '' ? (float) $it['bareme'] : 20.00;
            $stmt = $db->prepare("INSERT INTO notes_evaluation (id_uuid, eleve_id, matiere_id, eval_id, classe_id, $col, bareme) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE $col = VALUES($col)");
            $stmt->bind_param('siiiisi', $uuid, $eleveId, $matiere, $evalId, $classe, $note, $bareme);
        } else {
            $stmt = $db->prepare("INSERT INTO notes_sequence (id_uuid, eleve_id, matiere_id, classe_id, $col) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE $col = VALUES($col)");
            $stmt->bind_param('siiis', $uuid, $eleveId, $matiere, $classe, $note);
        }
        try { $stmt->execute(); $created++; } catch (Throwable $e) { $errors[] = $e->getMessage(); }
        $stmt->close();
    }
    $db->close();
    apiResponse(['success' => true, 'data' => ['created' => $created, 'errors' => $errors]]);
}

if ($method === 'PUT') {
    $body = readJsonBody();
    $id  = (int) ($body['id'] ?? 0);
    $seq = (int) ($body['seq'] ?? 0);
    if ($id <= 0 || $seq < 1 || $seq > 9) apiErrorCode('validation_error', 400);
    $col = 'm' . $seq;
    $note = ($body['note'] ?? '') === '' ? null : (float) $body['note'];
    $stmt = $db->prepare("UPDATE notes_evaluation SET $col = ? WHERE id = ?");
    $stmt->bind_param('di', $note, $id);
    $stmt->execute();
    if ($stmt->affected_rows <= 0) { $stmt->close(); $stmt = $db->prepare("UPDATE notes_sequence SET $col = ? WHERE id = ?"); $stmt->bind_param('di', $note, $id); $stmt->execute(); }
    $stmt->close(); $db->close();
    apiResponse(['success' => true]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);
    $db->query("UPDATE notes_evaluation SET deleted_at = NOW() WHERE id = $id");
    $db->query("UPDATE notes_sequence SET deleted_at = NOW() WHERE id = $id");
    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);
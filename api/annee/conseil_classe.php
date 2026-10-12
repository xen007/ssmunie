<?php
/**
 * SSMUNIE — Conseil de classe (B4a) — validation groupée + rollback.
 * POST {classe_id, decisions:[{eleve_id, decision, motif}]} → transaction (run_id).
 * DELETE ?run_id= → rollback (annule toutes les décisions du run).
 */
declare(strict_types=1);
$requiredPermission = 'grades.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$DEC = ['admis','admis_conditionnel','redouble','exclu','transfere'];

if ($method === 'POST') {
    $body = readJsonBody();
    $classe = (int) ($body['classe_id'] ?? 0);
    $items = $body['decisions'] ?? [];
    if ($classe <= 0 || !is_array($items) || empty($items)) apiErrorCode('validation_error', 400);
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    $runId = (int) ($db->query('SELECT COALESCE(MAX(run_id),0) FROM decisions_passage')->fetch_row()[0]) + 1;
    $db->begin_transaction();
    try {
        foreach ($items as $it) {
            $eleve = (int) ($it['eleve_id'] ?? 0); $decision = (string) ($it['decision'] ?? '');
            if ($eleve <= 0 || !in_array($decision, $DEC, true)) continue;
            $uuid = ssmunie_uuid(); $motif = trim((string) ($it['motif'] ?? ''));
            $stmt = $db->prepare('INSERT INTO decisions_passage (id_uuid, eleve_id, classe_id, decision, motif, run_id, decided_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE decision = VALUES(decision), motif = VALUES(motif), run_id = VALUES(run_id), decided_by = VALUES(decided_by), decided_at = NOW()');
            $stmt->bind_param('siissii', $uuid, $eleve, $classe, $decision, $motif, $runId, $uid);
            $stmt->execute(); $stmt->close();
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); $db->close(); apiErrorCode('generic', 500); }
    $db->close();
    apiResponse(['success' => true, 'data' => ['run_id' => $runId, 'count' => count($items)]]);
}

if ($method === 'DELETE') {
    $runId = (int) ($_GET['run_id'] ?? 0);
    if ($runId <= 0) apiErrorCode('validation_error', 400);
    $stmt = $db->prepare('UPDATE decisions_passage SET deleted_at = NOW() WHERE run_id = ?');
    $stmt->bind_param('i', $runId);
    $stmt->execute(); $affected = $stmt->affected_rows; $stmt->close(); $db->close();
    apiResponse(['success' => true, 'data' => ['rolled_back' => $affected]]);
}
apiErrorCode('method_not_allowed', 405);
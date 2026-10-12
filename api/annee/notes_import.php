<?php
/**
 * SSMUNIE — Import CSV de notes (B4a) — POST multipart (grades.edit).
 * Champ fichier 'file' + champ 'classe' (id).
 * Colonnes : matricule;matiere;seq;note[;eval]  (seq 1..9).
 */
declare(strict_types=1);
$requiredPermission = 'grades.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
require_once __DIR__ . '/../_notes.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') apiErrorCode('method_not_allowed', 405);

function ssmunie_csv_parse(string $c): array {
    $lines = preg_split('/\r\n|\r|\n/', trim($c)); if (!$lines || $lines[0]==='') return [];
    $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
    $delim = substr_count($lines[0],';') >= substr_count($lines[0],',') ? ';' : ',';
    $rows = []; foreach ($lines as $l) { if (trim($l)==='') continue; $rows[] = str_getcsv($l, $delim); }
    return $rows;
}

$file = $_FILES['file'] ?? null;
$classe = (int) ($_POST['classe'] ?? 0);
if (!$file || (int)($file['error'] ?? 1) !== UPLOAD_ERR_OK || $classe <= 0) apiErrorCode('validation_error', 400);
$content = (string) @file_get_contents((string)$file['tmp_name']);
$rows = ssmunie_csv_parse($content);
if (count($rows) < 2) apiErrorCode('validation_error', 400);
$header = array_map(fn($h) => strtolower(trim((string)$h)), $rows[0]);
$data = array_slice($rows, 1);

$table = ssmunie_note_table($db, $classe);
$maxSeq = ssmunie_note_max_seq(ssmunie_section_of_classe($db, $classe));
$created = 0; $errors = [];
foreach ($data as $i => $row) {
    $v = []; foreach ($header as $ci => $col) $v[$col] = isset($row[$ci]) ? trim((string)$row[$ci]) : '';
    $matricule = $v['matricule'] ?? ''; $matiere = $v['matiere'] ?? ''; $seq = (int)($v['seq'] ?? 0); $note = $v['note'] ?? '';
    if ($matricule==='' || $matiere==='' || $seq < 1 || $seq > $maxSeq) { $errors[] = ['line'=>$i+2,'reason'=>'invalid']; continue; }
    $stmt = $db->prepare('SELECT id_eleve FROM eleve WHERE matricule = ? AND classe_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('si', $matricule, $classe); $stmt->execute(); $er = $stmt->get_result(); $el = $er->fetch_assoc(); $stmt->close();
    if (!$el) { $errors[] = ['line'=>$i+2,'reason'=>'eleve_absent','matricule'=>$matricule]; continue; }
    $eleveId = (int)$el['id_eleve'];
    $stmt = $db->prepare('SELECT id_matiere FROM matiere WHERE code_matiere = ? OR nom_matiere = ? LIMIT 1');
    $stmt->bind_param('ss', strtoupper($matiere), $matiere); $stmt->execute(); $mr = $stmt->get_result(); $mt = $mr->fetch_assoc(); $stmt->close();
    if (!$mt) { $errors[] = ['line'=>$i+2,'reason'=>'matiere_absente','matiere'=>$matiere]; continue; }
    $matiereId = (int)$mt['id_matiere']; $col = 'm'.$seq; $noteVal = $note==='' ? null : (float)$note; $uuid = ssmunie_uuid();
    if ($table === 'notes_evaluation') {
        $evalId = (int)($v['eval'] ?? 1); $bareme = ($v['bareme'] ?? '') !== '' ? (float)$v['bareme'] : 20.00;
        $stmt = $db->prepare("INSERT INTO notes_evaluation (id_uuid, eleve_id, matiere_id, eval_id, classe_id, $col, bareme) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE $col = VALUES($col)");
        $stmt->bind_param('siiiisi', $uuid, $eleveId, $matiereId, $evalId, $classe, $noteVal, $bareme);
    } else {
        $stmt = $db->prepare("INSERT INTO notes_sequence (id_uuid, eleve_id, matiere_id, classe_id, $col) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE $col = VALUES($col)");
        $stmt->bind_param('siiis', $uuid, $eleveId, $matiereId, $classe, $noteVal);
    }
    try { $stmt->execute(); $created++; } catch (Throwable $e) { $errors[] = ['line'=>$i+2,'reason'=>$e->getMessage()]; }
    $stmt->close();
}
$db->close();
apiResponse(['success'=>true,'data'=>['created'=>$created,'errors'=>$errors]]);
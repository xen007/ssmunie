<?php
/**
 * SSMUNIE — Consultation globale des notes BRUTES (B4a, innovation).
 * GET (grades.view) ?classe= ou ?section= → toutes les notes non calculées,
 * à imprimer / donner aux élèves ou enseignants (feuille de vérification).
 */
declare(strict_types=1);
$requiredPermission = 'grades.view';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_notes.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$classe = (int) ($_GET['classe'] ?? 0);
$section = (int) ($_GET['section'] ?? 0);
if ($classe <= 0 && $section <= 0) apiErrorCode('validation_error', 400);

$classesWhere = $classe > 0 ? "c.id_classe = $classe" : "c.section_id = $section";
$classes = [];
$res = $db->query("SELECT c.id_classe, c.code_classe, c.libellee_classe, c.section_id FROM classe AS c WHERE c.actif = 1 AND ($classesWhere)");
while ($c = $res->fetch_assoc()) $classes[(int)$c['id_classe']] = $c;

$rows = [];
foreach ($classes as $cid => $c) {
    $table = ssmunie_note_table($db, $cid);
    $cols = $table === 'notes_evaluation' ? 'm1,m2,m3,m4,m5,m6,m7,m8,m9' : 'm1,m2,m3,m4,m5,m6';
    $sql = "SELECT n.id, n.eleve_id, n.matiere_id, n.$cols, n.bareme";
    if ($table === 'notes_evaluation') $sql .= ', n.eval_id, ev.nom_evaluation';
    $sql .= ", e.matricule, e.nom, e.prenom, m.code_matiere, m.nom_matiere";
    $sql .= " FROM $table n JOIN eleve e ON e.id_eleve = n.eleve_id AND e.deleted_at IS NULL JOIN matiere m ON m.id_matiere = n.matiere_id";
    if ($table === 'notes_evaluation') $sql .= ' LEFT JOIN evaluation ev ON ev.id_eval = n.eval_id';
    $sql .= " WHERE n.classe_id = $cid AND n.deleted_at IS NULL ORDER BY e.nom, e.prenom, m.nom_matiere";
    $r2 = $db->query($sql);
    while ($n = $r2->fetch_assoc()) { $n['code_classe'] = $c['code_classe']; $n['section_id'] = (int)$c['section_id']; $rows[] = $n; }
}
$db->close();
apiResponse(['success' => true, 'data' => $rows, 'classes' => array_values($classes)]);
<?php
/**
 * SSMUNIE — Génération de bulletin (B4a) — GET (grades.view).
 * ?classe=<id>&report_type=trim1|trim2|trim3|annual
 * Exécute le moteur de la section et renvoie le bulletin calculé.
 */
declare(strict_types=1);
$requiredPermission = 'grades.view';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
require_once __DIR__ . '/../_notes.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$classe = (int) ($_GET['classe'] ?? 0);
$report = trim((string) ($_GET['report_type'] ?? 'trim1'));
if ($classe <= 0) apiErrorCode('validation_error', 400);
if (!in_array($report, ['trim1','trim2','trim3','annual','seq'], true)) $report = 'trim1';

$section = ssmunie_section_of_classe($db, $classe);
$moteur = ssmunie_moteur($section);

// Config
$params = [];
foreach ($db->query("SELECT cle, valeur, type FROM parametres_niveau WHERE actif = 1 AND section_id = $section")->fetch_all(MYSQLI_ASSOC) as $r) {
    $params[$r['cle']] = $r['type'] === 'number' ? (float) $r['valeur'] : ($r['type'] === 'boolean' ? ($r['valeur'] === 'true') : $r['valeur']);
}
$echelles = $db->query("SELECT code, note_min FROM echelles_appreciation WHERE actif = 1 AND section_id = $section ORDER BY note_min DESC")->fetch_all(MYSQLI_ASSOC);
$grades = $db->query('SELECT examen, grade, note_min, note_max, est_valide FROM parametres_grades WHERE actif = 1 ORDER BY examen, note_max DESC')->fetch_all(MYSQLI_ASSOC);
$paliers = [];
try {
    $paliers = $db->query('SELECT heures_min, heures_max, sanction, exclusion_definitive FROM parametres_absence WHERE actif = 1 ORDER BY heures_min')->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $paliers = []; // B4c (absences) pas encore livré → aucune sanction
}

// Élèves
$eleves = [];
$res = $db->query("SELECT id_eleve, matricule, nom, prenom, genre FROM eleve WHERE classe_id = $classe AND deleted_at IS NULL ORDER BY nom, prenom");
while ($e = $res->fetch_assoc()) $eleves[$e['id_eleve']] = $e;

// Notes
$table = ssmunie_note_table($db, $classe);
if ($table === 'notes_evaluation') {
    $sql = "SELECT n.eleve_id, n.matiere_id, n.eval_id, n.bareme, n.m1,n.m2,n.m3,n.m4,n.m5,n.m6,n.m7,n.m8,n.m9, m.nom_matiere, m.code_matiere, ev.nom_evaluation";
    $sql .= ' FROM notes_evaluation n JOIN matiere m ON m.id_matiere = n.matiere_id LEFT JOIN evaluation ev ON ev.id_eval = n.eval_id';
    $sql .= " WHERE n.classe_id = $classe AND n.deleted_at IS NULL ORDER BY n.eleve_id, m.nom_matiere, n.eval_id";
} else {
    $sql = "SELECT n.eleve_id, n.matiere_id, n.m1,n.m2,n.m3,n.m4,n.m5,n.m6, m.nom_matiere, m.code_matiere, m.coefficient AS coef";
    $sql .= ' FROM notes_sequence n JOIN matiere m ON m.id_matiere = n.matiere_id';
    $sql .= " WHERE n.classe_id = $classe AND n.deleted_at IS NULL ORDER BY n.eleve_id, m.nom_matiere";
}
$res = $db->query($sql);
$notesByEleve = [];
while ($n = $res->fetch_assoc()) {
    $notesByEleve[(int)$n['eleve_id']][] = $n;
}

$students = [];
foreach ($eleves as $id => $e) {
    $students[] = ['eleve_id' => $id, 'matricule' => $e['matricule'], 'nom' => $e['nom'], 'prenom' => $e['prenom'], 'notes' => $notesByEleve[$id] ?? []];
}
$db->close();

$res = $moteur->calculer($students, [
    'report_type' => $report,
    'echelles' => $echelles,
    'grades' => $grades,
    'parametres' => $params,
    'paliers_absence' => $paliers,
    'niveau_code' => 'MOYEN',
]);

apiResponse(['success' => true, 'data' => $res, 'section_id' => $section, 'report_type' => $report]);
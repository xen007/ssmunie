<?php
/**
 * SSMUNIE — Matrice d'habilitations (lignes = enseignants, colonnes = matières).
 * GET (hr.view). Filtres : ?section=&filiere=&niveau=&classe=
 * Retourne enseignants, matières, classes et les cellules cochées.
 */

declare(strict_types=1);

$requiredPermission = 'hr.view';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$section = (int) ($_GET['section'] ?? 0);
$filiere = (int) ($_GET['filiere'] ?? 0);
$niveau  = (int) ($_GET['niveau'] ?? 0);
$classe  = (int) ($_GET['classe'] ?? 0);

// --- Matières (colonnes), filtrées par périmètre ---
$matSql = 'SELECT id_matiere, code_matiere, nom_matiere, section_id, filiere_id, niveau_id, coefficient, type FROM matiere WHERE actif = 1';
$mparams = [];
$mtypes  = '';
if ($section > 0) { $matSql .= ' AND section_id = ?'; $mparams[] = $section; $mtypes .= 'i'; }
if ($filiere > 0) { $matSql .= ' AND filiere_id = ?'; $mparams[] = $filiere; $mtypes .= 'i'; }
if ($niveau > 0)  { $matSql .= ' AND (niveau_id = ? OR niveau_id = 0 OR niveau_id IS NULL)'; $mparams[] = $niveau; $mtypes .= 'i'; }
$matSql .= ' ORDER BY section_id, filiere_id, nom_matiere';
$mstmt = $db->prepare($matSql);
if ($mtypes !== '') ssmunie_bind_dynamic($mstmt, $mtypes, $mparams);
$mstmt->execute();
$mres = $mstmt->get_result();
$matieres = [];
while ($row = $mres->fetch_assoc()) {
    $row['id_matiere'] = (int) $row['id_matiere'];
    $row['section_id'] = (int) $row['section_id'];
    $row['filiere_id'] = (int) $row['filiere_id'];
    $row['niveau_id']  = $row['niveau_id'] !== null ? (int) $row['niveau_id'] : 0;
    $row['coefficient'] = (float) $row['coefficient'];
    $matieres[] = $row;
}
$mstmt->close();

// --- Enseignants (lignes) ---
$eSql = 'SELECT id_enseignant, matricule_ens, civilite, nom, prenom, specialite FROM enseignant WHERE deleted_at IS NULL ORDER BY nom, prenom';
$eres = $db->query($eSql);
$enseignants = [];
while ($row = $eres->fetch_assoc()) {
    $row['id_enseignant'] = (int) $row['id_enseignant'];
    $enseignants[] = $row;
}

// --- Classes (pour le filtre) ---
$cSql = "SELECT id_classe, code_classe, libellee_classe, niveau_id, section_id, filiere_id FROM classe WHERE actif = 1";
$cparams = [];
$ctypes  = '';
if ($section > 0) { $cSql .= ' AND section_id = ?'; $cparams[] = $section; $ctypes .= 'i'; }
if ($filiere > 0) { $cSql .= ' AND filiere_id = ?'; $cparams[] = $filiere; $ctypes .= 'i'; }
if ($niveau > 0)  { $cSql .= ' AND niveau_id = ?'; $cparams[] = $niveau; $ctypes .= 'i'; }
$cSql .= ' ORDER BY code_classe';
$cstmt = $db->prepare($cSql);
if ($ctypes !== '') ssmunie_bind_dynamic($cstmt, $ctypes, $cparams);
$cstmt->execute();
$cres = $cstmt->get_result();
$classes = [];
while ($row = $cres->fetch_assoc()) {
    $row['id_classe']  = (int) $row['id_classe'];
    $row['niveau_id']  = (int) $row['niveau_id'];
    $row['section_id'] = (int) $row['section_id'];
    $row['filiere_id'] = (int) $row['filiere_id'];
    $classes[] = $row;
}
$cstmt->close();

// --- Cellules cochées ---
$hSql = 'SELECT id, enseignant_id, matiere_id, classe_id FROM enseignant_matiere WHERE annee_id = ?';
$hparams = [$anneeId];
$htypes  = 'i';
if ($classe > 0) { $hSql .= ' AND classe_id = ?'; $hparams[] = $classe; $htypes .= 'i'; }
else { $hSql .= ' AND classe_id IS NULL'; }
$hstmt = $db->prepare($hSql);
ssmunie_bind_dynamic($hstmt, $htypes, $hparams);
$hstmt->execute();
$hres = $hstmt->get_result();
$cells = [];
while ($row = $hres->fetch_assoc()) {
    $cells[] = [
        'id'            => (int) $row['id'],
        'enseignant_id' => (int) $row['enseignant_id'],
        'matiere_id'    => (int) $row['matiere_id'],
        'classe_id'     => $row['classe_id'] !== null ? (int) $row['classe_id'] : null,
    ];
}
$hstmt->close();
$db->close();

apiResponse([
    'success' => true,
    'data'    => [
        'enseignants' => $enseignants,
        'matieres'    => $matieres,
        'classes'     => $classes,
        'cells'       => $cells,
        'annee_id'    => $anneeId,
    ],
]);

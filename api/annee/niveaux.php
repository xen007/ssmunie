<?php
/**
 * SSMUNIE — Niveaux de la base annuelle courante — GET protégé.
 * Filtres optionnels : ?section=<id>&filiere=<id>
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$open = ssmunie_open_current_annee();
if ($open === null) {
    apiErrorCode('no_active_year', 409);
}
[, $db] = $open;

$section = isset($_GET['section']) ? (int) $_GET['section'] : 0;
$filiere = isset($_GET['filiere']) ? (int) $_GET['filiere'] : 0;

$sql    = 'SELECT id_niveau, section_id, filiere_id, code_niveau, libellee_niveau, ordre, annee_id FROM niveau WHERE 1=1';
$params = [];
$types  = '';
if ($section > 0) { $sql .= ' AND section_id = ?'; $params[] = $section; $types .= 'i'; }
if ($filiere > 0) { $sql .= ' AND filiere_id = ?'; $params[] = $filiere; $types .= 'i'; }
$sql .= ' ORDER BY section_id, filiere_id, ordre';

$stmt = $db->prepare($sql);
if ($types !== '') {
    ssmunie_bind_dynamic($stmt, $types, $params);
}
$stmt->execute();
$res = $stmt->get_result();
$niveaux = [];
while ($row = $res->fetch_assoc()) {
    $row['id_niveau']  = (int) $row['id_niveau'];
    $row['section_id'] = (int) $row['section_id'];
    $row['filiere_id'] = (int) $row['filiere_id'];
    $row['ordre']      = (int) $row['ordre'];
    $row['annee_id']   = (int) $row['annee_id'];
    $niveaux[] = $row;
}
$stmt->close();
$db->close();

apiResponse(['success' => true, 'data' => $niveaux]);

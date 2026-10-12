<?php
/**
 * SSMUNIE — Séries de la base annuelle courante — GET protégé.
 * Filtres optionnels : ?section=<id>&filiere=<id>&cycle=<1|2>
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
$cycle   = isset($_GET['cycle']) ? (int) $_GET['cycle'] : 0;

$sql    = 'SELECT id_serie, section_id, filiere_id, code_serie, libellee_serie, cycle, annee_id FROM serie WHERE 1=1';
$params = [];
$types  = '';
if ($section > 0) { $sql .= ' AND section_id = ?'; $params[] = $section; $types .= 'i'; }
if ($filiere > 0) { $sql .= ' AND filiere_id = ?'; $params[] = $filiere; $types .= 'i'; }
if ($cycle > 0)   { $sql .= ' AND cycle = ?';      $params[] = $cycle;   $types .= 'i'; }
$sql .= ' ORDER BY section_id, filiere_id, cycle, code_serie';

$stmt = $db->prepare($sql);
if ($types !== '') {
    ssmunie_bind_dynamic($stmt, $types, $params);
}
$stmt->execute();
$res = $stmt->get_result();
$series = [];
while ($row = $res->fetch_assoc()) {
    $row['id_serie']   = (int) $row['id_serie'];
    $row['section_id'] = (int) $row['section_id'];
    $row['filiere_id'] = (int) $row['filiere_id'];
    $row['cycle']      = (int) $row['cycle'];
    $row['annee_id']   = (int) $row['annee_id'];
    $series[] = $row;
}
$stmt->close();
$db->close();

apiResponse(['success' => true, 'data' => $series]);

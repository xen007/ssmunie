<?php
/**
 * SSMUNIE — Options (spécialités) de la base annuelle courante — GET protégé.
 * Filtres optionnels : ?secteur=<industriel|commercial|...>
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$open = ssmunie_open_current_annee();
if ($open === null) {
    apiErrorCode('no_active_year', 409);
}
[, $db] = $open;

$secteur = trim((string) ($_GET['secteur'] ?? ''));

$sql    = 'SELECT id_option, serie_id, section_id, filiere_id, code_option, libellee_option, secteur, domaine, annee_id FROM `option` WHERE 1=1';
$params = [];
$types  = '';
if ($secteur !== '') { $sql .= ' AND secteur = ?'; $params[] = $secteur; $types .= 's'; }
$sql .= ' ORDER BY secteur, code_option';

$stmt = $db->prepare($sql);
if ($types !== '') {
    ssmunie_bind_dynamic($stmt, $types, $params);
}
$stmt->execute();
$res = $stmt->get_result();
$options = [];
while ($row = $res->fetch_assoc()) {
    $row['id_option']  = (int) $row['id_option'];
    $row['serie_id']   = $row['serie_id'] !== null ? (int) $row['serie_id'] : null;
    $row['section_id'] = (int) $row['section_id'];
    $row['filiere_id'] = (int) $row['filiere_id'];
    $row['annee_id']   = (int) $row['annee_id'];
    $options[] = $row;
}
$stmt->close();
$db->close();

apiResponse(['success' => true, 'data' => $options]);

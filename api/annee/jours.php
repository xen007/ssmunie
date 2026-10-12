<?php
/**
 * SSMUNIE — Jours de la semaine — GET protégé.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$open = ssmunie_open_current_annee();
if ($open === null) {
    apiErrorCode('no_active_year', 409);
}
[, $db] = $open;

$res = $db->query('SELECT id_jour, libellee_jour, ordre, actif FROM jour ORDER BY ordre');
$jours = [];
while ($row = $res->fetch_assoc()) {
    $row['id_jour'] = (int) $row['id_jour'];
    $row['ordre']   = (int) $row['ordre'];
    $row['actif']   = (int) $row['actif'];
    $jours[] = $row;
}
$db->close();

apiResponse(['success' => true, 'data' => $jours]);

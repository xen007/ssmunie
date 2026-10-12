<?php
/**
 * SSMUNIE — Mois (ordre scolaire) — GET protégé.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$open = ssmunie_open_current_annee();
if ($open === null) {
    apiErrorCode('no_active_year', 409);
}
[, $db] = $open;

$res = $db->query('SELECT id_mois, libellee_mois, ordre_scolaire FROM mois ORDER BY ordre_scolaire');
$mois = [];
while ($row = $res->fetch_assoc()) {
    $row['id_mois']        = (int) $row['id_mois'];
    $row['ordre_scolaire'] = (int) $row['ordre_scolaire'];
    $mois[] = $row;
}
$db->close();

apiResponse(['success' => true, 'data' => $mois]);

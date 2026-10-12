<?php
/**
 * SSMUNIE — Liste des années scolaires — GET public.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$res = $mysqli->query('SELECT id, libellee, date_debut, date_fin, statut
                         FROM annee_scolaire
                        ORDER BY date_debut DESC');
$annees = [];
while ($row = $res->fetch_assoc()) {
    $row['id']     = (int) $row['id'];
    $row['statut'] = (int) $row['statut'];
    $annees[] = $row;
}

apiResponse(['success' => true, 'data' => $annees]);

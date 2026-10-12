<?php
/**
 * SSMUNIE — Référentiel : filières (3) — GET public.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$res = $mysqli->query('SELECT id, code, libelle_fr, libelle_en
                         FROM filiere
                        ORDER BY id');
$filieres = [];
while ($row = $res->fetch_assoc()) {
    $row['id'] = (int) $row['id'];
    $filieres[] = $row;
}

apiResponse(['success' => true, 'data' => $filieres]);

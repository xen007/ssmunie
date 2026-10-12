<?php
/**
 * SSMUNIE — Référentiel : sections (4) — GET public.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$res = $mysqli->query('SELECT id, code, libelle_fr, libelle_en, ministere, examen_final
                         FROM section
                        ORDER BY id');
$sections = [];
while ($row = $res->fetch_assoc()) {
    $row['id'] = (int) $row['id'];
    $sections[] = $row;
}

apiResponse(['success' => true, 'data' => $sections]);

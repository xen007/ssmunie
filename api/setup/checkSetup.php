<?php
/**
 * SSMUNIE — État du setup (B1 v2)
 * GET public → { success: true, needsSetup: bool, etablissement }
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

$needsSetup   = true;
$etablissement = null;

if ($mysqli instanceof mysqli) {
    $res = $mysqli->query('SELECT setup_completed, nom FROM etablissement LIMIT 1');
    if ($res && ($row = $res->fetch_assoc())) {
        $needsSetup    = ((int) $row['setup_completed'] !== 1);
        $etablissement = $row;
    }
}

apiResponse([
    'success'       => true,
    'needsSetup'    => $needsSetup,
    'etablissement' => $etablissement,
]);

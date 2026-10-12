<?php
/**
 * SSMUNIE — Aperçu du prochain matricule (SANS incrémenter).
 * GET ?mode=standard|section|classe|enseignant&section=<id>&classe=<id>
 * Permission : students.view.
 */

declare(strict_types=1);

$requiredPermission = 'students.view';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_matricule.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$mode    = trim((string) ($_GET['mode'] ?? 'standard'));
$section = (int) ($_GET['section'] ?? 0);
$classe  = isset($_GET['classe']) && $_GET['classe'] !== '' ? (int) $_GET['classe'] : null;

if (!in_array($mode, ['standard', 'section', 'classe', 'enseignant'], true)) {
    apiErrorCode('validation_error', 400);
}

$preview = ssmunie_peek_matricule($mode, $anneeId, $section, $classe, $db);
$db->close();

apiResponse([
    'success' => true,
    'data'    => [
        'matricule' => $preview,
        'mode'      => $mode,
        'annee_id'  => $anneeId,
    ],
]);

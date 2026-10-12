<?php
/**
 * SSMUNIE — Import CSV matières (settings.edit).
 * POST multipart 'file'. Colonnes : code_matiere, nom_matiere, section,
 * filiere, coefficient, type, actif.
 */

declare(strict_types=1);

$requiredPermission = 'settings.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

function ssmunie_csv_parse(string $content): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (!$lines || $lines[0] === '') return [];
    $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);
    $first = $lines[0];
    $delim = (substr_count($first, ';') >= substr_count($first, ',')) ? ';' : ',';
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line, $delim);
    }
    return $rows;
}

$file = $_FILES['file'] ?? null;
if (!$file || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
    apiErrorCode('validation_error', 400);
}
$content = (string) @file_get_contents((string) $file['tmp_name']);
$rows = ssmunie_csv_parse($content);
if (count($rows) < 2) apiErrorCode('validation_error', 400);

$header = array_map(function ($h) { return strtolower(trim((string) $h)); }, $rows[0]);
$dataRows = array_slice($rows, 1);

$secMap = []; $filMap = [];
foreach ($db->query('SELECT id_section, code FROM section')->fetch_all(MYSQLI_ASSOC) as $r) $secMap[strtoupper($r['code'])] = (int) $r['id_section'];
foreach ($db->query('SELECT id_filiere, code FROM filiere')->fetch_all(MYSQLI_ASSOC) as $r) $filMap[strtoupper($r['code'])] = (int) $r['id_filiere'];

$created = 0; $skipped = 0; $errors = [];

foreach ($dataRows as $idx => $row) {
    $lineNo = $idx + 2;
    $vals = [];
    foreach ($header as $ci => $col) $vals[$col] = isset($row[$ci]) ? trim((string) $row[$ci]) : '';

    $code = strtoupper($vals['code_matiere'] ?? '');
    $nom  = $vals['nom_matiere'] ?? '';
    if ($code === '' || $nom === '') { $errors[] = ['line' => $lineNo, 'reason' => 'missing_fields']; continue; }

    $secIn = strtoupper($vals['section'] ?? '');
    $filIn = strtoupper($vals['filiere'] ?? '');
    $section = isset($secMap[$secIn]) ? $secMap[$secIn] : (int) $secIn;
    $filiere = isset($filMap[$filIn]) ? $filMap[$filIn] : (int) $filIn;
    if ($section <= 0 || $filiere <= 0) { $errors[] = ['line' => $lineNo, 'reason' => 'invalid_scope']; continue; }

    $coef = $vals['coefficient'] !== '' ? (float) $vals['coefficient'] : 1.00;
    $type = in_array(strtolower($vals['type'] ?? ''), ['generale','technique','optionnelle','competence'], true) ? strtolower($vals['type']) : 'generale';
    $actif = (int) ($vals['actif'] ?? 1);

    $stmt = $db->prepare('INSERT IGNORE INTO matiere (code_matiere, nom_matiere, section_id, filiere_id, niveau_id, coefficient, type, actif, annee_id) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)');
    $stmt->bind_param('ssiidsii', $code, $nom, $section, $filiere, $coef, $type, $actif, $anneeId);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $created++; else $skipped++;
    $stmt->close();
}

$db->close();
apiResponse(['success' => true, 'data' => ['created' => $created, 'skipped' => $skipped, 'errors' => $errors]]);

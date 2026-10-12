<?php
/**
 * SSMUNIE — Import CSV élèves (students.edit).
 * POST multipart 'file' (CSV avec ligne d'en-tête).
 * Colonnes : nom, prenom, date_naissance, genre, section, filiere, niveau,
 *            classe, nom_tuteur, telephone_tuteur, nom_mere, telephone_mere, adresse.
 * Détection doublons (nom+prenom+date_naissance ou matricule) + transaction.
 */

declare(strict_types=1);

$requiredPermission = 'students.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_matricule.php';

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

$header   = array_map(function ($h) { return strtolower(trim((string) $h)); }, $rows[0]);
$dataRows = array_slice($rows, 1);

// Lookups (codes → ids)
$secMap = []; $filMap = []; $nivMap = []; $clsMap = [];
foreach ($db->query('SELECT id_section, code FROM section')->fetch_all(MYSQLI_ASSOC) as $r) $secMap[strtoupper($r['code'])] = (int) $r['id_section'];
foreach ($db->query('SELECT id_filiere, code FROM filiere')->fetch_all(MYSQLI_ASSOC) as $r) $filMap[strtoupper($r['code'])] = (int) $r['id_filiere'];
foreach ($db->query('SELECT id_niveau, code_niveau, section_id, filiere_id FROM niveau')->fetch_all(MYSQLI_ASSOC) as $r) $nivMap[strtoupper((string) $r['code_niveau'])] = $r;
foreach ($db->query('SELECT id_classe, code_classe, niveau_id, section_id, filiere_id FROM classe')->fetch_all(MYSQLI_ASSOC) as $r) $clsMap[strtoupper((string) $r['code_classe'])] = $r;

$uid = (int) ($_SESSION['user']['id'] ?? 0);
$valid = [];
$duplicates = [];
$errors = [];
$seen = [];

foreach ($dataRows as $idx => $row) {
    $lineNo = $idx + 2;
    $vals = [];
    foreach ($header as $ci => $col) {
        $vals[$col] = isset($row[$ci]) ? trim((string) $row[$ci]) : '';
    }

    $nom    = $vals['nom'] ?? '';
    $prenom = $vals['prenom'] ?? '';
    if ($nom === '' || $prenom === '') {
        $errors[] = ['line' => $lineNo, 'reason' => 'missing_name'];
        continue;
    }
    $dna = $vals['date_naissance'] ?? '';

    // Doublons (BDD)
    if ($dna === '') {
        $stmt = $db->prepare('SELECT id_eleve FROM eleve WHERE nom = ? AND prenom = ? AND date_naissance IS NULL AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('ss', $nom, $prenom);
    } else {
        $stmt = $db->prepare('SELECT id_eleve FROM eleve WHERE nom = ? AND prenom = ? AND date_naissance = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('sss', $nom, $prenom, $dna);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $dup = (bool) $res->fetch_assoc();
    $stmt->close();

    $key = strtolower($nom . '|' . $prenom . '|' . $dna);
    if ($dup || isset($seen[$key])) {
        $duplicates[] = ['line' => $lineNo, 'nom' => $nom, 'prenom' => $prenom];
        continue;
    }
    $seen[$key] = true;

    // Résolution section/filière/niveau/classe
    $section = 0; $filiere = 0; $niveauId = null; $classeId = null;

    $clsIn = strtoupper($vals['classe'] ?? '');
    if ($clsIn !== '') {
        if (isset($clsMap[$clsIn])) {
            $c = $clsMap[$clsIn];
            $classeId = (int) $c['id_classe'];
            $section  = (int) $c['section_id'];
            $filiere  = (int) $c['filiere_id'];
            $niveauId = (int) $c['niveau_id'];
        } else {
            $classeId = (int) $clsIn;
        }
    }

    $secIn = strtoupper($vals['section'] ?? '');
    if ($section <= 0 && $secIn !== '') {
        $section = isset($secMap[$secIn]) ? $secMap[$secIn] : (int) $secIn;
    }
    $filIn = strtoupper($vals['filiere'] ?? '');
    if ($filiere <= 0 && $filIn !== '') {
        $filiere = isset($filMap[$filIn]) ? $filMap[$filIn] : (int) $filIn;
    }
    if ($niveauId === null) {
        $nivIn = strtoupper($vals['niveau'] ?? '');
        if ($nivIn !== '') {
            if (isset($nivMap[$nivIn])) {
                $n = $nivMap[$nivIn];
                $niveauId = (int) $n['id_niveau'];
                if ($section <= 0) $section = (int) $n['section_id'];
                if ($filiere <= 0) $filiere = (int) $n['filiere_id'];
            } else {
                $niveauId = (int) $nivIn;
            }
        }
    }

    if ($section <= 0 || $filiere <= 0) {
        $errors[] = ['line' => $lineNo, 'reason' => 'invalid_scope'];
        continue;
    }

    $matricule = ssmunie_generer_matricule('standard', $anneeId, $section, $classeId, $db);
    $valid[] = [
        'matricule' => $matricule,
        'nom' => $nom, 'prenom' => $prenom, 'date_naissance' => $dna,
        'genre' => in_array(strtoupper($vals['genre'] ?? ''), ['M', 'F'], true) ? strtoupper($vals['genre']) : null,
        'classe_id' => $classeId, 'niveau_id' => $niveauId, 'section_id' => $section, 'filiere_id' => $filiere,
        'nom_tuteur' => $vals['nom_tuteur'] ?? '', 'telephone_tuteur' => $vals['telephone_tuteur'] ?? '',
        'nom_mere' => $vals['nom_mere'] ?? '', 'telephone_mere' => $vals['telephone_mere'] ?? '',
        'adresse' => $vals['adresse'] ?? '',
    ];
}

// Insertion transactionnelle
$created = 0;
if (!empty($valid)) {
    $db->begin_transaction();
    try {
        foreach ($valid as $v) {
            $s = function ($x) { $x = trim((string) $x); return $x === '' ? null : $x; };
            $cols = []; $types = ''; $params = [];
            $add = function ($col, $type, $val) use (&$cols, &$types, &$params) {
                $cols[] = $col; $types .= $type; $params[] = $val;
            };
            $add('matricule', 's', $v['matricule']);
            $add('matricule_mode', 's', 'standard');
            $add('nom', 's', $v['nom']);
            $add('prenom', 's', $v['prenom']);
            $add('date_naissance', 's', $s($v['date_naissance']));
            $add('genre', 's', $v['genre']);
            $add('classe_id', 'i', $v['classe_id']);
            $add('niveau_id', 'i', $v['niveau_id']);
            $add('section_id', 'i', $v['section_id']);
            $add('filiere_id', 'i', $v['filiere_id']);
            $add('nom_tuteur', 's', $s($v['nom_tuteur']));
            $add('telephone_tuteur', 's', $s($v['telephone_tuteur']));
            $add('nom_mere', 's', $s($v['nom_mere']));
            $add('telephone_mere', 's', $s($v['telephone_mere']));
            $add('adresse', 's', $s($v['adresse']));
            $add('created_by', 'i', $uid > 0 ? $uid : null);
            $add('annee_id', 'i', $anneeId);

            $sql = 'INSERT INTO eleve (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $stmt = $db->prepare($sql);
            ssmunie_bind_dynamic($stmt, $types, $params);
            $stmt->execute();
            $stmt->close();
            $created++;
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $created = 0;
        $errors[] = ['line' => 0, 'reason' => 'transaction_rolled_back'];
    }
}

$db->close();
apiResponse(['success' => true, 'data' => ['created' => $created, 'duplicates' => $duplicates, 'errors' => $errors]]);

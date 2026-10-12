<?php
/**
 * SSMUNIE — Élève — CRUD complet (fiche).
 * GET (students.view) / POST·PUT (students.edit) / DELETE (students.delete).
 * Matricule auto-généré (race-safe) selon matricule_mode.
 * Soft delete : deleted_at = NOW().
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET')      $requiredPermission = 'students.view';
elseif ($method === 'DELETE') $requiredPermission = 'students.delete';
else                        $requiredPermission = 'students.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_matricule.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

/** Lit une classe : section_id, filiere_id, niveau_id, code_classe. */
function ssmunie_eleve_classe(mysqli $db, int $classeId): ?array
{
    if ($classeId <= 0) return null;
    $stmt = $db->prepare('SELECT id_classe, code_classe, libellee_classe, niveau_id, section_id, filiere_id FROM classe WHERE id_classe = ? LIMIT 1');
    $stmt->bind_param('i', $classeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Récupère un élève complet (jointures labels). */
function ssmunie_eleve_get(mysqli $db, int $id): ?array
{
    $sql = "SELECT e.*, c.code_classe, c.libellee_classe, n.code_niveau, n.libellee_niveau,
                   s.code AS section_code, f.code AS filiere_code
              FROM eleve e
              LEFT JOIN classe c ON c.id_classe = e.classe_id
              LEFT JOIN niveau n ON n.id_niveau = e.niveau_id
              LEFT JOIN section s ON s.id_section = e.section_id
              LEFT JOIN filiere f ON f.id_filiere = e.filiere_id
             WHERE e.id_eleve = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if (!$row) return null;

    foreach (['id_eleve','classe_id','niveau_id','section_id','filiere_id','redoublant','malade','annee_id','created_by','updated_by'] as $k) {
        if (isset($row[$k])) $row[$k] = $row[$k] !== null ? (int) $row[$k] : null;
    }
    return $row;
}

if ($method === 'GET') {
    $mat = trim((string) ($_GET['matricule'] ?? ''));
    $id  = (int) ($_GET['id'] ?? 0);
    $eleve = null;
    if ($id > 0) {
        $eleve = ssmunie_eleve_get($db, $id);
    } elseif ($mat !== '') {
        $stmt = $db->prepare('SELECT id_eleve FROM eleve WHERE matricule = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('s', $mat);
        $stmt->execute();
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $stmt->close();
        if ($r) $eleve = ssmunie_eleve_get($db, (int) $r['id_eleve']);
    }
    $db->close();
    if (!$eleve || ($eleve['deleted_at'] ?? null) !== null) apiErrorCode('not_found', 404);
    apiResponse(['success' => true, 'data' => $eleve]);
}

if ($method === 'POST') {
    $body = readJsonBody();
    $nom    = trim((string) ($body['nom'] ?? ''));
    $prenom = trim((string) ($body['prenom'] ?? ''));
    $section = (int) ($body['section_id'] ?? 0);
    $filiere = (int) ($body['filiere_id'] ?? 0);
    if ($nom === '' || $prenom === '') apiErrorCode('validation_error', 400);

    $mode   = in_array($body['matricule_mode'] ?? 'standard', ['standard','section','classe'], true) ? ($body['matricule_mode'] ?? 'standard') : 'standard';
    $classeId = isset($body['classe_id']) && $body['classe_id'] !== '' ? (int) $body['classe_id'] : null;

    // Résolution section / filière / niveau (classe prioritaire)
    $classeRow = $classeId ? ssmunie_eleve_classe($db, $classeId) : null;
    if ($classeRow) {
        $section = (int) $classeRow['section_id'];
        $filiere = (int) $classeRow['filiere_id'];
    }
    if ($section <= 0 || $filiere <= 0) apiErrorCode('validation_error', 400);

    $niveauId = $classeRow ? (int) $classeRow['niveau_id'] : (isset($body['niveau_id']) && $body['niveau_id'] !== '' ? (int) $body['niveau_id'] : null);
    if ($mode === 'classe' && !$classeId) apiErrorCode('validation_error', 400);

    // Matricule
    $matricule = ssmunie_generer_matricule($mode, $anneeId, $section, $classeId, $db);

    $uid = (int) ($_SESSION['user']['id'] ?? 0);

    $cols = []; $types = ''; $vals = [];
    $add = function ($col, $type, $val) use (&$cols, &$types, &$vals) {
        $cols[] = $col; $types .= $type; $vals[] = $val;
    };
    $s = function ($v) { $v = trim((string) $v); return $v === '' ? null : $v; };
    $d = function ($v) { $v = trim((string) $v); return $v === '' ? null : $v; };

    $add('matricule', 's', $matricule);
    $add('matricule_mode', 's', $mode);
    $add('nom', 's', $nom);
    $add('prenom', 's', $prenom);
    $add('date_naissance', 's', $d($body['date_naissance'] ?? ''));
    $add('lieu_naissance', 's', $s($body['lieu_naissance'] ?? ''));
    $add('genre', 's', in_array($body['genre'] ?? '', ['M','F'], true) ? $body['genre'] : null);
    $add('nationalite', 's', $s($body['nationalite'] ?? 'Camerounaise'));
    $add('adresse', 's', $s($body['adresse'] ?? ''));
    $add('nom_tuteur', 's', $s($body['nom_tuteur'] ?? ''));
    $add('telephone_tuteur', 's', $s($body['telephone_tuteur'] ?? ''));
    $add('profession_tuteur', 's', $s($body['profession_tuteur'] ?? ''));
    $add('nom_mere', 's', $s($body['nom_mere'] ?? ''));
    $add('telephone_mere', 's', $s($body['telephone_mere'] ?? ''));
    $add('profession_mere', 's', $s($body['profession_mere'] ?? ''));
    $add('email_parent', 's', $s($body['email_parent'] ?? ''));
    $add('classe_id', 'i', $classeId);
    $add('niveau_id', 'i', $niveauId);
    $add('section_id', 'i', $section);
    $add('filiere_id', 'i', $filiere);
    $add('acte_naissance', 's', $s($body['acte_naissance'] ?? ''));
    $add('code_parent', 's', $s($body['code_parent'] ?? ''));
    $add('statut', 's', in_array($body['statut'] ?? 'actif', ['actif','inactif','diplome','transfere','decede'], true) ? ($body['statut'] ?? 'actif') : 'actif');
    $add('motif_statut', 's', $s($body['motif_statut'] ?? ''));
    $add('redoublant', 'i', (int) ($body['redoublant'] ?? 0) ? 1 : 0);
    $add('anciennete', 's', $s($body['anciennete'] ?? ''));
    $add('sante', 's', $s($body['sante'] ?? ''));
    $add('malade', 'i', (int) ($body['malade'] ?? 0) ? 1 : 0);
    $add('ethnie', 's', $s($body['ethnie'] ?? ''));
    $add('infos_supplementaire', 's', $s($body['infos_supplementaire'] ?? ''));
    $add('created_by', 'i', $uid > 0 ? $uid : null);
    $add('annee_id', 'i', $anneeId);

    $sql = 'INSERT INTO eleve (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $stmt = $db->prepare($sql);
    ssmunie_bind_dynamic($stmt, $types, $vals);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close(); $db->close();
        if (str_contains($e->getMessage(), 'Duplicate') || $e->getCode() === 1062) apiErrorCode('conflict', 409);
        apiErrorCode('generic', 500);
    }
    $id = (int) $stmt->insert_id;
    $stmt->close();

    $eleve = ssmunie_eleve_get($db, $id);
    $db->close();
    apiResponse(['success' => true, 'data' => $eleve]);
}

if ($method === 'PUT') {
    $body = readJsonBody();
    $id = (int) ($body['id_eleve'] ?? 0);
    if ($id <= 0) {
        // fallback : par matricule
        $mat = trim((string) ($body['matricule'] ?? ''));
        if ($mat !== '') {
            $stmt = $db->prepare('SELECT id_eleve FROM eleve WHERE matricule = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->bind_param('s', $mat);
            $stmt->execute();
            $res = $stmt->get_result();
            $r = $res->fetch_assoc();
            $stmt->close();
            if ($r) $id = (int) $r['id_eleve'];
        }
    }
    if ($id <= 0) apiErrorCode('validation_error', 400);

    $s = function ($v) { $v = trim((string) $v); return $v === '' ? null : $v; };
    $uid = (int) ($_SESSION['user']['id'] ?? 0);

    $sets = []; $types = ''; $vals = [];
    $set = function ($col, $type, $val) use (&$sets, &$types, &$vals) {
        $sets[] = "$col = ?"; $types .= $type; $vals[] = $val;
    };

    if (array_key_exists('nom', $body))             $set('nom', 's', trim((string) $body['nom']));
    if (array_key_exists('prenom', $body))          $set('prenom', 's', trim((string) $body['prenom']));
    if (array_key_exists('date_naissance', $body))  $set('date_naissance', 's', $s($body['date_naissance']));
    if (array_key_exists('lieu_naissance', $body))  $set('lieu_naissance', 's', $s($body['lieu_naissance']));
    if (array_key_exists('genre', $body))           $set('genre', 's', in_array($body['genre'] ?? '', ['M','F'], true) ? $body['genre'] : null);
    if (array_key_exists('nationalite', $body))     $set('nationalite', 's', $s($body['nationalite']));
    if (array_key_exists('adresse', $body))         $set('adresse', 's', $s($body['adresse']));
    if (array_key_exists('nom_tuteur', $body))      $set('nom_tuteur', 's', $s($body['nom_tuteur']));
    if (array_key_exists('telephone_tuteur', $body))$set('telephone_tuteur', 's', $s($body['telephone_tuteur']));
    if (array_key_exists('profession_tuteur', $body))$set('profession_tuteur', 's', $s($body['profession_tuteur']));
    if (array_key_exists('nom_mere', $body))        $set('nom_mere', 's', $s($body['nom_mere']));
    if (array_key_exists('telephone_mere', $body))  $set('telephone_mere', 's', $s($body['telephone_mere']));
    if (array_key_exists('profession_mere', $body)) $set('profession_mere', 's', $s($body['profession_mere']));
    if (array_key_exists('email_parent', $body))    $set('email_parent', 's', $s($body['email_parent']));
    if (array_key_exists('acte_naissance', $body))  $set('acte_naissance', 's', $s($body['acte_naissance']));
    if (array_key_exists('code_parent', $body))     $set('code_parent', 's', $s($body['code_parent']));
    if (array_key_exists('statut', $body))          $set('statut', 's', in_array($body['statut'] ?? 'actif', ['actif','inactif','diplome','transfere','decede'], true) ? ($body['statut'] ?? 'actif') : 'actif');
    if (array_key_exists('motif_statut', $body))    $set('motif_statut', 's', $s($body['motif_statut']));
    if (array_key_exists('redoublant', $body))      $set('redoublant', 'i', (int) ($body['redoublant'] ?? 0) ? 1 : 0);
    if (array_key_exists('anciennete', $body))      $set('anciennete', 's', $s($body['anciennete']));
    if (array_key_exists('sante', $body))           $set('sante', 's', $s($body['sante']));
    if (array_key_exists('malade', $body))          $set('malade', 'i', (int) ($body['malade'] ?? 0) ? 1 : 0);
    if (array_key_exists('ethnie', $body))          $set('ethnie', 's', $s($body['ethnie']));
    if (array_key_exists('infos_supplementaire', $body)) $set('infos_supplementaire', 's', $s($body['infos_supplementaire']));

    // Changement de classe → resynchronise niveau/section/filière
    if (array_key_exists('classe_id', $body)) {
        $classeId = $body['classe_id'] !== '' && $body['classe_id'] !== null ? (int) $body['classe_id'] : null;
        $classeRow = $classeId ? ssmunie_eleve_classe($db, $classeId) : null;
        $set('classe_id', 'i', $classeId);
        if ($classeRow) {
            $set('niveau_id', 'i', (int) $classeRow['niveau_id']);
            $set('section_id', 'i', (int) $classeRow['section_id']);
            $set('filiere_id', 'i', (int) $classeRow['filiere_id']);
        }
    }

    if (empty($sets)) apiErrorCode('validation_error', 400);

    $set('updated_by', 'i', $uid > 0 ? $uid : null);
    $vals[] = $id;
    $types .= 'i';

    $sql = 'UPDATE eleve SET ' . implode(', ', $sets) . ' WHERE id_eleve = ?';
    $stmt = $db->prepare($sql);
    ssmunie_bind_dynamic($stmt, $types, $vals);
    $stmt->execute();
    $stmt->close();

    $eleve = ssmunie_eleve_get($db, $id);
    $db->close();
    apiResponse(['success' => true, 'data' => $eleve]);
}

if ($method === 'DELETE') {
    $id  = (int) ($_GET['id'] ?? 0);
    $mat = trim((string) ($_GET['matricule'] ?? ''));
    if ($id <= 0 && $mat !== '') {
        $stmt = $db->prepare('SELECT id_eleve FROM eleve WHERE matricule = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('s', $mat);
        $stmt->execute();
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $stmt->close();
        if ($r) $id = (int) $r['id_eleve'];
    }
    if ($id <= 0) apiErrorCode('validation_error', 400);

    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    $stmt = $db->prepare("UPDATE eleve SET deleted_at = NOW(), statut = 'inactif', updated_by = ? WHERE id_eleve = ? AND deleted_at IS NULL");
    $stmt->bind_param('ii', $uid, $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);
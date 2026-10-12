<?php
/**
 * SSMUNIE — Classes — CRUD.
 * GET (settings.view) / POST·PUT·DELETE (settings.edit).
 * - effectif calculé (élèves actifs non supprimés).
 * - code_classe auto si vide (code du niveau + lettre A, B, C…).
 * Soft delete : actif = 0.
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $requiredPermission = 'settings.view';
} else {
    $requiredPermission = 'settings.edit';
}

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

/** Lettre de classe (1 → A, 26 → Z, 27 → AA). */
function ssmunie_class_letter(int $n): string
{
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

if ($method === 'GET') {
    $section = (int) ($_GET['section'] ?? 0);
    $filiere = (int) ($_GET['filiere'] ?? 0);
    $niveau  = (int) ($_GET['niveau'] ?? 0);
    $actif   = trim((string) ($_GET['actif'] ?? ''));

    $sql = "SELECT c.id_classe, c.code_classe, c.libellee_classe, c.niveau_id, c.section_id, c.filiere_id,
                   c.categorie_id, c.enseignant_principal_id, c.capacite_accueil, c.annee_id, c.actif,
                   n.code_niveau, n.libellee_niveau,
                   (SELECT COUNT(*) FROM eleve e WHERE e.classe_id = c.id_classe AND e.deleted_at IS NULL) AS effectif
              FROM classe c
              LEFT JOIN niveau n ON n.id_niveau = c.niveau_id
             WHERE 1=1";
    $params = [];
    $types  = '';
    if ($section > 0) { $sql .= ' AND c.section_id = ?'; $params[] = $section; $types .= 'i'; }
    if ($filiere > 0) { $sql .= ' AND c.filiere_id = ?'; $params[] = $filiere; $types .= 'i'; }
    if ($niveau > 0)  { $sql .= ' AND c.niveau_id = ?'; $params[] = $niveau; $types .= 'i'; }
    if ($actif !== '' && $actif !== 'all') { $sql .= ' AND c.actif = ?'; $params[] = (int) ($actif === '0' ? 0 : 1); $types .= 'i'; }
    $sql .= ' ORDER BY c.section_id, c.filiere_id, n.ordre, c.code_classe';

    $stmt = $db->prepare($sql);
    if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $row['id_classe']             = (int) $row['id_classe'];
        $row['niveau_id']             = (int) $row['niveau_id'];
        $row['section_id']            = (int) $row['section_id'];
        $row['filiere_id']            = (int) $row['filiere_id'];
        $row['categorie_id']          = $row['categorie_id'] !== null ? (int) $row['categorie_id'] : null;
        $row['enseignant_principal_id'] = $row['enseignant_principal_id'] !== null ? (int) $row['enseignant_principal_id'] : null;
        $row['capacite_accueil']      = (int) $row['capacite_accueil'];
        $row['annee_id']              = (int) $row['annee_id'];
        $row['actif']                 = (int) $row['actif'];
        $row['effectif']              = (int) $row['effectif'];
        $list[] = $row;
    }
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => $list]);
}

if ($method === 'POST') {
    $body = readJsonBody();
    $code    = trim((string) ($body['code_classe'] ?? ''));
    $libelle = trim((string) ($body['libellee_classe'] ?? ''));
    $niveau  = (int) ($body['niveau_id'] ?? 0);
    $section = (int) ($body['section_id'] ?? 0);
    $filiere = (int) ($body['filiere_id'] ?? 0);
    if ($libelle === '' || $niveau <= 0 || $section <= 0 || $filiere <= 0) apiErrorCode('validation_error', 400);

    // code auto si vide
    if ($code === '') {
        $ncode = 'CLS';
        $stmt = $db->prepare('SELECT code_niveau FROM niveau WHERE id_niveau = ? LIMIT 1');
        $stmt->bind_param('i', $niveau);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) $ncode = (string) $r['code_niveau'];
        $stmt->close();

        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM classe WHERE niveau_id = ?');
        $stmt->bind_param('i', $niveau);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt = (int) ($res->fetch_assoc()['c'] ?? 0);
        $stmt->close();
        $code = $ncode . ssmunie_class_letter($cnt + 1);
    }

    $categ = isset($body['categorie_id']) && $body['categorie_id'] !== '' ? (int) $body['categorie_id'] : null;
    $pp    = isset($body['enseignant_principal_id']) && $body['enseignant_principal_id'] !== '' ? (int) $body['enseignant_principal_id'] : null;
    $cap   = (int) ($body['capacite_accueil'] ?? 60);
    $actif = (int) ($body['actif'] ?? 1);

    $stmt = $db->prepare('INSERT INTO classe (code_classe, libellee_classe, niveau_id, section_id, filiere_id, categorie_id, enseignant_principal_id, capacite_accueil, annee_id, actif) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssiiiiiisi', $code, $libelle, $niveau, $section, $filiere, $categ, $pp, $cap, $anneeId, $actif);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        $db->close();
        if (str_contains($e->getMessage(), 'Duplicate') || $e->getCode() === 1062) apiErrorCode('conflict', 409);
        apiErrorCode('generic', 500);
    }
    $id = (int) $stmt->insert_id;
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => ['id_classe' => $id, 'code_classe' => $code]]);
}

if ($method === 'PUT') {
    $body = readJsonBody();
    $id = (int) ($body['id_classe'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);

    $code    = trim((string) ($body['code_classe'] ?? ''));
    $libelle = trim((string) ($body['libellee_classe'] ?? ''));
    $niveau  = (int) ($body['niveau_id'] ?? 0);
    $section = (int) ($body['section_id'] ?? 0);
    $filiere = (int) ($body['filiere_id'] ?? 0);
    if ($code === '' || $libelle === '' || $niveau <= 0 || $section <= 0 || $filiere <= 0) apiErrorCode('validation_error', 400);

    $categ = isset($body['categorie_id']) && $body['categorie_id'] !== '' ? (int) $body['categorie_id'] : null;
    $pp    = isset($body['enseignant_principal_id']) && $body['enseignant_principal_id'] !== '' ? (int) $body['enseignant_principal_id'] : null;
    $cap   = (int) ($body['capacite_accueil'] ?? 60);
    $actif = (int) ($body['actif'] ?? 1);

    $stmt = $db->prepare('UPDATE classe SET code_classe = ?, libellee_classe = ?, niveau_id = ?, section_id = ?, filiere_id = ?, categorie_id = ?, enseignant_principal_id = ?, capacite_accueil = ?, actif = ? WHERE id_classe = ?');
    $stmt->bind_param('ssiiiiiisi', $code, $libelle, $niveau, $section, $filiere, $categ, $pp, $cap, $actif, $id);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $stmt->close();
        $db->close();
        if (str_contains($e->getMessage(), 'Duplicate') || $e->getCode() === 1062) apiErrorCode('conflict', 409);
        apiErrorCode('generic', 500);
    }
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => ['id_classe' => $id]]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id_classe'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);
    $stmt = $db->prepare('UPDATE classe SET actif = 0 WHERE id_classe = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);

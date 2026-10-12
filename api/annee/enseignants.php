<?php
/**
 * SSMUNIE — Enseignants — liste paginée + création.
 * GET (hr.view) / POST (hr.edit). Matricule auto (ENS{AAAA}{INITIALE}{0000}).
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'hr.view';
else                   $requiredPermission = 'hr.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_matricule.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

if ($method === 'GET') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $statut = trim((string) ($_GET['statut'] ?? ''));
    $emploi = trim((string) ($_GET['statut_emploi'] ?? ''));
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(200, (int) ($_GET['per_page'] ?? 30)));

    $where  = 'WHERE deleted_at IS NULL';
    $params = [];
    $types  = '';
    if ($search !== '') {
        $where .= ' AND (matricule_ens LIKE ? OR nom LIKE ? OR prenom LIKE ? OR email LIKE ?)';
        $like = '%' . $search . '%';
        for ($i = 0; $i < 4; $i++) { $params[] = $like; $types .= 's'; }
    }
    if ($statut !== '' && $statut !== 'all') { $where .= ' AND statut = ?'; $params[] = $statut; $types .= 's'; }
    if ($emploi !== '') { $where .= ' AND statut_emploi = ?'; $params[] = $emploi; $types .= 's'; }

    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM enseignant $where");
    if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $total = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT id_enseignant, matricule_ens, civilite, nom, prenom, date_naissance, genre, telephone, email,
                   statut_emploi, grade, specialite, date_embauche, statut, photo_path, annee_id
              FROM enseignant $where ORDER BY nom, prenom LIMIT $offset, $perPage";
    $stmt = $db->prepare($sql);
    if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $row['id_enseignant'] = (int) $row['id_enseignant'];
        $row['annee_id']      = (int) $row['annee_id'];
        $row['salaire_base']  = isset($row['salaire_base']) ? (float) $row['salaire_base'] : null;
        $list[] = $row;
    }
    $stmt->close();
    $db->close();

    apiResponse([
        'success' => true, 'data' => $list,
        'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages,
    ]);
}

if ($method === 'POST') {
    $body = readJsonBody();
    $nom    = trim((string) ($body['nom'] ?? ''));
    $prenom = trim((string) ($body['prenom'] ?? ''));
    if ($nom === '' || $prenom === '') apiErrorCode('validation_error', 400);

    $matricule = ssmunie_generer_matricule('enseignant', $anneeId, 0, null, $db);
    $uid = (int) ($_SESSION['user']['id'] ?? 0);

    $s = function ($v) { $v = trim((string) $v); return $v === '' ? null : $v; };

    $cols = []; $types = ''; $vals = [];
    $add = function ($col, $type, $val) use (&$cols, &$types, &$vals) {
        $cols[] = $col; $types .= $type; $vals[] = $val;
    };

    $add('matricule_ens', 's', $matricule);
    $add('civilite', 's', in_array($body['civilite'] ?? '', ['M','Mme','Mlle'], true) ? $body['civilite'] : null);
    $add('nom', 's', $nom);
    $add('prenom', 's', $prenom);
    $add('date_naissance', 's', $s($body['date_naissance'] ?? ''));
    $add('lieu_naissance', 's', $s($body['lieu_naissance'] ?? ''));
    $add('genre', 's', in_array($body['genre'] ?? '', ['M','F'], true) ? $body['genre'] : null);
    $add('nationalite', 's', $s($body['nationalite'] ?? 'Camerounaise'));
    $add('adresse', 's', $s($body['adresse'] ?? ''));
    $add('telephone', 's', $s($body['telephone'] ?? ''));
    $add('email', 's', $s($body['email'] ?? ''));
    $add('statut_emploi', 's', in_array($body['statut_emploi'] ?? 'contractuel', ['permanent','contractuel','vacataire','stagiaire'], true) ? ($body['statut_emploi'] ?? 'contractuel') : 'contractuel');
    $add('grade', 's', $s($body['grade'] ?? ''));
    $add('specialite', 's', $s($body['specialite'] ?? ''));
    $add('date_embauche', 's', $s($body['date_embauche'] ?? ''));
    $add('debut_contrat', 's', $s($body['debut_contrat'] ?? ''));
    $add('fin_contrat', 's', $s($body['fin_contrat'] ?? ''));
    $add('salaire_base', 'd', ($body['salaire_base'] ?? null) !== null && ($body['salaire_base'] ?? '') !== '' ? (float) ($body['salaire_base'] ?? 0) : null);
    $add('langues', 's', $s($body['langues'] ?? ''));
    $add('statut', 's', in_array($body['statut'] ?? 'actif', ['actif','inactif','suspendu','parti'], true) ? ($body['statut'] ?? 'actif') : 'actif');
    $add('motif_statut', 's', $s($body['motif_statut'] ?? ''));
    $add('created_by', 'i', $uid > 0 ? $uid : null);
    $add('annee_id', 'i', $anneeId);

    $sql = 'INSERT INTO enseignant (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
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
    $db->close();
    apiResponse(['success' => true, 'data' => ['id_enseignant' => $id, 'matricule_ens' => $matricule]]);
}

apiErrorCode('method_not_allowed', 405);
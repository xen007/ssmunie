<?php
/**
 * SSMUNIE — Enseignant — fiche (GET/PUT/DELETE).
 * GET (hr.view) / PUT (hr.edit) / DELETE (hr.edit, soft delete).
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'hr.view';
else                   $requiredPermission = 'hr.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

function ssmunie_ens_get(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM enseignant WHERE id_enseignant = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    foreach (['id_enseignant','annee_id','created_by','updated_by'] as $k) {
        if (isset($row[$k])) $row[$k] = $row[$k] !== null ? (int) $row[$k] : null;
    }
    if (isset($row['salaire_base'])) $row['salaire_base'] = $row['salaire_base'] !== null ? (float) $row['salaire_base'] : null;
    return $row;
}

function ssmunie_ens_id(mysqli $db, string $mat, int $id): int
{
    if ($id > 0) return $id;
    if ($mat !== '') {
        $stmt = $db->prepare('SELECT id_enseignant FROM enseignant WHERE matricule_ens = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('s', $mat);
        $stmt->execute();
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $stmt->close();
        if ($r) return (int) $r['id_enseignant'];
    }
    return 0;
}

if ($method === 'GET') {
    $id = ssmunie_ens_id($db, trim((string) ($_GET['matricule'] ?? '')), (int) ($_GET['id'] ?? 0));
    $db->close();
    if ($id <= 0) apiErrorCode('not_found', 404);
    $ens = ssmunie_ens_get($db, $id);
    $db->close();
    if (!$ens) apiErrorCode('not_found', 404);
    apiResponse(['success' => true, 'data' => $ens]);
}

if ($method === 'PUT') {
    $body = readJsonBody();
    $id = ssmunie_ens_id($db, trim((string) ($body['matricule_ens'] ?? '')), (int) ($body['id_enseignant'] ?? 0));
    if ($id <= 0) apiErrorCode('validation_error', 400);

    $s = function ($v) { $v = trim((string) $v); return $v === '' ? null : $v; };
    $uid = (int) ($_SESSION['user']['id'] ?? 0);

    $sets = []; $types = ''; $vals = [];
    $set = function ($col, $type, $val) use (&$sets, &$types, &$vals) {
        $sets[] = "$col = ?"; $types .= $type; $vals[] = $val;
    };

    if (array_key_exists('civilite', $body))       $set('civilite', 's', in_array($body['civilite'] ?? '', ['M','Mme','Mlle'], true) ? $body['civilite'] : null);
    if (array_key_exists('nom', $body))            $set('nom', 's', trim((string) $body['nom']));
    if (array_key_exists('prenom', $body))         $set('prenom', 's', trim((string) $body['prenom']));
    if (array_key_exists('date_naissance', $body)) $set('date_naissance', 's', $s($body['date_naissance']));
    if (array_key_exists('lieu_naissance', $body)) $set('lieu_naissance', 's', $s($body['lieu_naissance']));
    if (array_key_exists('genre', $body))          $set('genre', 's', in_array($body['genre'] ?? '', ['M','F'], true) ? $body['genre'] : null);
    if (array_key_exists('nationalite', $body))    $set('nationalite', 's', $s($body['nationalite']));
    if (array_key_exists('adresse', $body))        $set('adresse', 's', $s($body['adresse']));
    if (array_key_exists('telephone', $body))      $set('telephone', 's', $s($body['telephone']));
    if (array_key_exists('email', $body))          $set('email', 's', $s($body['email']));
    if (array_key_exists('statut_emploi', $body))  $set('statut_emploi', 's', in_array($body['statut_emploi'] ?? 'contractuel', ['permanent','contractuel','vacataire','stagiaire'], true) ? ($body['statut_emploi'] ?? 'contractuel') : 'contractuel');
    if (array_key_exists('grade', $body))          $set('grade', 's', $s($body['grade']));
    if (array_key_exists('specialite', $body))     $set('specialite', 's', $s($body['specialite']));
    if (array_key_exists('date_embauche', $body))  $set('date_embauche', 's', $s($body['date_embauche']));
    if (array_key_exists('debut_contrat', $body))  $set('debut_contrat', 's', $s($body['debut_contrat']));
    if (array_key_exists('fin_contrat', $body))    $set('fin_contrat', 's', $s($body['fin_contrat']));
    if (array_key_exists('salaire_base', $body))   $set('salaire_base', 'd', ($body['salaire_base'] ?? null) !== null && ($body['salaire_base'] ?? '') !== '' ? (float) ($body['salaire_base'] ?? 0) : null);
    if (array_key_exists('langues', $body))        $set('langues', 's', $s($body['langues']));
    if (array_key_exists('statut', $body))         $set('statut', 's', in_array($body['statut'] ?? 'actif', ['actif','inactif','suspendu','parti'], true) ? ($body['statut'] ?? 'actif') : 'actif');
    if (array_key_exists('motif_statut', $body))   $set('motif_statut', 's', $s($body['motif_statut']));

    if (empty($sets)) apiErrorCode('validation_error', 400);

    $set('updated_by', 'i', $uid > 0 ? $uid : null);
    $vals[] = $id;
    $types .= 'i';

    $sql = 'UPDATE enseignant SET ' . implode(', ', $sets) . ' WHERE id_enseignant = ?';
    $stmt = $db->prepare($sql);
    ssmunie_bind_dynamic($stmt, $types, $vals);
    $stmt->execute();
    $stmt->close();

    $ens = ssmunie_ens_get($db, $id);
    $db->close();
    apiResponse(['success' => true, 'data' => $ens]);
}

if ($method === 'DELETE') {
    $id = ssmunie_ens_id($db, trim((string) ($_GET['matricule'] ?? '')), (int) ($_GET['id'] ?? 0));
    if ($id <= 0) apiErrorCode('validation_error', 400);
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    $stmt = $db->prepare("UPDATE enseignant SET deleted_at = NOW(), statut = 'inactif', updated_by = ? WHERE id_enseignant = ? AND deleted_at IS NULL");
    $stmt->bind_param('ii', $uid, $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);
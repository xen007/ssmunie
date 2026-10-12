<?php
/** SSMUNIE — Catégories de classe (B4b) — CRUD (finance.view / finance.edit).
 *  Invariant : Σ(tranches non nulles) = frais_sco. */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'finance.view'; else $requiredPermission = 'finance.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

function ssmunie_check_tranches(array $b): ?string {
    $t = [(float)($b['tranche1']??0), (float)($b['tranche2']??0), (float)($b['tranche3']??0), (float)($b['tranche4']??0)];
    $frais = (float)($b['frais_sco']??0);
    $somme = array_sum(array_filter($t, fn($x) => $x > 0));
    if (abs($somme - $frais) > 0.01) return "Invariant tranches : somme ($somme) != frais_sco ($frais)";
    return null;
}

if ($method === 'GET') {
    $rows = $db->query('SELECT * FROM categorie_classe WHERE deleted_at IS NULL ORDER BY code_cat')->fetch_all(MYSQLI_ASSOC);
    $db->close(); apiResponse(['success'=>true, 'data'=>$rows]);
}
if ($method === 'POST') {
    $b = readJsonBody();
    $err = ssmunie_check_tranches($b); if ($err) { $db->close(); apiErrorCode('validation_error', 400, $err); }
    $uuid = ssmunie_uuid();
    $stmt = $db->prepare('INSERT INTO categorie_classe (id_uuid, code_cat, libellee_cat, description, frais_ins, frais_sco, tranche1, tranche2, tranche3, tranche4) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('ssssdddddd', $uuid, $b['code_cat'], $b['libellee_cat'], $b['description'], $b['frais_ins'], $b['frais_sco'], $b['tranche1'], $b['tranche2'], $b['tranche3'], $b['tranche4']);
    $stmt->execute(); $id = $stmt->insert_id; $stmt->close(); $db->close();
    apiResponse(['success'=>true, 'data'=>['id_categorie'=>$id]]);
}
if ($method === 'PUT') {
    $b = readJsonBody(); $id = (int)($b['id_categorie']??0); if ($id <= 0) apiErrorCode('validation_error', 400);
    $err = ssmunie_check_tranches($b); if ($err) { $db->close(); apiErrorCode('validation_error', 400, $err); }
    $stmt = $db->prepare('UPDATE categorie_classe SET code_cat=?, libellee_cat=?, description=?, frais_ins=?, frais_sco=?, tranche1=?, tranche2=?, tranche3=?, tranche4=? WHERE id_categorie=?');
    $stmt->bind_param('ssssdddddi', $b['code_cat'], $b['libellee_cat'], $b['description'], $b['frais_ins'], $b['frais_sco'], $b['tranche1'], $b['tranche2'], $b['tranche3'], $b['tranche4'], $id);
    $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success'=>true]);
}
if ($method === 'DELETE') {
    $id = (int)($_GET['id']??0); if ($id <= 0) apiErrorCode('validation_error', 400);
    $db->query("UPDATE categorie_classe SET deleted_at = NOW() WHERE id_categorie = $id");
    $db->close(); apiResponse(['success'=>true]);
}
apiErrorCode('method_not_allowed', 405);
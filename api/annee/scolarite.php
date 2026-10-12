<?php
/**
 * SSMUNIE — Scolarité (B4b) — encaissement + annulation.
 * POST {eleve_id, montant, date_paye, observation} → ventilation auto (inscription d'abord).
 * DELETE ?code=PAY… {motif_annulation} → annulation (ligne négative ANN, motif OBLIGATOIRE).
 * GET → liste des versements (filtres).
 */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'finance.view'; else $requiredPermission = 'finance.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_finances.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$uid = (int) ($_SESSION['user']['id'] ?? 0);
$role = (string) ($_SESSION['user']['role'] ?? '');

if ($method === 'GET') {
    $classe = (int)($_GET['classe']??0); $eleve = (int)($_GET['eleve']??0);
    $sql = 'SELECT s.*, e.matricule, e.nom, e.prenom FROM scolarite s JOIN eleve e ON e.id_eleve = s.eleve_id WHERE 1=1';
    $params=[]; $types='';
    if ($classe>0) { $sql .= ' AND e.classe_id = ?'; $params[]=$classe; $types.='i'; }
    if ($eleve>0)  { $sql .= ' AND s.eleve_id = ?'; $params[]=$eleve; $types.='i'; }
    $sql .= ' ORDER BY s.date_paye DESC, s.id_sco DESC LIMIT 2000';
    $stmt = $db->prepare($sql);
    if ($types!=='') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $db->close();
    apiResponse(['success'=>true, 'data'=>$rows]);
}

if ($method === 'POST') {
    $b = readJsonBody();
    $eleveId = (int)($b['eleve_id']??0); $montant = (float)($b['montant']??0); $datePaye = (string)($b['date_paye'] ?? date('Y-m-d'));
    if ($eleveId <= 0 || $montant <= 0) apiErrorCode('validation_error', 400);
    $sit = ssmunie_eleve_situation($db, $eleveId);
    if (!$sit) { $db->close(); apiErrorCode('not_found', 404); }
    $ins_active = $sit['frais_ins'] > 0;
    $rep = ssmunie_repartir_paiement($montant, $sit['reste_ins'], $sit['reste_sco'], $ins_active);
    if ($rep['rejet']) { $db->close(); apiErrorCode('validation_error', 400, 'Montant dépasse le total dû (' . $rep['total_du'] . ')'); }

    $code = ssmunie_next_code($db, $anneeId, 'PAY');
    $obs = trim((string)($b['observation']??''));
    $db->begin_transaction();
    try {
        $insId = null;
        if ($rep['part_ins'] > 0) {
            $uuid = ssmunie_uuid();
            $stmt = $db->prepare('INSERT INTO inscription (id_uuid, eleve_id, categorie_id, montant, frais_total, code_versement, date_inscription, created_by) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->bind_param('siiddssi', $uuid, $eleveId, $sit['categorie_id'], $rep['part_ins'], $sit['ins_net'], $code, $datePaye, $uid);
            $stmt->execute(); $insId = $stmt->insert_id; $stmt->close();
        }
        if ($rep['part_sco'] > 0) {
            $uuid = ssmunie_uuid();
            $stmt = $db->prepare('INSERT INTO scolarite (id_uuid, eleve_id, inscription_id, montant, code_versement, type_action, observation, date_paye, created_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $ta = 'versement';
            $stmt->bind_param('siidssssi', $uuid, $eleveId, $insId, $rep['part_sco'], $code, $ta, $obs, $datePaye, $uid);
            $stmt->execute(); $stmt->close();
        }
        // log
        $uuid = ssmunie_uuid();
        $detail = json_encode(['montant'=>$montant,'part_ins'=>$rep['part_ins'],'part_sco'=>$rep['part_sco']]);
        $stmt = $db->prepare('INSERT INTO scolarite_logs (id_uuid, eleve_id, code_versement, action, detail, user_id, user_role) VALUES (?,?,?,?,?,?,?)');
        $act = 'versement';
        $stmt->bind_param('sisssis', $uuid, $eleveId, $code, $act, $detail, $uid, $role);
        $stmt->execute(); $stmt->close();
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); $db->close(); apiErrorCode('generic', 500); }
    $after = ssmunie_eleve_situation($db, $eleveId);
    $db->close();
    apiResponse(['success'=>true, 'data'=>['code_versement'=>$code, 'part_ins'=>$rep['part_ins'], 'part_sco'=>$rep['part_sco'], 'situation'=>$after]]);
}

if ($method === 'DELETE') {
    $code = trim((string)($_GET['code'] ?? ''));
    $b = readJsonBody();
    $motif = trim((string)($b['motif_annulation'] ?? ''));
    if ($code === '') apiErrorCode('validation_error', 400);
    if ($motif === '') { $db->close(); apiErrorCode('validation_error', 400, 'motif_annulation obligatoire'); }
    // versement d'origine (inscription + scolarité, même code)
    $insRow = $db->query("SELECT id_ins, eleve_id, montant FROM inscription WHERE code_versement = '$code' AND valide = 1 LIMIT 1")->fetch_assoc();
    $scoRow = $db->query("SELECT id_sco, eleve_id, montant FROM scolarite WHERE code_versement = '$code' AND type_action = 'versement' LIMIT 1")->fetch_assoc();
    if (!$insRow && !$scoRow) { $db->close(); apiErrorCode('not_found', 404); }
    $eleveId = (int)($insRow['eleve_id'] ?? $scoRow['eleve_id'] ?? 0);
    $annCode = ssmunie_next_code($db, $anneeId, 'ANN');
    $db->begin_transaction();
    try {
        if ($insRow) {
            $db->query("UPDATE inscription SET valide = 0, motif_annulation = '" . $db->real_escape_string($motif) . "' WHERE id_ins = " . (int)$insRow['id_ins']);
        }
        if ($scoRow) {
            $uuid = ssmunie_uuid(); $neg = -(float)$scoRow['montant'];
            $stmt = $db->prepare('INSERT INTO scolarite (id_uuid, eleve_id, inscription_id, montant, code_versement, code_ref, type_action, motif_annulation, date_paye, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $ta2 = 'annulation'; $today = date('Y-m-d');
            $stmt->bind_param('siidsssssi', $uuid, $eleveId, $insRow['id_ins']??null, $neg, $annCode, $code, $ta2, $motif, $today, $uid);
            $stmt->execute(); $stmt->close();
        }
        $uuid = ssmunie_uuid(); $detail = json_encode(['code_origine'=>$code, 'motif'=>$motif]);
        $stmt = $db->prepare('INSERT INTO scolarite_logs (id_uuid, eleve_id, code_versement, action, detail, user_id, user_role) VALUES (?,?,?,?,?,?,?)');
        $act2 = 'annulation';
        $stmt->bind_param('sisssis', $uuid, $eleveId, $annCode, $act2, $detail, $uid, $role);
        $stmt->execute(); $stmt->close();
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); $db->close(); apiErrorCode('generic', 500); }
    $db->close();
    apiResponse(['success'=>true, 'data'=>['code_annulation'=>$annCode]]);
}
apiErrorCode('method_not_allowed', 405);
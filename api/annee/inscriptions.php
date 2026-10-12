<?php
/** SSMUNIE — Inscriptions (B4b) — guichet spécialisé (frais_ins > 0). */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'finance.view'; else $requiredPermission = 'finance.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_finances.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$uid = (int) ($_SESSION['user']['id'] ?? 0); $role = (string)($_SESSION['user']['role'] ?? '');

if ($method === 'GET') {
    $classe = (int)($_GET['classe']??0);
    $sql = 'SELECT i.*, e.matricule, e.nom, e.prenom FROM inscription i JOIN eleve e ON e.id_eleve = i.eleve_id WHERE 1=1';
    $params=[];$types='';
    if ($classe>0){ $sql.=' AND e.classe_id = ?'; $params[]=$classe;$types.='i'; }
    $sql .= ' ORDER BY i.date_inscription DESC, i.id_ins DESC LIMIT 2000';
    $stmt=$db->prepare($sql); if($types!=='') ssmunie_bind_dynamic($stmt,$types,$params);
    $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $db->close();
    apiResponse(['success'=>true,'data'=>$rows]);
}
if ($method === 'POST') {
    $b = readJsonBody();
    $eleveId=(int)($b['eleve_id']??0); $montant=(float)($b['montant']??0); $date=(string)($b['date_inscription']??date('Y-m-d'));
    if ($eleveId<=0 || $montant<=0) apiErrorCode('validation_error',400);
    $sit = ssmunie_eleve_situation($db, $eleveId);
    if (!$sit) { $db->close(); apiErrorCode('not_found',404); }
    if ($sit['frais_ins'] <= 0) { $db->close(); apiErrorCode('validation_error',400,'Aucune inscription due (frais_ins = 0)'); }
    if ($montant > $sit['reste_ins']) { $db->close(); apiErrorCode('validation_error',400,'Montant > reste inscription (' . $sit['reste_ins'] . ')'); }
    $code = ssmunie_next_code($db, $anneeId, 'PAY');
    $uuid = ssmunie_uuid();
    $stmt = $db->prepare('INSERT INTO inscription (id_uuid, eleve_id, categorie_id, montant, frais_total, code_versement, date_inscription, created_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param('siiddssi', $uuid, $eleveId, $sit['categorie_id'], $montant, $sit['ins_net'], $code, $date, $uid);
    $stmt->execute(); $stmt->close();
    $uuid=ssmunie_uuid(); $detail=json_encode(['montant'=>$montant]);
    $stmt=$db->prepare('INSERT INTO scolarite_logs (id_uuid, eleve_id, code_versement, action, detail, user_id, user_role) VALUES (?,?,?,?,?,?,?)');
    $act = 'versement';
    $stmt->bind_param('sisssis',$uuid,$eleveId,$code,$act,$detail,$uid,$role);
    $stmt->execute(); $stmt->close(); $db->close();
    apiResponse(['success'=>true,'data'=>['code_versement'=>$code]]);
}
if ($method === 'DELETE') {
    $id=(int)($_GET['id']??0); if($id<=0) apiErrorCode('validation_error',400);
    $b=readJsonBody(); $motif=trim((string)($b['motif_annulation']??''));
    if($motif===''){ $db->close(); apiErrorCode('validation_error',400,'motif_annulation obligatoire'); }
    $db->query("UPDATE inscription SET valide=0, motif_annulation='" . $db->real_escape_string($motif) . "' WHERE id_ins=$id");
    $db->close(); apiResponse(['success'=>true]);
}
apiErrorCode('method_not_allowed', 405);
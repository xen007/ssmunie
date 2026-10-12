<?php
/** SSMUNIE — Attribution de réduction à un élève (B4b).
 * POST {eleve_id, type_id, motif} → montant_applique CALCULÉ (pas saisi).
 */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method==='GET') $requiredPermission='finance.view'; else $requiredPermission='finance.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_finances.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$uid=(int)($_SESSION['user']['id']??0); $role=(string)($_SESSION['user']['role']??'');

if ($method==='GET'){ $eleve=(int)($_GET['eleve']??0);
  $sql='SELECT er.*, tr.code_type, tr.libelle, tr.application, tr.taux, tr.montant FROM eleve_reduction er JOIN types_reductions tr ON tr.id_type=er.type_id WHERE er.deleted_at IS NULL';
  $p=[];$t=''; if($eleve>0){ $sql.=' AND er.eleve_id=?'; $p[]=$eleve;$t.='i'; } $sql.=' ORDER BY er.id DESC';
  $stmt=$db->prepare($sql); if($t!=='') ssmunie_bind_dynamic($stmt,$t,$p); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $db->close(); apiResponse(['success'=>true,'data'=>$rows]); }

if ($method==='POST'){ $b=readJsonBody(); $eleveId=(int)($b['eleve_id']??0); $typeId=(int)($b['type_id']??0); $motif=trim((string)($b['motif']??''));
  if($eleveId<=0 || $typeId<=0) apiErrorCode('validation_error',400);
  $type=$db->query("SELECT application, taux, montant FROM types_reductions WHERE id_type=$typeId AND statut=1")->fetch_assoc();
  if(!$type){ $db->close(); apiErrorCode('not_found',404); }
  $sit=ssmunie_eleve_situation($db,$eleveId); if(!$sit){ $db->close(); apiErrorCode('not_found',404); }
  $base = match($type['application']){ 'inscription'=>$sit['frais_ins'], 'total'=>$sit['frais_ins']+$sit['frais_sco'], default=>$sit['frais_sco'] };
  $taux=(float)($type['taux']??0); $montant_applique = $taux>0 ? round($base*$taux/100,2) : (float)($type['montant']??0);
  $uuid=ssmunie_uuid();
  $stmt=$db->prepare('INSERT INTO eleve_reduction (id_uuid, eleve_id, type_id, montant_applique, motif, valide_par) VALUES (?,?,?,?,?,?)');
  $stmt->bind_param('siidsi',$uuid,$eleveId,$typeId,$montant_applique,$motif,$uid); $stmt->execute(); $id=$stmt->insert_id; $stmt->close();
  $uuid=ssmunie_uuid(); $detail=json_encode(['type_id'=>$typeId,'montant_applique'=>$montant_applique]);
  $stmt=$db->prepare('INSERT INTO scolarite_logs (id_uuid, eleve_id, action, detail, user_id, user_role) VALUES (?,?,?,?,?,?)');
  $act = 'reduction_attribuee';
  $stmt->bind_param('sissis',$uuid,$eleveId,$act,$detail,$uid,$role); $stmt->execute(); $stmt->close(); $db->close();
  apiResponse(['success'=>true,'data'=>['id'=>$id,'montant_applique'=>$montant_applique]]); }

if ($method==='DELETE'){ $id=(int)($_GET['id']??0); if($id<=0) apiErrorCode('validation_error',400);
  $db->query("UPDATE eleve_reduction SET deleted_at=NOW() WHERE id=$id"); $db->close(); apiResponse(['success'=>true]); }
apiErrorCode('method_not_allowed', 405);
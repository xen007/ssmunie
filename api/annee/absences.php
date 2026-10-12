<?php
/** SSMUNIE — Absences (B4b) — saisie individuelle + logs audit. */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method==='GET') $requiredPermission='students.view'; else $requiredPermission='students.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_absences.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$uid=(int)($_SESSION['user']['id']??0); $role=(string)($_SESSION['user']['role']??'');

if ($method==='GET'){ $classe=(int)($_GET['classe']??0); $eleve=(int)($_GET['eleve']??0); $mois=(int)($_GET['mois']??0);
  $sql='SELECT a.*, e.matricule, e.nom, e.prenom FROM absences a JOIN eleve e ON e.id_eleve=a.eleve_id WHERE a.deleted_at IS NULL';
  $p=[];$t='';
  if($classe>0){ $sql.=' AND e.classe_id=?'; $p[]=$classe;$t.='i'; } if($eleve>0){ $sql.=' AND a.eleve_id=?'; $p[]=$eleve;$t.='i'; } if($mois>0){ $sql.=' AND a.mois_id=?'; $p[]=$mois;$t.='i'; }
  $sql.=' ORDER BY e.nom, e.prenom, a.mois_id';
  $stmt=$db->prepare($sql); if($t!=='') ssmunie_bind_dynamic($stmt,$t,$p); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $db->close(); apiResponse(['success'=>true,'data'=>$rows]); }

if ($method==='POST'){ $b=readJsonBody(); $eleveId=(int)($b['eleve_id']??0); $moisId=(int)($b['mois_id']??0); $total=(int)($b['abs_total']??0); $just=(int)($b['abs_justifies']??0); $obs=trim((string)($b['observation']??''));
  if($eleveId<=0 || $moisId<1 || $moisId>9) apiErrorCode('validation_error',400);
  $ex=$db->query("SELECT id_abs, abs_total, abs_justifies FROM absences WHERE eleve_id=$eleveId AND mois_id=$moisId AND deleted_at IS NULL")->fetch_assoc();
  if($ex){ $uuid=ssmunie_uuid(); $stmt=$db->prepare('UPDATE absences SET abs_total=?, abs_justifies=?, observation=? WHERE id_abs=?'); $stmt->bind_param('iisi',$total,$just,$obs,$ex['id_abs']); $stmt->execute(); $stmt->close(); ssmunie_log_absence($db,$eleveId,$moisId,$ex['abs_total'],$ex['abs_justifies'],$total,$just,'modification',$obs,$uid,$role); }
  else { $uuid=ssmunie_uuid(); $stmt=$db->prepare('INSERT INTO absences (id_uuid, eleve_id, mois_id, abs_total, abs_justifies, observation) VALUES (?,?,?,?,?,?)'); $stmt->bind_param('siiiss',$uuid,$eleveId,$moisId,$total,$just,$obs); $stmt->execute(); $stmt->close(); ssmunie_log_absence($db,$eleveId,$moisId,null,null,$total,$just,'creation',$obs,$uid,$role); }
  $db->close(); apiResponse(['success'=>true]); }

if ($method==='DELETE'){ $id=(int)($_GET['id']??0); if($id<=0) apiErrorCode('validation_error',400);
  $row=$db->query("SELECT eleve_id, mois_id, abs_total, abs_justifies FROM absences WHERE id_abs=$id")->fetch_assoc();
  if($row){ $db->query("UPDATE absences SET deleted_at=NOW() WHERE id_abs=$id"); ssmunie_log_absence($db,(int)$row['eleve_id'],(int)$row['mois_id'],$row['abs_total'],$row['abs_justifies'],0,0,'annulation',null,$uid,$role); }
  $db->close(); apiResponse(['success'=>true]); }
apiErrorCode('method_not_allowed', 405);
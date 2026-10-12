<?php
/** SSMUNIE — Saisie groupée d'absences (B4b) — transaction + logs. */
declare(strict_types=1);
$requiredPermission='students.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_absences.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$uid=(int)($_SESSION['user']['id']??0); $role=(string)($_SESSION['user']['role']??'');
$b=readJsonBody(); $classeId=(int)($b['classe_id']??0); $moisId=(int)($b['mois_id']??0); $items=$b['absences']??[];
if($classeId<=0 || $moisId<1 || $moisId>9 || !is_array($items)) apiErrorCode('validation_error',400);
$db->begin_transaction();
try { $n=0; foreach($items as $it){
  $eleveId=(int)($it['eleve_id']??0); $total=(int)($it['abs_total']??0); $just=(int)($it['abs_justifies']??0);
  if($eleveId<=0) continue;
  $ex=$db->query("SELECT id_abs, abs_total, abs_justifies FROM absences WHERE eleve_id=$eleveId AND mois_id=$moisId AND deleted_at IS NULL")->fetch_assoc();
  if($ex){ $stmt=$db->prepare('UPDATE absences SET abs_total=?, abs_justifies=? WHERE id_abs=?'); $stmt->bind_param('iii',$total,$just,$ex['id_abs']); $stmt->execute(); $stmt->close(); ssmunie_log_absence($db,$eleveId,$moisId,$ex['abs_total'],$ex['abs_justifies'],$total,$just,'modification',null,$uid,$role); }
  else { $uuid=ssmunie_uuid(); $stmt=$db->prepare('INSERT INTO absences (id_uuid, eleve_id, mois_id, abs_total, abs_justifies) VALUES (?,?,?,?,?)'); $stmt->bind_param('siiii',$uuid,$eleveId,$moisId,$total,$just); $stmt->execute(); $stmt->close(); ssmunie_log_absence($db,$eleveId,$moisId,null,null,$total,$just,'creation',null,$uid,$role); }
  $n++; } $db->commit(); } catch(Throwable $e){ $db->rollback(); $db->close(); apiErrorCode('generic',500); }
$db->close(); apiResponse(['success'=>true,'data'=>['created'=>$n]]);
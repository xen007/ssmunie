<?php
/** SSMUNIE — Justification d'absence (B4b) — POST {eleve_id, mois_id, abs_justifies, motif}. */
declare(strict_types=1);
$requiredPermission='students.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_absences.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$uid=(int)($_SESSION['user']['id']??0); $role=(string)($_SESSION['user']['role']??'');
$b=readJsonBody(); $eleveId=(int)($b['eleve_id']??0); $moisId=(int)($b['mois_id']??0); $just=(int)($b['abs_justifies']??0); $motif=trim((string)($b['motif']??''));
if($eleveId<=0 || $moisId<1 || $moisId>9 || $motif==='') apiErrorCode('validation_error',400);
$ex=$db->query("SELECT id_abs, abs_total, abs_justifies FROM absences WHERE eleve_id=$eleveId AND mois_id=$moisId AND deleted_at IS NULL")->fetch_assoc();
$anT=$ex['abs_total']??null; $anJ=$ex['abs_justifies']??null;
if($ex){ $stmt=$db->prepare('UPDATE absences SET abs_justifies=? WHERE id_abs=?'); $stmt->bind_param('ii',$just,$ex['id_abs']); $stmt->execute(); $stmt->close(); }
else { $uuid=ssmunie_uuid(); $stmt=$db->prepare('INSERT INTO absences (id_uuid, eleve_id, mois_id, abs_total, abs_justifies) VALUES (?,?,?,?,?)'); $stmt->bind_param('siiii',$uuid,$eleveId,$moisId,0,$just); $stmt->execute(); $stmt->close(); }
ssmunie_log_absence($db,$eleveId,$moisId,$anT,$anJ,$ex['abs_total']??0,$just,'justification',$motif,$uid,$role);
$db->close(); apiResponse(['success'=>true]);
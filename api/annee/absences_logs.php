<?php
/** SSMUNIE — Journal d'audit des absences (B4b) — GET (students.view). */
declare(strict_types=1);
$requiredPermission='students.view';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$eleve=(int)($_GET['eleve']??0); $action=trim((string)($_GET['action']??''));
$sql='SELECT l.*, e.matricule, e.nom, e.prenom FROM absences_logs l JOIN eleve e ON e.id_eleve=l.eleve_id WHERE 1=1';
$p=[];$t=''; if($eleve>0){ $sql.=' AND l.eleve_id=?'; $p[]=$eleve;$t.='i'; } if($action!==''){ $sql.=' AND l.action=?'; $p[]=$action;$t.='s'; }
$sql.=' ORDER BY l.date_action DESC, l.id_log DESC LIMIT 2000';
$stmt=$db->prepare($sql); if($t!=='') ssmunie_bind_dynamic($stmt,$t,$p); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); $db->close(); apiResponse(['success'=>true,'data'=>$rows]);
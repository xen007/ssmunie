<?php
/** SSMUNIE — Statuts de scolarité (B4b) — CRUD (finance.view / finance.edit). */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method==='GET') $requiredPermission='finance.view'; else $requiredPermission='finance.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if ($method==='GET'){ $rows=$db->query('SELECT * FROM statut_scolarite WHERE actif=1 ORDER BY date_tranche')->fetch_all(MYSQLI_ASSOC); $db->close(); apiResponse(['success'=>true,'data'=>$rows]); }
if ($method==='POST'){ $b=readJsonBody(); $uuid=ssmunie_uuid();
  $stmt=$db->prepare('INSERT INTO statut_scolarite (id_uuid, code_statut, libelle, date_tranche) VALUES (?,?,?,?)');
  $stmt->bind_param('ssss',$uuid,$b['code_statut'],$b['libelle'],$b['date_tranche']); $stmt->execute(); $id=$stmt->insert_id; $stmt->close(); $db->close(); apiResponse(['success'=>true,'data'=>['id_statut'=>$id]]); }
if ($method==='PUT'){ $b=readJsonBody(); $id=(int)($b['id_statut']??0); if($id<=0) apiErrorCode('validation_error',400);
  $stmt=$db->prepare('UPDATE statut_scolarite SET code_statut=?, libelle=?, date_tranche=? WHERE id_statut=?');
  $stmt->bind_param('sssi',$b['code_statut'],$b['libelle'],$b['date_tranche'],$id); $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success'=>true]); }
if ($method==='DELETE'){ $id=(int)($_GET['id']??0); if($id<=0) apiErrorCode('validation_error',400);
  $db->query("UPDATE statut_scolarite SET actif=0 WHERE id_statut=$id"); $db->close(); apiResponse(['success'=>true]); }
apiErrorCode('method_not_allowed', 405);
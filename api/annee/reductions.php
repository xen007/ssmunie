<?php
/** SSMUNIE — Types de réduction (B4b) — CRUD (finance.view / finance.edit). */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method==='GET') $requiredPermission='finance.view'; else $requiredPermission='finance.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if ($method==='GET'){ $rows=$db->query('SELECT * FROM types_reductions WHERE deleted_at IS NULL ORDER BY id_type')->fetch_all(MYSQLI_ASSOC); $db->close(); apiResponse(['success'=>true,'data'=>$rows]); }
if ($method==='POST'){ $b=readJsonBody(); $uuid=ssmunie_uuid();
  $app = in_array($b['application']??'', ['inscription','scolarite','total'], true) ? $b['application'] : 'scolarite';
  $stmt=$db->prepare('INSERT INTO types_reductions (id_uuid, code_type, libelle, description, application, taux, montant, statut) VALUES (?,?,?,?,?,?,?,?)');
  $stmt->bind_param('sssssdii',$uuid,$b['code_type'],$b['libelle'],$b['description'],$app,$b['taux'],$b['montant'],$b['statut']);
  $stmt->execute(); $id=$stmt->insert_id; $stmt->close(); $db->close(); apiResponse(['success'=>true,'data'=>['id_type'=>$id]]); }
if ($method==='PUT'){ $b=readJsonBody(); $id=(int)($b['id_type']??0); if($id<=0) apiErrorCode('validation_error',400);
  $app = in_array($b['application']??'', ['inscription','scolarite','total'], true) ? $b['application'] : 'scolarite';
  $stmt=$db->prepare('UPDATE types_reductions SET code_type=?, libelle=?, description=?, application=?, taux=?, montant=?, statut=? WHERE id_type=?');
  $stmt->bind_param('ssssddii',$b['code_type'],$b['libelle'],$b['description'],$app,$b['taux'],$b['montant'],$b['statut'],$id);
  $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success'=>true]); }
if ($method==='DELETE'){ $id=(int)($_GET['id']??0); if($id<=0) apiErrorCode('validation_error',400);
  $db->query("UPDATE types_reductions SET deleted_at=NOW() WHERE id_type=$id"); $db->close(); apiResponse(['success'=>true]); }
apiErrorCode('method_not_allowed', 405);
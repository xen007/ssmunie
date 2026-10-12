<?php
/** SSMUNIE — Paramètres de sanction (B4b) — CRUD (finance.view / settings.edit). */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method==='GET') $requiredPermission='finance.view'; else $requiredPermission='settings.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if ($method==='GET'){ $rows=$db->query('SELECT * FROM parametres_absence WHERE actif=1 ORDER BY palier')->fetch_all(MYSQLI_ASSOC); $db->close(); apiResponse(['success'=>true,'data'=>$rows]); }
if ($method==='POST'){ $b=readJsonBody(); $uuid=ssmunie_uuid();
  $stmt=$db->prepare('INSERT INTO parametres_absence (id_uuid, palier, heures_min, heures_max, sanction, duree_jours, exclusion_definitive, convocation_parents) VALUES (?,?,?,?,?,?,?,?)');
  $stmt->bind_param('siiisiii',$uuid,$b['palier'],$b['heures_min'],$b['heures_max'],$b['sanction'],$b['duree_jours'],$b['exclusion_definitive'],$b['convocation_parents']);
  $stmt->execute(); $id=$stmt->insert_id; $stmt->close(); $db->close(); apiResponse(['success'=>true,'data'=>['id'=>$id]]); }
if ($method==='PUT'){ $b=readJsonBody(); $id=(int)($b['id']??0); if($id<=0) apiErrorCode('validation_error',400);
  $stmt=$db->prepare('UPDATE parametres_absence SET palier=?, heures_min=?, heures_max=?, sanction=?, duree_jours=?, exclusion_definitive=?, convocation_parents=? WHERE id=?');
  $stmt->bind_param('iiisiiii',$b['palier'],$b['heures_min'],$b['heures_max'],$b['sanction'],$b['duree_jours'],$b['exclusion_definitive'],$b['convocation_parents'],$id);
  $stmt->execute(); $stmt->close(); $db->close(); apiResponse(['success'=>true]); }
if ($method==='DELETE'){ $id=(int)($_GET['id']??0); if($id<=0) apiErrorCode('validation_error',400);
  $db->query("UPDATE parametres_absence SET actif=0 WHERE id=$id"); $db->close(); apiResponse(['success'=>true]); }
apiErrorCode('method_not_allowed', 405);
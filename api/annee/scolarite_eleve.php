<?php
/** SSMUNIE — Fiche scolarité d'un élève (B4b) — GET (finance.view). */
declare(strict_types=1);
$requiredPermission = 'finance.view';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_finances.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$classe = (int)($_GET['classe']??0);
$eleveId = (int)($_GET['eleve']??0);
if ($eleveId<=0 && $classe<=0) apiErrorCode('validation_error',400);
if ($classe>0) {
    $ids=[];
    $res=$db->query("SELECT id_eleve FROM eleve WHERE classe_id=$classe AND deleted_at IS NULL ORDER BY nom, prenom");
    while($r=$res->fetch_assoc()) $ids[]=(int)$r['id_eleve'];
    $list=[]; foreach($ids as $id){ $x=ssmunie_eleve_situation($db,$id); if($x) $list[]=$x; }
    $db->close(); apiResponse(['success'=>true,'data'=>$list]);
}
$sit = ssmunie_eleve_situation($db, $eleveId);
if (!$sit) { $db->close(); apiErrorCode('not_found',404); }
$historique = $db->query("SELECT id_sco, montant, code_versement, code_ref, type_action, motif_annulation, observation, date_paye, created_at FROM scolarite WHERE eleve_id = $eleveId ORDER BY id_sco DESC")->fetch_all(MYSQLI_ASSOC);
$inscriptions = $db->query("SELECT id_ins, montant, frais_total, code_versement, date_inscription, valide, motif_annulation FROM inscription WHERE eleve_id = $eleveId ORDER BY id_ins DESC")->fetch_all(MYSQLI_ASSOC);
$reductions = $db->query("SELECT er.id, er.montant_applique, er.motif, er.date_attribution, tr.code_type, tr.libelle, tr.application, tr.taux, tr.montant FROM eleve_reduction er JOIN types_reductions tr ON tr.id_type = er.type_id WHERE er.eleve_id = $eleveId AND er.deleted_at IS NULL ORDER BY er.id DESC")->fetch_all(MYSQLI_ASSOC);
$db->close();
apiResponse(['success'=>true,'data'=>['situation'=>$sit,'historique'=>$historique,'inscriptions'=>$inscriptions,'reductions'=>$reductions]]);
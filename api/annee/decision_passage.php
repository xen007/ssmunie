<?php
/**
 * SSMUNIE — Décision de passage finale (B4b) — GET ?classe=.
 * Étend B4a : décision auto (moteur) + sanction d'absence.
 * Si sanction.exclusion_definitive → décision forcée à 'exclu'.
 */
declare(strict_types=1);
$requiredPermission='grades.view';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../lib/moteurs.php';
require_once __DIR__ . '/../_notes.php';
require_once __DIR__ . '/../_absences.php';
require_once __DIR__ . '/../lib/finances.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$classe=(int)($_GET['classe']??0); if($classe<=0) apiErrorCode('validation_error',400);
$section=ssmunie_section_of_classe($db,$classe);
$moteur=ssmunie_moteur($section);
$params=[]; foreach($db->query("SELECT cle, valeur, type FROM parametres_niveau WHERE actif=1 AND section_id=$section")->fetch_all(MYSQLI_ASSOC) as $r){ $params[$r['cle']]=$r['type']==='number'?(float)$r['valeur']:($r['type']==='boolean'?($r['valeur']==='true'):$r['valeur']); }
$echelles=$db->query("SELECT code, note_min FROM echelles_appreciation WHERE actif=1 AND section_id=$section ORDER BY note_min DESC")->fetch_all(MYSQLI_ASSOC);
$grades=$db->query('SELECT examen, grade, note_min, note_max, est_valide FROM parametres_grades WHERE actif=1 ORDER BY examen, note_max DESC')->fetch_all(MYSQLI_ASSOC);
$paliers=$db->query('SELECT palier, heures_min, heures_max, sanction, duree_jours, exclusion_definitive, convocation_parents FROM parametres_absence WHERE actif=1')->fetch_all(MYSQLI_ASSOC);
$eleves=[]; $res=$db->query("SELECT id_eleve, matricule, nom, prenom FROM eleve WHERE classe_id=$classe AND deleted_at IS NULL ORDER BY nom, prenom"); while($e=$res->fetch_assoc()) $eleves[$e['id_eleve']]=$e;
$table=ssmunie_note_table($db,$classe);
if($table==='notes_evaluation'){ $sql="SELECT n.eleve_id, n.matiere_id, n.eval_id, n.bareme, n.m1,n.m2,n.m3,n.m4,n.m5,n.m6,n.m7,n.m8,n.m9, m.nom_matiere FROM notes_evaluation n JOIN matiere m ON m.id_matiere=n.matiere_id WHERE n.classe_id=$classe AND n.deleted_at IS NULL"; }
else { $sql="SELECT n.eleve_id, n.matiere_id, n.m1,n.m2,n.m3,n.m4,n.m5,n.m6, m.nom_matiere, m.coefficient AS coef FROM notes_sequence n JOIN matiere m ON m.id_matiere=n.matiere_id WHERE n.classe_id=$classe AND n.deleted_at IS NULL"; }
$notesBy=[]; $res=$db->query($sql); while($n=$res->fetch_assoc()) $notesBy[(int)$n['eleve_id']][]=$n;
$students=[]; foreach($eleves as $id=>$e){ $students[]=['eleve_id'=>$id,'notes'=>$notesBy[$id]??[]]; }
$res=$moteur->calculer($students,['report_type'=>'annual','echelles'=>$echelles,'grades'=>$grades,'parametres'=>$params,'paliers_absence'=>$paliers,'niveau_code'=>'MOYEN']);
$out=[]; foreach($eleves as $id=>$e){
  $auto=$res['students'][$id]['decision']??null; $moy=$res['students'][$id]['moyenne_generale']??null;
  $heures=ssmunie_absences_non_justifiees($db,$id); $sanction=ssmunie_chercher_sanction($heures,$paliers);
  $final=$auto; $motif='';
  if($sanction && ($sanction['exclusion_definitive']??0)){ $final='exclu'; $motif='Exclusion définitive (' . $heures . 'h non justifiées)'; }
  $out[]=['eleve_id'=>$id,'matricule'=>$e['matricule'],'nom'=>$e['nom'],'prenom'=>$e['prenom'],'moyenne'=>$moy,'decision_auto'=>$auto,'heures_non_justifiees'=>$heures,'sanction'=>$sanction,'decision_finale'=>$final,'motif'=>$motif];
}
$db->close();
apiResponse(['success'=>true,'data'=>$out]);
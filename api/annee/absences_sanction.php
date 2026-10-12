<?php
/** SSMUNIE — Sanction d'absence calculée (B4b) — GET ?eleve=. */
declare(strict_types=1);
$requiredPermission='students.view';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_absences.php';
require_once __DIR__ . '/../lib/finances.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
$eleveId=(int)($_GET['eleve']??0); if($eleveId<=0) apiErrorCode('validation_error',400);
$heures=ssmunie_absences_non_justifiees($db,$eleveId);
$paliers=$db->query('SELECT palier, heures_min, heures_max, sanction, duree_jours, exclusion_definitive, convocation_parents FROM parametres_absence WHERE actif=1')->fetch_all(MYSQLI_ASSOC);
$sanction=ssmunie_chercher_sanction($heures,$paliers);
$db->close();
apiResponse(['success'=>true,'data'=>['eleve_id'=>$eleveId,'heures_non_justifiees'=>$heures,'sanction'=>$sanction]]);
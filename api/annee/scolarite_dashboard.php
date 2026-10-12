<?php
/** SSMUNIE — Dashboard scolarité (B4b) — GET (finance.reports). */
declare(strict_types=1);
$requiredPermission = 'finance.reports';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_finances.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$attendu=0.0; $percu_ins=0.0; $percu_sco=0.0; $nb=0;
$res = $db->query('SELECT e.id_eleve FROM eleve e JOIN classe c ON c.id_classe = e.classe_id JOIN categorie_classe cc ON cc.id_categorie = c.categorie_id WHERE e.deleted_at IS NULL');
$ids=[]; while($r=$res->fetch_assoc()) $ids[]=(int)$r['id_eleve'];
foreach($ids as $id){ $s = ssmunie_eleve_situation($db, $id); if($s){ $attendu += $s['total_net']; $nb++; } }
$percu_ins = (float)($db->query('SELECT COALESCE(SUM(montant),0) FROM inscription WHERE valide=1')->fetch_row()[0]);
$percu_sco = (float)($db->query('SELECT COALESCE(SUM(montant),0) FROM scolarite')->fetch_row()[0]);
$percu = $percu_ins + $percu_sco;
$parMois = $db->query("SELECT DATE_FORMAT(date_paye,'%Y-%m') mois, SUM(montant) total FROM scolarite WHERE type_action='versement' GROUP BY mois ORDER BY mois")->fetch_all(MYSQLI_ASSOC);
$db->close();
apiResponse(['success'=>true,'data'=>['nb_eleves'=>$nb,'attendu'=>round($attendu,2),'percu'=>round($percu,2),'percu_ins'=>round($percu_ins,2),'percu_sco'=>round($percu_sco,2),'reste'=>round($attendu-$percu,2),'par_mois'=>$parMois]]);
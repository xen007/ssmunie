<?php
/**
 * SSMUNIE — Helpers financiers (B4b) — dépendants BDD.
 * ssmunie_next_code() : compteur de versement race-safe (mode 'versement').
 * ssmunie_eleve_situation() : situation financière complète d'un élève.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/finances.php';

if (!function_exists('ssmunie_next_code')) {
    /** Code de versement unique par année : PREFIX + 5 chiffres (race-safe, FOR UPDATE). */
    function ssmunie_next_code(mysqli $db, int $anneeId, string $prefix): string
    {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("SELECT compteur FROM matricule_counter WHERE annee_id = ? AND mode = 'versement' AND IFNULL(section_id,0)=0 AND IFNULL(classe_id,0)=0 FOR UPDATE");
            $stmt->bind_param('i', $anneeId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $next = $row ? (int) $row['compteur'] + 1 : 1;
            if ($row) {
                $db->query("UPDATE matricule_counter SET compteur = $next WHERE annee_id = $anneeId AND mode = 'versement' AND IFNULL(section_id,0)=0 AND IFNULL(classe_id,0)=0");
            } else {
                $db->query("INSERT INTO matricule_counter (annee_id, mode, section_id, classe_id, compteur) VALUES ($anneeId, 'versement', 0, 0, $next)");
            }
            $db->commit();
        } catch (Throwable $e) { $db->rollback(); throw $e; }
        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('ssmunie_eleve_situation')) {
    /** Situation financière complète (frais, réductions, nets, cumuls, restes, statut, tranche). */
    function ssmunie_eleve_situation(mysqli $db, int $eleveId): ?array
    {
        $stmt = $db->prepare('SELECT e.id_eleve, e.matricule, e.nom, e.prenom, e.classe_id, e.statut, c.categorie_id, cc.frais_ins, cc.frais_sco, cc.tranche1, cc.tranche2, cc.tranche3, cc.tranche4 FROM eleve e LEFT JOIN classe c ON c.id_classe = e.classe_id LEFT JOIN categorie_classe cc ON cc.id_categorie = c.categorie_id WHERE e.id_eleve = ?');
        $stmt->bind_param('i', $eleveId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;

        $frais_ins = (float) ($row['frais_ins'] ?? 0);
        $frais_sco = (float) ($row['frais_sco'] ?? 0);
        $tranches = [(float) ($row['tranche1'] ?? 0), (float) ($row['tranche2'] ?? 0), (float) ($row['tranche3'] ?? 0), (float) ($row['tranche4'] ?? 0)];

        $redTypes = $db->query("SELECT tr.application, tr.taux, tr.montant FROM eleve_reduction er JOIN types_reductions tr ON tr.id_type = er.type_id WHERE er.eleve_id = $eleveId AND er.actif = 1 AND er.deleted_at IS NULL AND tr.statut = 1")->fetch_all(MYSQLI_ASSOC);
        $calc = ssmunie_calc_reductions($redTypes, $frais_ins, $frais_sco);

        $cumul_ins = (float) ($db->query("SELECT COALESCE(SUM(montant),0) FROM inscription WHERE eleve_id = $eleveId AND valide = 1")->fetch_row()[0]);
        $cumul_sco = (float) ($db->query("SELECT COALESCE(SUM(montant),0) FROM scolarite WHERE eleve_id = $eleveId")->fetch_row()[0]);

        $ins_net = $calc['ins_net']; $sco_net = $calc['sco_net'];
        $reste_ins = max(0.0, $ins_net - $cumul_ins);
        $reste_sco = max(0.0, $sco_net - $cumul_sco);
        $total_net = $ins_net + $sco_net;
        $cumul_global = $cumul_ins + $cumul_sco;
        $reste_global = $reste_ins + $reste_sco;
        $tranches_eff = ssmunie_tranches_effectives($frais_sco, $sco_net, $tranches);

        return [
            'eleve_id' => (int) $eleveId, 'matricule' => $row['matricule'], 'nom' => $row['nom'], 'prenom' => $row['prenom'],
            'classe_id' => (int) ($row['classe_id'] ?? 0), 'categorie_id' => (int) ($row['categorie_id'] ?? 0),
            'statut_eleve' => $row['statut'],
            'frais_ins' => $frais_ins, 'frais_sco' => $frais_sco,
            'red_ins' => $calc['red_ins'], 'red_sco' => $calc['red_sco'], 'red_total' => $calc['red_total'],
            'ins_net' => $ins_net, 'sco_net' => $sco_net, 'total_net' => round($total_net, 2),
            'cumul_ins' => round($cumul_ins, 2), 'cumul_sco' => round($cumul_sco, 2), 'cumul_global' => round($cumul_global, 2),
            'reste_ins' => round($reste_ins, 2), 'reste_sco' => round($reste_sco, 2), 'reste_global' => round($reste_global, 2),
            'statut_global' => ssmunie_statut_global($total_net, $cumul_global, $reste_global),
            'tranches_eff' => $tranches_eff,
            'tranche_courante' => ssmunie_tranche_courante($cumul_sco, $tranches_eff),
            'reductions' => $redTypes,
        ];
    }
}
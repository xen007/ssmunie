<?php
/**
 * SSMUNIE — Moteur financier PUR (B4b) — traduction fidèle de la ROADMAP finance v1.0.
 * Aucune E/S : entrée = données, sortie = résultat (testable isolément).
 *
 * Clarification utilisateur (A) : chaque réduction s'applique à SA base
 * (inscription / scolarité / total). JAMAIS de cascade d'une base sur l'autre.
 * Pour 'total' : réduction du total répartie AU PRORATA des nets (aucune priorité).
 */
declare(strict_types=1);

if (!function_exists('ssmunie_calc_reductions')) {
    /**
     * §4 — Réductions. $types : [{application, taux, montant}, ...].
     * Retourne [ins_net, sco_net, red_ins, red_sco, red_total].
     */
    function ssmunie_calc_reductions(array $types, float $frais_ins, float $frais_sco): array
    {
        $red_ins = 0.0; $red_sco = 0.0; $red_total = 0.0;
        foreach ($types as $r) {
            $app = $r['application'] ?? 'scolarite';
            $base = match ($app) {
                'inscription' => $frais_ins,
                'total' => $frais_ins + $frais_sco,
                default => $frais_sco,
            };
            $taux = (float) ($r['taux'] ?? 0);
            $m = $taux > 0 ? $base * $taux / 100 : (float) ($r['montant'] ?? 0);
            if ($app === 'inscription') $red_ins += $m;
            elseif ($app === 'scolarite') $red_sco += $m;
            else $red_total += $m;
        }
        $ins_net = max(0.0, $frais_ins - $red_ins);
        $sco_net = max(0.0, $frais_sco - $red_sco);
        // 'total' : prorata (aucune cascade prioritaire entre les bases)
        $somme = $ins_net + $sco_net;
        if ($red_total > 0 && $somme > 0) {
            $ratio = max(0.0, $somme - $red_total) / $somme;
            $ins_net *= $ratio;
            $sco_net *= $ratio;
        }
        return [
            'ins_net' => round($ins_net, 2),
            'sco_net' => round($sco_net, 2),
            'red_ins' => round($red_ins, 2),
            'red_sco' => round($red_sco, 2),
            'red_total' => round($red_total, 2),
        ];
    }
}

if (!function_exists('ssmunie_tranches_effectives')) {
    /**
     * §5 — Tranches dynamiques. Ne compte que les tranches > 0.
     * Ajuste au ratio de réduction puis répartit le résidu sur la dernière tranche active.
     * Garantie : Σ t_eff = frais_sco_net (au franc près).
     */
    function ssmunie_tranches_effectives(float $frais_sco_brut, float $frais_sco_net, array $tranches): array
    {
        $ratio = $frais_sco_brut > 0 ? $frais_sco_net / $frais_sco_brut : 1.0;
        $eff = []; $derniere = null;
        foreach ($tranches as $i => $t) {
            $t = (float) $t;
            $eff[$i] = $t <= 0 ? 0.0 : round($t * $ratio);
            if ($eff[$i] > 0) $derniere = $i;
        }
        if ($derniere !== null) {
            $diff = round($frais_sco_net) - array_sum($eff);
            $eff[$derniere] += $diff;
        }
        return array_values($eff);
    }
}

if (!function_exists('ssmunie_tranche_courante')) {
    /** §5 — Tranche actuelle (ou « Soldé »). */
    function ssmunie_tranche_courante(float $verse_sco, array $tranches_eff): string
    {
        $cumul = 0.0; $n = 1; $total = 0.0;
        foreach ($tranches_eff as $t) { $total += (float) $t; }
        foreach ($tranches_eff as $t) {
            $t = (float) $t;
            if ($t <= 0) { $n++; continue; }
            $cumul += $t;
            if ($verse_sco < $cumul) return "Tranche $n";
            $n++;
        }
        if ($total > 0 && $verse_sco >= $total) return 'Soldé';
        return "Tranche $n";
    }
}

if (!function_exists('ssmunie_statut_global')) {
    /** §6 — Statut financier global. */
    function ssmunie_statut_global(float $total_net_global, float $cumul_global, float $reste_global): string
    {
        if ($total_net_global <= 0) return 'neant';
        if ($cumul_global <= 0) return 'impaye';
        if ($reste_global <= 0) return 'solde';
        return 'partiel';
    }
}

if (!function_exists('ssmunie_repartir_paiement')) {
    /**
     * §7 — Répartition d'un paiement. I2 : rejet si montant > total dû.
     * Retourne ['rejet'=>bool, 'part_ins'=>float, 'part_sco'=>float, 'total_du'=>float].
     */
    function ssmunie_repartir_paiement(float $montant, float $reste_ins, float $reste_sco, bool $ins_active): array
    {
        $total_du = $reste_ins + $reste_sco;
        if ($montant > $total_du + 0.001) {
            return ['rejet' => true, 'part_ins' => 0.0, 'part_sco' => 0.0, 'total_du' => round($total_du, 2)];
        }
        if ($ins_active && $reste_ins > 0) {
            $part_ins = min($montant, $reste_ins);
            $part_sco = $montant - $part_ins;
        } else {
            $part_ins = 0.0; $part_sco = $montant;
        }
        return ['rejet' => false, 'part_ins' => round($part_ins, 2), 'part_sco' => round($part_sco, 2), 'total_du' => round($total_du, 2)];
    }
}

if (!function_exists('ssmunie_mois_scolaire')) {
    /** §4.7 — Mois calendaire → mois scolaire (Sept=1 .. Mai=9). 6/7/8 → null (refus). */
    function ssmunie_mois_scolaire(int $moisCalendaire): ?int
    {
        if ($moisCalendaire >= 9 && $moisCalendaire <= 12) return $moisCalendaire - 8;
        if ($moisCalendaire >= 1 && $moisCalendaire <= 5) return $moisCalendaire + 4;
        return null;
    }
}

if (!function_exists('ssmunie_chercher_sanction')) {
    /** §4.6 — Cherche le palier de sanction pour un nombre d'heures non justifiées. */
    function ssmunie_chercher_sanction(int $heures, array $paliers): ?array
    {
        usort($paliers, fn($a, $b) => ((int) $a['palier']) <=> ((int) $b['palier']));
        foreach ($paliers as $p) {
            $min = (int) ($p['heures_min'] ?? 0);
            $max = isset($p['heures_max']) && $p['heures_max'] !== null && $p['heures_max'] !== '' ? (int) $p['heures_max'] : null;
            if ($heures >= $min && ($max === null || $heures <= $max)) return $p;
        }
        return null;
    }
}
<?php
/**
 * SSMUNIE — Moteur de génération d'emploi du temps (B5) — classe PHP PURE.
 * Aucune E/S (pas de BDD, pas de requête) : entrée = données, sortie = résultat.
 * Testable isolément (voir api/lib/edt.test.php).
 *
 * Modèle d'occupation PAR dimension (un même créneau (jour,tranche) peut recevoir
 * plusieurs cours de classes/enseignants/salles différents) :
 *   profAt  [jour][tranche] = enseignant_id
 *   classeAt[jour][tranche] = classe_id
 *   salleAt [jour][tranche] = salle
 *
 * Contraintes dures :
 *   1. Anti-conflit PROF   : 1 prof ne peut être dans 2 cours au même créneau.
 *   2. Anti-conflit CLASSE : 1 classe ne peut avoir 2 cours au même créneau.
 *   3. Anti-conflit SALLE  : (si salle spécifiée) pas 2 cours même salle même créneau.
 *   4. Respect indisponibilités (prof vs créneau).
 *   5. Respect max_heures_jour_prof.
 *   6. Respect max_seances_matiere_jour (par classe × matière).
 *   7. Respect duree_max_bloc (bloc = créneaux consécutifs, min(duree_max_seance, duree_max_bloc)).
 *   8. Pas de cours sur tranches de pause (est_pause=1) ni sur marqueur de fin (durée nulle).
 *
 * Ordre de génération :
 *   1. Trier les cours par volume horaire décroissant (les plus lourds d'abord).
 *   2. Pour chaque cours, chercher le prochain créneau libre compatible.
 *   3. Retourner affectations + cours non placés (avec raison).
 */

declare(strict_types=1);

class MoteurEDT
{
    private array $params;
    private array $contraintes;

    // État interne (réinitialisé à chaque generer()).
    private array $profAt   = []; // [jourId][trancheId] = enseignantId
    private array $classeAt = []; // [jourId][trancheId] = classeId
    private array $salleAt  = []; // [jourId][trancheId] = salle
    private array $profDay  = []; // [enseignantId][jourId] = nb créneaux
    private array $matClsDay = []; // [classeId][matiereId][jourId] = nb créneaux
    private array $coursTranches = []; // [coursId][jourId] = [ordres...]

    public function __construct(array $params = [], array $contraintes = [])
    {
        $this->params = array_merge([
            'max_heures_jour_prof'     => 6,
            'max_seances_matiere_jour' => 2,
            'duree_max_bloc'           => 3,
            'autoriser_samedi'         => 0,
            'mode_prioritaire'         => 'volume',
        ], $params);
        $this->contraintes = $contraintes;
    }

    /**
     * Génère les affectations.
     *
     * @param array $cours     [{id_cours, matiere_id, classe_id, enseignant_id, volume_horaire,
     *                          duree_max_seance?, sessions?, salle?}, ...]
     *                          sessions : nb de créneaux hebdo à placer (défaut = volume_horaire).
     * @param array $tranches  [{id, heure_debut, heure_fin, est_pause, ordre, actif?}, ...]
     * @param array $jours     [{id_jour, libellee_jour, ordre, actif?}, ...]
     * @param array $indispos  [{enseignant_id, jour_id, tranche_id}, ...]
     * @return array {affectations:[], non_places:[], stats:{}}
     */
    public function generer(array $cours, array $tranches, array $jours, array $indispos): array
    {
        $this->reset();

        $joursOk    = $this->joursUtilisables($jours);
        $tranchesOk = $this->tranchesUtilisables($tranches);
        $indispoSet = $this->indisposSet($indispos);
        $ordre      = $this->triCours($cours, $indispoSet);

        $affectations = [];
        $nonPlaces    = [];
        $sessionsDemandees = 0;
        $sessionsPlacees   = 0;

        foreach ($ordre as $c) {
            $coursId   = (int) ($c['id_cours'] ?? $c['id'] ?? 0);
            $matiereId = (int) ($c['matiere_id'] ?? 0);
            $classeId  = (int) ($c['classe_id'] ?? 0);
            $ensId     = (isset($c['enseignant_id']) && $c['enseignant_id'] !== null && $c['enseignant_id'] !== '')
                        ? (int) $c['enseignant_id'] : null;
            $salle     = trim((string) ($c['salle'] ?? ''));

            $nb = isset($c['sessions']) && is_numeric($c['sessions'])
                ? (int) $c['sessions']
                : (int) ($c['volume_horaire'] ?? 0);
            if ($nb <= 0) $nb = 1;
            $sessionsDemandees += $nb;

            $slots   = $this->slotsOrdonnes($joursOk, $tranchesOk, $coursId, $ensId);
            $placees = 0;
            $raisons = [];
            $fail    = false;

            for ($s = 0; $s < $nb; $s++) {
                $placed = false;
                foreach ($slots as $slot) {
                    [$jourId, $jourOrdre, $trancheId, $trancheOrdre] = $slot;
                    if ($this->compatible($c, $coursId, $matiereId, $classeId, $ensId, $salle, $jourId, $trancheId, $trancheOrdre, $indispoSet, $raisons)) {
                        $this->placer($coursId, $matiereId, $classeId, $ensId, $salle, $jourId, $trancheId, $trancheOrdre);
                        $affectations[] = [
                            'cours_id'      => $coursId,
                            'matiere_id'    => $matiereId,
                            'classe_id'     => $classeId,
                            'enseignant_id' => $ensId,
                            'jour_id'       => $jourId,
                            'tranche_id'    => $trancheId,
                            'salle'         => $salle !== '' ? $salle : null,
                        ];
                        $placees++;
                        $sessionsPlacees++;
                        $placed = true;
                        break;
                    }
                }
                if (!$placed) { $fail = true; break; }
            }

            if ($fail) {
                $nonPlaces[] = [
                    'cours_id'      => $coursId,
                    'matiere_id'    => $matiereId,
                    'classe_id'     => $classeId,
                    'enseignant_id' => $ensId,
                    'raison'        => $this->raisonDominante($raisons),
                    'demande'       => $nb,
                    'placees'       => $placees,
                ];
            }
        }

        return [
            'affectations' => $affectations,
            'non_places'   => $nonPlaces,
            'stats'        => [
                'cours_total'        => count($cours),
                'cours_places'       => count($cours) - count($nonPlaces),
                'cours_non_places'   => count($nonPlaces),
                'sessions_demandees' => $sessionsDemandees,
                'sessions_placees'   => $sessionsPlacees,
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Normalisation
    // -----------------------------------------------------------------------

    private function reset(): void
    {
        $this->profAt = $this->classeAt = $this->salleAt = [];
        $this->profDay = $this->matClsDay = $this->coursTranches = [];
    }

    private function joursUtilisables(array $jours): array
    {
        $out = [];
        foreach ($jours as $j) {
            $id    = (int) ($j['id_jour'] ?? $j['id'] ?? 0);
            $ordre = (int) ($j['ordre'] ?? 0);
            $actif = (int) ($j['actif'] ?? 1);
            if ($id <= 0 || $actif === 0) continue;
            if ((int) $this->params['autoriser_samedi'] === 0 && $ordre >= 6) continue;
            $out[] = ['id' => $id, 'ordre' => $ordre, 'libellee' => $j['libellee_jour'] ?? ''];
        }
        usort($out, fn($a, $b) => $a['ordre'] <=> $b['ordre']);
        return $out;
    }

    private function tranchesUtilisables(array $tranches): array
    {
        $out = [];
        foreach ($tranches as $t) {
            $id    = (int) ($t['id'] ?? 0);
            $ordre = (int) ($t['ordre'] ?? 0);
            $pause = (int) ($t['est_pause'] ?? 0);
            $actif = (int) ($t['actif'] ?? 1);
            if ($id <= 0 || $actif === 0 || $pause === 1) continue;
            if (strcmp((string) ($t['heure_fin'] ?? ''), (string) ($t['heure_debut'] ?? '')) <= 0) continue;
            $out[] = ['id' => $id, 'ordre' => $ordre];
        }
        usort($out, fn($a, $b) => $a['ordre'] <=> $b['ordre']);
        return $out;
    }

    private function indisposSet(array $indispos): array
    {
        $set = [];
        foreach ($indispos as $i) {
            $ens  = (int) ($i['enseignant_id'] ?? 0);
            $jour = (int) ($i['jour_id'] ?? 0);
            $tr   = (int) ($i['tranche_id'] ?? 0);
            if ($ens > 0 && $jour > 0 && $tr > 0) $set[$ens][$jour][$tr] = true;
        }
        return $set;
    }

    private function triCours(array $cours, array $indispoSet): array
    {
        $mode  = (string) $this->params['mode_prioritaire'];
        $ordre = array_values($cours);
        usort($ordre, function ($a, $b) use ($indispoSet, $mode) {
            if ($mode === 'profs') {
                $ia = $this->nbIndispos($a, $indispoSet);
                $ib = $this->nbIndispos($b, $indispoSet);
                if ($ib !== $ia) return $ib <=> $ia;
            }
            $va = (int) ($a['volume_horaire'] ?? 0);
            $vb = (int) ($b['volume_horaire'] ?? 0);
            return $vb <=> $va;
        });
        return $ordre;
    }

    private function nbIndispos(array $c, array $indispoSet): int
    {
        $ens = isset($c['enseignant_id']) ? (int) $c['enseignant_id'] : 0;
        if ($ens <= 0) return 0;
        $n = 0;
        foreach (($indispoSet[$ens] ?? []) as $trs) $n += count($trs);
        return $n;
    }

    // -----------------------------------------------------------------------
    // Ordonnancement des créneaux candidats
    // -----------------------------------------------------------------------

    /** Retourne [[jourId, jourOrdre, trancheId, trancheOrdre], ...] selon le mode. */
    private function slotsOrdonnes(array $jours, array $tranches, int $coursId, ?int $ensId): array
    {
        $mode  = (string) $this->params['mode_prioritaire'];
        $slots = [];
        foreach ($jours as $j) {
            foreach ($tranches as $t) {
                $slots[] = [$j['id'], $j['ordre'], $t['id'], $t['ordre']];
            }
        }
        if (!$slots) return $slots;

        if ($mode === 'equilibre') {
            $deja    = $this->nbPlacesCours($coursId);
            $nbJours = count($jours);
            $pivot   = $nbJours > 0 ? $deja % $nbJours : 0;
            usort($slots, function ($a, $b) use ($pivot, $nbJours) {
                $ra = ($a[1] - 1 - $pivot + $nbJours) % $nbJours;
                $rb = ($b[1] - 1 - $pivot + $nbJours) % $nbJours;
                if ($ra !== $rb) return $ra <=> $rb;
                return $a[3] <=> $b[3];
            });
            return $slots;
        }

        if ($mode === 'profs' && $ensId !== null) {
            $load = $this->profDay[$ensId] ?? [];
            usort($slots, function ($a, $b) use ($load) {
                $la = (int) ($load[$a[0]] ?? 0);
                $lb = (int) ($load[$b[0]] ?? 0);
                if ($la !== $lb) return $la <=> $lb;
                if ($a[1] !== $b[1]) return $a[1] <=> $b[1];
                return $a[3] <=> $b[3];
            });
            return $slots;
        }

        usort($slots, function ($a, $b) {
            if ($a[1] !== $b[1]) return $a[1] <=> $b[1];
            return $a[3] <=> $b[3];
        });
        return $slots;
    }

    private function nbPlacesCours(int $coursId): int
    {
        $n = 0;
        foreach ($this->coursTranches[$coursId] ?? [] as $trs) $n += count($trs);
        return $n;
    }

    // -----------------------------------------------------------------------
    // Compatibilité + placement
    // -----------------------------------------------------------------------

    private function compatible(
        array $c, int $coursId, int $matiereId, int $classeId, ?int $ensId, string $salle,
        int $jourId, int $trancheId, int $trancheOrdre, array $indispoSet, array &$raisons
    ): bool {
        $maxHeures  = max(1, (int) $this->params['max_heures_jour_prof']);
        $maxMat     = max(1, (int) $this->params['max_seances_matiere_jour']);
        $blocGlobal = max(1, (int) $this->params['duree_max_bloc']);
        $blocCours  = max(1, (int) ($c['duree_max_seance'] ?? $blocGlobal));
        $blocMax    = min($blocCours, $blocGlobal);

        // 4. Indisponibilité
        if ($ensId !== null && isset($indispoSet[$ensId][$jourId][$trancheId])) {
            $raisons['indisponibilite'] = true;
            return false;
        }
        // 1. Conflit prof
        if ($ensId !== null && isset($this->profAt[$jourId][$trancheId]) && (int) $this->profAt[$jourId][$trancheId] === $ensId) {
            $raisons['conflit_prof'] = true;
            return false;
        }
        // 2. Conflit classe
        if (isset($this->classeAt[$jourId][$trancheId]) && (int) $this->classeAt[$jourId][$trancheId] === $classeId) {
            $raisons['conflit_classe'] = true;
            return false;
        }
        // 3. Conflit salle
        if ($salle !== '' && isset($this->salleAt[$jourId][$trancheId]) && $this->salleAt[$jourId][$trancheId] === $salle) {
            $raisons['conflit_salle'] = true;
            return false;
        }
        // 5. Max heures/jour prof
        if ($ensId !== null && (int) ($this->profDay[$ensId][$jourId] ?? 0) >= $maxHeures) {
            $raisons['max_heures_jour_prof'] = true;
            return false;
        }
        // 6. Max séances matière/jour (classe × matière)
        if ($classeId > 0 && (int) ($this->matClsDay[$classeId][$matiereId][$jourId] ?? 0) >= $maxMat) {
            $raisons['max_seances_matiere_jour'] = true;
            return false;
        }
        // 7. Bloc max (créneaux consécutifs)
        if ($this->consecutifs($coursId, $jourId, $trancheOrdre) > $blocMax) {
            $raisons['duree_max_bloc'] = true;
            return false;
        }
        return true;
    }

    private function consecutifs(int $coursId, int $jourId, int $ordre): int
    {
        $placed = $this->coursTranches[$coursId][$jourId] ?? [];
        $len = 1;
        $o = $ordre - 1;
        while (in_array($o, $placed, true)) { $len++; $o--; }
        $o = $ordre + 1;
        while (in_array($o, $placed, true)) { $len++; $o++; }
        return $len;
    }

    private function placer(int $coursId, int $matiereId, int $classeId, ?int $ensId, string $salle, int $jourId, int $trancheId, int $trancheOrdre): void
    {
        if ($ensId !== null) $this->profAt[$jourId][$trancheId] = $ensId;
        $this->classeAt[$jourId][$trancheId] = $classeId;
        if ($salle !== '') $this->salleAt[$jourId][$trancheId] = $salle;
        if ($ensId !== null) $this->profDay[$ensId][$jourId] = (int) ($this->profDay[$ensId][$jourId] ?? 0) + 1;
        if ($classeId > 0) $this->matClsDay[$classeId][$matiereId][$jourId] = (int) ($this->matClsDay[$classeId][$matiereId][$jourId] ?? 0) + 1;
        $this->coursTranches[$coursId][$jourId][] = $trancheOrdre;
    }

    private function raisonDominante(array $raisons): string
    {
        $priorite = [
            'indisponibilite'          => 1,
            'conflit_prof'             => 2,
            'conflit_classe'           => 3,
            'conflit_salle'            => 4,
            'max_heures_jour_prof'     => 5,
            'max_seances_matiere_jour' => 6,
            'duree_max_bloc'           => 7,
        ];
        $best = 'no_slot';
        $bestP = 99;
        foreach ($raisons as $r => $_) {
            $p = $priorite[$r] ?? 8;
            if ($p < $bestP) { $bestP = $p; $best = $r; }
        }
        return $best;
    }
}

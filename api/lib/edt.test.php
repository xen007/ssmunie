<?php
/**
 * SSMUNIE — Tests unitaires du moteur EDT (B5).
 * Exécution : php api/lib/edt.test.php
 * Sortie : 1 ligne par test (PASS/FAIL) + résumé. Exit 1 si un test échoue.
 */

declare(strict_types=1);

require_once __DIR__ . '/edt.php';

$GLOBALS['__t_pass'] = 0;
$GLOBALS['__t_fail'] = 0;

function check(string $name, bool $cond, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['__t_pass']++;
        echo "[PASS] $name\n";
    } else {
        $GLOBALS['__t_fail']++;
        echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

// --- Fixtures ---------------------------------------------------------------

function jours5(): array {
    return [
        ['id_jour' => 1, 'libellee_jour' => 'Lundi',    'ordre' => 1, 'actif' => 1],
        ['id_jour' => 2, 'libellee_jour' => 'Mardi',    'ordre' => 2, 'actif' => 1],
        ['id_jour' => 3, 'libellee_jour' => 'Mercredi', 'ordre' => 3, 'actif' => 1],
        ['id_jour' => 4, 'libellee_jour' => 'Jeudi',    'ordre' => 4, 'actif' => 1],
        ['id_jour' => 5, 'libellee_jour' => 'Vendredi', 'ordre' => 5, 'actif' => 1],
    ];
}

function tranches3(): array {
    return [
        ['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1],
        ['id' => 12, 'heure_debut' => '08:25:00', 'heure_fin' => '09:20:00', 'est_pause' => 0, 'ordre' => 2, 'actif' => 1],
        ['id' => 13, 'heure_debut' => '09:20:00', 'heure_fin' => '10:00:00', 'est_pause' => 0, 'ordre' => 3, 'actif' => 1],
    ];
}

function tranchesPause(): array {
    return [
        ['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1],
        ['id' => 99, 'heure_debut' => '10:00:00', 'heure_fin' => '10:10:00', 'est_pause' => 1, 'ordre' => 4, 'actif' => 1],
        ['id' => 13, 'heure_debut' => '09:20:00', 'heure_fin' => '10:00:00', 'est_pause' => 0, 'ordre' => 3, 'actif' => 1],
    ];
}

function findAffect(array $res, int $coursId): array {
    return array_values(array_filter($res['affectations'], fn($a) => $a['cours_id'] === $coursId));
}

// ===========================================================================
// T1 — Placement simple (1 cours, 1 créneau)
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1]],
    tranches3(), jours5(), []
);
check('T1 placement simple', count($r['affectations']) === 1 && $r['affectations'][0]['jour_id'] === 1 && $r['affectations'][0]['tranche_id'] === 11);

// ===========================================================================
// T2 — Volume multiple → plusieurs créneaux distincts
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 3, 'duree_max_seance' => 3]],
    tranches3(), jours5(), []
);
$ids = array_map(fn($a) => $a['jour_id'] . ':' . $a['tranche_id'], $r['affectations']);
check('T2 volume multiple', count($r['affectations']) === 3 && count(array_unique($ids)) === 3);

// ===========================================================================
// T3 — Détection conflit prof (même prof, même créneau)
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [
        ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1],
        ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 200, 'enseignant_id' => 1000, 'volume_horaire' => 1],
    ],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
$np = $r['non_places'][0] ?? null;
check('T3 conflit prof', count($r['affectations']) === 1 && $np !== null && $np['cours_id'] === 2 && $np['raison'] === 'conflit_prof', $np['raison'] ?? 'no-np');

// ===========================================================================
// T4 — Détection conflit classe (même classe, même créneau)
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [
        ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1],
        ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 100, 'enseignant_id' => 2000, 'volume_horaire' => 1],
    ],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
$np = $r['non_places'][0] ?? null;
check('T4 conflit classe', count($r['affectations']) === 1 && $np !== null && $np['cours_id'] === 2 && $np['raison'] === 'conflit_classe', $np['raison'] ?? 'no-np');

// ===========================================================================
// T5 — Détection conflit salle (même salle, même créneau)
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [
        ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1, 'salle' => 'S1'],
        ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 200, 'enseignant_id' => 2000, 'volume_horaire' => 1, 'salle' => 'S1'],
    ],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
$np = $r['non_places'][0] ?? null;
check('T5 conflit salle', count($r['affectations']) === 1 && $np !== null && $np['raison'] === 'conflit_salle', $np['raison'] ?? 'no-np');

// ===========================================================================
// T6 — Respect indisponibilité
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1]],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]],
    [['enseignant_id' => 1000, 'jour_id' => 1, 'tranche_id' => 11]]
);
$np = $r['non_places'][0] ?? null;
check('T6 indisponibilité', count($r['affectations']) === 0 && $np !== null && $np['raison'] === 'indisponibilite', $np['raison'] ?? 'no-np');

// ===========================================================================
// T7 — Respect max_heures_jour_prof (comportement)
// ===========================================================================
$r = (new MoteurEDT(['max_heures_jour_prof' => 2, 'max_seances_matiere_jour' => 10, 'duree_max_bloc' => 10]))
    ->generer(
        [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 10, 'duree_max_seance' => 10]],
        tranches3(), jours5(), []
    );
$perDay = [];
foreach ($r['affectations'] as $a) $perDay[$a['jour_id']] = ($perDay[$a['jour_id']] ?? 0) + 1;
$maxDay = max($perDay);
check('T7 max_heures_jour_prof', count($r['affectations']) === 10 && $maxDay === 2, "maxDay=$maxDay placed=" . count($r['affectations']));

// ===========================================================================
// T8 — Respect max_seances_matiere_jour (comportement)
// ===========================================================================
$r = (new MoteurEDT(['max_heures_jour_prof' => 10, 'max_seances_matiere_jour' => 1, 'duree_max_bloc' => 10]))
    ->generer(
        [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 5, 'duree_max_seance' => 10]],
        tranches3(), jours5(), []
    );
$perDay = [];
foreach ($r['affectations'] as $a) $perDay[$a['jour_id']] = ($perDay[$a['jour_id']] ?? 0) + 1;
$maxDay = max($perDay);
check('T8 max_seances_matiere_jour', count($r['affectations']) === 5 && $maxDay === 1, "maxDay=$maxDay placed=" . count($r['affectations']));

// ===========================================================================
// T9 — Respect pause (aucun cours sur tranche de pause)
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 2, 'duree_max_seance' => 3]],
    tranchesPause(), [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
$onPause = array_filter($r['affectations'], fn($a) => $a['tranche_id'] === 99);
check('T9 respect pause', count($r['affectations']) === 2 && count($onPause) === 0, 'placés=' . count($r['affectations']));

// ===========================================================================
// T10 — Retour des non-placés avec raison
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [
        ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1],
        ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 200, 'enseignant_id' => 1000, 'volume_horaire' => 1],
    ],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
$np = $r['non_places'][0] ?? null;
check('T10 non-placés + raison', $np !== null && isset($np['raison']) && $np['raison'] !== '' && $np['raison'] !== 'no_slot', $np['raison'] ?? 'no-np');

// ===========================================================================
// T11 — Tri par volume décroissant
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [
        ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 5],
        ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 200, 'enseignant_id' => 2000, 'volume_horaire' => 1],
    ],
    tranches3(), jours5(), []
);
check('T11 tri volume décroissant', $r['affectations'][0]['cours_id'] === 1 && count(findAffect($r, 1)) === 5 && count(findAffect($r, 2)) === 1);

// ===========================================================================
// T12 — autoriser_samedi=0 : Samedi exclu
// ===========================================================================
$r = (new MoteurEDT(['autoriser_samedi' => 0]))->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1]],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 6, 'libellee_jour' => 'Samedi', 'ordre' => 6, 'actif' => 1]], []
);
check('T12 samedi exclu (0)', count($r['affectations']) === 0 && count($r['non_places']) === 1);

// ===========================================================================
// T13 — autoriser_samedi=1 : Samedi inclus
// ===========================================================================
$r = (new MoteurEDT(['autoriser_samedi' => 1]))->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1]],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 6, 'libellee_jour' => 'Samedi', 'ordre' => 6, 'actif' => 1]], []
);
check('T13 samedi inclus (1)', count($r['affectations']) === 1 && $r['affectations'][0]['jour_id'] === 6);

// ===========================================================================
// T14 — Respect duree_max_bloc (pas plus de N consécutifs)
// ===========================================================================
$r = (new MoteurEDT(['duree_max_bloc' => 3]))->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 2, 'duree_max_seance' => 2]],
    tranches3(), jours5(), []
);
// blocMax = min(2,3)=2 : les 2 séances sont consécutives (t1,t2) → OK.
$ordres = array_map(fn($a) => $a['tranche_id'], findAffect($r, 1));
sort($ordres);
check('T14 duree_max_bloc (2 ok)', count($ordres) === 2);

// Bloc strict : 1 jour, 3 tranches, duree_max_seance=2 → 2 placés max consécutifs.
$r2 = (new MoteurEDT(['duree_max_bloc' => 3]))->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 3, 'duree_max_seance' => 2]],
    tranches3(), [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
$placed = findAffect($r2, 1);
$ordres = array_map(fn($a) => $a['tranche_id'], $placed);
sort($ordres);
$consec = (count($ordres) === 2 && $ordres === [11, 12]);
check('T14b bloc strict (max 2 consécutifs)', count($r2['affectations']) === 2 && $consec, 'placed=' . count($r2['affectations']));

// ===========================================================================
// T15 — mode equilibre : répartit sur la semaine
// ===========================================================================
$r = (new MoteurEDT(['mode_prioritaire' => 'equilibre', 'duree_max_bloc' => 10]))->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 3, 'duree_max_seance' => 10]],
    tranches3(),
    [
        ['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1],
        ['id_jour' => 2, 'libellee_jour' => 'Mardi', 'ordre' => 2, 'actif' => 1],
    ],
    []
);
$jours = array_map(fn($a) => $a['jour_id'], $r['affectations']);
check('T15 mode equilibre', count($r['affectations']) === 3 && count(array_unique($jours)) === 2, 'jours=' . implode(',', $jours));

// ===========================================================================
// T16 — mode profs : indispos d'abord
// ===========================================================================
$r = (new MoteurEDT(['mode_prioritaire' => 'profs']))->generer(
    [
        ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1],
        ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 200, 'enseignant_id' => 2000, 'volume_horaire' => 1],
    ],
    tranches3(),
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]],
    [['enseignant_id' => 1000, 'jour_id' => 1, 'tranche_id' => 12]] // prof 1000 indispo t2
);
check('T16 mode profs (indispo en premier)', $r['affectations'][0]['cours_id'] === 1 && findAffect($r, 1)[0]['tranche_id'] !== 12);

// ===========================================================================
// T17 — Aucun créneau → tous non placés (raison no_slot)
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1]],
    [], [], []
);
$np = $r['non_places'][0] ?? null;
check('T17 aucun créneau', count($r['affectations']) === 0 && $np !== null && $np['raison'] === 'no_slot', $np['raison'] ?? 'no-np');

// ===========================================================================
// T18 — Enseignant null : pas de conflit prof
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [
        ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => null, 'volume_horaire' => 1],
        ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 200, 'enseignant_id' => null, 'volume_horaire' => 1],
    ],
    [['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1]],
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
check('T18 enseignant null', count($r['affectations']) === 2, 'placed=' . count($r['affectations']));

// ===========================================================================
// T19 — Marqueur de fin (durée nulle) exclu
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 1]],
    [
        ['id' => 11, 'heure_debut' => '07:30:00', 'heure_fin' => '08:25:00', 'est_pause' => 0, 'ordre' => 1, 'actif' => 1],
        ['id' => 88, 'heure_debut' => '16:40:00', 'heure_fin' => '16:40:00', 'est_pause' => 0, 'ordre' => 13, 'actif' => 1],
    ],
    [['id_jour' => 1, 'libellee_jour' => 'Lundi', 'ordre' => 1, 'actif' => 1]], []
);
check('T19 marqueur fin exclu', count($r['affectations']) === 1 && $r['affectations'][0]['tranche_id'] === 11);

// ===========================================================================
// T20 — volume_horaire = 0 → 1 séance par défaut
// ===========================================================================
$r = (new MoteurEDT())->generer(
    [['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 0]],
    tranches3(), jours5(), []
);
check('T20 volume 0 → 1 séance', count($r['affectations']) === 1);

// ===========================================================================
// T21 — Déterminisme (2 exécutions identiques)
// ===========================================================================
$inputCours = [
    ['id_cours' => 1, 'matiere_id' => 10, 'classe_id' => 100, 'enseignant_id' => 1000, 'volume_horaire' => 3],
    ['id_cours' => 2, 'matiere_id' => 20, 'classe_id' => 200, 'enseignant_id' => 2000, 'volume_horaire' => 2],
    ['id_cours' => 3, 'matiere_id' => 30, 'classe_id' => 100, 'enseignant_id' => 3000, 'volume_horaire' => 2],
];
$a = (new MoteurEDT())->generer($inputCours, tranches3(), jours5(), []);
$b = (new MoteurEDT())->generer($inputCours, tranches3(), jours5(), []);
check('T21 déterminisme', $a === $b);

// ===========================================================================
// Résumé
// ===========================================================================
echo "\n===== $GLOBALS[__t_pass] PASS / $GLOBALS[__t_fail] FAIL =====\n";
exit($GLOBALS['__t_fail'] === 0 ? 0 : 1);

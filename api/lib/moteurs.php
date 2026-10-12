<?php
/**
 * SSMUNIE — Moteurs de bulletin (B4a) — classes PHP PURES, testables isolément.
 * Traduction fidèle de la logique legacy ssm/ssmcol (calculs.js) :
 *   primaire : moyenne matière = (Σmoy / Σbarème) × 20  (SANS coefficient), APC.
 *   collège  : moyenne = Σ(moy×coef)/Σcoef, grades GCE, décisions.
 * Aucune E/S (pas de BDD, pas de requête) : entrée = données, sortie = résultat.
 */

declare(strict_types=1);

abstract class MoteurBulletin
{
    protected function val($v): bool
    {
        return $v !== null && $v !== '' && is_numeric($v);
    }

    /** Moyenne des valeurs numériques (ignore les vides). */
    protected function moy(array $vals): ?float
    {
        $f = array_values(array_filter($vals, fn($v) => $this->val($v)));
        $f = array_map('floatval', $f);
        return $f ? array_sum($f) / count($f) : null;
    }

    /** (Σ valeurs) / (Σ barèmes) × 20 — formule de normalisation primaire. */
    protected function normalise(array $notes, array $baremes): ?float
    {
        $n = array_sum(array_map('floatval', $notes));
        $b = array_sum(array_map('floatval', $baremes));
        return ($b > 0) ? ($n / $b) * 20 : null;
    }

    /** Appréciation depuis une échelle (triée note_min décroissant). */
    protected function appreciation(?float $note, array $echelles): ?string
    {
        if ($note === null) return null;
        foreach ($echelles as $e) {
            if ($note >= (float) $e['note_min']) return $e['code'];
        }
        return null;
    }

    /** Rangs ex-æquo. $moyennes = [id => moyenne]. Retourne [id => rang]. */
    protected function rangs(array $moyennes): array
    {
        arsort($moyennes);
        $rangs = []; $rang = 0; $prev = null; $i = 0;
        foreach ($moyennes as $id => $m) {
            $i++;
            if ($prev === null || $m < $prev) $rang = $i;
            $rangs[$id] = $rang;
            $prev = $m;
        }
        return $rangs;
    }

    /** Décision automatique (mode auto) — cf. computeBulletins legacy. */
    protected function decisionAuto(?float $moyenne, array $params, array $matieres, bool $isTerminal, int $nbPapers, array $grades, string $niveauCode): array
    {
        $seuil    = (float) ($params['seuil_admission'] ?? 10);
        $noteMin  = (float) ($params['note_min_matiere'] ?? 6);
        $minPaper = (int) ($params['min_papers'] ?? ($niveauCode === 'FORM5' ? 4 : 2));

        if ($isTerminal) {
            if ($nbPapers >= $minPaper) return ['admis', "$nbPapers papers / min $minPaper"];
            return ['echec', "$nbPapers / $minPaper papers"];
        }
        if ($moyenne !== null && $moyenne >= $seuil) {
            $mauvaises = array_filter($matieres, fn($m) => $m['moy'] !== null && $m['moy'] < $noteMin);
            if ($mauvaises) return ['redouble', "Matière(s) < $noteMin/20"];
            return ['admis', ''];
        }
        return ['redouble', "Moyenne < $seuil/20"];
    }

    /** Sélectionne la colonne m$s. */
    protected function col(array $row, int $s): ?float
    {
        $k = 'm' . $s;
        return $this->val($row[$k] ?? null) ? (float) $row[$k] : null;
    }
}

/**
 * PRIMAIRE + MATERNELLE — APC (notes par évaluation, m1..m9, sans coefficient).
 */
class MoteurPrimaire extends MoteurBulletin
{
    public function calculer(array $students, array $opts): array
    {
        $report   = $opts['report_type'] ?? 'trim1';
        $echelles = $opts['echelles'] ?? [];
        // séquences du trimestre (ordre 1..9) — primaire 3 par trimestre
        $seqMap = ['trim1'=>[1,2,3], 'trim2'=>[4,5,6], 'trim3'=>[7,8,9], 'annual'=>[1,2,3,4,5,6,7,8,9]];
        $seqs = $seqMap[$report] ?? $seqMap['trim1'];

        $out = [];
        foreach ($students as $st) {
            $rows = $st['notes'] ?? [];
            $matieres = [];
            // regroupe par matière
            $parMat = [];
            foreach ($rows as $r) $parMat[$r['matiere_id']][] = $r;

            $matSynths = []; // matière → synth /20
            foreach ($parMat as $mid => $evals) {
                $sumMoy = 0; $sumBareme = 0; $nom = '';
                foreach ($evals as $e) {
                    $nom = $e['nom_matiere'] ?? $nom;
                    $bareme = (float) ($e['bareme'] ?? 20);
                    if ($report === 'annual') {
                        // moy par trimestre + Gmoy
                        $m1 = $this->moy(array_map(fn($s)=>$this->col($e,$s), [1,2,3]));
                        $m2 = $this->moy(array_map(fn($s)=>$this->col($e,$s), [4,5,6]));
                        $m3 = $this->moy(array_map(fn($s)=>$this->col($e,$s), [7,8,9]));
                        $gmoy = $this->moy([$m1, $m2, $m3]);
                        if ($gmoy !== null) { $sumMoy += $gmoy; $sumBareme += $bareme; }
                    } else {
                        $notes = array_map(fn($s)=>$this->col($e,$s), $seqs);
                        $m = $this->moy($notes);
                        if ($m !== null) { $sumMoy += $m; $sumBareme += $bareme; }
                    }
                }
                $synth = $sumBareme > 0 ? ($sumMoy / $sumBareme) * 20 : null;
                $matSynths[$mid] = $synth;
                $matieres[] = ['matiere_id'=>$mid, 'nom'=>$nom, 'moy'=>$synth, 'appreciation'=>$this->appreciation($synth, $echelles), 'coef'=>1, 'total'=>$synth];
            }

            // moyenne générale (Σ notes / Σ barèmes) × 20
            $allNotes = []; $allBaremes = [];
            foreach ($rows as $e) {
                $bareme = (float) ($e['bareme'] ?? 20);
                if ($report === 'annual') {
                    foreach ([[1,2,3],[4,5,6],[7,8,9]] as $grp) {
                        $m = $this->moy(array_map(fn($s)=>$this->col($e,$s), $grp));
                        if ($m !== null) { $allNotes[] = $m; $allBaremes[] = $bareme; }
                    }
                } else {
                    foreach ($seqs as $s) {
                        $c = $this->col($e, $s);
                        if ($c !== null) { $allNotes[] = $c; $allBaremes[] = $bareme; }
                    }
                }
            }
            $moyGen = $this->normalise($allNotes, $allBaremes);
            $matieres = $this->triRangMatiere($matieres);
            $out[$st['eleve_id']] = [
                'matieres' => $matieres,
                'moyenne_generale' => $moyGen,
                'appreciation' => $this->appreciation($moyGen, $echelles),
            ];
        }
        $rangs = $this->rangs(array_map(fn($o) => $o['moyenne_generale'], $out));
        foreach ($out as $id => &$o) $o['rang'] = $rangs[$id] ?? null;
        unset($o);
        return ['students'=>$out, 'class_averages'=>$this->classe($out)];
    }

    protected function triRangMatiere(array $matieres): array
    {
        usort($matieres, fn($a,$b) => ($b['moy']??-1) <=> ($a['moy']??-1));
        $rang = 0; $prev = null; $i = 0;
        foreach ($matieres as &$m) { $i++; if ($prev===null || $m['moy'] < $prev) $rang=$i; $m['rang_matiere']=$rang; $prev=$m['moy']; }
        unset($m);
        return $matieres;
    }

    protected function classe(array $out): array
    {
        $v = array_values(array_filter(array_map(fn($o)=>$o['moyenne_generale'], $out), fn($x)=>$x!==null));
        return $v ? ['highest'=>max($v),'lowest'=>min($v),'middle'=>array_sum($v)/count($v)] : ['highest'=>null,'lowest'=>null,'middle'=>null];
    }
}

/** COLLÈGE GÉNÉRAL + TECHNIQUE — matières + coefficients + GCE. */
class MoteurCollege extends MoteurBulletin
{
    public function calculer(array $students, array $opts): array
    {
        $report   = $opts['report_type'] ?? 'seq';
        $echelles = $opts['echelles'] ?? [];
        $grades   = $opts['grades'] ?? [];
        $params   = $opts['parametres'] ?? [];
        $niveau   = $opts['niveau_code'] ?? 'MOYEN';
        $examen   = $niveau === 'FORM5' ? 'O_LEVEL' : 'A_LEVEL';
        $isTerminal = in_array($niveau, ['FORM5','LOWER6','UPPER6'], true);

        $out = [];
        foreach ($students as $st) {
            $rows = $st['notes'] ?? [];
            $matieres = []; $totCoef = 0; $totPts = 0; $nbPapers = 0;
            foreach ($rows as $r) {
                $coef = (float) ($r['coef'] ?? 1);
                $moy = $this->moyMatiere($r, $report, $niveau);
                $grade = null;
                if ($isTerminal && $moy !== null) {
                    $g = $this->gradeGCE($moy, $examen, $grades);
                    $grade = $g['grade'] ?? null;
                    if (($g['est_valide'] ?? false)) $nbPapers++;
                }
                if ($moy !== null) { $totCoef += $coef; $totPts += $moy * $coef; }
                $matieres[] = ['matiere_id'=>$r['matiere_id'], 'nom'=>$r['nom_matiere']??'', 'moy'=>$moy, 'coef'=>$coef, 'total'=>$moy!==null?$moy*$coef:null, 'grade'=>$grade, 'appreciation'=>$this->appreciation($moy,$echelles)];
            }
            $moyGen = $totCoef > 0 ? $totPts / $totCoef : null;
            [$dec, $motif] = $this->decisionAuto($moyGen, $params, $matieres, $isTerminal, $nbPapers, $grades, $niveau);
            // sanction absence (exclusion définitive override)
            $sanction = $this->sanction((int)($st['heures_absence_nj'] ?? 0), $opts['paliers_absence'] ?? []);
            if ($sanction && ($sanction['exclusion_definitive'] ?? false)) { $dec = 'exclu'; $motif = 'Exclusion définitive (' . $st['heures_absence_nj'] . 'h non justifiées)'; }
            $matieres = $this->triRangMatiere($matieres);
            $out[$st['eleve_id']] = [
                'matieres'=>$matieres, 'moyenne_generale'=>$moyGen, 'appreciation'=>$this->appreciation($moyGen,$echelles),
                'decision'=>$dec, 'motif'=>$motif, 'sanction_absence'=>$sanction, 'nb_papers_valides'=>$nbPapers,
            ];
        }
        $rangs = $this->rangs(array_map(fn($o)=>$o['moyenne_generale'], $out));
        foreach ($out as $id => &$o) $o['rang'] = $rangs[$id] ?? null;
        unset($o);
        return ['students'=>$out, 'class_averages'=>$this->classe($out)];
    }

    protected function moyMatiere(array $r, string $report, string $niveau): ?float
    {
        // report : seq|trim1|trim2|trim3|annual — collège 2 séquences/trimestre
        if ($report === 'seq') return $this->val($r['note'] ?? null) ? (float)$r['note'] : null;
        $map = ['trim1'=>[1,2],'trim2'=>[3,4],'trim3'=>[5,6],'annual'=>[1,2,3,4,5,6]];
        $seqs = $map[$report] ?? [1,2];
        if ($report === 'annual') {
            $m1 = $this->moy(array_map(fn($s)=>$this->col($r,$s),[1,2]));
            $m2 = $this->moy(array_map(fn($s)=>$this->col($r,$s),[3,4]));
            $m3 = $this->moy(array_map(fn($s)=>$this->col($r,$s),[5,6]));
            return $this->moy([$m1,$m2,$m3]);
        }
        return $this->moy(array_map(fn($s)=>$this->col($r,$s), $seqs));
    }

    protected function gradeGCE(float $note, string $examen, array $grades): array
    {
        foreach ($grades as $g) {
            if ($g['examen'] === $examen && $note >= (float)$g['note_min'] && $note <= (float)$g['note_max']) return $g;
        }
        return [];
    }

    protected function sanction(int $heures, array $paliers): ?array
    {
        foreach ($paliers as $p) {
            $min = (int)($p['heures_min'] ?? 0); $max = $p['heures_max'] !== null ? (int)$p['heures_max'] : PHP_INT_MAX;
            if ($heures >= $min && $heures <= $max) return $p;
        }
        return null;
    }

    protected function triRangMatiere(array $matieres): array
    {
        usort($matieres, fn($a,$b) => ($b['moy']??-1) <=> ($a['moy']??-1));
        $rang=0;$prev=null;$i=0;
        foreach ($matieres as &$m) { $i++; if($prev===null||$m['moy']<$prev)$rang=$i; $m['rang_matiere']=$rang; $prev=$m['moy']; }
        unset($m); return $matieres;
    }

    protected function classe(array $out): array
    {
        $v = array_values(array_filter(array_map(fn($o)=>$o['moyenne_generale'], $out), fn($x)=>$x!==null));
        return $v ? ['highest'=>max($v),'lowest'=>min($v),'middle'=>array_sum($v)/count($v)] : ['highest'=>null,'lowest'=>null,'middle'=>null];
    }
}

/** MATERNELLE — compétences transversales (même calcul que primaire). */
class MoteurMaternel extends MoteurPrimaire {}

/** TECHNIQUE — collège + validation de modules (même calcul, seuil 05). */
class MoteurCollegeTechnique extends MoteurCollege {}

/** Fabrique : renvoie le moteur selon la section. */
function ssmunie_moteur(int $sectionId): MoteurBulletin
{
    return match ($sectionId) {
        1 => new MoteurMaternel(),
        2 => new MoteurPrimaire(),
        3 => new MoteurCollege(),
        4 => new MoteurCollegeTechnique(),
        default => new MoteurPrimaire(),
    };
}

if (!function_exists('ssmunie_uuid')) {
    /** UUID v4 (MySQL n'en a pas de fiable en php). */
    function ssmunie_uuid(): string
    {
        $d = random_bytes(16); $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
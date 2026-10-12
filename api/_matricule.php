<?php
/**
 * SSMUNIE — Génération de matricules (B3)
 * =============================================================
 * 4 modes :
 *   standard   : {AAAA}{INITIALE}{compteur 4}              → 2026LYC0001
 *   section    : {AAAA}{INITIALE}-{CODE_SECTION}{compteur} → 2026LYC-P0001
 *   classe     : {AAAA}{INITIALE}-{CODE_CLASSE}-{compteur} → 2026LYC-6EA-0001
 *   enseignant : ENS{AAAA}{INITIALE}{compteur 4}           → ENS2026LYC0001
 *
 * CODE_SECTION : M (maternelle), P (primaire), G (général), T (technique).
 * INITIALE : etablissement.initiale (meta), ex. LYC.
 * AAAA : année de date_debut de l'année scolaire.
 *
 * Race-safe : compteur dans matricule_counter, incrémenté via
 * SELECT ... FOR UPDATE + UPDATE (verrou de ligne / gap-lock InnoDB).
 * 2 onglets simultanés ne produisent jamais le même matricule.
 */

declare(strict_types=1);

if (!function_exists('ssmunie_etablissement_initiale')) {
    /** Initiale de l'établissement (meta.etablissement.initiale), ex. LYC. */
    function ssmunie_etablissement_initiale(): string
    {
        global $mysqli;
        $initiale = 'SSM';
        if ($mysqli instanceof mysqli) {
            $res = $mysqli->query('SELECT initiale FROM etablissement LIMIT 1');
            if ($res && ($row = $res->fetch_assoc()) && !empty($row['initiale'])) {
                $initiale = strtoupper(trim((string) $row['initiale']));
            }
        }
        return $initiale;
    }
}

if (!function_exists('ssmunie_annee_start_year')) {
    /** Année civile (AAAA) de la date_debut de l'année scolaire $anneeId. */
    function ssmunie_annee_start_year(int $anneeId): string
    {
        global $mysqli;
        $year = date('Y');
        if ($mysqli instanceof mysqli) {
            $stmt = $mysqli->prepare('SELECT date_debut FROM annee_scolaire WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $anneeId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $ts = strtotime((string) $row['date_debut']);
                if ($ts !== false) {
                    $year = date('Y', $ts);
                }
            }
            $stmt->close();
        }
        return $year;
    }
}

if (!function_exists('ssmunie_section_letter')) {
    /** Lettre de section pour le matricule : M / P / G / T. */
    function ssmunie_section_letter(int $sectionId): string
    {
        switch ($sectionId) {
            case 1: return 'M';
            case 2: return 'P';
            case 3: return 'G';
            case 4: return 'T';
            default: return 'X';
        }
    }
}

if (!function_exists('ssmunie_classe_code')) {
    /** code_classe d'une classe (pour le mode 'classe'). */
    function ssmunie_classe_code(mysqli $db, int $classeId): string
    {
        if ($classeId <= 0) {
            return 'CLS';
        }
        $stmt = $db->prepare('SELECT code_classe FROM classe WHERE id_classe = ? LIMIT 1');
        $stmt->bind_param('i', $classeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $code = 'CLS';
        if ($row = $res->fetch_assoc()) {
            $code = (string) ($row['code_classe'] !== null && $row['code_classe'] !== '' ? $row['code_classe'] : 'CLS');
        }
        $stmt->close();
        return $code;
    }
}

if (!function_exists('ssmunie_format_matricule')) {
    /** Formate un matricule sans toucher au compteur. */
    function ssmunie_format_matricule(string $mode, string $year, string $initiale, string $sectionLetter, string $codeClasse, int $n): string
    {
        $pad = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        switch ($mode) {
            case 'section':
                return $year . $initiale . '-' . $sectionLetter . $pad;
            case 'classe':
                return $year . $initiale . '-' . $codeClasse . '-' . $pad;
            case 'enseignant':
                return 'ENS' . $year . $initiale . $pad;
            case 'standard':
            default:
                return $year . $initiale . $pad;
        }
    }
}

if (!function_exists('ssmunie_counter_next')) {
    /**
     * Valeur suivante du compteur (race-safe). $increment = false → simple
     * aperçu (aucune écriture). section_id / classe_id : 0 = « non applicable ».
     */
    function ssmunie_counter_next(mysqli $db, int $anneeId, string $mode, int $sectionId, int $classeId, bool $increment = true): int
    {
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT id, compteur FROM matricule_counter
                                   WHERE annee_id = ? AND mode = ? AND section_id = ? AND classe_id = ?
                                   FOR UPDATE');
            $stmt->bind_param('isii', $anneeId, $mode, $sectionId, $classeId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            $next = 1;
            if ($row) {
                $next = (int) $row['compteur'] + 1;
                if ($increment) {
                    $upd = $db->prepare('UPDATE matricule_counter SET compteur = ? WHERE id = ?');
                    $upd->bind_param('ii', $next, $row['id']);
                    $upd->execute();
                    $upd->close();
                }
            } elseif ($increment) {
                $ins = $db->prepare('INSERT INTO matricule_counter (annee_id, mode, section_id, classe_id, compteur) VALUES (?, ?, ?, ?, 1)');
                $ins->bind_param('isii', $anneeId, $mode, $sectionId, $classeId);
                $ins->execute();
                $ins->close();
            }

            $db->commit();
            return $next;
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}

if (!function_exists('ssmunie_generer_matricule')) {
    /**
     * Génère ET réserve (incrémente) un matricule.
     * @param string  $mode    standard | section | classe | enseignant
     * @param int     $annee   id de l'année scolaire (meta.annee_scolaire.id)
     * @param int     $section id de section (pour le mode 'section')
     * @param int|null $classe id de classe (pour le mode 'classe')
     * @param mysqli  $db      connexion à la base annuelle
     */
    function ssmunie_generer_matricule(string $mode, int $annee, int $section, ?int $classe, mysqli $db): string
    {
        $mode = in_array($mode, ['standard', 'section', 'classe', 'enseignant'], true) ? $mode : 'standard';
        $year      = ssmunie_annee_start_year($annee);
        $initiale  = ssmunie_etablissement_initiale();
        $secKey    = 0;
        $clsKey    = 0;
        $letter    = '';
        $codeClass = '';

        if ($mode === 'section') {
            $secKey = max(0, $section);
            $letter = ssmunie_section_letter($section);
        } elseif ($mode === 'classe') {
            $clsKey    = max(0, (int) $classe);
            $codeClass = ssmunie_classe_code($db, (int) $classe);
        }

        $n = ssmunie_counter_next($db, $annee, $mode, $secKey, $clsKey, true);
        return ssmunie_format_matricule($mode, $year, $initiale, $letter, $codeClass, $n);
    }
}

if (!function_exists('ssmunie_peek_matricule')) {
    /** Aperçu du prochain matricule SANS incrémenter (preview). */
    function ssmunie_peek_matricule(string $mode, int $annee, int $section, ?int $classe, mysqli $db): string
    {
        $mode = in_array($mode, ['standard', 'section', 'classe', 'enseignant'], true) ? $mode : 'standard';
        $year      = ssmunie_annee_start_year($annee);
        $initiale  = ssmunie_etablissement_initiale();
        $secKey    = 0;
        $clsKey    = 0;
        $letter    = '';
        $codeClass = '';

        if ($mode === 'section') {
            $secKey = max(0, $section);
            $letter = ssmunie_section_letter($section);
        } elseif ($mode === 'classe') {
            $clsKey    = max(0, (int) $classe);
            $codeClass = ssmunie_classe_code($db, (int) $classe);
        }

        $n = ssmunie_counter_next($db, $annee, $mode, $secKey, $clsKey, false);
        return ssmunie_format_matricule($mode, $year, $initiale, $letter, $codeClass, $n);
    }
}

<?php
/**
 * SSMUNIE — Helpers notes (B4a).
 * Sélection de la table selon la section de la classe :
 *   sections 1 (maternel) & 2 (primaire) → notes_evaluation (par évaluation, m1..m9)
 *   sections 3 (général) & 4 (technique) → notes_sequence (par matière, m1..m6)
 */
declare(strict_types=1);

if (!function_exists('ssmunie_note_table')) {
    function ssmunie_note_table(mysqli $db, int $classeId): string
    {
        $stmt = $db->prepare('SELECT section_id FROM classe WHERE id_classe = ? LIMIT 1');
        $stmt->bind_param('i', $classeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $section = (int) ($res->fetch_assoc()['section_id'] ?? 0);
        $stmt->close();
        return in_array($section, [1, 2], true) ? 'notes_evaluation' : 'notes_sequence';
    }
}

if (!function_exists('ssmunie_note_max_seq')) {
    function ssmunie_note_max_seq(int $sectionId): int
    {
        return in_array($sectionId, [1, 2], true) ? 9 : 6;
    }
}

if (!function_exists('ssmunie_section_of_classe')) {
    function ssmunie_section_of_classe(mysqli $db, int $classeId): int
    {
        $stmt = $db->prepare('SELECT section_id FROM classe WHERE id_classe = ? LIMIT 1');
        $stmt->bind_param('i', $classeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $section = (int) ($res->fetch_assoc()['section_id'] ?? 0);
        $stmt->close();
        return $section;
    }
}
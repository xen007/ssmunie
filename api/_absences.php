<?php
/** SSMUNIE — Helpers absences (B4b) : log + cumul non justifié. */
declare(strict_types=1);
require_once __DIR__ . '/lib/moteurs.php';

if (!function_exists('ssmunie_log_absence')) {
    function ssmunie_log_absence(mysqli $db, int $eleveId, int $moisId, $anT, $anJ, $nT, $nJ, string $action, ?string $motif, int $uid, string $role): void
    {
        $uuid = ssmunie_uuid();
        $stmt = $db->prepare('INSERT INTO absences_logs (id_uuid, eleve_id, mois_id, ancien_total, ancien_justifies, nouveau_total, nouveau_justifies, action, motif, user_id, user_role) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('siiiiiissis', $uuid, $eleveId, $moisId, $anT, $anJ, $nT, $nJ, $action, $motif, $uid, $role);
        $stmt->execute(); $stmt->close();
    }
}

if (!function_exists('ssmunie_absences_non_justifiees')) {
    /** Cumul des heures non justifiées d'un élève (année). */
    function ssmunie_absences_non_justifiees(mysqli $db, int $eleveId): int
    {
        $row = $db->query("SELECT COALESCE(SUM(abs_total - abs_justifies),0) AS s FROM absences WHERE eleve_id = $eleveId AND deleted_at IS NULL")->fetch_assoc();
        return max(0, (int) $row['s']);
    }
}
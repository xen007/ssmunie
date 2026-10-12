<?php
/**
 * SSMUNIE — Paramètres & référentiels de bulletin (B4a).
 * GET (settings.view) -> {parametres, echelles, grades, sequences}.
 * PUT (settings.edit) -> met à jour une clé de parametres_niveau.
 */
declare(strict_types=1);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') $requiredPermission = 'settings.view'; else $requiredPermission = 'settings.edit';
require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);
if ($method === 'GET') {
    $section = (int) ($_GET['section'] ?? 0);
    $w = $section > 0 ? ' AND section_id = ' . $section : '';
    $params = $db->query('SELECT id, section_id, cle, valeur, type FROM parametres_niveau WHERE actif = 1' . $w . ' ORDER BY section_id, cle')->fetch_all(MYSQLI_ASSOC);
    $echelles = $db->query('SELECT code, section_id, libelle_fr, libelle_en, note_min, ordre FROM echelles_appreciation WHERE actif = 1' . $w . ' ORDER BY section_id, note_min DESC')->fetch_all(MYSQLI_ASSOC);
    $grades = $db->query('SELECT examen, grade, note_min, note_max, est_valide FROM parametres_grades WHERE actif = 1 ORDER BY examen, note_max DESC')->fetch_all(MYSQLI_ASSOC);
    $sequences = $db->query('SELECT id, libellee_sequence, trimestre, ordre FROM sequence_scolaire WHERE actif = 1 ORDER BY ordre')->fetch_all(MYSQLI_ASSOC);
    $db->close();
    apiResponse(['success' => true, 'data' => ['parametres' => $params, 'echelles' => $echelles, 'grades' => $grades, 'sequences' => $sequences]]);
}
if ($method === 'PUT') {
    $body = readJsonBody();
    $section = (int) ($body['section_id'] ?? 0);
    $cle = trim((string) ($body['cle'] ?? ''));
    if ($section <= 0 || $cle === '') apiErrorCode('validation_error', 400);
    $valeur = $body['valeur'] ?? '';
    $stmt = $db->prepare('UPDATE parametres_niveau SET valeur = ? WHERE section_id = ? AND cle = ?');
    $stmt->bind_param('sis', $valeur, $section, $cle);
    $stmt->execute(); $stmt->close(); $db->close();
    apiResponse(['success' => true]);
}
apiErrorCode('method_not_allowed', 405);
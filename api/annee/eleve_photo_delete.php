<?php
/**
 * SSMUNIE — Suppression de la photo d'un élève (students.edit).
 * DELETE ?matricule= ou ?id= → fichiers supprimés + champs remis à NULL.
 */

declare(strict_types=1);

$requiredPermission = 'students.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_uploads.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'DELETE') {
    apiErrorCode('method_not_allowed', 405);
}

$mat = trim((string) ($_GET['matricule'] ?? ''));
$id  = (int) ($_GET['id'] ?? 0);

$eleve = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT id_eleve, matricule, photo_path FROM eleve WHERE id_eleve = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $eleve = $res->fetch_assoc();
    $stmt->close();
} elseif ($mat !== '') {
    $stmt = $db->prepare('SELECT id_eleve, matricule, photo_path FROM eleve WHERE matricule = ? LIMIT 1');
    $stmt->bind_param('s', $mat);
    $stmt->execute();
    $res = $stmt->get_result();
    $eleve = $res->fetch_assoc();
    $stmt->close();
}
if (!$eleve) apiErrorCode('not_found', 404);

$idEleve = (int) $eleve['id_eleve'];

// Suppression fichiers
$root = dirname(ssmunie_upload_root()); // racine du projet (chemins stockés relatifs au projet)
$paths = [];
if (!empty($eleve['photo_path'])) {
    $full = $root . '/' . ltrim((string) $eleve['photo_path'], '/');
    $paths[] = $full;
    $paths[] = dirname($full) . '/mini.jpg';
}
foreach ($paths as $p) {
    if (is_file($p)) @unlink($p);
}

$stmt = $db->prepare('DELETE FROM photo_eleve WHERE eleve_id = ?');
$stmt->bind_param('i', $idEleve);
$stmt->execute();
$stmt->close();

$stmt = $db->prepare('UPDATE eleve SET photo_path = NULL, photo_updated_at = NULL WHERE id_eleve = ?');
$stmt->bind_param('i', $idEleve);
$stmt->execute();
$stmt->close();
$db->close();

apiResponse(['success' => true]);

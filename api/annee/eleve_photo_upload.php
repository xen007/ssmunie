<?php
/**
 * SSMUNIE — Photo d'élève — POST multipart (students.edit).
 * Champ fichier : 'photo'. Identifiant : matricule OU id.
 * MIME vérifié via finfo (falsifié → rejet), redimensionnée 400/200 (GD).
 * Chemin : uploads/eleves/<matricule>/<annee>/photo.jpg + mini.jpg.
 */

declare(strict_types=1);

$requiredPermission = 'students.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_uploads.php';
require_once __DIR__ . '/../_matricule.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

$file = $_FILES['photo'] ?? null;
if (!$file || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
    apiErrorCode('validation_error', 400);
}

// Identifier l'élève
$mat = trim((string) ($_POST['matricule'] ?? ''));
$id  = (int) ($_POST['id'] ?? 0);
$eleve = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT id_eleve, matricule FROM eleve WHERE id_eleve = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $eleve = $res->fetch_assoc();
    $stmt->close();
} elseif ($mat !== '') {
    $stmt = $db->prepare('SELECT id_eleve, matricule FROM eleve WHERE matricule = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('s', $mat);
    $stmt->execute();
    $res = $stmt->get_result();
    $eleve = $res->fetch_assoc();
    $stmt->close();
}
if (!$eleve) apiErrorCode('not_found', 404);

$idEleve   = (int) $eleve['id_eleve'];
$matricule = (string) $eleve['matricule'];
$year      = ssmunie_annee_start_year($anneeId);

// 1. MIME réel (falsifié → rejet)
$mime = ssmunie_file_mime((string) $file['tmp_name']);
if (!ssmunie_allowed_image_mime($mime)) {
    apiErrorCode('invalid_mime', 400);
}

// 2. Répertoire + traitement
$dir = ssmunie_upload_root() . '/eleves/' . $matricule . '/' . $year;
if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
    apiErrorCode('upload_failed', 500);
}
$photoPath = $dir . '/photo.jpg';
$miniPath  = $dir . '/mini.jpg';

$result = ssmunie_save_photo((string) $file['tmp_name'], $photoPath, $miniPath);
if (!$result['ok']) {
    apiErrorCode($result['error'] === 'unsupported_image' || $result['error'] === 'invalid_image' ? 'invalid_mime' : 'upload_failed', 400);
}

$uid      = (int) ($_SESSION['user']['id'] ?? 0);
$relPhoto = 'uploads/eleves/' . $matricule . '/' . $year . '/photo.jpg';
$relMini  = 'uploads/eleves/' . $matricule . '/' . $year . '/mini.jpg';

// 3. Métadonnées photo_eleve (upsert) + eleve.photo_path
$stmt = $db->prepare('INSERT INTO photo_eleve (eleve_id, annee_id, chemin, miniature, hash_sha256, taille_octets, uploaded_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE chemin = VALUES(chemin), miniature = VALUES(miniature), hash_sha256 = VALUES(hash_sha256), taille_octets = VALUES(taille_octets), uploaded_by = VALUES(uploaded_by), uploaded_at = NOW()');
$stmt->bind_param('iisssii', $idEleve, $anneeId, $relPhoto, $relMini, $result['hash'], $result['size'], $uid);
$stmt->execute();
$stmt->close();

$stmt = $db->prepare('UPDATE eleve SET photo_path = ?, photo_updated_at = NOW() WHERE id_eleve = ?');
$stmt->bind_param('si', $relPhoto, $idEleve);
$stmt->execute();
$stmt->close();
$db->close();

apiResponse([
    'success' => true,
    'data'    => [
        'photo_path' => $relPhoto,
        'miniature'  => $relMini,
        'hash'       => $result['hash'],
        'size'       => $result['size'],
    ],
]);

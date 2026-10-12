<?php
/**
 * SSMUNIE — Documents + photo enseignant — POST multipart / DELETE (hr.edit).
 * Documents : CNI, contrat, CV, diplôme (PDF/JPG/PNG, max 10 Mo).
 * Photo : type='photo' (redimensionnée 400x400, GD).
 * Fichiers : uploads/enseignants/<matricule>/<type>_<uuid>.<ext>.
 */

declare(strict_types=1);

$requiredPermission = 'hr.edit';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_uploads.php';
require_once __DIR__ . '/../_matricule.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    $ensId = (int) ($_GET['enseignant_id'] ?? 0);
    if ($ensId <= 0) apiErrorCode('validation_error', 400);
    $stmt = $db->prepare('SELECT id, enseignant_id, type, chemin, nom_original, hash_sha256, taille_octets, mime_type, uploaded_at FROM document_enseignant WHERE enseignant_id = ? ORDER BY uploaded_at DESC');
    $stmt->bind_param('i', $ensId);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['enseignant_id'] = (int) $row['enseignant_id'];
        $row['taille_octets'] = $row['taille_octets'] !== null ? (int) $row['taille_octets'] : null;
        $list[] = $row;
    }
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => $list]);
}

function ssmunie_ens_row(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT id_enseignant, matricule_ens, cni_path, contrat_path, cv_path, diplome_path, photo_path FROM enseignant WHERE id_enseignant = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

if ($method === 'POST') {
    $file = $_FILES['file'] ?? null;
    if (!$file || (int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        apiErrorCode('validation_error', 400);
    }
    $ensId = (int) ($_POST['enseignant_id'] ?? 0);
    $type  = trim((string) ($_POST['type'] ?? ''));
    $ens   = ssmunie_ens_row($db, $ensId);
    if (!$ens) apiErrorCode('not_found', 404);

    $matricule = (string) $ens['matricule_ens'];
    $dir = ssmunie_upload_root() . '/enseignants/' . $matricule;
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        apiErrorCode('upload_failed', 500);
    }

    $mime = ssmunie_file_mime((string) $file['tmp_name']);
    $sizeBytes = (int) $file['size'];

    // --- Photo enseignant ---
    if ($type === 'photo') {
        if (!ssmunie_allowed_image_mime($mime)) apiErrorCode('invalid_mime', 400);
        $photoPath = $dir . '/photo.jpg';
        $miniPath  = $dir . '/mini.jpg';
        $result = ssmunie_save_photo((string) $file['tmp_name'], $photoPath, $miniPath);
        if (!$result['ok']) apiErrorCode($result['error'] === 'resize_failed' ? 'upload_failed' : 'invalid_mime', 400);
        $rel = 'uploads/enseignants/' . $matricule . '/photo.jpg';
        $stmt = $db->prepare('UPDATE enseignant SET photo_path = ?, photo_updated_at = NOW() WHERE id_enseignant = ?');
        $stmt->bind_param('si', $rel, $ensId);
        $stmt->execute();
        $stmt->close();
        $db->close();
        apiResponse(['success' => true, 'data' => ['photo_path' => $rel]]);
    }

    // --- Document ---
    $allowedTypes = ['cni', 'contrat', 'cv', 'diplome', 'attestation', 'autre'];
    if (!in_array($type, $allowedTypes, true)) apiErrorCode('validation_error', 400);

    if ($sizeBytes > 10 * 1024 * 1024) apiErrorCode('file_too_large', 400);
    if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) apiErrorCode('invalid_mime', 400);

    $extMap = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    $ext    = $extMap[$mime];
    $uuid   = bin2hex(random_bytes(8));
    $fname  = $type . '_' . $uuid . '.' . $ext;
    $dest   = $dir . '/' . $fname;

    if (!@move_uploaded_file((string) $file['tmp_name'], $dest)) {
        apiErrorCode('upload_failed', 500);
    }

    $uid  = (int) ($_SESSION['user']['id'] ?? 0);
    $orig = isset($file['name']) ? (string) $file['name'] : $fname;
    $hash = hash_file('sha256', $dest) ?: null;
    $rel  = 'uploads/enseignants/' . $matricule . '/' . $fname;

    $stmt = $db->prepare('INSERT INTO document_enseignant (enseignant_id, type, chemin, nom_original, hash_sha256, taille_octets, mime_type, uploaded_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssisi', $ensId, $type, $rel, $orig, $hash, $sizeBytes, $mime, $uid);
    $stmt->execute();
    $docId = (int) $stmt->insert_id;
    $stmt->close();

    // Mise à jour du chemin principal pour les types porteurs
    $pathCols = ['cni' => 'cni_path', 'contrat' => 'contrat_path', 'cv' => 'cv_path', 'diplome' => 'diplome_path'];
    if (isset($pathCols[$type])) {
        $col = $pathCols[$type];
        $stmt = $db->prepare("UPDATE enseignant SET $col = ? WHERE id_enseignant = ?");
        $stmt->bind_param('si', $rel, $ensId);
        $stmt->execute();
        $stmt->close();
    }

    $db->close();
    apiResponse(['success' => true, 'data' => ['id' => $docId, 'chemin' => $rel]]);
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) apiErrorCode('validation_error', 400);

    $stmt = $db->prepare('SELECT enseignant_id, type, chemin FROM document_enseignant WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $doc = $res->fetch_assoc();
    $stmt->close();
    if (!$doc) apiErrorCode('not_found', 404);

    $ensId = (int) $doc['enseignant_id'];
    $type  = (string) $doc['type'];
    $full  = dirname(ssmunie_upload_root()) . '/' . ltrim((string) $doc['chemin'], '/');
    if (is_file($full)) @unlink($full);

    $stmt = $db->prepare('DELETE FROM document_enseignant WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $pathCols = ['cni' => 'cni_path', 'contrat' => 'contrat_path', 'cv' => 'cv_path', 'diplome' => 'diplome_path'];
    if (isset($pathCols[$type])) {
        $col = $pathCols[$type];
        $stmt = $db->prepare("UPDATE enseignant SET $col = NULL WHERE id_enseignant = ? AND $col = ?");
        $stmt->bind_param('is', $ensId, $doc['chemin']);
        $stmt->execute();
        $stmt->close();
    }

    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);

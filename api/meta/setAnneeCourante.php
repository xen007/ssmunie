<?php
/**
 * SSMUNIE — Change l'année scolaire courante (D22) — POST admin.
 * Body : { anneeId }
 * NB : réglage global (affecte toutes les sessions) — restreint au rôle admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

requireRole('admin');

$body    = readJsonBody();
$anneeId = (int) ($body['anneeId'] ?? 0);
if ($anneeId <= 0) {
    apiErrorCode('validation_error', 400);
}

$stmt = $mysqli->prepare('SELECT id FROM annee_scolaire WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $anneeId);
$stmt->execute();
$res = $stmt->get_result();
$exists = $res->fetch_assoc() !== null;
$stmt->close();

if (!$exists) {
    apiErrorCode('not_found', 404);
}

$stmt = $mysqli->prepare('UPDATE etablissement SET annee_courante_id = ?');
$stmt->bind_param('i', $anneeId);
$stmt->execute();
$stmt->close();

apiResponse(['success' => true, 'anneeId' => $anneeId]);

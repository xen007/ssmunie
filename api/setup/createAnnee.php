<?php
/**
 * SSMUNIE — Crée une année scolaire + sa base annuelle (B2).
 * POST admin. Body : { libellee, date_debut, date_fin }
 * 1. Upsert de l'année en meta (annee_scolaire)
 * 2. Crée la base <initiale>-<YYYY> (tables + seed, idempotent)
 * 3. Définit annee_courante_id si absente
 */

declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiErrorCode('method_not_allowed', 405);
}

requireRole('admin');

$body      = readJsonBody();
$libellee  = trim((string) ($body['libellee'] ?? ''));
$dateDebut = (string) ($body['date_debut'] ?? '');
$dateFin   = (string) ($body['date_fin'] ?? '');

if ($libellee === '' || $dateDebut === '' || $dateFin === '') {
    apiErrorCode('validation_error', 400);
}
if (strtotime($dateFin) <= strtotime($dateDebut)) {
    apiErrorCode('validation_error', 400);
}

// 1. Upsert de l'année en meta
$anneeId = 0;
$stmt = $mysqli->prepare('SELECT id FROM annee_scolaire WHERE libellee = ? LIMIT 1');
$stmt->bind_param('s', $libellee);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if ($res && ($a = $res->fetch_assoc())) {
    $anneeId = (int) $a['id'];
    $stmt = $mysqli->prepare('UPDATE annee_scolaire SET date_debut = ?, date_fin = ?, statut = 1 WHERE id = ?');
    $stmt->bind_param('ssi', $dateDebut, $dateFin, $anneeId);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $mysqli->prepare('INSERT INTO annee_scolaire (libellee, date_debut, date_fin, statut) VALUES (?, ?, ?, 1)');
    $stmt->bind_param('sss', $libellee, $dateDebut, $dateFin);
    $stmt->execute();
    $anneeId = (int) $stmt->insert_id;
    $stmt->close();
}

// 2. Base annuelle (tables + seed)
try {
    $dbName = ssmunie_create_annee_db($anneeId);
} catch (Throwable $e) {
    error_log('SSMUNIE createAnnee: ' . $e->getMessage());
    apiErrorCode('setup_failed', 500);
}

// 3. Année courante si absente
$stmt = $mysqli->prepare('UPDATE etablissement SET annee_courante_id = ? WHERE annee_courante_id IS NULL OR annee_courante_id = 0');
$stmt->bind_param('i', $anneeId);
$stmt->execute();
$stmt->close();

apiResponse(['success' => true, 'anneeId' => $anneeId, 'dbName' => $dbName]);

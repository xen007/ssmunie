<?php
/**
 * SSMUNIE — GET établissement + années scolaires (public, whitelisté)
 * =============================================================
 * GET /ssmunie/api/meta/getEtablissement.php
 *
 * Retourne :
 * {
 *   success: true,
 *   etablissement: { nom, initiale, logo, ministere, contact, email,
 *                    adresse, devise, annee_courante_id },
 *   anneesDisponibles: [ { id, libellee, date_debut, date_fin, statut } ]
 * }
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../_helpers.php';

$etablissement = null;
$annees        = [];

if ($mysqli instanceof mysqli) {
    try {
        $res = $mysqli->query('SELECT * FROM etablissement LIMIT 1');
        if ($res && $res->num_rows > 0) {
            $etablissement = $res->fetch_assoc();
        }

        $resAnnee = $mysqli->query('SELECT * FROM annee_scolaire ORDER BY date_debut DESC');
        if ($resAnnee) {
            while ($row = $resAnnee->fetch_assoc()) {
                $annees[] = [
                    'id'         => (int) $row['id'],
                    'libellee'   => (string) $row['libellee'],
                    'date_debut' => (string) $row['date_debut'],
                    'date_fin'   => (string) $row['date_fin'],
                    'statut'     => (int) $row['statut'],
                ];
            }
        }
    } catch (Throwable $e) {
        // BDD indisponible → valeurs par défaut ci-dessous.
    }
}

if (!is_array($etablissement)) {
    $etablissement = [
        'nom'               => 'SSMUNIE',
        'initiale'          => 'SSM',
        'logo'              => null,
        'ministere'         => null,
        'contact'           => null,
        'email'             => null,
        'adresse'           => null,
        'devise'            => null,
        'annee_courante_id' => null,
    ];
} else {
    $etablissement = [
        'nom'               => $etablissement['nom'] ?? 'SSMUNIE',
        'initiale'          => $etablissement['initiale'] ?? null,
        'logo'              => $etablissement['logo'] ?? null,
        'ministere'         => $etablissement['ministere'] ?? null,
        'contact'           => $etablissement['contact'] ?? null,
        'email'             => $etablissement['email'] ?? null,
        'adresse'           => $etablissement['adresse'] ?? null,
        'devise'            => $etablissement['devise'] ?? null,
        'annee_courante_id' => $etablissement['annee_courante_id'] !== null ? (int) $etablissement['annee_courante_id'] : null,
    ];
}

apiResponse([
    'success'           => true,
    'etablissement'     => $etablissement,
    'anneesDisponibles' => $annees,
], 200);

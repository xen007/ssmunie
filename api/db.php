<?php
/**
 * SSMUNIE — Socle base de données (B1 v2)
 * =============================================================
 * - Session moderne (A1) : __Host-ssm_sid (HTTPS) / ssm_sid (HTTP)
 * - Sessions en BDD via DbSessionHandler (A5)
 * - Fingerprint de session (A2) + double timeout (A3)
 * - Connexion mysqli à la base "meta" (ssmunie_meta, utf8mb4)
 * - Helper getBaseAnnee() → connexion à une base annuelle (B2)
 * - AUCUN CORS (app same-origin, F6) — réintroduit en B9 si WAN
 *
 * INTERDITS (héritage ssm/ssmcol) : Dbase, dbs.php, PRIM_SESSIONID,
 * COLL_SESSIONID — jamais utilisés ici.
 */

declare(strict_types=1);

require_once __DIR__ . '/_session_handler.php';

// ---------------------------------------------------------------------------
// Environnement
// ---------------------------------------------------------------------------
$appEnv = getenv('APP_ENV') ?: 'dev';
$isProd = in_array($appEnv, ['prod', 'production'], true);

error_reporting($isProd ? 0 : E_ALL);
ini_set('display_errors', $isProd ? '0' : '1');
ini_set('log_errors', '1');

// ---------------------------------------------------------------------------
// Constantes de session (A3 — double timeout)
// ---------------------------------------------------------------------------
const SSMUNIE_IDLE_TIMEOUT = 1800;      // 30 min (réarmé à chaque requête)
const SSMUNIE_ABSOLUTE_TIMEOUT = 43200; // 12 h (depuis la création, jamais réarmé)
const SSMUNIE_WARN_AFTER = 1500;        // 25 min (seuil d'avertissement)

/**
 * Normalise une adresse IP pour le fingerprint (B3.1).
 * - ::1  → 127.0.0.1 (localhost IPv6)
 * - ::ffff:a.b.c.d → a.b.c.d (IPv4 mappée)
 * - IPv4 → troncature /24 (3 premiers octets + .0)
 * - IPv6 → troncature /64 (4 premiers segments + ::)
 * Évite qu'une même machine (proxy Vite ::1 vs 127.0.0.1) ne casse la session au F5.
 */
function ssmunie_normalize_ip(?string $ip): string
{
    $ip = trim((string) $ip);
    if ($ip === '') return 'unknown';
    if ($ip === '::1') return '127.0.0.1';
    if (str_starts_with($ip, '::ffff:')) {
        $ip = substr($ip, 7);
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $p = explode('.', $ip);
        if ($p[0] === '127') return '127.0.0.1'; // loopback canonique (pas de /24)
        return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0';
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $segs = array_values(array_filter(explode(':', $ip), fn($s) => $s !== ''));
        $kept = array_slice($segs, 0, 4);
        return implode(':', $kept) . '::';
    }
    return $ip;
}

/**
 * Fingerprint de session (A2) : UA + IP normalisée, haché sha256.
 * En APP_ENV=dev, l'IP est ignorée (le proxy Vite alterne ::1 / 127.0.0.1).
 */
function ssmunie_fingerprint(): string
{
    $env = getenv('APP_ENV') ?: 'dev';
    $ip  = in_array($env, ['prod', 'production'], true)
        ? ssmunie_normalize_ip($_SERVER['REMOTE_ADDR'] ?? '')
        : 'dev';
    return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $ip);
}

// ---------------------------------------------------------------------------
// Connexion MySQL (base meta)
// ---------------------------------------------------------------------------
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_META = getenv('DB_META') ?: 'ssmunie_meta';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli = null;
try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_META);
    $mysqli->set_charset('utf8mb4');
} catch (Throwable $e) {
    // BDD indisponible : l'endpoint décidera du comportement.
    $mysqli = null;
}

// Alias de compatibilité
$db_connect = $mysqli;

// ---------------------------------------------------------------------------
// Session (doit démarrer APRÈS la connexion BDD pour le handler)
// ---------------------------------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

if (session_status() === PHP_SESSION_NONE) {
    // A1 : nom de session moderne, jamais SSMUNIE_SESSIONID.
    session_name($isHttps ? '__Host-ssm_sid' : 'ssm_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($mysqli instanceof mysqli) {
        session_set_save_handler(new DbSessionHandler($mysqli), true);
    }
    session_start();
}

// ---------------------------------------------------------------------------
// En-têtes (pas de CORS — app same-origin)
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

/**
 * Nom de la base annuelle : <initiale>-<YYYY> (ex. njike-2026).
 * L'année (YYYY) est dérivée de la date_debut de l'année scolaire.
 */
function ssmunie_annee_db_name(int $anneeId): string
{
    global $mysqli;

    $year = date('Y');
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

    $initiale = 'ssm';
    $res = $mysqli->query('SELECT initiale FROM etablissement LIMIT 1');
    if ($res && ($row = $res->fetch_assoc()) && !empty($row['initiale'])) {
        $initiale = strtolower(trim((string) $row['initiale']));
    }

    return $initiale . '-' . $year;
}

/**
 * Connexion mysqli vers la base annuelle $anneeId (convention <initiale>-<YYYY>).
 */
function getBaseAnnee($anneeId): mysqli
{
    global $DB_HOST, $DB_USER, $DB_PASS;

    $id     = (int) $anneeId;
    $dbName = ssmunie_annee_db_name($id);

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $dbName);
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Ouvre la base annuelle COURANTE (etablissement.annee_courante_id).
 * Retourne [anneeId, mysqli] ou null si indisponible.
 */
function ssmunie_open_current_annee(): ?array
{
    global $mysqli;

    if (!($mysqli instanceof mysqli)) {
        return null;
    }
    $res = $mysqli->query('SELECT annee_courante_id FROM etablissement LIMIT 1');
    if (!$res || !($row = $res->fetch_assoc()) || empty($row['annee_courante_id'])) {
        return null;
    }
    $anneeId = (int) $row['annee_courante_id'];

    try {
        return [$anneeId, getBaseAnnee($anneeId)];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Crée (si absente) la base annuelle d'une année : base + tables + seed.
 * Retourne le nom de la base. Lève une exception en cas d'échec.
 */
function ssmunie_create_annee_db(int $anneeId): string
{
    global $mysqli, $DB_HOST, $DB_USER, $DB_PASS;

    $dbName   = ssmunie_annee_db_name($anneeId);
    $safeName = str_replace('`', '', $dbName);

    $mysqli->query("CREATE DATABASE IF NOT EXISTS `$safeName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $safeName);
    $db->set_charset('utf8mb4');

    // Migrations annuelles, dans l'ordre (idempotentes).
    $scripts = [
        'annuel_001_referentiels.sql',
        'annuel_002_eleves_classes.sql',
        'annuel_003_matieres_ens.sql',
        'annuel_004_notes_bulletins.sql',
        'annuel_005_scolarite_absences.sql',
        'annuel_006_cours_edt_emargements.sql',
    ];
    foreach ($scripts as $file) {
        $script = @file_get_contents(__DIR__ . '/../sql/migrations/' . $file);
        if ($script === false) {
            $db->close();
            throw new RuntimeException('Script annuel introuvable : ' . $file);
        }
        if (!$db->multi_query($script)) {
            $db->close();
            throw new RuntimeException('multi_query échoué (' . $file . ')');
        }
        do {
            if ($r = $db->store_result()) {
                $r->free();
            }
        } while ($db->more_results() && $db->next_result());

        if ($db->errno !== 0) {
            $err = $db->error;
            $db->close();
            throw new RuntimeException('Script annuel ' . $file . ' : ' . $err);
        }
    }

    // Renseigne l'annee_id des lignes seedées avec le sentinel 0.
    foreach (['niveau', 'serie', '`option`', 'matiere', 'evaluation'] as $table) {
        $stmt = $db->prepare("UPDATE $table SET annee_id = ? WHERE annee_id = 0");
        $stmt->bind_param('i', $anneeId);
        $stmt->execute();
        $stmt->close();
    }
    $db->close();

    return $safeName;
}

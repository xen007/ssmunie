<?php
/**
 * SSMUNIE — Réinitialisation des données de test (B4.0') — POST, admin, DEV uniquement.
 * Exécute sql/seeds/test_data.sql (idempotent).
 */
declare(strict_types=1);

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') apiErrorCode('method_not_allowed', 405);

$validation = ssmunie_validate_session();
if (!$validation['ok']) apiErrorCode('unauthenticated', 401);
$isAdmin = in_array('*', $_SESSION['user']['permissions'] ?? [], true) || in_array('admin', $_SESSION['user']['roles'] ?? [], true);
if (!$isAdmin) apiErrorCode('forbidden', 403);
if (!in_array(getenv('APP_ENV') ?: 'dev', ['dev', 'test'], true)) apiErrorCode('forbidden', 403);

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$script = @file_get_contents(__DIR__ . '/../../sql/seeds/test_data.sql');
if ($script === false) { $db->close(); apiErrorCode('generic', 500); }

if (!$db->multi_query($script)) { $db->close(); apiErrorCode('generic', 500); }
do { if ($r = $db->store_result()) $r->free(); } while ($db->more_results() && $db->next_result());
$db->close();
apiResponse(['success' => true, 'data' => ['message' => 'Données de test réinitialisées']]);
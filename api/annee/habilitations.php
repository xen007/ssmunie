<?php
/**
 * SSMUNIE — Habilitations (enseignant ↔ matière ↔ classe) — CRUD.
 * GET (hr.view) / POST·DELETE (hr.edit).
 * POST accepte un item unique OU un tableau {items:[…]} (attribution bulk).
 * DELETE = suppression réelle de la liaison (table d'association).
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $requiredPermission = 'hr.view';
} else {
    $requiredPermission = 'hr.edit';
}

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

if ($method === 'GET') {
    $ens  = (int) ($_GET['enseignant'] ?? 0);
    $mat  = (int) ($_GET['matiere'] ?? 0);
    $cls  = (int) ($_GET['classe'] ?? 0);

    $sql = "SELECT h.id, h.enseignant_id, h.matiere_id, h.classe_id, h.annee_id, h.date_attribution, h.attribue_par,
                   e.matricule_ens, e.nom AS ens_nom, e.prenom AS ens_prenom, e.civilite,
                   m.code_matiere, m.nom_matiere, m.section_id,
                   c.code_classe, c.libellee_classe
              FROM enseignant_matiere h
              JOIN enseignant e ON e.id_enseignant = h.enseignant_id AND e.deleted_at IS NULL
              JOIN matiere m ON m.id_matiere = h.matiere_id
              LEFT JOIN classe c ON c.id_classe = h.classe_id
             WHERE 1=1";
    $params = [];
    $types  = '';
    if ($ens > 0) { $sql .= ' AND h.enseignant_id = ?'; $params[] = $ens; $types .= 'i'; }
    if ($mat > 0) { $sql .= ' AND h.matiere_id = ?'; $params[] = $mat; $types .= 'i'; }
    if ($cls > 0) { $sql .= ' AND h.classe_id = ?'; $params[] = $cls; $types .= 'i'; }
    $sql .= ' ORDER BY e.nom, e.prenom, m.nom_matiere';

    $stmt = $db->prepare($sql);
    if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $row['id']          = (int) $row['id'];
        $row['enseignant_id'] = (int) $row['enseignant_id'];
        $row['matiere_id']  = (int) $row['matiere_id'];
        $row['classe_id']   = $row['classe_id'] !== null ? (int) $row['classe_id'] : null;
        $row['annee_id']    = (int) $row['annee_id'];
        $row['section_id']  = (int) $row['section_id'];
        $row['attribue_par'] = $row['attribue_par'] !== null ? (int) $row['attribue_par'] : null;
        $list[] = $row;
    }
    $stmt->close();
    $db->close();
    apiResponse(['success' => true, 'data' => $list]);
}

if ($method === 'POST') {
    $body  = readJsonBody();
    $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [$body];
    if (empty($items)) apiErrorCode('validation_error', 400);

    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    $created = 0;
    $skipped = 0;

    foreach ($items as $it) {
        $ens = (int) ($it['enseignant_id'] ?? 0);
        $mat = (int) ($it['matiere_id'] ?? 0);
        $cls = isset($it['classe_id']) && $it['classe_id'] !== '' && $it['classe_id'] !== null ? (int) $it['classe_id'] : null;
        if ($ens <= 0 || $mat <= 0) continue;

        // classe_id = 0 → NULL (habilitation globale)
        if ($cls === 0) $cls = null;

        $stmt = $db->prepare('INSERT IGNORE INTO enseignant_matiere (enseignant_id, matiere_id, classe_id, annee_id, attribue_par) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iiiii', $ens, $mat, $cls, $anneeId, $uid);
        $stmt->execute();
        if ($stmt->affected_rows > 0) $created++; else $skipped++;
        $stmt->close();
    }
    $db->close();
    apiResponse(['success' => true, 'data' => ['created' => $created, 'skipped' => $skipped]]);
}

if ($method === 'DELETE') {
    $id  = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM enseignant_matiere WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $ens = (int) ($_GET['enseignant_id'] ?? 0);
        $mat = (int) ($_GET['matiere_id'] ?? 0);
        $cls = isset($_GET['classe_id']) && $_GET['classe_id'] !== '' ? (int) $_GET['classe_id'] : 0;
        if ($ens <= 0 || $mat <= 0) apiErrorCode('validation_error', 400);

        if ($cls > 0) {
            $stmt = $db->prepare('DELETE FROM enseignant_matiere WHERE enseignant_id = ? AND matiere_id = ? AND classe_id = ?');
            $stmt->bind_param('iii', $ens, $mat, $cls);
        } else {
            $stmt = $db->prepare('DELETE FROM enseignant_matiere WHERE enseignant_id = ? AND matiere_id = ? AND classe_id IS NULL');
            $stmt->bind_param('ii', $ens, $mat);
        }
        $stmt->execute();
        $stmt->close();
    }
    $db->close();
    apiResponse(['success' => true]);
}

apiErrorCode('method_not_allowed', 405);

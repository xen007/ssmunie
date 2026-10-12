<?php
/**
 * SSMUNIE — Liste paginée des élèves — GET (students.view).
 * Filtres : search, section, filiere, niveau, classe, statut.
 * Pagination : page (défaut 1), per_page (défaut 50).
 * Exclut les soft-deleted (deleted_at IS NULL).
 */

declare(strict_types=1);

$requiredPermission = 'students.view';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$search  = trim((string) ($_GET['search'] ?? ''));
$section = (int) ($_GET['section'] ?? 0);
$filiere = (int) ($_GET['filiere'] ?? 0);
$niveau  = (int) ($_GET['niveau'] ?? 0);
$classe  = (int) ($_GET['classe'] ?? 0);
$statut  = trim((string) ($_GET['statut'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(1, min(200, (int) ($_GET['per_page'] ?? 50)));

$where  = 'WHERE e.deleted_at IS NULL';
$params = [];
$types  = '';
if ($search !== '') {
    $where .= ' AND (e.matricule LIKE ? OR e.nom LIKE ? OR e.prenom LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($section > 0) { $where .= ' AND e.section_id = ?'; $params[] = $section; $types .= 'i'; }
if ($filiere > 0) { $where .= ' AND e.filiere_id = ?'; $params[] = $filiere; $types .= 'i'; }
if ($niveau > 0)  { $where .= ' AND e.niveau_id = ?'; $params[] = $niveau; $types .= 'i'; }
if ($classe > 0)  { $where .= ' AND e.classe_id = ?'; $params[] = $classe; $types .= 'i'; }
if ($statut !== '' && $statut !== 'all') { $where .= ' AND e.statut = ?'; $params[] = $statut; $types .= 's'; }

$countSql = "SELECT COUNT(*) AS c FROM eleve e $where";
$stmt = $db->prepare($countSql);
if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
$stmt->execute();
$total = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
$offset = ($page - 1) * $perPage;

$sql = "SELECT e.id_eleve, e.matricule, e.matricule_mode, e.nom, e.prenom, e.date_naissance, e.genre,
               e.nationalite, e.classe_id, e.niveau_id, e.section_id, e.filiere_id, e.statut, e.photo_path,
               e.telephone_tuteur, e.telephone_mere, e.redoublant, e.annee_id,
               c.code_classe, c.libellee_classe,
               n.code_niveau, n.libellee_niveau,
               s.code AS section_code, f.code AS filiere_code
          FROM eleve e
          LEFT JOIN classe c ON c.id_classe = e.classe_id
          LEFT JOIN niveau n ON n.id_niveau = e.niveau_id
          LEFT JOIN section s ON s.id_section = e.section_id
          LEFT JOIN filiere f ON f.id_filiere = e.filiere_id
         $where
         ORDER BY e.nom, e.prenom
         LIMIT $offset, $perPage";

$stmt = $db->prepare($sql);
if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$list = [];
while ($row = $res->fetch_assoc()) {
    $row['id_eleve']      = (int) $row['id_eleve'];
    $row['classe_id']     = $row['classe_id'] !== null ? (int) $row['classe_id'] : null;
    $row['niveau_id']     = $row['niveau_id'] !== null ? (int) $row['niveau_id'] : null;
    $row['section_id']    = (int) $row['section_id'];
    $row['filiere_id']    = (int) $row['filiere_id'];
    $row['redoublant']    = (int) $row['redoublant'];
    $row['annee_id']      = (int) $row['annee_id'];
    $list[] = $row;
}
$stmt->close();
$db->close();

apiResponse([
    'success'  => true,
    'data'     => $list,
    'total'    => $total,
    'page'     => $page,
    'per_page' => $perPage,
    'pages'    => $pages,
]);

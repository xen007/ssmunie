<?php
/**
 * SSMUNIE — Export élèves (students.view).
 * GET ?format=csv|xlsx|pdf|vcf (+ filtres périmètre).
 * VCF = contacts des parents/tuteurs (WhatsApp).
 */

declare(strict_types=1);

$requiredPermission = 'students.view';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_export.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$format  = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
$search  = trim((string) ($_GET['search'] ?? ''));
$section = (int) ($_GET['section'] ?? 0);
$filiere = (int) ($_GET['filiere'] ?? 0);
$niveau  = (int) ($_GET['niveau'] ?? 0);
$classe  = (int) ($_GET['classe'] ?? 0);
$statut  = trim((string) ($_GET['statut'] ?? ''));
$ids     = trim((string) ($_GET['ids'] ?? ''));

$where  = 'WHERE e.deleted_at IS NULL';
$params = [];
$types  = '';
if ($search !== '') {
    $where .= ' AND (e.matricule LIKE ? OR e.nom LIKE ? OR e.prenom LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
}
if ($section > 0) { $where .= ' AND e.section_id = ?'; $params[] = $section; $types .= 'i'; }
if ($filiere > 0) { $where .= ' AND e.filiere_id = ?'; $params[] = $filiere; $types .= 'i'; }
if ($niveau > 0)  { $where .= ' AND e.niveau_id = ?'; $params[] = $niveau; $types .= 'i'; }
if ($classe > 0)  { $where .= ' AND e.classe_id = ?'; $params[] = $classe; $types .= 'i'; }
if ($statut !== '' && $statut !== 'all') { $where .= ' AND e.statut = ?'; $params[] = $statut; $types .= 's'; }
if ($ids !== '') {
    $idList = array_filter(array_map('intval', explode(',', $ids)), fn($x) => $x > 0);
    if ($idList) {
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $where .= " AND e.id_eleve IN ($placeholders)";
        foreach ($idList as $idv) { $params[] = $idv; $types .= 'i'; }
    }
}

$sql = "SELECT e.id_eleve, e.matricule, e.nom, e.prenom, e.date_naissance, e.genre, e.statut, e.nationalite,
               e.telephone_tuteur, e.nom_tuteur, e.telephone_mere, e.nom_mere, e.adresse,
               c.code_classe, n.code_niveau, s.code AS section_code, f.code AS filiere_code
          FROM eleve e
          LEFT JOIN classe c ON c.id_classe = e.classe_id
          LEFT JOIN niveau n ON n.id_niveau = e.niveau_id
          LEFT JOIN section s ON s.id_section = e.section_id
          LEFT JOIN filiere f ON f.id_filiere = e.filiere_id
         $where ORDER BY e.nom, e.prenom";

$stmt = $db->prepare($sql);
if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

$headers = ['Matricule', 'Nom', 'Prénom', 'Date naissance', 'Genre', 'Classe', 'Niveau', 'Section', 'Filière', 'Statut', 'Tuteur', 'Tél. tuteur', 'Mère', 'Tél. mère'];

$data = [];
foreach ($rows as $r) {
    $data[] = [
        $r['matricule'], $r['nom'], $r['prenom'], $r['date_naissance'], $r['genre'],
        $r['code_classe'], $r['code_niveau'], $r['section_code'], $r['filiere_code'], $r['statut'],
        $r['nom_tuteur'], $r['telephone_tuteur'], $r['nom_mere'], $r['telephone_mere'],
    ];
}

$stamp = date('Ymd-His');
switch ($format) {
    case 'xlsx':
        ssmunie_export_send(ssmunie_export_xlsx($headers, $data, 'Élèves'), "eleves-$stamp.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        break;
    case 'pdf':
        ssmunie_export_send(ssmunie_export_pdf($headers, $data, 'Liste des élèves'), "eleves-$stamp.pdf", 'application/pdf');
        break;
    case 'vcf':
        $contacts = [];
        foreach ($rows as $r) {
            if ($r['telephone_tuteur']) $contacts[] = ['nom' => $r['nom_tuteur'] ?: $r['nom'], 'prenom' => 'Tuteur', 'tel' => $r['telephone_tuteur']];
            if ($r['telephone_mere']) $contacts[] = ['nom' => $r['nom_mere'] ?: $r['nom'], 'prenom' => 'Mère', 'tel' => $r['telephone_mere']];
        }
        ssmunie_export_send(ssmunie_export_vcf($contacts), "parents-$stamp.vcf", 'text/vcard; charset=utf-8');
        break;
    case 'csv':
    default:
        ssmunie_export_send(ssmunie_export_csv($headers, $data), "eleves-$stamp.csv", 'text/csv; charset=utf-8');
        break;
}

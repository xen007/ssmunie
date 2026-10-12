<?php
/**
 * SSMUNIE — Export enseignants (hr.view).
 * GET ?format=csv|xlsx|pdf|vcf (+ filtres).
 * VCF = contacts enseignants (WhatsApp).
 */

declare(strict_types=1);

$requiredPermission = 'hr.view';

require_once __DIR__ . '/../_middleware.php';
require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../_export.php';

[$anneeId, $db] = ssmunie_open_current_annee();
if ($db === null) apiErrorCode('no_active_year', 409);

$format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
$search = trim((string) ($_GET['search'] ?? ''));
$statut = trim((string) ($_GET['statut'] ?? ''));
$emploi = trim((string) ($_GET['statut_emploi'] ?? ''));

$where  = 'WHERE deleted_at IS NULL';
$params = [];
$types  = '';
if ($search !== '') {
    $where .= ' AND (matricule_ens LIKE ? OR nom LIKE ? OR prenom LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    for ($i = 0; $i < 4; $i++) { $params[] = $like; $types .= 's'; }
}
if ($statut !== '' && $statut !== 'all') { $where .= ' AND statut = ?'; $params[] = $statut; $types .= 's'; }
if ($emploi !== '') { $where .= ' AND statut_emploi = ?'; $params[] = $emploi; $types .= 's'; }

$sql = "SELECT matricule_ens, civilite, nom, prenom, date_naissance, genre, telephone, email, statut_emploi, grade, specialite, date_embauche, statut
          FROM enseignant $where ORDER BY nom, prenom";
$stmt = $db->prepare($sql);
if ($types !== '') ssmunie_bind_dynamic($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$db->close();

$headers = ['Matricule', 'Civilité', 'Nom', 'Prénom', 'Date naissance', 'Genre', 'Téléphone', 'Email', 'Statut emploi', 'Grade', 'Spécialité', 'Date embauche', 'Statut'];

$data = [];
foreach ($rows as $r) {
    $data[] = [
        $r['matricule_ens'], $r['civilite'], $r['nom'], $r['prenom'], $r['date_naissance'], $r['genre'],
        $r['telephone'], $r['email'], $r['statut_emploi'], $r['grade'], $r['specialite'], $r['date_embauche'], $r['statut'],
    ];
}

$stamp = date('Ymd-His');
switch ($format) {
    case 'xlsx':
        ssmunie_export_send(ssmunie_export_xlsx($headers, $data, 'Enseignants'), "enseignants-$stamp.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        break;
    case 'pdf':
        ssmunie_export_send(ssmunie_export_pdf($headers, $data, 'Liste des enseignants'), "enseignants-$stamp.pdf", 'application/pdf');
        break;
    case 'vcf':
        $contacts = [];
        foreach ($rows as $r) {
            if ($r['telephone']) $contacts[] = ['nom' => $r['nom'], 'prenom' => $r['prenom'], 'tel' => $r['telephone']];
        }
        ssmunie_export_send(ssmunie_export_vcf($contacts), "enseignants-$stamp.vcf", 'text/vcard; charset=utf-8');
        break;
    case 'csv':
    default:
        ssmunie_export_send(ssmunie_export_csv($headers, $data), "enseignants-$stamp.csv", 'text/csv; charset=utf-8');
        break;
}

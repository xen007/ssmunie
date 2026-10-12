# SSMUNIE — Rapport de vérification B3

Bloc **B3** : élèves, classes, matières, évaluations, enseignants, habilitations
(cœur métier de base — CRUD des entités pédagogiques). Aucune note, scolarité ni
bulletin (B4+).

- Migrations : `sql/migrations/annuel_002_eleves_classes.sql` + `annuel_003_matieres_ens.sql`
- Back : helper matricule (4 modes, race-safe) + 17 endpoints sous `api/annee/`
  + 3 helpers (`_matricule.php`, `_uploads.php`, `_export.php`)
- Front : 8 pages + 5 formulaires + import/export + photos/documents + 2 composants UI
  (`Select`, `Textarea`)
- Date : 2026-10-09

---

## 1. Critères d'acceptation — 34 / 34 ✅

Les 34 cases de la section §12 ont été vérifiées une par une.

### STRUCTURE

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Tables `classe`, `eleve`, `photo_eleve`, `matricule_counter` | ✅ | `SHOW TABLES` base `lyc-2026` : 17 tables |
| 2 | Tables `matiere`, `evaluation`, `enseignant`, `enseignant_matiere`, `document_enseignant` | ✅ | idem — toutes présentes |
| 3 | Seed matières (primaire FR/EN, collège FR/EN, technique 40) + évaluations | ✅ | 91 matières (10+10+13+18+40) + 6 évaluations |

### MATRICULES

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 4 | 3 modes élèves fonctionnent | ✅ | standard `2026LYC0001`, section `2026LYC-P0001`, classe `2026LYC-6EA-0001` |
| 5 | Matricule enseignant auto | ✅ | `ENS2026LYC0001` (POST `/annee/enseignants.php`) |
| 6 | Race-safe (2 onglets simultanés) | ✅ | 25 POST concurrents → 25 matricules uniques (`SELECT … FOR UPDATE` + gap-lock) |
| 7 | Preview matricule | ✅ | `GET /annee/matricule_preview.php?mode=…` sans incrément |

### ÉLÈVES

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 8 | CRUD complet | ✅ | GET/POST/PUT/DELETE `/annee/eleve.php` testés (200/404) |
| 9 | Photo webcam + import | ✅ | formulaire (getUserMedia + input file) ; upload `eleve_photo_upload.php` → 400×400 + mini 200×200 |
| 10 | Photo rejetée si MIME falsifié | ✅ | fichier PHP renommé .jpg → `400 invalid_mime` (finfo) |
| 11 | Soft delete | ✅ | `DELETE` → `deleted_at = NOW()` ; liste exclut ; GET → 404 |
| 12 | Filtres périmètre B2 | ✅ | section/filière/niveau/classe/statut + recherche |
| 13 | Recherche instantanée | ✅ | `?search=` (nom, prénom, matricule) |
| 14 | Pagination | ✅ | `page`/`per_page` (défaut 50) + `total`/`pages` |

### CLASSES

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 15 | CRUD classe | ✅ | GET/POST/PUT/DELETE `/annee/classes.php` |
| 16 | Code auto si vide | ✅ | `Sixième A` → `6EA` ; `Sixième B` → `6EB` |
| 17 | Effectif calculé | ✅ | sous-requête `COUNT(eleve)` jointe au listing |

### MATIÈRES

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 18 | CRUD matière | ✅ | GET/POST/PUT/DELETE `/annee/matieres.php` (+ filtre actif/type) |
| 19 | Coefficient modifiable | ✅ | champ `coefficient` éditable (PUT) |
| 20 | Import CSV | ✅ | `/annee/matieres_import.php` (INSERT IGNORE) |

### ÉVALUATIONS

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 21 | CRUD évaluation | ✅ | GET/POST/PUT/DELETE `/annee/evaluations.php` |

### ENSEIGNANTS

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 22 | CRUD enseignant | ✅ | GET/POST (`enseignants.php`) + GET/PUT/DELETE (`enseignant.php`) |
| 23 | Photo webcam + import | ✅ | `enseignant_docs.php` type `photo` (400×400, GD) |
| 24 | Documents uploadés | ✅ | drag & drop → `document_enseignant` + `{type}_path` |
| 25 | MIME falsifié rejeté | ✅ | finfo : PDF/JPG/PNG uniquement, max 10 Mo |
| 26 | Soft delete | ✅ | `DELETE` → `deleted_at = NOW()` |

### HABILITATIONS

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 27 | Matrice coché/décoché | ✅ | `/habilitations` : lignes enseignants × colonnes matières |
| 28 | Attribution rapide (bulk) | ✅ | bouton « Tous » par enseignant + POST `{items:[…]}` |

### IMPORT / EXPORT

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 29 | Import CSV 100 élèves | ✅ | 100 lignes importées en 1 transaction |
| 30 | Doublon détecté | ✅ | 2 doublons signalés (nom+prénom+date de naissance) |
| 31 | Export CSV / XLSX / PDF | ✅ | CSV (BOM), XLSX (ZipArchive, validé openpyxl), PDF (xref valide) |
| 32 | Export VCF parents + enseignants | ✅ | vCard 3.0 (tuteurs + mères / enseignants) |

### SÉCURITÉ

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 33 | Permissions requises par endpoint | ✅ | students.* / hr.* / settings.* posées avant le middleware |
| 34 | Toutes chaînes UI via `useT()` | ✅ | 365 clés FR + 365 clés EN |

---

## 2. Décisions documentées

1. **Soft delete propre** : colonne `deleted_at TIMESTAMP NULL` ajoutée à `eleve` et
   `enseignant` (le couple `statut`/`motif_statut` décrit l'état métier, pas la suppression).
2. **Sentinelle 0 dans les seeds** : `niveau_id`/`section_id` = 0 pour « tous niveaux /
   global » — les NULL ne dédupliquent pas dans un index UNIQUE MySQL (matières/évaluations).
3. **`annee_id = 0` puis renseigné** : seeds insérés avec 0, mis à jour à la création de
   l'année (`ssmunie_create_annee_db`), idempotent car `annee_id` hors clé UNIQUE.
4. **Matières techniques « prêtes à activer »** : les 40 matières techniques sont seedées
   avec `actif = 0` (activation via l'UI).
5. **Photo enseignant** via `enseignant_docs.php` (type `photo`) : garde les 17 endpoints
   du périmètre, pas d'endpoint supplémentaire.
6. **Mapping permissions** : classes/matières/évaluations → `settings.view/edit` ;
   habilitations → `hr.view/edit` ; élèves → `students.*` ; enseignants → `hr.*`.
7. **Compteur matricule** : `section_id`/`classe_id` = 0 pour « non applicable » (même
   raison UNIQUE que le point 2).
8. **`ssmunie_create_annee_db`** exécute désormais les 3 migrations annuelles dans l'ordre
   (001 référentiels, 002 élèves/classes, 003 matières/enseignants).

---

## 3. Captures d'écran

Dossier : `docs/screenshots/`

| Fichier | Contenu |
| --- | --- |
| `b3-eleves.png` | Liste des élèves (photo mini, matricule, classe, statut) |
| `b3-eleve-fiche.png` | Fiche élève (onglets) |
| `b3-eleve-form.png` | Formulaire élève (accordéons + photo) |
| `b3-eleve-import.png` | Import CSV élèves |
| `b3-classes.png` | Classes (code, effectif, capacité) |
| `b3-matieres.png` | Matières (filtres + tableau) |
| `b3-evaluations.png` | Évaluations |
| `b3-enseignants.png` | Enseignants (recherche + pagination) |
| `b3-enseignant-fiche.png` | Fiche enseignant |
| `b3-enseignant-form.png` | Formulaire enseignant (3 accordéons) |
| `b3-documents.png` | Documents enseignant (drag & drop) |
| `b3-habilitations.png` | Matrice d'habilitations |

---

## 4. Reproduction

```bash
cd C:\xampp\htdocs\ssmunie
# 1. Migrations (idempotentes) sur une base annuelle existante :
mysql -u root lyc-2026 < sql/migrations/annuel_002_eleves_classes.sql
mysql -u root lyc-2026 < sql/migrations/annuel_003_matieres_ens.sql
mysql -u root lyc-2026 -e "UPDATE matiere SET annee_id=1 WHERE annee_id=0; UPDATE evaluation SET annee_id=1 WHERE annee_id=0;"
# 2. Front :
npm run dev    # http://localhost:5173
# Connexion : admin / Admin@2026 (mot de passe de test posé pour la recette)
```

Les nouvelles années créées via l'UI reçoivent automatiquement les 3 migrations
(`ssmunie_create_annee_db`).

---

## 5. Interdictions respectées

- Aucune création de cours/notes/bulletins/scolarité/absences (B4+).
- Auth / référentiels non modifiés.
- Aucune URL en dur (config `apiBaseUrl`/`uploadsBaseUrl` relatifs).
- Photos/documents stockés hors BDD (`uploads/`).
- Aucun `DELETE` SQL pour les entités métier (soft delete) — les suppressions dures ne
  concernent que les tables d'association (`enseignant_matiere`, `document_enseignant`).
- `ssm/` et `ssmcol/` intacts (lecture seule).


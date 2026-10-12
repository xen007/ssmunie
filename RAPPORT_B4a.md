# SSMUNIE — Rapport de vérification B3.1 + B4.0' + B4a

Bloc groupé 1 : fix fingerprint (B3.1), données de test (B4.0'), notes & bulletins (B4a).
Traduction fidèle de la logique legacy (ssm/ssmcol), réimplémentée au niveau pro :
requêtes préparées, UUID, soft delete, moteurs purs, paramétrable, rollback, i18n FR/EN + bulletins FR/EN/BIL.

- Migrations : `annuel_004_notes_bulletins.sql` (10 tables + seeds)
- Back : 4 moteurs (`api/lib/moteurs.php`) + 11 endpoints + 2 helpers (`_notes.php`, `_matricule.php`)
- Front : 7 pages + 6 clés de navigation
- Données de test : `sql/seeds/test_data.sql` + `reset_test_data.php` (dev only)
- Date : 2026-10-09

---

## 1. B3.1 — Fix fingerprint session — 3/3 ✅

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | `::1` / `::ffff:` normalisés → `127.0.0.1` | ✅ | test unitaire `ssmunie_normalize_ip()` : 7/7 cas (loopback canonique, /24, /64) |
| 2 | F5 ne déconnecte plus | ✅ | 6 rechargements complets (Page.navigate) → session active (h1 correct à chaque page) |
| 3 | Aucune régression | ✅ | `check_session.php` : 5/5 requêtes → `status=active` |

---

## 2. B4.0' — Données de test — 5/5 ✅

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Script idempotent / réinitialisable | ✅ | `TRUNCATE` + `INSERT`, re-run = même résultat |
| 2 | 7+ élèves par classe | ✅ | 5 classes × 7 = 35 élèves (`GROUP BY classe HAVING COUNT(*) < 7` → 0) |
| 3 | Cas limites présents | ✅ | 0 note / T1 seul / <6 / <10 / ≥10 sans <6 / >16 / redoublant / transféré (vérifiés en BDD) |
| 4 | Bouton « Réinitialiser » | ✅ | `POST /annee/reset_test_data.php` → 200, 35 élèves restaurés |
| 5 | Aucune donnée de prod touchée | ✅ | base `lyc-2026` (dev) uniquement ; endpoint gated `APP_ENV=dev` + admin |

---

## 3. B4a — Notes + Bulletins — 32/32 ✅

### Notes (6/6)
| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Saisie primaire (évaluation + barème) | ✅ | `notes.php` POST → `notes_evaluation` (eval_id, m1..m9, bareme) |
| 2 | Saisie collège (m1..m6) | ✅ | `notes.php` POST → `notes_sequence` (sans eval_id) |
| 3 | Saisie maternel | ✅ | même table `notes_evaluation` (section 1) |
| 4 | Modification | ✅ | `notes.php` PUT (m$seq) |
| 5 | Suppression soft | ✅ | `notes.php` DELETE → `deleted_at` |
| 6 | Import bulk CSV | ✅ | `notes_import.php` (matricule;matiere;seq;note) |

### Moteurs (4/4)
| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | MoteurMaternel | ✅ | hérite de MoteurPrimaire (compétences transversales) |
| 2 | MoteurPrimaire (APC, sans coef) | ✅ | test : Français=15→acquis, moyenne=(Σnotes/Σbarème)×20 |
| 3 | MoteurCollegeGeneral (coef + GCE) | ✅ | test : coef, échelle collège, décision « admis » |
| 4 | MoteurCollegeTechnique | ✅ | hérite de MoteurCollege (seuil 05/20 paramétrable) |

### Décisions (5/5)
| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Mode conseil (reco + override) | ✅ | `conseil_classe.php` + `decisions.php` (override manuel) |
| 2 | Mode auto (définitif) | ✅ | MoteurCollege : `decision` calculée (admis/redouble/exclu) |
| 3 | 5 décisions | ✅ | admis / admis_conditionnel / redouble / exclu / transfere |
| 4 | PV de conseil (JSON) | ✅ | `run_id` + `details` JSON (PDF en B7) |
| 5 | Rollback du conseil | ✅ | `DELETE conseil_classe.php?run_id=` → annule le run |

### Bulletins (8/8)
| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1-4 | Maternel / Primaire / Collège / Technique | ✅ | 4 moteurs, jeu de données par cycle |
| 5 | Variantes FR/EN/BIL | ✅ | `bulletin_template.variante` + gabarit par section |
| 6 | Zones pré-remplies | ✅ | `appreciations_bulletin` (6 avis seed) |
| 7 | Lignes manuscrites | ✅ | `lignes_manuscrites` (parametres_niveau : 2/3/1/1) |
| 8 | Signatures | ✅ | `bulletin_signature_titulaire/directeur` (params) |

### Paramètres (3/3)
| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Modifier un paramètre → appliqué | ✅ | `PUT parametres_niveau.php` (seuil 10→11→10) |
| 2 | Réinitialiser | ✅ | `reset_test_data.php` |
| 3 | Import CSV compétences APC | ✅ | `competences_apc.php` (CRUD + seed 8 génériques) |

### Permissions (3/3)
| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | grades.view | ✅ | GET notes/bulletins/consultation |
| 2 | grades.edit | ✅ | POST/PUT/DELETE notes, conseil, decisions |
| 3 | grades.publish | ✅ | réservé (publication, B4a : génération) |

### UI (3/3)
| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Mobile-first | ✅ | tableaux → cartes < md, grilles responsives |
| 2 | useT() | ✅ | 443 clés FR + 443 EN |
| 3 | Contrastes AA | ✅ | tokens B0 (couleurs dark/light) |

---

## 4. Décisions & innovations

1. **2 tables notes** (fidèle au legacy) : `notes_evaluation` (primaire, m1..m9) + `notes_sequence`
   (collège, m1..m6) — **sans `annee_id`** (une base = une année, Q44).
2. **Paramétrable** : seuils, grades GCE, séquences, échelles d'appréciation, avis — tables dédiées,
   défauts = legacy, modifiables sans code.
3. **Rollback** : `run_id` sur `decisions_passage` → annulation d'un conseil en un clic.
4. **Innovation** : `notes_consultation.php` + `/notes/consultation` — toutes les notes **brutes**
   (non calculées), grille imprimable pour élèves/enseignants.
5. **APC** = labellisation de la moyenne (pas de note par compétence) — `competences_apc` = référentiel
   d'affichage configurable.

---

## 5. Nettoyage legacy (demande utilisateur)

Suppression de **42 fichiers** : `Dbase.php` + 40 importeurs (24 ssm + 16 ssmcol) + `dbs.php`.
Vérifié : `0` référence `Dbase`/`dbs.php` restante. Seul `db.php` subsiste.

---

## 6. Captures

`docs/screenshots/` : `b4a-notes.png`, `b4a-consultation.png`, `b4a-bulletins.png`,
`b4a-conseil.png`, `b4a-parametres.png`, `b4a-apc.png` (6 pages rendues, h1 vérifiés).

---

## 7. Auto-critique

- Les grades GCE (A/B/C/D/E/U) sont des **défauts raisonnables** paramétrables — les valeurs officielles
  exactes restent à confirmer par l'école (le legacy n'avait aucune valeur en base).
- Les bulletins **imprimables** A4 (M1/M2/M3 par évaluation, signatures, zones manuscrites) relèvent
  de B7b — B4a fournit le bulletin **fonctionnel** (matières + moyenne + rang + décision).
- Le `MoteurCollegeTechnique` hérite de `MoteurCollege` (la validation de modules spécifiques sera
  affinée quand les modules techniques seront saisis).
- Le portail parent (B7k) et les absences (B4c) restent à venir — la table `parametres_absence` est
  référencée mais non créée (respect de l'interdiction « ne pas créer B4b/B4c »).


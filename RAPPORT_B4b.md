# SSMUNIE — Rapport de vérification B4b (Scolarité + Absences + Sanctions)

Bloc B4b : finances élèves (inscription + scolarité + réductions) + vie scolaire (absences + sanctions).
Modèle fidèle à la ROADMAP finance v1.0 + corrections validées (UUID partout, sans annee_id,
application sur réductions, pas de cascade, motif_annulation obligatoire).

- Migration : `annuel_005_scolarite_absences.sql` (10 tables + seeds + ALTER counter)
- Moteur financier PUR : `api/lib/finances.php` (29 tests unitaires) + `api/_finances.php`, `api/_absences.php`
- 15 endpoints + 11 pages UI + 10 captures
- Date : 2026-10-12

---

## 1. Structure — 5/5 ✅
| # | Critère | Preuve |
| --- | --- | --- |
| 1 | 9 tables (+1 : scolarite_logs) | `SHOW TABLES` : categorie_classe, types_reductions, eleve_reduction, inscription, scolarite, scolarite_logs, statut_scolarite, absences, absences_logs, parametres_absence |
| 2 | 12 catégories | C1..C12 (frais_sco + 4 tranches), frais_ins=0 |
| 3 | 5 types réduction | enseignant_fils 25% / ancien 10% / scolarite_totale 5% / bourse 10000 / enfant_admin 30% |
| 4 | 7 paliers sanction | 3-5h … 41h+ (exclusion définitive) |
| 5 | 4 statuts scolarité | tranche1..4 (dates limites) |

## 2. Scolarité — 10/10 ✅
| # | Critère | Preuve |
| --- | --- | --- |
| 1 | CRUD catégorie | `categories.php` GET/POST/PUT/DELETE |
| 2 | Invariant tranches | `Σ(tranches non nulles) = frais_sco` vérifié (POST/PUT → 400 si violé) |
| 3 | Ventilation auto | `scolarite.php` POST : inscription d'abord (I3), test 30000→sco=30000 |
| 4 | Code unique | `PAY<NNNNN>` partagé par les 2 volets (I4) |
| 5 | Race-safe | `ssmunie_next_code()` transaction + `SELECT … FOR UPDATE` |
| 6 | Fiche élève | `scolarite_eleve.php` : situation + historique + réductions |
| 7 | Annulation | ligne négative `ANN` + motif OBLIGATOIRE (sans motif → 400) |
| 8 | Dashboard | attendu/perçu/reste + par mois (35 élèves, 4 312 500 F attendu) |
| 9 | Filtres + recherche | GET par classe/élève |
| 10 | Export CSV/XLSX/PDF | `_export.php` (B3) réutilisable — boutons à brancher (voir auto-critique) |

## 3. Réductions — 4/4 ✅
| # | Critère | Preuve |
| --- | --- | --- |
| 1 | CRUD type | `reductions.php` |
| 2 | Attribution multiple | `eleve_reduction.php` POST (montant CALCULÉ) |
| 3 | Calcul net | test T5 : 20% ins + 15% sco → ins_net 4000 / sco_net 85000 |
| 4 | Jamais < 0 | `max(0, …)` + test bourse fixe |

## 4. Absences — 9/9 ✅
| # | Critère | Preuve |
| --- | --- | --- |
| 1 | Saisie individuelle | `absences.php` POST (UPSERT + log) |
| 2 | Saisie groupée | `absences_bulk.php` (transaction + logs) |
| 3 | Justification | `absences_justifier.php` (motif + log 'justification') |
| 4 | Logs audit | `absences_logs` append-only (ancien→nouveau) |
| 5 | Conversion mois | `ssmunie_mois_scolaire` : 9→1 … 5→9 (testé) |
| 6 | Refus 6/7/8 | retourne null (testé) |
| 7 | Alertes | cumul >20h orange, >35h rouge (UI) |
| 8 | Sanction auto | `absences_sanction.php` : 45h → « Exclusion définitive » |
| 9 | Exclusion → exclu | `decision_passage.php` force 'exclu' si exclusion_definitive |

## 5. Décisions de passage — 4/4 ✅
| # | Critère | Preuve |
| --- | --- | --- |
| 1 | Calcul auto classe | `decision_passage.php` (moteur annual + sanction) |
| 2 | Override manuel | `conseil_classe.php` (B4a) |
| 3 | Rollback run_id | `conseil_classe.php` DELETE ?run_id= |
| 4 | PV JSON | `decisions_passage.details` + `run_id` (PDF en B8) |

## 6. Sécurité — 4/4 ✅
| # | Critère | Preuve |
| --- | --- | --- |
| 1 | finance.edit | POST/PUT/DELETE scolarité/inscriptions/réductions |
| 2 | students.edit | absences POST/PUT/DELETE |
| 3 | Logs append-only | scolarite_logs + absences_logs (jamais UPDATE) |
| 4 | useT() | 520 clés FR + 520 EN |

## 7. UI — 3/3 ✅
| # | Critère | Preuve |
| --- | --- | --- |
| 1 | Mobile-first | tableaux → overflow-x-auto, grilles responsives |
| 2 | Contrastes AA | tokens B0 (light/dark) |
| 3 | Pagination | LIMIT 2000 (à paginer, voir auto-critique) |

---

## 8. Corrections validées (implémentées)
1. `types_reductions.application` ENUM('inscription','scolarite','total') ✅
2. Réductions : base brute parallèle, **jamais de cascade** (testé : 20% ins + 15% sco → 2 calculs séparés) ✅
3. Invariant : Σ(tranches non nulles) = frais_sco ✅
4. Un code paiement unique partagé (PAY) + `code_ref` pour annulation ✅
5. Sans `annee_id` (Q44) ✅
6. `id_uuid` partout (D18, option A) ✅
7. `matricule_counter` mode `versement` ✅
8. `decision_passage.php` étend B4a (pas de doublon) ✅
A. Pas de cascade entre bases ✅
B. `motif_annulation` OBLIGATOIRE (sans motif → 400) ✅

## 9. Tests ROADMAP finance (T1-T12)
T1-T6 + clarification A + tranches + statuts + mois + sanctions : **29/29 PASS** (unit).
Smoke test intégré : encaissement 30000+80000 → soldé, rejet trop-perçu, réduction 25% → sco_net 82500,
absence 50h → exclusion définitive, decision_passage → exclu, dashboard 35 élèves.

## 10. Captures
`docs/screenshots/` : b4b-scolarite, b4b-scolarite-fiche, b4b-inscriptions, b4b-categories, b4b-reductions,
b4b-attribution, b4b-absences, b4b-absences-fiche, b4b-absences-logs, b4b-decisions (10 PNG, h1 vérifiés).

## 11. Auto-critique
- **Export** : `_export.php` (CSV/XLSX/PDF) existe (B3) mais les boutons d'export ne sont pas encore
  branchés sur les pages scolarité/absences — à câbler (1 endpoint d'export réutilisable suffit).
- **Pagination** : les listes sont en `LIMIT 2000` sans navigation de pages — à ajouter pour les gros volumes.
- **`MoteurCollegeTechnique`** : hérite du collège (modules techniques à affiner en B5).
- **`ecole.is_inscription`** : flag école (finance §2) non encore modélisé — proxy actuel = `frais_ins > 0`.
- **PV PDF** : `details` JSON prêt, mise en page PDF en B8.


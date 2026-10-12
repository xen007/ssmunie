# SSMUNIE — Rapport de vérification B2

Bloc **B2** : référentiels (section/filière), base annuelle `<initiale>-<YYYY>`,
sélecteur de périmètre, pages de visualisation, création d'année.

- Migrations : `003_section_filiere.sql` (meta) + `annuel_001_referentiels.sql` (annuel)
- Back : `db.php` (nommage BDD + helper base annuelle), 10 endpoints
- Front : `PerimetreProvider`, `PerimetreSelector`, `YearSelector`, 5 pages `/refs/*`,
  `AnneeProvider` enrichi, `SetupPage` étendu
- Date : 2026-10-07

---

## 1. Critères d'acceptation — 17 / 17 ✅

| # | Critère | Statut | Preuve |
| --- | --- | --- | --- |
| 1 | Table `section` en meta (4 lignes) | ✅ | MAT / PRIM / GEN / TECH |
| 2 | Table `filiere` en meta (3 lignes) | ✅ | FR / EN / BIL |
| 3 | Base annuelle `<initiale>-<YYYY>` + 7 tables | ✅ | `lyc-2026` : niveau, serie, option, mois, jour, section, filiere (+ schema_version) |
| 4 | Niveaux des 4 sections | ✅ | 58 niveaux (maternelle 6, primaire 6+6+12, général 7+7, technique 4+3+7) |
| 5 | Séries technique F1→F8 + ACA→G3 | ✅ | 51 séries (dont F1..F8 et ACA/ACC/CG/FIG/SES/G1/G2/G3) |
| 6 | Options techniques avec secteur | ✅ | 33 options (18 industrielles, 12 commerciales, 2 artistiques, 1 autres) |
| 7 | Mois ordre scolaire Septembre=1…Mai=9 | ✅ | 9 mois, ordre_scolaire 1→9 |
| 8 | `GET /api/meta/sections.php` → 200 (4) | ✅ | curl 200 |
| 9 | `GET /api/annee/niveaux.php` → 200 (tous) | ✅ | 58 niveaux |
| 10 | `GET /api/annee/options.php?secteur=industriel` → filtre | ✅ | 18 options |
| 11 | PerimetreSelector filtre sans rechargement | ✅ | sélection "Primaire" → 24 niveaux (CP présent, Sixième exclu), filtrage client-side |
| 12 | Périmètre persiste après rechargement | ✅ | `localStorage["ssmunie-perimetre"]` relu au boot |
| 13 | Admin crée une année via l'UI | ✅ | modale → `createAnnee.php` → 200 + base créée (testé : 2027-2028) |
| 14 | Script `annuel_001_referentiels.sql` idempotent | ✅ | re-run → toujours 58 niveaux (INSERT IGNORE + clés UNIQUE) |
| 15 | Aucune référence à l'ancien `cycle` | ✅ | `section` remplace l'ancien niveau ; `serie.cycle` (INT) = 1ᵉʳ/2ᵉ cycle d'une section (concept distinct) |
| 16 | Aucune FK inter-bases | ✅ | `section`/`filiere` copiées localement ; `niveau`/`serie`/`option` n'ont que des INDEX |
| 17 | Chaînes UI via `useT()` | ✅ | ~60 nouvelles clés FR/EN |

---

## 2. Décisions documentées (ambiguïtés du prompt)

1. **Nom de base annuelle** : `<initiale lowercase>-<YYYY>` dérivé de `date_debut`
   (ex. `lyc-2026`) — helper `ssmunie_annee_db_name()`.
2. **`option` (mot réservé)** : backticks partout ; colonnes `section_id`/`filiere_id`
   ajoutées + `serie_id` **NULL-able** (le CAP industriel n'a pas de série — la
   spécialité EST l'option, cf. §2) ; colonne `domaine` ajoutée (Génie électrique, etc.).
3. **Mois** : Septembre(1) → Mai(9) — interprétation retenue de la section 3.3.
4. **Création de la 1ʳᵉ année** : `setup/complete.php` crée aussi la base annuelle
   (best-effort) ; `createAnnee.php` sert aux années suivantes.
5. **Séries EN techniques** : ajoutées (GCE O/A Level) pour complétude du référentiel.

---

## 3. Captures d'écran

Dossier : `docs/screenshots/`

| Fichier | Contenu |
| --- | --- |
| `b2-home.png` | Accueil : sélecteurs année + périmètre + item Référentiels |
| `b2-sections.png` | Table des 4 sections |
| `b2-niveaux.png` | Table des niveaux |
| `b2-series.png` | Table des séries |
| `b2-options.png` | Table des options (avec secteur) |
| `b2-mois-jours.png` | Mois & jours |

---

## 4. Reproduction

```bash
cd C:\xampp\htdocs\ssmunie
# Meta
mysql -u root < sql/migrations/003_section_filiere.sql
# Création d'une année + base annuelle (via l'app : YearSelector → "Nouvelle année")
# ou en CLI :
mysql -u root -e 'SELECT 1'   # le endpoint createAnnee.php fait tout (admin)
```

État final : 1 année (2026-2027) + 1 base annuelle (`lyc-2026`).

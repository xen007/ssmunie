# SSMUNIE — Revue de la ROADMAP v6 (avant B4a)

Lecture seule — analyse de la `SSMUNIE_ROADMAP_v6.md`, croisée avec l'audit existant
(`AUDIT_FONCTIONNALITES.md`, `FUSION_SSM_SSMCOL.md`) et vérification directe des arborescences
`ssm/` et `ssmcol/` (comptages confirmés : 115 fichiers `.php` côté ssm, 116 côté ssmcol,
185 `.js` côté ssm, 175 côté ssmcol).

---

## VOLET 1 — EXHAUSTIVITÉ

### 1.1 Cartographie des 20 fonctionnalités de l'audit (§1) → blocs de la roadmap

| Fonctionnalité (audit §1) | Statut | § roadmap | Remarque |
| --- | --- | --- | --- |
| 1 Tableau d'honneur / palmarès | ✅ | B4e | ex-æquo → source `ssm/hon.js` (version refondue) |
| 2 Carte scolaire / ID Cards | ✅ | B7d | bug `s.cl`/`s.stat` déjà noté |
| 3 Certificats / diplômes | ✅ | B7c | + radiation/réussite (absents aujourd'hui) ajoutés |
| 4 Accessoires | ✅ | B6a | 2 schémas concurrents notés |
| 5 Matériel | ✅ | B6c | schéma seul → neuf |
| 6 Émargements / cahier de textes | ✅ | B5c | schéma seul → neuf |
| 7 Absences | ✅ | B4c | sanctions = ssmcol seul (7 paliers) |
| 8 Devoirs | ✅ | B6f | absent → neuf |
| 9 Discipline (cahier de liaison) | ✅ | B7f | X-Auth → session noté |
| 10 Transport | ✅ | B6b | bug `tarif` vs `tarifs` noté |
| 11 Cantine | ✅ | B6h | absent → neuf |
| 12 Paie / salaires | ✅ | B6g | absent → neuf |
| 13 Dépenses / finances | ✅ | B6e | schéma seul → neuf |
| 14 Bibliothèque | ✅ | B6i | absent → neuf |
| 15 Notifications | ✅ | B7h | cloche décorative → neuf |
| 16 Analytics / statistiques | ✅ | B4e | source `ssm/analytics/**` (v2) |
| 17 Signature | ✅ | B7g | implémentée mais jamais alimentée |
| 18 Gestion des rôles | ✅ | B1 (fait) | hors périmètre v6 |
| 19 Paramétrage | ✅ | B8f | 4 générations de schéma à unifier |
| 20 Mobile / PWA | ✅ | B9d, B9e | PWA absente aujourd'hui |

**Verdict fonctionnalités : 20/20 couvertes.**

### 1.2 Tables fantômes et endpoints de l'audit

| Élément legacy | Statut | § roadmap | Remarque |
| --- | --- | --- | --- |
| Tables `materiel`, `sorties` | ✅ | B6c | schéma seul |
| Table `emargements` + vues | ✅ | B5c | schéma seul |
| Table `notifications` | ✅ | B7h | schéma seul |
| Tables `depenses`/`finances` | ✅ | B6e | 2 lignes seed, aucun endpoint |
| Tables `bus`, `tarif_transport`, `abonnements_transport` | ✅ | B6b | schéma seul |
| Tables `jourdispo`, `disponibilites`, `parametres_horaires` | ✅ | (abandon implicite) | vestiges, correctement non portés |
| `seedcol.php`, `trig.php` (outils destructifs) | 🟡 | (implicite) | à « abandonner » explicitement dans v7 |
| `eleve_matiere` vs `eleve_matieres` (doublon) | ✅ | B5b | « fusionner » mentionné |
| `typeEleve.php` (type d'élève) | 🟠 DOUTE | — | non mappé — voir 1.3 |
| `classeSup.php` (passage de classe) | 🟠 DOUTE | B4e/B7c | `promotion.js` listé comme certificat, pas comme promotion de masse — voir 1.3 |

### 1.3 MANQUANTS identifiés

| # | Manque | Gravité | Proposition d'insertion |
| --- | --- | --- | --- |
| M1 | **Portail parent** : `src/parent/` (5 fichiers identiques dans les 2 forks : `homeParent.js`, `studpar.js`, `absencesEl.js`, `bulletinEl.js`, `bultEl.js`). Le login parent par `code_parent` existe mais aucun bloc ne porte l'interface parent. | ÉLEVÉE | Nouveau bloc **B7k « Portail parent »** (ou intégrer à B7h) : login par code_parent, vue des enfants, absences/notes/bulletins. |
| M2 | **Migration de données legacy → base annuelle ssmunie** : l'audit §6.5 Phase 2 (fusion `njikep`+`njikec`+`njikect`, ~1 546 fiches, remapping `cycle`/`niveau`, `annee_scolaire_id`) n'a **aucun bloc** dans la roadmap v6. Sans lui, B4-B7 tournent à vide. | CRITIQUE | Nouveau bloc **B3.2 « Import/migration des données legacy »** (CSV/SQL des 7 bases → `lyc-YYYY`) avant B4a, ou volet dédié en Phase 1. |
| M3 | **`ROADMAP finance v2.0` absente** : B4b y renvoie (« document complet fourni » + tests T1-T16 + invariants I1-I5). Vérifié : aucun fichier `*finance*`/`*ROADMAP*` dans `ssm/`, `ssmcol/`, `ssmunie/`. | CRITIQUE | Fournir le document avant B4b, sinon les « Tests T1-T16 » sont indéfinis. |
| M4 | **Promotion / passage de classe** (`classeSup.php`, `classeSup.js` — nomenclature `SIL/CP/CE…` codée en dur) | MOYENNE | Ajouter au périmètre B4e (ou B4b) : décision de passage → passage de masse automatique, nomenclature configurable (audit §6.4bis). |
| M5 | `typeEleve.php` (catégorie d'élève) | FAIBLE | À trancher : porter ou abandonner (audit §5.1 « Divers »). |

---

## VOLET 2 — COHÉRENCE ET DÉPENDANCES

| Bloc | Problème | Ajustement proposé |
| --- | --- | --- |
| B4a | **« 3 moteurs » (titre) vs « 4 moteurs » (Décisions §6.7)**. `SSMUNIE_v5` §2.2 tranche : 4 (maternel, primaire, collège général, technique). | Corriger le titre en « 4 moteurs ». |
| B4a | `bulletin_template` + « variantes FR/EN/BIL » chevauchent B7b (« 3 variantes FR/EN/BIL »). | Clarifier la frontière : B4a = données + calcul + gabarit de données ; B7b = rendu A4/impression. |
| B5a / B5b | **Inversion de dépendance** : B5a (emploi du temps) consomme `cours` (matière × classe × prof × volume) qui est créé par B5b. | Réordonner **B5b avant B5a**, ou déplacer « CRUD cours » en tête de B5a. |
| B4b | Références à `ROADMAP finance v2.0`, T1-T16, I1-I5 non fournies. | Fournir le doc (cf. M3) ; sinon B4b non spécifiable. |
| B4e | Dépend de B4a (notes) **et** B4b (scolarité) pour les stats croisées — l'ordre B4a→B4b→B4c→B4d→B4e est correct. | RAS (confirmer seulement que B4e = stats, pas de nouveau stockage). |
| B3.1 | Correctement placé en Phase 0 (avant B4a) ; le fix `::1 vs 127.0.0.1` est **réel et confirmé** (reproduit pendant B3 : le proxy Vite cible `localhost` qui peut résoudre en IPv6, le fingerprint de session casse alors le rechargement). | RAS — confirmer que le fix porte sur `vite.config.js` (`target: 127.0.0.1`) et/ou `ssmunie_fingerprint()`. |
| B7a → B7b-e | `EnTeteDocument` est la dépendance commune de tous les documents ; placé en tête de Phase 4. | RAS. |
| B8d (licence) | Sans dépendance amont ; pourrait être fait plus tôt si la licence bloque un déploiement pilote. | Optionnel : remonter en Phase 1. |
| Global | Aucun bloc ne semble dépasser 800 lignes de prompt, mais B4a/B4b/B6a/B7b sont denses et multi-références. | Découper B4a en « schéma+moteurs » puis « endpoints+UI » si besoin ; B4b dépend du doc finance. |

---

## VOLET 3 — RISQUES

| Bloc | Risque | Garde-fou |
| --- | --- | --- |
| B4a | **Hallucination sur les règles officielles** (bulletin primaire APC, grades GCE, décisions de passage, appréciations). Les règles ne sont pas déductibles du code (audit §6.4/§8.2). | Fournir les textes officiels ; 4 moteurs = classes PHP **pures** testables ; trancher D5/D8 avant de coder. |
| B4b | **Manipulation d'argent** (split auto, invariants I1-I5, annulation atomique). Risque d'erreur financière. | T1-T16 en tests automatisés ; écritures compensatoires ; journalisation ; jamais de LWW sur le financier (déjà prévu B10c). |
| B5a | **Génération auto** = problème de satisfaction de contraintes (NP-difficile en général). | Porter `emploi.php` (598 l.) au lieu de réécrire ; édition manuelle en secours ; heuristiques + validation. |
| B6a | **Deux schémas accessoires incompatibles** (`acc_tarif` vs `accessoire_tarifs`). | Unifier sur le modèle `ssm` (reçus `reference_recu`, seul éprouvé en réel sur `gsa-2026`). |
| B7b | **Impression A4 multi-variantes** (FR/EN/BIL × 2 ministères × 4 cycles) : fragile en rendu. | B7a d'abord ; CSS print dédié ; tests sur papier ; gabarits = données. |
| B9a | **Migration shadcn ~180 composants** : très lourde, risque de régression massive. | Réévaluer le coût/bénéfice ; possible de durcir le design system B0 existant au lieu de migrer tout. |
| B10c | **HLC / LWW / rollback** : complexité distribuée réelle. | Scope minimal : LWW seulement sur le non-financier ; compensatoire sur le financier ; pas de sync auto des écritures d'argent. |
| Global portage | **~230 endpoints legacy** dont ~211 sans session et ~40 zombies. | Suivre la grille audit §5 (porter / fusionner / abandonner) ; ne pas porter les zombies ; réécrire les « handle*/Dbase » via la couche unifiée. |

---

## VOLET 4 — AVIS GLOBAL

La roadmap est **solide et bien fondée** : elle reprend fidèlement l'inventaire de l'audit,
coupe correctement les 20 fonctionnalités en blocs, et respecte la discipline UI mobile-first (B3.1
est un vrai bug, reproduit en B3). Les phases 0→8 sont dans le bon ordre global.

Quatre corrections avant de lancer B4a :
1. **Corriger « 3 moteurs » → « 4 moteurs »** (B4a, cohérence avec §6.7).
2. **Réordonner B5b avant B5a** (les cours sont l'entrée de l'emploi du temps).
3. **Ajouter un bloc « Portail parent »** (M1) — fonctionnalité legacy réelle, aujourd'hui orpheline.
4. **Ajouter un bloc « Migration de données legacy »** (M2) et **fournir la ROADMAP finance v2.0** (M3) :
   sans ces deux-là, B4a-B7 tournent sur des bases vides et B4b n'est pas spécifiable.

Priorité recommandée : **B3.1** (immédiat, petit) → **trancher D5 et D8** (règles bulletin primaire +
rôle de `id_eval`) → **B4a** (cœur, mais seulement après fourniture des règles officielles) → **B4b**
(après fourniture de la ROADMAP finance).

Risque de périmètre principal : **B9a (shadcn)** — l'ampleur (~180 composants) mérite d'être
reconsidérée ou différée au profit d'un durcissement du design system B0 déjà en place.


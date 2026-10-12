# SSMUNIE — Avis final sur la ROADMAP v7

Lecture seule. Croisé avec l'audit legacy et vérification directe (comptages BDD, présence doc).

---

## VOLET 1 — EXHAUSTIVITÉ FINALE

| Élément | Statut | Preuve / remarque |
| --- | --- | --- |
| B3.2 migration legacy intégré | ✅ | Phase 1, bloquant, critères d'acceptation présents |
| B7k portail parent intégré | ✅ | Phase 4, source `src/parent/` citée |
| Passage de classe dans B4e | ✅ | `classeSup.php/js` + nomenclature configurable |
| 4 moteurs bulletins | ✅ | titre corrigé « 4 moteurs » |
| UUID partout (D18) | ✅ | §0.2 + B3.2 « ajout id_uuid sur toutes les tables métier » |
| Critères d'acceptation | 🟡 PARTIEL | présents pour B3.2 et B4a seulement — à étendre à B4b-e |
| Liste « abandonnés » explicite | ✅ | seedcol, trig, typeEleve, Dbase, dbs, view… |
| Table décision → bloc bloqué | ✅ | D5/D8/D4/D14/D15/D18/D11/D22 résolus |
| Comptage 1 546 élèves | ✅ VÉRIFIÉ | `njikep`=714, `njikec`=524, `njikect`=308 → 1 546 exact |
| **ROADMAP finance v2.0 « fournie »** | ❌ **FAUX** | vérifié : le fichier n'existe dans aucun des 3 dossiers (`ssm/`, `ssmcol/`, `ssmunie/`) |

**Bilan : tout mon delta v6→v7 est intégré SAUF un point critique** — la v7 affirme
« ROADMAP finance v2.0 — fournie » alors que le document est toujours absent.

---

## VOLET 2 — COHÉRENCE

| Bloc | Problème | Ajustement |
| --- | --- | --- |
| B4b | `ROADMAP finance v2.0` marquée « fournie » mais absente → T1-T16 / I1-I5 indéfinis | **Fournir réellement le doc** (bloquant) |
| B4a / B4c | `sanctions_absence.php` listé en endpoint **dans les deux blocs** | Un seul propriétaire (recommander B4c) |
| B4a / B4c | Sanctions config éclatée : `parametres_niveau.sanction_1..4` (B4a) vs `parametres_absence` « 7 paliers » (B4c) | Clarifier le mapping 7 paliers → 4 sanctions + un seul dépositaire |
| B4a | D5 « résolu (paramétrable) » : le **cadre** est là, mais le **référentiel de compétences APC** (liste des compétences par matière NA/ECA/A/E) n'est pas spécifié | Fournir ce référentiel avant `MoteurPrimaire` |
| §0.4 | « 6e technique : seuil 05/20 (MINESEC 2026) » — terminologie « 6e technique » + seuil à sourcer | Citer la source officielle (risque d'hallucination) |
| B5a/B5b | Libellés échangés entre v6 et v7 (B5a était EDT, devient Cours) — noté dans la v7, mais gêne la traçabilité | Accepter v7 comme référence, conserver un tableau de correspondance |
| B3.2 | Bloc de migration nommé « B3.2 » mais placé en Phase 1 | Cosmétique : renommer « B-MIG » ou « B4.0 » pour clarté |
| Abandonnés | « 8 endpoints EDT fantômes » et « 26 endpoints fantômes ssm » non nommés | Lister les fichiers (l'audit §5.4 en nomme 15) |

---

## VOLET 3 — RISQUES

| Bloc | Risque | Garde-fou |
| --- | --- | --- |
| B3.2 | Écriture de données réelles (1 546 élèves) | Gel d'écriture + table de correspondance + retour arrière testé sur copie (déjà prévus ✅) |
| B4a | Hallucination règles officielles (APC, GCE, « 6e technique 05/20 ») | Sourcer chaque règle ; moteurs = classes PHP pures testées ; référentiel APC fourni |
| B4b | Argent (split auto, I1-I5) **sans doc de référence** | **Bloqué** tant que la ROADMAP finance n'est pas fournie ; T1-T16 automatisés |
| B5b | Génération EDT (satisfaction de contraintes) | Porter `emploi.php` (598 l.) ; édition manuelle en secours |
| B6a | 2 schémas accessoires | Unifier sur le modèle `ssm` (`reference_recu`, éprouvé sur gsa-2026) |
| B7b | Impression A4 multi-variantes | B7a d'abord ; CSS print dédié ; tests papier |
| B9a | Migration shadcn ~180 composants | Réévaluer coût/bénéfice — possible de durcir le design system B0 existant |
| B10c | HLC / LWW / rollback | Scope minimal ; jamais LWW sur le financier |

---

## VOLET 4 — RÉALISME (charge)

| Phase | Blocs | Charge estimée | Priorité |
| --- | --- | --- | --- |
| 0 | B3.1 | < 1 h | Immédiate |
| 1 | B3.2 + B4a-e | **Le gros morceau** (~60 % du projet) | Très haute |
| 2 | B5a-c | Moyenne | Moyenne |
| 3 | B6a-i | Moyenne (portage + neuf) | Moyenne |
| 4 | B7a-k | Moyenne (portage) | Moyenne |
| 5 | B8a-f | Faible-moyenne | Faible |
| 6 | B9a-f | **Élevée** (shadcn) | À réévaluer |
| 7 | B10a-e | Élevée (offline) | Différable |
| 8 | B11a-c | Faible | Finale |

Recommandation de report : **B9a (shadcn)** et **Phase 7 (offline)** peuvent être différés
après une première mise en production pilote — la valeur immédiate est dans les phases 1-4.

---

## VOLET 5 — AVIS GLOBAL + VERDICT PAR PHASE

| Phase | Verdict | Motif |
| --- | --- | --- |
| 0 — Socle | 🟢 VERT | B3.1 trivial et réel |
| 1 — Cœur pédago/financier | 🟠 ORANGE | B3.2 ✅ ; B4a 🟠 (référentiel APC + seuil à sourcer) ; **B4b 🔴 (doc finance absente)** |
| 2 — Périphériques pédago | 🟢 VERT | bien borné |
| 3 — Modules admin/commerciaux | 🟢 VERT | portage + neuf, borné |
| 4 — Documents/comm/médias | 🟢 VERT | portage + B7k |
| 5 — Rapports/journal/admin | 🟢 VERT | faible risque |
| 6 — UI/UX | 🟠 ORANGE | B9a shadcn = risque de périmètre |
| 7 — Offline/WAN | 🟠 ORANGE | B10c complexité distribuée |
| 8 — Recette/déploiement | 🟢 VERT | dépend de l'amont |

**VERDICT GLOBAL : 🟠 FEU ORANGE.**

La v7 est **nettement meilleure que la v6** : comptages exacts (1 546 vérifié), 4 moteurs,
UUID, décisions paramétrables, critères d'acceptation, table décision→bloc, liste d'abandonnés.

**Une seule chose empêche le feu vert complet :** la v7 affirme que la « ROADMAP finance
v2.0 » est fournie, alors qu'elle ne l'est pas. Tant qu'elle manque, B4b (scolarité/argent) est
indéfinissable (T1-T16, I1-I5).

**Action avant lancement :**
1. Fournir réellement la ROADMAP finance v2.0 (ou retirer la mention « fournie » et la traiter
   comme un pré-requis ouvert).
2. Sourcer « 6e technique 05/20 » + fournir le référentiel de compétences APC (pour MoteurPrimaire).
3. Trancher la duplication `sanctions_absence.php` et le mapping 7 paliers → 4 sanctions.

**Ceci fait, feu vert pour lancer B3.1 + B3.2, puis B4a.**

